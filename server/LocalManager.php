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
    public $db;
    /**
     * 保存数据的表名
     * @var string
     */
    public $tableName = 'bee_server';
    /**
     * 检查间隔时间
     * @var int
     */
    public $interval = 3;
    /**
     * 连接超时时间
     * @var int
     */
    public $connTimeout = 1;

    /**
     * 服务上线
     */
    const STATUS_ONLINE = 'online';
    /**
     * 服务下线
     */
    const STATUS_OFFLINE = 'offline';

    public $actMap = [
        'online' => '上线',
        'offline' => '下线',
        'reload' => '重载',
        'stop' => '停止',
        'start' => '启动',
        'restart' => '重启'
    ];

    public function init()
    {
        $this->db = $this->sureComponent($this->db);
    }

    /**
     * server添加
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
     * @throws \Exception
     */
    public function addServer($data)
    {
        $data['status'] = self::STATUS_ONLINE;
        $data['online_time'] = time();
        $this->db
            ->from($this->tableName)
            ->save($data, ['remote', 'ip']);
    }

    /**
     * 移除server
     * @param $remote
     * @throws \Exception
     */
    public function removeServer($remote)
    {
        $data = [
            'remote' => $remote,
            'status' => self::STATUS_OFFLINE,
            'offline_time' => time()
        ];
        $this->db
            ->from($this->tableName)
            ->updateByAttr($data, 'remote');
    }

    public function createTable()
    {
        $sql1 = <<<SQL
create table bee_server(
  id INTEGER PRIMARY KEY AutoIncrement,
  remote varchar(255), -- 连接字符串
  ip varchar(20), -- 注册服务器的IP地址
  name varchar(255), -- server名称
  class varchar(255), -- server启动类
  master_pid INTEGER, -- 主进程PID
  manager_pid INTEGER, -- 管理进程PID
  cmd_path text, -- 启动脚本路径
  online_time INTEGER, -- 上线时间
  status INTEGER, -- 状态
  offline_time INTEGER, -- 下线时间
  check_time INTEGER, -- 最后一次检查的时间
  check_log INTEGER -- 最后一次结果日志
);
SQL;
        $sql2 = <<<SQL
create table bee_server_log
(
  id INTEGER PRIMARY KEY AutoIncrement,
  server_id int, -- bee_server的id
  remote INTEGER, -- 连接字符串
  act varchar(32), -- 执行的动作
  log text, -- 执行返回日志
  time INTEGER -- 执行时间
);
SQL;

        $this->db->exec($sql1);
        $this->db->exec($sql2);
    }

    /**
     * 检查server
     */
    public function checkServer()
    {
        $res = $this->db
            ->from($this->tableName)
            ->all();
        foreach ($res as $row) {
            $flag = true;

            /* 进程存活检查 */
            if (!posix_kill($row['master_pid'], 0)) {
                /* 尝试回收其他进程 */
                posix_kill($row['manager_pid'], SIGTERM);
                $flag = false;
            }

            /* 端口访问检查 */
            if (!stream_socket_client($row['remote'], $errno, $error, $this->connTimeout)) {
                $flag = false;
            } else {
                $flag = true;
            }

            $updateData = [
                'check_time' => time(),
                'check_log' => $error ?: 'success'
            ];
            if ($flag == false) {
                $updateData['status'] = self::STATUS_OFFLINE;
            }
            $this->db
                ->from($this->tableName)
                ->updateById($updateData, $row['id']);

            /* 如果ping失败，执行启动命令 */
            if ($flag == false) {
                $cmd = $row['cmd_path'] . " -s start -d yes";
                $shellReturn = Shell::simpleExec($cmd, $error);
                $this->opLog([
                    'server_id' => $row['id'],
                    'remote' => $row['remote'],
                    'act' => 'start',
                    'log' => $error ?: $shellReturn
                ]);
            }
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
            $this->checkServer();
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
            $r[$row['status']][$row['remote']] = $row;
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
     *  'log' => '操作结果日志'
     * ]
     * @param $data
     */
    public function opLog($data)
    {
        $data['time'] = time();
        $this->db
            ->from($this->tableName . '_log')
            ->insert($data);
    }

    /**
     * 获取全部server日志
     * @return array|bool
     */
    public function getAllLog()
    {
        $res = $this->db
            ->from($this->tableName . '_log')
            ->order('id desc')
            ->all();
        return $res;
    }
}