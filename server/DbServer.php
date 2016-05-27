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
    const OP_INSERT = 'insert'; //插入数据
    const OP_UPDATE = 'update'; //更新数据
    const OP_DEL = 'delete'; //删除数据
    const OP_FIND = 'find'; //查找数据

    const API_PK = 'by_pk';
    const API_SQL = 'by_sql';

    protected $canOpType = array(
        self::OP_INSERT,
        self::OP_UPDATE,
        self::OP_DEL,
        self::OP_FIND
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
    protected $qps = 100; //每秒个进程每秒最多写入多少条数据。
    protected $tick = 1000;
    protected $queueRedis; //使用那个redis配置
    protected $queueKey; //消息队列的key
    protected $serverName = 'db_server';
    protected $useTrans = 1; //是否使用事务
    protected $queueMode = 1; //

    public function init()
    {
        $config = $this->c('queue');
        $this->qps = $config['qps'] ? $config['qps'] : 100;
        $this->queueRedis = $config['redis'] ? $config['redis'] : 'redis.main';
        $this->queueKey = $config['key'] ? $config['key'] : 'db_server_queue';
        $this->useTrans = intval($config['use_trans']);
        $this->tick = $config['tick'] ? $config['tick'] : 1000;
        $this->queueMode = $config['queue_mode'] ? $config['queue_mode'] : 1;
    }

    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        if ($workerId >= $this->c('serverd.worker_num')) {
            $this->tick($this->tick, array($this, 'execQueue'), array($this, $workerId));
        }
        parent::onWorkerStart($server, $workerId);
    }

    /**
     * 得到消息队列的Key。原则上一个task进程一个消息队列
     * @param $db
     * @param $tableName
     * @return string
     */
    public function getQueueKeyByDb($db, $tableName)
    {
        if ($this->queueMode == self::QUEUE_MODE_BIND) {
            $num = sprintf('%u', crc32($db . $tableName));
        } else {
            $num = \Functions::milliSecondTime();
        }
        $id = $num % $this->c('serverd.task_worker_num') + $this->c('serverd.worker_num');
        $key = $this->queueKey . '_' . $id;
        return $key;
    }

    /**
     * 根据worker_id得到队列key
     * @return string
     */
    public function getQueueKeyByWorkerId()
    {
        $key = $this->queueKey . '_' . $this->getWorkerId();
        return $key;
    }

    /**
     * 得到消息队驱动
     * @return \CoreRedis
     */
    public function getQueueDriver()
    {
        $object = \CoreRedis::getInstance($this->queueRedis);
        return $object;
    }

    public function onReceive(\swoole_server $server, $fd, $fromId, $data)
    {
        $arr = json_decode($data, true);
        if (($errno = $this->check($arr)) !== 0) {
            $this->error($fd, $errno);
        } else {
            if ($arr['async']) { //异步操作进入redis队列
                $redis = $this->getQueueDriver();
                $redis->rPush($this->getQueueKeyByDb($arr['db'], $arr['table_name']), $data);
                $this->success($fd);
            } else { //同步操作直接执行
                $res = $this->taskWait($arr);
                $this->success($fd, $res);
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
        } elseif ($data['async'] && $data['op'] == self::OP_FIND) {
            $errno = self::ERR_SYNC_NO_FIND;
        } elseif ($data['api'] == self::API_PK && $data['op'] != self::OP_INSERT && $data['pk'] == false) {
            $errno = self::ERR_NO_PK;
        } else {
            $errno = 0;
        }
        return $errno;
    }

    /**
     * task 同步情况下执行sql,异步情况下定时器执行消息队列
     * @param \swoole_server $server
     * @param int $taskId
     * @param $fromId
     * @param $data
     * @return array|bool|int|mixed|null
     */
    public function onTask(\swoole_server $server, $taskId, $fromId, $data)
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
     * @param $timerId
     * @param $param
     * @return null
     */
    public function execQueue($timerId, $param)
    {
        $redis = $this->getQueueDriver();
        $queueKey = $this->getQueueKeyByWorkerId();
        $execArr = array();
        for ($i = 0; $i < $this->qps; $i++) {
            $str = $redis->lPop($queueKey);
            if ($str == false) { //当前队列已经没有数据了
                break;
            }
            $data = json_decode($str, true);
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
            if ($this->useTrans) {
                $db->beginTransaction();
            }
            foreach ($dbArr as $row) {
                if ($row['api'] == self::API_PK) {
                    $this->execByPk($row);
                } elseif ($row['api'] == self::API_SQL) {
                    $this->execBySql($row);
                }
                $num++;
            }
            if ($this->useTrans) {
                $db->commit();
            }
        }
        if ($this->debug) {
            $msg =  '当前key：' . $queueKey;
            $msg .= "; 事务：{$this->useTrans}";
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
                case self::OP_DEL :
                    $r = $model->delById($data['pk']);
                    break;
                case self::OP_FIND :
                    $r = $model->findById($data['pk']);
                    break;
                default :
                    break;
            }
        } catch (\PDOException $e) {
            $this->errorLog(\CoreJson::encode($data) . '：' . $e->getMessage());
            return 0;
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
                case self::OP_FIND :
                    $r = $model->all($data['sql'], $data['params']);
                    break;
                default:
                    $r = $model->exec($data['data'], $data['params']);
            }
        } catch (\PDOException $e) {
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