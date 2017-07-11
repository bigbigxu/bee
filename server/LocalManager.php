<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/6/23
 * Time: 14:33
 * server 本地管理器
 */
namespace bee\server;

use bee\client\Shell;
use bee\core\BeeSqlite;
use bee\core\TComponent;

class LocalManager
{
    use TComponent;
    /**
     * 使用的数据库
     * @var BeeSqlite
     */
    protected $db;
    /**
     * 保存server列表的数据表
     * @var string
     */
    protected $tableName = 'bee_server';
    /**
     * 保存相关日志的数据表
     * @var string
     */
    protected $logTableName = 'bee_server_log';
    /**
     * 检查间隔时间
     * @var int
     */
    protected $interval = 3;
    /**
     * 连接超时时间
     * @var int
     */
    protected $connTimeout = 1;

    /**
     * 服务上线
     */
    const STATUS_ONLINE = 'online';
    /**
     * 服务下线
     */
    const STATUS_OFFLINE = 'offline';

    const REMOVE_NOT = 0;
    /**
     * server 已经移除监控队列
     */
    const REMOVE_YES = 1;
    /*
     * 启动server
     */
    const CMD_START = 'start';
    /**
     * 关闭server
     */
    const CMD_STOP = 'stop';
    /**
     * 重载server
     */
    const CMD_RELOAD = 'reload';
    /**
     * 移除server
     */
    const CMD_REMOVE = 'remove';
    /**
     * 添加server
     */
    const CMD_ADD = 'add';
    /**
     * 删除server
     */
    const CMD_DEL = 'del';

    const ERR_CMD = 10;
    const ERR_REMOTE = 11;

    protected $cmdMap = [
        self::CMD_START,
        self::CMD_STOP,
        self::CMD_RELOAD,
        self::CMD_ADD,
        self::CMD_REMOVE,
        self::CMD_DEL
    ];

    public function init()
    {
        $this->db = $this->sureComponent($this->db);
    }

    /**
     * server上线
     * data包含如下字段
     * [
     *  'remote' => '访问协议',
     *  'name' => 'server名称',
     *  'class' => 'server类名称',
     *  'cmd_path' => 'server启动脚本路径',
     *  'master_pid' => '主进程ID',
     *  'manager_pid' => '管理进程pid',
     * ]
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public function online($data)
    {
        $data['status'] = self::STATUS_ONLINE;
        $data['online_time'] = time();
        return $this->db
            ->from($this->tableName)
            ->save($data, ['remote']);
    }

    /**
     * 下线server
     * @param $remote
     * @return bool
     * @throws \Exception
     */
    public function offline($remote)
    {
        $data = [
            'remote' => $remote,
            'status' => self::STATUS_OFFLINE,
            'offline_time' => time()
        ];
        return $this->db
            ->from($this->tableName)
            ->updateByAttr($data, 'remote');
    }

    /**
     * 将server从管理列表中移除
     * 移除的server不在进行检查
     * @param $id
     * @return bool|int
     * @throws \Exception
     */
    public function remove($id)
    {
        $data = [
            'is_remove' => self::REMOVE_YES,
        ];
        return $this->db
            ->from($this->tableName)
            ->updateById($data, $id);
    }

    /**
     * 将server添加到管理列表，server必须已经注册。
     * @param $id
     * @return bool|int
     * @throws \Exception
     */
    public function add($id)
    {
        $data = [
            'is_remove' => self::REMOVE_NOT,
        ];
        return $this->db
            ->from($this->tableName)
            ->updateById($data, $id);
    }

    /**
     * 根据主键删除server
     * @param $id
     * @return bool|int
     * @throws \Exception
     */
    public function del($id)
    {
        return $this->db
            ->from($this->tableName)
            ->delById($id);
    }

    /**
     * 检查server
     */
    public function check()
    {
        $res = $this->db
            ->from($this->tableName)
            ->andFilter('is_remove', '=', self::REMOVE_NOT)
            ->all();
        foreach ($res as $row) {
            /* 进程存活检查 */
            if (!posix_kill($row['master_pid'], 0)) {
                /* 尝试回收其他进程 */
                posix_kill($row['manager_pid'], SIGTERM);
            }

            /* 端口访问检查 */
            if (!stream_socket_client($row['remote'], $errno, $checkLog, $this->connTimeout)) {
                $flag = false;
            } else {
                $flag = true;
            }

            /* 更新检查结果 */
            $updateData = [
                'check_time' => time(),
                'check_log' => $checkLog ?: 'success',
                'status' => $flag ? self::STATUS_ONLINE : self::STATUS_OFFLINE
            ];
            $this->db
                ->from($this->tableName)
                ->updateById($updateData, $row['id']);

            /* 如果ping失败，执行启动命令 */
            if ($flag == false) {
                $this->act($row['id'], self::CMD_START, $checkLog);
            }
        }
    }

