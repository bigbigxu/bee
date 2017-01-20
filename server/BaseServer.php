<?php
namespace bee\server;

/**
 * Class BaseServer
 * @package bee\server
 * swoole server基础封装类。
 * 1. 解决代码提示问题
 * 2. 增加server的默认行为和配置
 * 3. 注释
 */
class BaseServer
{
    /**
     * 服务器资源链接句柄
     * @var \swoole_server
     */
    protected $s;
    /**
     * 配置文件数组
     * @var array|mixed
     */
    protected $config = array();
    /**
     * 包结束符
     * @var mixed|string
     */
    protected $eof = '';
    /**
     * 监听IP
     * @var string
     */
    protected $host = '0.0.0.0';
    /**
     * 监听端口
     * @var string
     */
    protected $port = '9501';
    /**
     * @var string server运行模式
     */
    protected $mode = SWOOLE_PROCESS;
    /**
     * 协议类型
     * @var string
     */
    protected $protocol = self::PROTOCOL_TCP;
    /**
     * server对象
     * @var BaseServer
     */
    private static $_instance;
    /**
     * @var string server 运行根目录
     */
    protected $baseDir;
    /**
     * @var string 当前运行环境
     */
    protected $env;
    /**
     * @var int 是否为调试模式
     */
    protected $debug = 0;
    /**
     * @var array 注册的回调函数
     */
    protected $callback = array();
    /**
     * bee框架地址
     * @var string
     */
    protected $beeDir;
    /**
     * 日志目录
     * @var string
     */
    protected $logDir;
    /**
     * 数据目录
     * @var string
     */
    protected $dataDir;
    /**
     * 程序运行目录
     * @var string
     */
    protected $runDir;
    /**
     * 进程相关数据保存目录
     * @var string
     */
    protected $varDir;
    /**
     * pid 文件保存路径
     * @var string
     */
    protected $pidFile;
    /**
     * 服务器运行日志
     * @var string
     */
    protected $logFile;
    /**
     * 错误日志文件
     * @var string
     */
    protected $errorFile;
    /**
     * 访问日志文件
     * @var string
     */
    protected $accessFile;
    /**
     * 调试日志文件
     * @var string
     */
    protected $debugFile;
    /**
     * 统计文件
     * @var string
     */
    protected $statsFile;
    /**
     * @var string 服务名称
     */
    protected $name = 'swoole';
    /**
     * server类型
     * @var string
     */
    protected $serverType = self::SERVER_BASE;
    /**
     * 进程运行相关情况统计。
     * 每隔一定时间，定时器会将内容写到 run/stats 文件中。
     * BaseServer 默认不提供定时器，需要在子类中自行添加。
     * @var array
     */
    protected $stats = [];
    /*
     * 进程全局数据。使用的时候必须注意oom问题
     * 此数据会在进程启动时从文件载入， 停止时写入文件
     */
    protected $processData = [];

    //运行环境常量
    const ENV_DEV = 'dev'; //开发环境
    const ENV_TEST = 'test'; //测试环境
    const ENV_PRO = 'pro'; //生产环境

    //server命令
    const CMD_START = 'start';
    const CMD_STOP = 'stop';
    const CMD_RESTART = 'restart';
    const CMD_RELOAD = 'reload';

    /* 协议类型常量 */
    const PROTOCOL_TCP = 'tcp';
    const PROTOCOL_UDP = 'udp';
    const PROTOCOL_HTTP = 'http';
    const PROTOCOL_WS = 'ws';

    /* server 类型常量 */
    const SERVER_BASE = '\swoole_server'; /* 基础swoole server */
    const SERVER_HTTP = '\swoole_http_server'; /* http swoole server */
    const SERVER_WEBSOCKET = '\swoole_websocket_server'; /* websocket server */
    const SERVER_REDIS = '\swoole_redis_server'; /* redis 协议server */

    /**
     * 执行环境检查
     * BaseServer constructor.
     */
    public function __construct()
    {
        self::checkEnv();
        $this->beeDir = realpath(__DIR__ . '/..');
    }

    /**
     * 创建server
     */
    private function _createServer()
    {
        $this->host = $this->c('server.host');
        $this->port = $this->c('server.port');
        $this->mode = $this->c('server.server_mode');
        $this->protocol = $this->c('server.socket_type');
        $type = $this->serverType;
        $this->s = new $type($this->host, $this->port, $this->mode, $this->protocol);
    }

