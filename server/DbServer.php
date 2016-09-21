<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/5/4
 * Time: 17:46
 * db服务器。用于异步写入mysql
 * 通信协议如下。
 * 使用主键关系来维系。
 * array(
 *      'auth' => array(), //权限认证区
 *      'op' => '', //当前执行的操作类型
 *      'sync' => 1, //是否为同步操作，默认为异步
 *      'db' => 'db.main', //当前操作的数据库
 *      'table_name' => 'test', //当前要操作的表
 *      'pk' => '1', //当前要操作类型的主键
 *      'data' => array(), //要操作的数据
 *      'api' => 'by_pk', //api操作类型
 * );
 *
 *
 * 使用sql
 * array(
 *      'auth' => array(), //权限认证区
 *      'op' => '', //当前执行的操作类型
 *      'sync' => 1, //是否为同步操作，默认为异步
 *      'db' => 'db.main', //当前操作的数据库
 *      'table_name' => 'test', //当前要操作的表
 *      'sql' => 'sql', //sql语句
 *      'params' => array(), //绑定参数
 *      'api' => 'by_pk', //api操作类型
 * );
 *
 * @desc db_server使用批量事务提交来优化Mysql 写入性能
 * 数据条数受qps配置影响
 *
 * 每个task进程一个队列，名称为key_{$workerId}
 * 入队的方式使用crc32算法取余。这并不是平均策略，只是为了保证同一个表分配到同一个队列中。
 */
namespace bee\server;
class DbServer extends BaseServer
{
    /**
     * OP_INSERT 标识为一个数据插入
     * OP_UPDATE 标识为一个数据更新
     * OP_DELETE 标识为一个数据删除
     * OP_SELECT 标识为一个数据查找
     * OP_ERROR  标识为一个错误操作
     * OP_REQUEST 标识为一个请求操作
     */
    const OP_INSERT = 'insert';
    const OP_UPDATE = 'update';
    const OP_DELETE = 'delete';
    const OP_SELECT = 'select';
    const OP_ERROR = 'error';
    const OP_REQUEST = 'request';

    /**
     * API_PK 增加根据数组insert；修改，删除根据pk；查询根据pk
     * API_SQL 复杂的请求，使用sql语句来执行
     */
    const API_PK = 'by_data';
    const API_SQL = 'by_sql';

    protected $canOpType = array(
        self::OP_INSERT,
        self::OP_UPDATE,
        self::OP_DELETE,
        self::OP_SELECT
    );

    const ERR_NO = 0;
    const ERR_DATA = 10;
    const ERR_TOKEN = 11;
    const ERR_DB_SET = 12;
    const ERR_SYNC_NO_FIND = 13;
    const ERR_NO_PK = 14;

    const QUEUE_MODE_BIND = 1; //绑定db,tablename分配
    const QUEUE_MODE_RANDOM = 2; //完全随机分配

    protected $errArr = array(
        self::ERR_NO => 'OK',
        self::ERR_DATA => '数据格式错误',
        self::ERR_TOKEN => 'token错误',
        self::ERR_DB_SET => '数据库或表名格式错误',
        self::ERR_SYNC_NO_FIND => '异步模式下不能执行查询操作',
        self::ERR_NO_PK => '没有设置主键'
    );

    /**
     * 批量提交的数量，如果大于1，将使用事务批量提交。可以提高性能。
     * @var int
     */
    protected $batchNum = 1;
    /**
     * 统计每个进程执行情况
     * OP_REQUEST 收到的请求数量
     * OP_INSERT 插入执行数量
     * OP_SELECT 查询执行数量
     * OP_UPDATE 更新执行数量
     * OP_DELETE 删除执行数量
     * OP_ERROR 错误sql执行数
     * @var array
     */
    protected $runInfo = array(
        self::OP_REQUEST => 0,
        self::OP_INSERT => 0,
        self::OP_SELECT => 0,
        self::OP_UPDATE => 0,
        self::OP_DELETE => 0,
        self::OP_ERROR => 0,
    );
    /**
     * 当前队列
     * @var \SplQueue
     */
    protected $queue;
    protected $name = 'db_server';
    protected $leastQueueTime = 15; //队列最少执行时间
    protected $lastQueueTime = 0; //队列上一次执行时间

    public function init()
    {
        $config = $this->c('queue');
        $this->batchNum = $config['batch_num'] ? $config['batch_num'] : 1;
        $this->queue = new \SplQueue();
        $this->lastQueueTime = time();
    }

