<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/1/10
 * Time: 9:35
 * 使用udp server 来汇总记录多台服务器的日志。
 *
 * 协议说明，一个json字符串。格式如下
 * array(
 *      'app_id' => '应用ID',
 *      'mark' => '日志标识',
 *      'msg' => '日志内容',
 *      'time' => '日志时间',
 *      'ip' => '产生日志的ip',
 * )
 */
namespace bee\server;
class LogServer extends BaseServer
{
    public $serverType = self::SERVER_BASE;
    /**
     * sqlite pdo操作对象
     * @var \PDO
     */
    protected $pdo;
    public $config = array(
        /**
         * 运行时配置。swoole::set需要设置的参数
         */
        'serverd' => [
            'worker_num' => 1,
            'task_worker_num' => 1,
            'max_request' => 100240,
            'max_conn' => 1000,
            'dispatch_mode' => 2,
            'daemonize' => true,
            'backlog' => 128,
        ],

        /**
         * server实例化和其他自定义参数
         */
        'server' => [
            'host' => '0.0.0.0',
            'port' => 9601,
            'server_mode' => SWOOLE_PROCESS,
            'socket_type' => SWOOLE_SOCK_UDP,
            'env' => 'pro',
            'debug' => false,
            'server_name' => 'log_server',
        ]
    );

    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        parent::onWorkerStart($server, $workerId);
        $this->pdo = new \PDO("sqlite:{$this->dataDir}/log.db");
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        /**
         * task 进程加一个定时器，用来处理日志数据。
         */
        if ($this->isTaskWorker()) {
            if (!($this->processData['tick_list'] instanceof \SplQueue)) {
                $this->processData['tick_list'] = new \SplQueue();
            }
            $this->tick(500, array($this, 'tickList'));
        }
    }

    public function onReceive(\swoole_server $server, $fd, $fromId, $data)
    {
        $arr = json_decode($data, true);
        if ($arr == false || !is_array($arr)) {
            $this->errorLog("{$data} json解析失败：". json_last_error_msg());
            return null;
        }

        $insert = [
            'app_id' => (string)$arr['app_id'], /* 应用ID */
            'mark' => (string)$arr['mark'], /* 日志标识 */
            'time' => $arr['time'] ?: time(),
            'msg' => $arr['msg']
        ];
        if (!$insert['app_id'] || !$insert['mark']) {
            $this->errorLog("receive：{$data} 缺少参数");
            return null;
        }
        $this->task($insert);
    }

    public function onTask(\swoole_server $server, $taskId, $fromId, $data)
    {
        /* @var  \SplQueue $spl */
        $spl = $this->processData['tick_list'];
        $spl->push($data);
        $this->gc();
    }

    /*
     * 定时器处理key回调函数
     */
    public function tickList()
    {
        /* @var  \SplQueue $spl */
        $spl = $this->processData['tick_list'];
        $n = $spl->count();
        $this->pdo->beginTransaction();
        for ($i = 0; $i < $n; $i++) {
            try {
                $this->insert($spl->shift());
            } catch (\PDOException $e) {
                $this->errorLog($e->errorInfo[2]);
                continue;
            }
        }
        $this->pdo->commit();
    }

    public function insert($data)
    {
        $table = "log_{$data['app_id']}";
        $field = [];
        $placeholder = [];
        $params = [];
        foreach ($data as $key => $row) {
            $params[':' . $key] = $row;
            $field[] = "`{$key}`";
            $placeholder[] = ':' . $key;
        }
        //插入当前记录
        $sql = "insert into {$table} (" . implode(', ', $field) . ') values (' .
            implode(', ', $placeholder) . ')';
        try  {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            if (strpos($e->errorInfo[2], 'no such table') !== false) {
                $this->createLogTable($table);
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                throw $e;
            }
        }
    }

    /**
     * 创建日志表，一个引用一个表
     * @param $table
     */
    public function createLogTable($table)
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$table}
(
  id INTEGER PRIMARY KEY AutoIncrement,
  app_id varchar(32),
  time int,
  mark varchar(32),
  msg text
);
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
    }

    /**
     * 触发垃圾回收
     * @param bool $force
     * @return null
     */
    public function gc($force = false)
    {
        if ($force == false && mt_rand(1, 10000) > 1) {
            return null;
        }
        $allTable = $this->pdo
            ->query("select name from sqlite_master where type='table' and name like 'log_%'")
            ->fetchAll(\PDO::FETCH_ASSOC);
        $minTime = time() - 30 * 24 * 3600;
        foreach ((array)$allTable as $row) {
            $n = $this->pdo
                ->exec("delete from {$row['name']} where time <= {$minTime}");
            $this->log("{$this->logDir}/clear.log", "删除{$row['name']} {$n} 条记录");
        }
    }
}