    /**
     * 根据配置文件生成server默认配置
     */
    private function _initConfig()
    {
        if (is_writable($this->c('server.base_dir')) == false) {
            die("server.base_dir 不可使用\n");
        }
        $this->debug = (int)$this->c('server.debug');
        $this->env = $this->c('server.env');
        $this->eof = $this->c('serverd.package_eof');
        $this->name = $this->c('server.server_name');

        /* 目录位置初始化 */
        $this->baseDir = rtrim($this->c('server.base_dir'), '/');
        $this->runDir = $this->baseDir . '/run';
        $this->logDir = $this->baseDir . '/log';
        $this->dataDir = $this->baseDir . '/data';
        $this->varDir = $this->baseDir . '/var';

        /* 文件配置初始化 */
        $this->pidFile = $this->runDir . '/server.pid';
        $this->errorFile = $this->logDir . '/error.log';
        $this->accessFile = $this->logDir . '/access.log';
        $this->debugFile = $this->logDir . '/debug.log';
        $this->statsFile = $this->logDir . '/stats.log';
        $this->logFile = $this->runDir . '/server.log';

        if ($this->c('serverd.log_file') == false) {
            $this->c("serverd.log_file", $this->logFile);
        }
    }

    /**
     * 设置php运行时的环境
     * server.php_env配置节用于在server运行修改php.ini的配置
     */
    public function setPhpEnv()
    {
        $env = array_merge($this->getDefaultPhpEnv(), (array)$this->c('server.php_env'));
        foreach ($env as $key => $value) {
            ini_set($key, $value);
        }
        register_shutdown_function(array($this, 'shutdownFunction'));
    }

    /**
     * 设置php默认的环境相关的默认配置
     * @return array
     */
    public function getDefaultPhpEnv()
    {
        return array(
            'display_errors' => 0,
            'error_reporting' => E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED,
            'log_errors' => 1,
            'error_log' => $this->errorFile
        );
    }

    /**
     * 实例化一个对象
     * @return static
     */
    public static function getInstance()
    {
        $name = get_called_class();
        if (!isset(self::$_instance)) {
            self::$_instance = new $name();
        }
        return self::$_instance;
    }

    /**
     * 子类可以重写此函数。
     */
    public function init()
    {

    }

    /**
     * 完成回调函数的注册
     * 所有使用on开始的函数都被认为是回调函数
     * 使用this注册，那可回调函数中可使用this　
     */
    public function registerCallback()
    {
        $methods = get_class_methods($this);
        foreach ($methods as $row) {
            if (preg_match('/^on(\w+)$/', $row, $ma)) {
                $event = ucfirst($ma[1]);
                if (!isset($this->callback[$event])) {
                    $this->callback[$event] = array($this, $row);
                }
            }
        }
        foreach ($this->callback as $event => $fun) {
            $this->s->on($event, $fun);
        }
        $this->callback = array();
    }

    /**
     * 执行环境检查
     */
    public static function checkEnv()
    {
        if (strtolower(PHP_OS) != 'linux') {
            die("请于linux下运行\n");
        }
        if (!extension_loaded('swoole')) {
            die("请安装swoole扩展\n");
        }
        if (substr(php_sapi_name(), 0, 3) != 'cli') {
            die("请以cli模式运行\n");
        }
        if (PHP_VERSION < '5.4') {
            die("php版本不能小于5.4\n");
        }
    }

    /**
     * 启动server，监听所有TCP/UDP端口
     * 如果想设置开机启动　在/etc/rc.local　加入启动命令
     * start之前创建的对象，所有worker进程共享，如果要修改，只能重启服务
     * @return bool
     */
    public function start()
    {
        $this->init(); //如果子类有其它初始化要求，重载init方法。
        $this->setPhpEnv(); //设置php运行环境
        $this->_createServer();
        $this->s->set($this->c('serverd'));
        $this->registerCallback(); //注册回调函数
        echo "你可使用--help查看命令更多选项\n";
        echo "server is starting\n";
        $this->s->start(); //启动服务
    }

    /**
     * 重启所有worker进程
     * 重启所有worker进程 kill -10 主进程PID，仅重启task进程 kill -12 主进程PID
     *
     * 平滑重启只对onWorkerStart或onReceive等在Worker进程中include/require的PHP文件有效，
     *  Server启动前就已经include/require的PHP文件，不能通过平滑重启重新加载
     *
     * 对于Server的配置即$serv->set()中传入的参数设置，必须关闭/重启整个Server才可以重新加载*
     * @return bool
     */
    public function reloadWorker()
    {
        return $this->s->reload();
    }

    /**
     * 关闭服务器
     * 此函数可以用在worker进程内。向主进程发送SIGTERM也可以实现关闭服务器。
     * kill -15 主进程PID
     * @return bool
     */
    public function shutdown()
    {
        return $this->s->shutdown();
    }


    /**
     * 关闭客户端连接
     * 操作成功返回true，失败返回false.
     * Server主动close连接，也一样会触发onClose事件。
     * 不要在close之后写清理逻辑。应当放置到onClose回调中处理。
     *
     * @param int $fd 当前连接描述符
     * @return bool
     */
    public function close($fd)
    {
        return $this->s->close($fd);
    }