    public function onReceive(\swoole_server $server, $fd, $fromId, $data)
    {
        $this->runInfo['request']++;
        if ($this->isDebug()) {
            $this->accessLog($data);
        }

        $arr = json_decode($data, true);
        if (($errno = $this->check($arr)) !== 0) {
            $this->runInfo['error']++;
            $this->error($fd, $errno);
        } else {
            if ($arr['sync']) { //同步操作
                $res = $this->execOne($data);
                $this->success($fd, $res);
            } else { //异步操作
                $this->queue->push($arr);
                if ($this->queue->count() >= $this->batchNum
                    || time() - $this->lastQueueTime >= $this->leastQueueTime) {
                    $this->lastQueueTime = time();
                    $this->execQueue();
                }
                $this->success($fd);
            }
        }
    }

    /**
     * 执行数据检查
     * @param $data
     * @return bool|int
     */
    public function check($data)
    {
        if (is_array($data) == false) {
            $errno = self::ERR_DATA;
        } elseif ($data['db'] == false || $data['table_name'] == false) {
            $errno = self::ERR_DB_SET;
        } elseif ($data['sync'] != 1 && $data['op'] == self::OP_SELECT) {
            $errno = self::ERR_SYNC_NO_FIND;
        } elseif ($data['api'] == self::API_PK && $data['op'] != self::OP_INSERT && $data['pk'] == false) {
            $errno = self::ERR_NO_PK;
        } else {
            $errno = 0;
        }
        return $errno;
    }

    public function execOne($data)
    {
        $r = array();
        if ($data['api'] == self::API_PK) {
            $r = $this->execByPk($data);
        } elseif ($data['api'] == self::API_SQL) {
            $r = $this->execBySql($data);
        }
        return $r;
    }

    /**
     * 消息队列方式执行
     * @return null
     */
    public function execQueue()
    {
        $execArr = array();
        for ($i = 0; $i < $this->batchNum; $i++) {
            $data = $this->queue->pop();
            if ($data == false) { //非法数据
                continue;
            }
            $execArr[$data['db']][] = $data;
        }
        if ($execArr == false) {
            return null;
        }
        $num = 0;
        foreach ($execArr as $key => $dbArr) {
            try {
                $db = \App::db($key);
            } catch (\PDOException $e) {
                $this->errorLog("{$key} 数据库配置不存在" . $e->getMessage());
                continue;
            }
            $db->beginTransaction();
            foreach ($dbArr as $row) {
                $this->execOne($row);
                $num++;
            }
            $db->commit();
        }
        if ($this->isDebug()) {
            $msg = '当前进程：' . getmypid();
            $msg .= "; 执行条数：{$num}";
            $this->debugLog($msg);
        }
    }

    /**
     * 根据数据和主键设置执行
     * @param $data
     * @return mixed
     */
    public function execByPk($data)
    {
        $r = 0;
        try  {
            $model = \App::m($data['table_name'], $data['db']);
            switch ($data['op']) {
                case self::OP_INSERT :
                    $r = $model->insert($data['data']);
                    break;
                case self::OP_UPDATE :
                    $r = $model->updateById($data['data'], $data['pk']);
                    break;
                case self::OP_DELETE :
                    $r = $model->delById($data['pk']);
                    break;
                case self::OP_SELECT :
                    $r = $model->findById($data['pk']);
                    break;
                default :
                    break;
            }
            $this->runInfo[$data['op']]++;
        } catch (\PDOException $e) {
            $this->runInfo[self::OP_ERROR]++;
            $this->errorLog(\CoreJson::encode($data) . '：' . $e->getMessage());
            return false;
        }
        return $r;
    }

    /**
     * 根据sql语句执行
     * @param $data
     * @return array|bool|int|null
     */
    public function execBySql($data)
    {
        $r = 0;
        try  {
            $model = \App::m($data['db'], $data['table_name']);
            switch ($data['op']) {
                case self::OP_SELECT :
                    $r = $model->all($data['sql'], $data['params']);
                    break;
                default:
                    $r = $model->exec($data['data'], $data['params']);
            }
            $this->runInfo[$data['op']]++;
        } catch (\PDOException $e) {
            $this->runInfo[self::OP_ERROR]++;
            $this->errorLog(\CoreJson::encode($data) . '：' . $e->getMessage());
            return $r;
        }
        return $r;
    }

    /**
     * 返回错误
     * @param $fd
     * @param $errno
     * @return bool
     */
    public function error($fd, $errno)
    {
        $data = array(
            'code' => $errno,
            'msg' => $this->errArr[$errno],
        );
        return $this->send($fd, \CoreJson::encode($data));
    }

    /**
     * @param $fd
     * @param $res
     * @return bool
     */
    public function success($fd, $res = array())
    {
        $data = array(
            'code' => 0,
            'msg' => 'ok',
            'data' => $res
        );
        return $this->send($fd, \CoreJson::encode($data));
    }
}