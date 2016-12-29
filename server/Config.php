<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/12/28
 * Time: 18:25
 * swoole 配置文件管理器
 */
namespace bee\server;
class Config
{
    /**
     * 运行时配置。swoole::set需要设置的参数
     * @var array
     */
    public $serverd = [
        'worker_num' => 4,
        'max_request' => 100240,
        'max_conn' => 10240,
        'task_worker_num' => 4,
        'task_max_request' => 100240,
        'task_tmpdir' => '/tmp',
        'dispatch_mode' => 2,
        'daemonize' => false,
        'backlog' => 128,
        'heartbeat_check_interval' => 60,
        'heartbeat_idle_time' => 60,
    ];
    /**
     * server实例化和其他自定义参数
     * @var array
     */
    public $server = [
        'host' => '0.0.0.0',
        'port' => 9501,
        'server_mode' => SWOOLE_PROCESS,
        'socket_type' => SWOOLE_SOCK_TCP,
        'env' => 'pro',
        'debug' => false,
        'server_name' => 'swoole',
        'base_dir' => __DIR__,
        'server_type' => BaseServer::SERVER_BASE
    ];

    /**
     * 设置启动的worker进程数量，建议cpu核心数的 1-4倍
     * @param $num
     * @return $this
     */
    public function setWorkerNum($num)
    {
        $this->serverd['worker_num'] = $num;
        return $this;
    }

    /**
     * 获取工作进程的数量
     * @return mixed
     */
    public function getWorkerNum()
    {
        return $this->serverd['worker_num'];
    }

    /**
     * 设置一个worker经常最大处理的任务数量。
     * 超过此数量，进程自动退出，防止PHP进程内存溢出。
     * @param $num
     * @return $this
     */
    public function setMaxRequest($num)
    {
        $this->serverd['max_request'] = $num;
        return $this;
    }

    public function getMaxRequest()
    {
        return $this->serverd['max_request'];
    }

    /**
     * 设置tcp最大连接数
     * @param $num
     * @return $this
     */
    public function setMaxConn($num)
    {
        $this->serverd['max_conn'] = $num;
        return $this;
    }

    public function getMaxConn()
    {
        return $this->serverd['max_conn'];
    }

    /**
     * 配置task进程的数量，配置此参数后将会启用task功能
     * @param $num
     * @return $this
     */
    public function setTaskWorkerNum($num)
    {
        $this->serverd['task_worker_num'] = $num;
        return $this;
    }

    public function getTaskWorkerNum()
    {
        return $this->serverd['task_worker_num'];
    }

    /**
     * 设置一个task进程最大处理的任务数量。
     * @param $num
     * @return $this
     */
    public function setTaskMaxRequest($num)
    {
        $this->serverd['task_max_request'] = $num;
        return $this;
    }

    public function getTaskMaxRequest()
    {
        return $this->serverd['task_max_request'];
    }

    /**
     * 设置task的数据临时目录,如果投递的数据超过8192字节，将启用临时文件来保存数据
     * @param $dir
     * @return $this
     */
    public function setTaskTmpdir($dir)
    {
        $this->serverd['task_tmpdir'] = $dir;
        return $this;
    }

    public function getTaskTmpdir()
    {
        return $this->serverd['task_tmpdir'];
    }

    /**
     * 数据包分发策略
     * 1:轮循模式，收到会轮循分配给每一个worker进程
     * 2:根据连接的文件描述符分配worker。这样可以保证同一个连接发来的数据只会被同一个worker处理
     * 3:抢占模式，主进程会根据Worker的忙闲状态选择投递，只会投递给处于闲置状态的Worker
     * 4:IP分配，根据客户端IP进行取模hash，分配给一个固定的worker进程
     * 5:UID分配，需要用户代码中调用 $serv-> bind() 将一个连接绑定1个uid
     *
     * dispatch_mode配置在BASE模式是无效的，因为BASE不存在投递任务
     *
     * @param $mode
     * @return $this
     */
    public function setDisPatchMode($mode)
    {
        $this->serverd['dispatch_mode'] = $mode;
        return $this;
    }

    public function getDisPatchMode()
    {
        return $this->serverd['dispatch_mode'];
    }

    /**
     * 是不是做为守护进程
     * @param $bool
     * @return $this
     */
    public function setDaemonize($bool)
    {
        $this->serverd['daemonize'] = (bool)$bool;
        return $this;
    }

    public function getDaemonize()
    {
        return $this->serverd['daemonize'];
    }

    /**
     * Listen队列长度
     * @param $num
     * @return $this
     */
    public function setBacklog($num)
    {
        $this->serverd['backlog'] = $num;
        return $this;
    }