    /**
     * 向客户端发送数据
     *
     *  * $data，发送的数据。TCP协议最大不得超过2M，UDP协议不得超过64K
     *  * 发送成功会返回true，如果连接已被关闭或发送失败会返回false
     *
     *  TCP服务器
     *  * send操作具有原子性，多个进程同时调用send向同一个连接发送数据，不会发生数据混杂
     *  * 如果要发送超过2M的数据，可以将数据写入临时文件，然后通过sendfile接口进行发送
     *
     *
     * UDP服务器
     *  * send操作会直接在worker进程内发送数据包，不会再经过主进程转发
     *  * 使用fd保存客户端IP，from_id保存from_fd和port
     *  * 如果在onReceive后立即向客户端发送数据，可以不传$from_id
     *  * 如果向其他UDP客户端发送数据，必须要传入from_id
     *  * 在外网服务中发送超过64K的数据会分成多个传输单元进行发送，如果其中一个单元丢包，会导致整个包被丢弃。
     *  * 所以外网服务，建议发送1.5K以下的数据包
     *
     * @param int $fd
     * @param string $data
     * @param int $fromId
     * @return bool
     */
    public function send($fd, $data, $fromId = 0)
    {
        return $this->s->send($fd, $data . $this->eof, $fromId);
    }

    /**
     * 发送文件到TCP客户端连接
     *
     * endfile函数调用OS提供的sendfile系统调用，由操作系统直接读取文件并写入socket。
     * sendfile只有2次内存拷贝，使用此函数可以降低发送大量文件时操作系统的CPU和内存占用。
     *
     * $filename 要发送的文件路径，如果文件不存在会返回false
     * 操作成功返回true，失败返回false
     * 此函数与swoole_server->send都是向客户端发送数据，不同的是sendfile的数据来自于指定的文件。
     *
     * @param int $fd
     * @param string $filename 文件绝对路径
     * @return bool
     */
    public function sendFile($fd, $filename)
    {
        clearstatcache();
        if (!file_exists($filename)) {
            return false;
        }
        return $this->s->sendfile($fd, $filename);
    }

    /**
     * 获取连接的信息
     * connection_info可用于UDP服务器，但需要传入from_id参数
     *
     *      array (
     *           'from_id' => 0,
     *           'from_fd' => 12,
     *           'connect_time' => 1392895129,
     *           'last_time' => 1392895137,
     *           'from_port' => 9501,
     *           'remote_port' => 48918,
     *           'remote_ip' => '127.0.0.1',
     *      )
     *
     *  * $udp_client = $serv->connection_info($fd, $from_id);
     *  * var_dump($udp_client);
     *  * from_id 来自哪个reactor线程
     *  * server_fd 来自哪个server socket 这里不是客户端连接的fd
     *  * server_port 来自哪个Server端口
     *  * remote_port 客户端连接的端口
     *  * remote_ip 客户端连接的ip
     *  * connect_time 连接到Server的时间，单位秒
     *  * last_time 最后一次发送数据的时间，单位秒
     *
     * @param int $fd
     * @param int $fromId
     * @return array | bool
     */
    public function connectionInfo($fd, $fromId = -1)
    {
        return $this->s->connection_info($fd, $fromId);
    }

    /**
     * 得到客户端IP地址
     * @param int $fd
     */
    public function clientIp($fd)
    {
        $info = $this->connectionInfo($fd);
        return $info['remote_ip'];
    }

    /**
     * 用来遍历当前Server所有的客户端连接，connection_list方法是基于共享内存的，
     * 不存在IOWait，遍历的速度很快。
     * 另外connection_list会返回所有TCP连接，而不仅仅是当前worker进程的TCP连接
     *
     * 示例：
     *
     *      $start_fd = 0;
     *      while(true)
     *      {
     *          $conn_list = $serv->connection_list($start_fd, 10);
     *          if($conn_list===false or count($conn_list) === 0)
     *          {
     *              echo "finish\n";
     *              break;
     *          }
     *          $start_fd = end($conn_list);
     *          var_dump($conn_list);
     *          foreach($conn_list as $fd)
     *          {
     *              $serv->send($fd, "broadcast");
     *          }
     *      }
     *
     * @param int $startFd 起始fd
     * @param int $pageSize 每页取多少条，最大不得超过100
     * @return array | bool
     */
    public function connectionList($startFd = 0, $pageSize = 10)
    {
        return $this->s->connection_list($startFd, $pageSize);
    }