    /**
     * 执行server动作命令
     * @param $id
     * @param $act
     * @param string $log 执行动作原因
     * @return bool
     */
    public function act($id, $act, $log = '')
    {
        $res = $this->getOne($id);
        if ($res == false) {
            return $this->setErrno(self::ERR_REMOTE);
        }
        if (!in_array($act, $this->cmdMap)) {
            return $this->setErrno(self::ERR_CMD);
        }

        $serverId = $res['id'];
        $cmd = '';
        $shellReturn = '';
        if ($act == self::CMD_DEL) {
            $this->del($serverId);
        } elseif ($act == self::CMD_REMOVE) {
            $this->remove($serverId);
        } elseif ($act == self::CMD_ADD) {
            $this->add($serverId);
        } elseif (in_array($act, [self::CMD_START, self::CMD_STOP, self::CMD_RELOAD])) {
            $cmd = "{$res['cmd_path']} -s {$act} -d yes";
            $shellReturn = Shell::simpleExec($cmd, $this->errmsg);
        } else {
            return false;
        }

        $this->opLog([
            'server_id' => $res['id'],
            'remote' => $res['remote'],
            'act' => $act,
            'cmd' => $cmd,
            'check_log' => $log,
            'shell_log' => $this->errmsg ?: $shellReturn
        ]);
        if ($this->hasError()) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 定时器循环检查server
     */
    public function crontabCheck()
    {
        set_time_limit(0);
        $sleepTime = max(1, $this->interval);
        $n = intval(60 / $sleepTime) - 1;
        for ($i = 0; $i < $n; $i++) {
            $this->check();
            sleep($sleepTime);
        }
    }

    /**
     * 获取server列表
     * @return array
     */
    public function serverList()
    {
        $res = $this->db
            ->from($this->tableName)
            ->all();
        $r = [];
        foreach ($res as $row) {
            $r[$row['remote']] = $row;
        }
        return $r;
    }

    /**
     * 保存server操作日志
     * 日志格式
     * [
     *  'server_id' => '对象bee_server中的id',
     *  'remote' => '服务器地址',
     *  'act' => '操作类型',
     *  'shell_log' => '执行命令返回日志',
     *  'cmd' => '执行的命令',
     *  'check_log' => '检查日志'
     * ]
     * @param $data
     */
    public function opLog($data)
    {
        $data['time'] = time();
        $this->db
            ->from($this->logTableName)
            ->insert($data);
    }

    /**
     * 获取指定server的日志
     * @param string $remote
     * @param int $page
     * @param int $pageSize
     * @return array|bool
     */
    public function getLogByRemote($remote, $page = 1, $pageSize = 10)
    {
        $res = $this->db
            ->from($this->logTableName)
            ->andFilter('remote', '=', $remote)
            ->page($page, $pageSize)
            ->order('id desc')
            ->all();
        return $res;
    }

    /**
     * 获取全部日志
     * @param int $page
     * @param int $pageSize
     * @return array|bool
     */
    public function getAllLog($page = 1, $pageSize = 10)
    {
        $res = $this->db
            ->from($this->logTableName)
            ->page($page, $pageSize)
            ->order('id desc')
            ->all();
        return $res;
    }

    /**
     * 根据server_id获取一个server的情况
     * 可以是id值或者remote值
     * @param id
     * @return array
     */
    public function getOne($id)
    {
        if (strpos($id, "://")) {
            $field = 'remote';
        } else {
            $field = 'id';
        }
        return $this->db
            ->from($this->tableName)
            ->andFilter($field, '=', $id)
            ->one();
    }

    /**
     * 获取指定server的日志
     * @param string $id
     * @param int $page
     * @param int $pageSize
     * @return array|bool
     */
    public function getLogById($id, $page = 1, $pageSize = 10)
    {
        $res = $this->db
            ->from($this->logTableName)
            ->andFilter('server_id', '=', $id)
            ->page($page, $pageSize)
            ->order('id desc')
            ->all();
        return $res;
    }

    /**
     * 获取db组件
     * @return BeeSqlite
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * 获取表名
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * 获取日志表名
     * @return string
     */
    public function getLogTableName()
    {
        return $this->logTableName;
    }

    public function errmsgMap()
    {
        return [
            self::ERR_CMD => '未知的命令',
            self::ERR_REMOTE => "未知的服务"
        ];
    }

    /**
     * sqlite
     * 创建需要的数据表
     */
    public function createTableForSqlite()
    {
        $serverSql = <<<SQL
create table {$this->tableName}(
  id INTEGER PRIMARY KEY AutoIncrement,
  remote varchar(255), -- 连接字符串
  ip varchar(20), -- 注册服务器的IP地址
  name varchar(255), -- server名称
  class varchar(255), -- server启动类
  master_pid INTEGER, -- 主进程PID
  manager_pid INTEGER, -- 管理进程PID
  cmd_path text, -- 启动脚本路径
  online_time INTEGER, -- 上线时间
  status varchar(20), -- 状态
  is_remove INTEGER default 0, -- 是否移除
  offline_time INTEGER, -- 下线时间
  check_time INTEGER, -- 最后一次检查的时间
  check_log INTEGER -- 最后一次结果日志
);
SQL;
        $logSql = <<<SQL
create table {$this->logTableName}
(
  id INTEGER PRIMARY KEY AutoIncrement,
  server_id int, -- bee_server的id
  remote int, -- 连接字符串
  act varchar(32), -- 执行的动作
  cmd varchar(255), -- 执行的命令
  check_log text, -- 检查日志
  shell_log text, -- shell脚本执行返回日志
  username varchar(20), -- 执行用户
  time int -- 执行时间
);
SQL;

        $this->db->exec($serverSql);
        $this->db->exec($logSql);
    }
}