    /**
     * 启用心跳检测
     * @param int $roundTime 此选项表示每隔多久轮循一次，单位为秒
     * @param int $idleTime 表示连接最大允许空闲的时间
     * @return $this
     */
    public function setHeartbeat($roundTime, $idleTime)
    {
        $this->serverd['heartbeat_check_interval'] = $roundTime;
        $this->serverd['heartbeat_idle_time'] = $idleTime;
        return $this;
    }

    public function getHeartbeat()
    {
        return array(
            $this->serverd['heartbeat_check_interval'],
            $this->serverd['heartbeat_idle_time']
        );
    }

    /**
     * 设置worker/task子进程的所属用户
     * @param $user
     * @param $group
     * @return $this
     */
    public function setUser($user, $group)
    {
        $this->serverd['user'] = $user;
        $this->serverd['group'] = $group;
        return $this;
    }

    public function getUser()
    {
        return array(
            $this->serverd['user'],
            $this->serverd['group']
        );
    }

    /**
     * 设置包结束符检查
     * @param string $eof 包的结束符
     * @param bool $split 底层会从数据包中间查找EOF，并拆分数据包
     * @return $this
     */
    public function setEofCheck($eof, $split = false)
    {
        $this->serverd['open_eof_check'] = true;
        $this->serverd['package_eof'] = $eof;
        $this->serverd['open_eof_split'] = $split;
        return $this;
    }

    /**
     * 设置包长度检查
     * @param string $type 长度类型，和php pack函数一致
     * @param int $lengthOffset 第几个字节是包长度的值
     * @param int $bodyOffset 第几个字节开始计算长度
     * @return $this
     */
    public function setLengthCheck($type, $lengthOffset = 0, $bodyOffset = 0)
    {
        $this->serverd['open_length_check'] = true;
        $this->serverd['package_length_type'] = $type;
        $this->serverd['package_length_offset'] = $lengthOffset;
        $this->serverd['package_body_offset'] = $bodyOffset;
        return $this;
    }

    /**
     * 设置最大数据包尺寸
     * @param $num
     * @return $this
     */
    public function setPackageMaxLength($num)
    {
        $this->serverd['package_max_length'] = $num;
        return $this;
    }

    /**
     * 设置日志文件
     * @param $file
     * @return $this
     */
    public function setLogFile($file)
    {
        $this->serverd['log_file'] = $file;
        return $this;
    }

    /**
     * 0 =>DEBUG
     * 1 =>TRACE
     * 2 =>INFO
     * 3 =>NOTICE
     * 4 =>WARNING
     * 5 =>ERROR
     *
     * @param $level
     * @return $this
     */
    public function setLogLevel($level)
    {
        $this->serverd['log_level'] = $level;
        return $this;
    }

    /**
     * 设置监听ip:port
     * @param $host
     * @param $port
     * @return $this
     */
    public function setListen($host, $port)
    {
        $this->server['host'] = $host;
        $this->server['port'] = $port;
        return $this;
    }

    /**
     * 设置服务器模式
     * SWOOLE_PROCESS 进程模式
     * SWOOLE_BASE BASE模式下reactor和worker是同一个角色，连接在worker进程维持
     * @param $mode
     * @return $this
     */
    public function setServerMode($mode)
    {
        $this->server['server_mode'] = $mode;
        return $this;
    }

    /**
     * 设置socket模式
     * @param $type
     * @return $this
     */
    public function setSocketType($type)
    {
        $this->server['socket_type'] = $type;
        return $this;
    }

    /**
     * 设置运行环境类型
     * @param $env
     * @return $this
     */
    public function setEnv($env)
    {
        $this->server['env'] = $env;
        return $this;
    }

    /**
     * 设置是否为debug模式
     * @param $debug
     * @return $this
     */
    public function setDebug($debug)
    {
        $this->server['debug'] = $debug;
        return $this;
    }

    /**
     * 设置进程名称
     * @param $name
     * @return $this
     */
    public function setServerName($name)
    {
        $this->server['server_name'] = $name;
        return $this;
    }

    /**
     * 设置运行目录
     * @param $dir
     * @return $this
     */
    public function setBaseDir($dir)
    {
        $this->server['base_dir'] = $dir;
        return $this;
    }

    /**
     * 设置是否加载框架
     * @param $config
     * @return $this
     */
    public function setBeeConfig($config)
    {
        $this->server['load_bee'] = true;
        $this->server['bee_config'] = $config;
        return $this;
    }

    /**
     * 导出配置文件
     * @return array
     */
    public function exportConfig()
    {
        return array(
            'serverd' => $this->serverd,
            'server' => $this->server
        );
    }

    /**
     * 载入一个配置文件
     * @param $config
     */
    public function loadConfig($config)
    {
        if (is_string($config)) { /* 字符串被认为是一个配置文件路径 */
            $config = require $config;
        }
        $this->serverd = $config['serverd'];
        $this->server = $config['server'];
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        return new static;
    }
};