    /**
     * 投递一个异步任务到task_worker池中。此函数会立即返回。worker进程可以继续处理新的请求
     *
     * 注意事项
     *  * 数据超过8K时会启用临时文件来保存。当临时文件内容超过 server->package_max_length 时底层会抛出一个警告。
     *  * 使用swoole_server_task必须为Server设置onTask和onFinish回调，否则swoole_server->start会失败
     *  * task操作的次数必须小于onTask处理速度，如果投递容量超过处理能力，task会塞满缓存区，
     *  * 导致worker进程发生阻塞。worker进程将无法接收新的请求
     *
     * @param mixed $data
     * @param int $taskWorkerId
     * @return bool
     */
    public function task($data, $taskWorkerId = -1)
    {
        return $this->s->task($data, $taskWorkerId);
    }

    /**
     * taskwait与task方法作用相同，用于投递一个异步的任务到task进程池去执行。
     * 与task不同的是taskwait是阻塞等待的，直到任务完成或者超时返回
     *
     * @param mixed $data
     * @param float $timeout
     * @param int $taskWorkerId
     * @return string
     */
    public function taskWait($data, $timeout = 0.5, $taskWorkerId = -1)
    {
        return $this->s->taskwait($data, $timeout, $taskWorkerId);
    }

    /**
     * 此函数用于在task进程中通知worker进程，投递的任务已完成。此函数可以传递结果数据给worker进程
     *  $serv->finish("response");
     * 使用swoole_server::finish函数必须为Server设置onFinish回调函数。此函数只可用于task进程的onTask回调中
     *
     * swoole_server::finish是可选的。如果worker进程不关心任务执行的结果，不需要调用此函数
     * 在onTask回调函数中return字符串，等同于调用finish
     *
     * 在onTask中，不能返回false或者null，不然swoole会认为task执行失败
     * @param string $data
     */
    public function finish($data)
    {
        $this->s->finish($data);
    }

    /**
     * 检测服务器所有连接，并找出已经超过约定时间的连接。
     * 如果指定if_close_connection，则自动关闭超时的连接。未指定仅返回连接的fd数组'
     *
     *  * $if_close_connection是否关闭超时的连接，默认为true
     *  * 调用成功将返回一个连续数组，元素是已关闭的$fd。
     *  * 调用失败返回false
     *
     * @param bool $close
     * @return array
     */
    public function heartbeat($close = true)
    {
        return $this->s->heartbeat($close);
    }

    /**
     * 返回当前服务器主进程的PID
     */
    public function getMasterPid()
    {
        return $this->s->master_pid;
    }

    /**
     * 返回当前服务器管理进程的PID
     */
    public function getManagerPid()
    {
        return $this->s->manager_pid;
    }

    /**
     * 得到最后一次的错误信息
     */
    public function getErrmsg()
    {
        $errno = swoole_errno();
        return \swoole_strerror($errno);
    }

    /**
     * 设置运行时参数
     *
     * swoole_server->set函数用于设置swoole_server运行时的各项参数。
     * 服务器启动后通过$serv->setting来访问set函数设置的参数数组。
     *
     * 只能在swoole_server::start前调用
     * @param array $config
     */
    public function set($config)
    {
        $this->s->set($config);
    }

    /**
     * 注册事件回调函数
     * @param string $event
     * @param mixed $callback
     * @return $this
     */
    public function on($event, $callback)
    {
        $event = ucfirst($event);
        $this->callback[$event] = $callback;
        return $this;
    }

    /**
     * Swoole提供了swoole_server::addListener来增加监听的端口。
     * 业务代码中可以通过调用swoole_server::connection_info来获取某个连接来自于哪个端口
     *
     * * SWOOLE_TCP/SWOOLE_SOCK_TCP tcp ipv4 socket
     * * SWOOLE_TCP6/SWOOLE_SOCK_TCP6 tcp ipv6 socket
     * * SWOOLE_UDP/SWOOLE_SOCK_UDP udp ipv4 socket
     * * SWOOLE_UDP6/SWOOLE_SOCK_UDP6 udp ipv6 socket
     * * SWOOLE_UNIX_DGRAM unix socket dgram
     * * SWOOLE_UNIX_STREAM unix socket stream
     *
     * 可以混合使用UDP/TCP，同时监听内网和外网端口。 示例：
     *      $serv->addlistener("127.0.0.1", 9502, SWOOLE_SOCK_TCP);
     *      $serv->addlistener("192.168.1.100", 9503, SWOOLE_SOCK_TCP);
     *      $serv->addlistener("0.0.0.0", 9504, SWOOLE_SOCK_UDP);
     *      $serv->addlistener("/var/run/myserv.sock", 0, SWOOLE_UNIX_STREAM);
     *
     * @param string $host
     * @param int $port
     * @param int $type
     */
    public function addListener($host, $port, $type = SWOOLE_SOCK_TCP)
    {
        $this->s->addlistener($host, $port, $type);
    }

    /**
     * 增加tick定时器，定时器仅在当前进程空间内有效，可以有间隔时间相同的定时器
     * $ms 最大不得超过 86400000
     * 定时器中，如果存在sleep或阻塞操作，会阻塞worker的onreceive，task进程的ontask
     *
     * 回调函数执行的时候，会传递2个参数。一个是timer_id，一个param参数
     * @param int $ms
     * @param mixed $callback
     * @param mixed $param
     * @return int 返回当前定时器代号
     */
    public function tick($ms, $callback, $param = null)
    {
        return $this->s->tick($ms, $callback, $param);
    }

    /**
     * 在指定的时间后执行函数
     *
     * swoole_server::after函数是一个一次性定时器，执行完成后就会销毁。
     *
     * $after_time_ms 指定时间，单位为毫秒
     * $callback_function 时间到期后所执行的函数，必须是可以调用的。callback函数不接受任何参数
     * $after_time_ms 最大不得超过 86400000
     * 此方法是swoole_timer_after函数的别名
     *
     * @param $ms
     * @param int $ms
     * @param mixed $callback
     */
    public function after($ms, $callback)
    {
        $this->s->after($ms, $callback);
    }

    /**
     * 删除设定的定时器，此定时器不会再触发
     * @param $id
     */
    public function clearTimer($id)
    {
        $this->s->clearAfter($id);
    }

    /**
     * 此函数可以向任意worker进程或者task进程发送消息。
     * 在非主进程和管理进程中可调用。收到消息的进程会触发onPipeMessage事件
     *
     *  * $message为发送的消息数据内容
     *  * $dst_worker_id为目标进程的ID，范围是0 ~ (worker_num + task_worker_num - 1)
     *
     * !! 使用sendMessage必须注册onPipeMessage事件回调函数
     * @param string $message
     * @param int $workerId
     * @return bool
     */
    public function sendMessage($message, $workerId = -1)
    {
        return $this->s->sendMessage($message, $workerId);
    }

    /**
     * 检测fd对应的连接是否存在。
     * @param $fd
     * @return mixed
     */
    public function exists($fd)
    {
        return $this->s->exists($fd);
    }

    /**
     * swoole_server::set()
     * @return array
     */
    public function getSetting()
    {
        return $this->s->setting;
    }

    /**
     * 得到当前worker进程ID
     * @return int
     */
    public function getWorkerId()
    {
        return $this->s->worker_id;
    }

    /**
     * 得到当前Worker进程的操作系统进程ID。与posix_getpid()的返回值相同。
     * @return int
     */
    public function getWorkerPid()
    {
        return $this->s->worker_pid;
    }

    /**
     * 是否 Task 工作进程
     *  true  表示当前的进程是Task工作进程
     *  false 表示当前的进程是Worker进程
     * @return bool
     */
    public function isTaskWorker()
    {
        return $this->s->taskworker;
    }

    /**
     * TCP连接迭代器，可以使用foreach遍历服务器当前所有的连接，
     * 此属性的功能与swoole_server->connnection_list是一致的，
     * 但是更加友好。遍历的元素为单个连接的fd
     *
     * 连接迭代器依赖pcre库，未安装pcre库无法使用此功能
     *
     *      foreach($server->connections as $fd)
     *      {
     *          $server->send($fd, "hello");
     *      }
     *
     *      echo "当前服务器共有 ".count($server->connections). " 个连接\n";
     *
     * !! 注意$connections属性是一个迭代器对象, 只能通过foreach进行遍历操作。
     * @return array
     */
    public function getConnections()
    {
        return $this->s->connections;
    }

    /**
     * 得到或设置配置参数
     * @param string $path
     * @param null $value
     * @return array|mixed|null
     */
    public function c($path = '', $value = null)
    {
        if ($path == '') {
            return $this->config;
        }
        $pathArr = explode('.', $path);
        $tmp = &$this->config;
        foreach ($pathArr as &$row) {
            $tmp = &$tmp[$row];
        }
        if ($value !== null) {
            $tmp = $value;
        }
        return $tmp;
    }

    /**
     * !! 在onStart中创建的全局资源对象不能在worker进程中被使用，
     *      因为发生onStart调用时，worker进程已经创建好了。
     *      新创建的对象在主进程内，worker进程无法访问到此内存区域。
     *      因此全局对象创建的代码需要放置在swoole_server_start之前。
     *
     * !! onStart回调中，仅允许echo、打印Log、修改进程名称。不得执行其他操作。
     *      onWorkerStart和onStart回调是在不同进程中并行执行的，不存在先后顺序。
     * Server启动在主进程的主线程回调此函数
     * @param \swoole_server $server
     */
    public function onStart(\swoole_server $server)
    {
        if (is_dir($this->runDir) == false) {
            mkdir($this->runDir); //创建运行目录
        }
        $pidStr = '';
        $pidStr .= "master_pid={$server->master_pid}\n";
        $pidStr .= "manager_pid={$server->manager_pid}";
        file_put_contents($this->pidFile, $pidStr);
        $this->serverLog("server is start\n");
        swoole_set_process_name($this->name . "_master");
    }

    /**
     * 此事件在Server结束时发生
     *
     * 强制kill进程不会回调onShutdown，如kill -9
     * 需要使用kill -15来发送SIGTREM信号到主进程才能按照正常的流程终止
     * @param \swoole_server $server
     */
    public function onShutdown(\swoole_server $server)
    {
        $this->serverLog("server shutdown");
    }

    /**
     * 此事件在worker进程/task进程启动时发生。这里创建的对象可以在进程生命周期内使用
     *
     * 通过$worker_id参数的值来，判断worker是普通worker还是task_worker。
     * $worker_id>= $serv->setting['worker_num'] 时表示这个进程是task_worker。
     *
     *      如果想使用swoole_server_reload实现代码重载入，必须在workerStart中require你的业务文件，
     *      而不是在文件头部。在onWorkerStart调用之前已包含的文件，不会重新载入代码。
     *
     *      可以将公用的，不易变的php文件放置到onWorkerStart之前。
     *      这样虽然不能重载入代码，但所有worker是共享的，不需要额外的内存来保存这些数据。
     *      onWorkerStart之后的代码每个worker都需要在内存中保存一份
     * @param \swoole_server $server
     * @param $workerId
     */
    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        /* 清理可能的 apc，opcache缓存 */
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        if ($workerId == 0) {
            if (!is_dir($this->logDir)) {
                mkdir($this->logDir);
            }

            if (!is_dir($this->dataDir)) {
                mkdir($this->dataDir);
            }
            if (!is_dir($this->varDir)) {
                mkdir($this->varDir);
            }
        }
        if ($workerId >= $server->setting['worker_num']) {
            swoole_set_process_name("{$this->name}_task");
        } else {
            swoole_set_process_name("{$this->name}_event");
        }

        //加载框架配置文件
        if ($this->c('server.load_bee')) {
            require __DIR__ . '/../App.php';
            $configPath = $this->c('server.bee_config');
            \App::getInstance($configPath);
        }
        $this->loadTaskData();
    }

    /**
     * 此事件在worker进程终止时发生。在此函数中可以回收worker进程申请的各类资源
     * @param \swoole_server $server
     * @param $workerId
     */
    public function onWorkerStop(\swoole_server $server, $workerId)
    {
        $this->saveTaskData();
    }

    /**
     * 有新的连接进入时，在worker进程中回调
     * onConnect/onClose这2个回调发生在worker进程内，而不是主进程。
     * @param \swoole_server $server
     * @param $fd
     * @param $fromId
     */
    public function onConnect(\swoole_server $server, $fd, $fromId)
    {

    }

    /**
     * 接收到数据时回调此函数，发生在worker进程中
     *
     * UDP协议，onReceive可以保证总是收到一个完整的包，最大长度不超过64K
     * UDP协议下，$fd参数是对应客户端的IP，$from_id是客户端的端口
     *
     * TCP协议是流式的，onReceive无法保证数据包的完整性，
     * 可能会同时收到多个请求包，也可能只收到一个请求包的一部分数据
     *
     * 这里面添加的定时器也是整个进程周期内有销
     * @param \swoole_server $server
     * @param $fd
     * @param $fromId
     * @param $data
     */
    public function onReceive(\swoole_server $server, $fd, $fromId, $data)
    {
        $data = trim($data, $this->eof);
        $this->send($fd, 'server:' . $data, $fromId);
    }


    /**
     * TCP客户端连接关闭后，在worker进程中回调此函数
     *
     * onClose回调函数如果发生了致命错误，会导致连接泄漏。通过netstat命令会看到大量CLOSE_WAIT状态的TCP连接
     *
     *      这里回调onClose时表示客户端连接已经关闭，所以无需执行$server->close($fd)。
     *      代码中执行$serv->close($fd) 会抛出PHP错误告警
     * @param \swoole_server $server
     * @param $fd
     * @param $fromId
     */
    public function onClose(\swoole_server $server, $fd, $fromId)
    {

    }

    /**
     *
     * task投递的任务，由onTask完成处理
     * 在task_worker进程内被调用。worker进程可以使用swoole_server_task函数向task_worker进程投递新的任务
     *
     * !! 1.7.2以上的版本，$data的长度不受限制，如果超过SW_BUFFER_SIZE，将自动写入临时文件
     *
     *      1.7.2以上的版本，在onTask函数中 return字符串，表示将此内容返回给worker进程。
     *      worker进程中会触发onFinish函数，表示投递的task已完成
     * @param \swoole_server $server
     * @param int $taskId 任务ID，由swoole扩展内自动生成，用于区分不同的任务。$task_id和$from_id组合起来才是全局唯一的
     * @param $fromId
     * @param $data
     */
    public function onTask(\swoole_server $server, $taskId, $fromId, $data)
    {

    }


    /**
     * 当worker进程投递的任务在task_worker中完成时，
     * task进程会通过swoole_server->finish()方法将任务处理的结果发送给worker进程。
     *
     * task进程的onTask事件中没有调用finish方法或者return结果。worker进程不会触发onFinish
     * @param \swoole_server $server
     * @param $taskId
     * @param $data
     */
    public function onFinish(\swoole_server $server, $taskId, $data)
    {

    }

    /**
     * 当工作进程收到由sendMessage发送的管道消息时会触发onPipeMessage事件。
     * worker/task进程都可能会触发onPipeMessage事件
     * @param \swoole_server $server
     * @param $fromWorkerId
     * @param $message
     */
    public function onPipeMessage(\swoole_server $server, $fromWorkerId, $message)
    {

    }

    /**
     * 当worker/task_worker进程发生异常后会在Manager进程内回调此函数。
     *      此函数主要用于报警和监控，一旦发现Worker进程异常退出，
     *      那么很有可能是遇到了致命错误或者进程CoreDump。
     *      通过记录日志或者发送报警的信息来提示开发者进行相应的处理
     * @param \swoole_server $server
     * @param $workerId
     * @param $workerPid
     * @param $exitCode
     */
    public function onWorkerError(\swoole_server $server, $workerId, $workerPid, $exitCode)
    {
        $this->serverLog("{$workerId}:{$workerPid} exit by {$exitCode}");
    }

    /**
     * 当管理进程启动时调用它
     * 在这个回调函数中可以修改管理进程的名称
     * @param \swoole_server $server
     */
    public function onManagerStart(\swoole_server $server)
    {
        swoole_set_process_name("{$this->name}_manager");
    }

    /**
     * 当管理进程结束时调用它
     * @param \swoole_server $server
     */
    public function onManagerStop(\swoole_server $server)
    {

    }

    /**
     * 记录server运行，启动，错误的日志。
     * @param $msg
     */
    public function serverLog($msg)
    {
        $this->log($this->logFile, $msg);
    }

    /**
     * 记录访问错误日志
     * @param $msg
     */
    public function errorLog($msg)
    {
        $this->log($this->errorFile, $msg);
    }

    /**
     * 记录访问日志
     * @param $msg
     */
    public function accessLog($msg)
    {
        $this->log($this->accessFile, $msg);
    }

    /**
     * 调试日志
     * @param $msg
     */
    public function debugLog($msg)
    {
        $this->log($this->debugFile, $msg);
    }

    /**
     * 记录日志。
     * @param $file
     * @param $msg
     */
    public function log($file, $msg)
    {
        $time = date('Y-m-d H:i:s');
        $msg = "[{$time}] {$msg}" . PHP_EOL;
        file_put_contents($file, $msg, FILE_APPEND);
    }

    /********************以下为管理进程相关命令*******************/

    /**
     * 停止命令
     */
    public function stop()
    {
        $masterPid = $this->getMasterPidByFile();
        if ($masterPid == false) {
            die("not found master pid\n");
        }
        $signal = SIGTERM;
        shell_exec("kill -s {$signal} {$masterPid}");
        echo "server is stopped\n";
    }

    /**
     * 重启所有worker进程
     */
    public function reload()
    {
        $masterPid = $this->getMasterPidByFile();
        if ($masterPid == false) {
            die("not found master pid\n");
        }
        $signal = SIGUSR1;
        shell_exec("kill -s {$signal} {$masterPid}");
    }

    /**
     * 从pid文件中得到主进程id
     */
    public function getMasterPidByFile()
    {
        $pidArr = parse_ini_file($this->pidFile);
        return $pidArr['master_pid'];
    }

    /**
     * 重启服务
     */
    public function restart()
    {
        $this->stop();
        sleep(3);
        $this->start();
    }

    /**
     *
     * 命令行选项
     * c, config 指定配置文件
     * h,host指定ip
     * p,port指定端口
     * d,daemon 是否后台运行。默认false
     * s,表示相关的启动命令
     * 通过命令行参数来设置相关选项
     * @param string $defaultConfigPath 默认的配置文件路径。如果没有-c,--config选项，则要加载的配置的文件
     * @return array
     */
    public function getOptsByCli($defaultConfigPath = null)
    {
        $cmdOpts = 'c:h:p:ds:';
        $cmdLongOpts = array(
            'config:',
            'host:',
            'port:',
            'daemon',
            'base_dir:',
            'help',
            'debug'
        );
        $opts = getopt($cmdOpts, $cmdLongOpts);
        if (isset($opts['help'])) {
            self::help();
        }
        $method = $opts['s'];
        $allowMethod = array('status', 'start', 'stop', 'restart', 'reload');
        if (in_array($method, $allowMethod) == false) {
            die("Usage: server {start|stop|restart|reload|status}\n");
        }
        if (isset($opts['c']) || isset($opts['config'])) { //设置配置文件选项
            $config = require ($opts['c'] ?: $opts['config']);
        } elseif (is_array($defaultConfigPath)) {
            $config = $defaultConfigPath;
        } elseif (is_string($defaultConfigPath)) {
            $config = require $defaultConfigPath;
        } else {
            $config = array();
        }
        if ($opts['h'] || $opts['host']) { //设置主机
            $config['server']['host'] =  $opts['h'] ?: $opts['host'];
        }
        if ($opts['p'] || $opts['port']) { //设置端口
            $config['server']['port'] =  $opts['p'] ?: $opts['port'];
        }
        if (isset($opts['d']) || isset($opts['daemon'])) { //设置后台运行
            $config['serverd']['daemonize'] =  true;
        }
        if ($opts['base_dir']) {
            $config['server']['base_dir'] =  $opts['base_dir'];
        }
        if (isset($opts['debug'])) {
            $config['server']['debug'] = 1;
        }
        return array($method, $config);
    }

    public function status()
    {
        $str = file_get_contents($this->statsFile);
        die($str);
    }

    /**
     * 运行之前修改配置。
     * 进行数组合并
     * 在执行start之前，必须先调用些方法设置配置文件
     * @param mixed $config 可以是一个文件路径或数组
     * @param bool $merge 是否合并配置文件
     * @return $this
     */
    public function setConfig($config, $merge = true)
    {
        if (is_array($config)) {

        } elseif(is_readable($config)) {
            $config = require $config;
        } else {
            $config = array();
        }
        if ($merge == true) {
            $this->config = array_merge_recursive($this->config, $config);
        } else {
            $this->config = $config;
        }
        $this->_initConfig(); //配置server默认行为
        return $this;
    }

    /**
     * 返回swoole_server对象
     * @return \swoole_server
     */
    public function getSwoole()
    {
        return $this->s;
    }

    /**
     * 执行命令
     * @param $cmd
     */
    public function run($cmd)
    {
        $this->$cmd();
    }

    /**
     * 输出命令行帮助
     */
    public static function help()
    {
        $arr = array(
            '-s，指定当前服务动作，start启动，stop停止，restart重启，reload重载',
            '-c --config，指定启动的配置文件。如果未指定将加载默认配置',
            '-d --daemon，指定服务以守护进程方式运行',
            '-h --host， 指定服务监听IP，默认为0.0.0.0',
            '-p --port，指定服务监听端口，默认为9501',
            '--base_dir，指定server运行目录',
            '--debug，开启调试模式，将有更多的日志记录在debug.log中',
            '--help，查看命令帮助'
        );
        $str = implode("\n", $arr) . "\n";
        die($str);
    }

    /**
     * 返回当前是否为debug模式
     * @return int
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * 将server运行统计数据写入文件。
     */
    public function writeStats()
    {
        $str = file_get_contents($this->statsFile);
        $res = json_decode($str, true);
        $workerId = $this->getWorkerId();
        foreach ($this->stats as $key => $row) {
            $res[$workerId][$key] += $row;
        }

        file_put_contents($this->statsFile, json_encode($res));
        $this->stats = [];
    }

    /**
     * 保存进程数据
     */
    public function saveTaskData()
    {
        $workerId = $this->getWorkerId();
        if ($workerId == false) {
            return null;
        } else {
            $workerId = sprintf('%03d',$workerId);
        }
        $file = $this->varDir . '/data_' . $workerId;
        file_put_contents($file, serialize($this->processData));
    }

    /**
     * 载入进程数据
     */
    public function loadTaskData()
    {
        $workerId = $this->getWorkerId();
        if ($workerId == false) {
            return null;
        } else {
            $workerId = sprintf('%03d',$workerId);
        }
        $file = $this->varDir . '/data_' . $workerId;
        if (!is_file($file)) {
            return null;
        }
        $copyDir = $this->varDir . '/history_' . date('Y-m-d#H-i-s');
        if (!is_dir($copyDir)) {
            mkdir($copyDir);
        }
        $str = file_get_contents($file);
        $this->processData = unserialize($str);
        copy($file, $copyDir . '/data_' . $workerId);
        unlink($file);
    }

    public function shutdownFunction()
    {
        $this->saveTaskData();
    }
}