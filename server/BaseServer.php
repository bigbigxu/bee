<?php
namespace bee\server;

/**
 * Class BaseServer
 * @package iphp\server
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
     * server对象
     * @var BaseServer
     */
    private static $_instance;
    protected $baseDir; //程序运行根目录

    /**
     * 加载配置文件
     * BaseServer constructor.
     * @param string $configPath
     */
    public function __construct($configPath = '')
    {
        self::checkEnv();
        if (!file_exists($configPath)) {
            $configPath = __DIR__ . '/config.php';
        }
        $this->config = require($configPath);
        if($this->c('server.debug') == true) { //debug模式下设置非后台运行
            $this->config['serverd']['daemonize'] = false;
        }
        if ($this->c('server.base_dir') == false) {
            die("请指定程序运行根目录\n");
        }

        $this->baseDir = rtrim($this->c('server.base_dir'), '/');
        $this->c("server.run_dir", $this->baseDir . '/run'); //运行目录
        $this->c("server.log_dir", $this->baseDir . "/log"); //日志目录
        $this->c("server.data_dir", $this->baseDir . "/data"); //数据目录

        //定义相关文件
        $this->c("server.pid_file", $this->baseDir . '/run/server.pid');
        $this->c("server.error_log", $this->baseDir . '/log/error.log');
        $this->c("server.access_log", $this->baseDir . '/log/access.log');
        if ($this->c('serverd.log_file') == false) {
            $this->c("serverd.log_file", $this->baseDir . '/run/server.log');
        }
        error_reporting(E_ALL & ~E_NOTICE);
        ini_set('error_log', $this->c('server.error_log'));
        $this->init();
    }


    /**
     * 实例化一个对象
     * @param $configPath
     * @return static
     */
    public static function getInstance($configPath = '')
    {
        $name = get_called_class();
        if (!isset(self::$_instance)) {
            self::$_instance = new $name($configPath);
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
     * 使用this注册，那可回调函数中可使用this　
     */
    public function registerCallback()
    {
        $methods = get_class_methods($this);
        foreach ($methods as $row) {
            if (preg_match('/^on(\w+)$/', $row, $ma)) {
                $this->on($ma[1], array($this, $row));
            }
        }
    }
    /**
     * 执行环境检查
     */
    public static function checkEnv()
    {
        if (strtolower(PHP_OS) !='linux') {
            die('请于linux下运行');
        }
        if (!extension_loaded('swoole')) {
            die('请安装swoole扩展');
        }
        if (substr(php_sapi_name(), 0, 3) != 'cli') {
            die("请以cli模式运行");
        }
        if (PHP_VERSION < '5.3') {
            die('php版本不能小于5.3');
        }
    }
    /**
     * 启动成功后会创建worker_num + task_worker_num + 2个进程。
     * master进程用于事件分发。
     * Manager进程，管理worker进程。
     * worker进程对 收到的数据进行处理，包括协议解析和响应请求。
     * 如果想设置开机启动　在/etc/rc.local　加入启动命令
     *
     * start之前创建的对象，所有worker进程共享，如果要修改，只能重启服务
     * @return bool
     */
    public function start()
    {
        $this->eof = $this->c('serverd.package_eof');
        $host = $this->c('server.host');
        $port = $this->c('server.port');
        $mode = $this->c('server.server_mode');
        $type = $this->c('server.socket_type');
        $this->s = new \swoole_server($host, $port, $mode, $type);

        $this->s->set($this->c('serverd'));
        $this->registerCallback();
        return $this->s->start();
    }

    /**
     * 重启所有worker进程
     *
     *  重启所有worker进程 kill -SIGUSR1 主进程PID
     *  仅重启task进程 kill -SIGUSR2 主进程PID
     *
     * 平滑重启只对onWorkerStart或onReceive等在Worker进程中include/require的PHP文件有效，
     * Server启动前就已经include/require的PHP文件，不能通过平滑重启重新加载
     *
     * 对于Server的配置即$serv->set()中传入的参数设置，必须关闭/重启整个Server才可以重新加载
     * Server可以监听一个内网端口，然后可以接收远程的控制命令，去重启所有worker
     *
     * @return bool
     */
    public function reloadWorker()
    {
        return $this->s->reload();
    }

    /**
     * 关闭服务器
     * 此函数可以用在worker进程内。向主进程发送SIGTERM也可以实现关闭服务器。
     * kill -SIGTERM 主进程PID
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
     *
     * @param mixed $data
     * @param float $timeout
     * @param int $taskWorkerId
     * @return string
     */
    public function taskWait($data, $timeout = 0.5,$taskWorkerId = -1)
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
     */
    public function on($event, $callback)
    {
        $this->s->on($event, $callback);
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
     * 增加tick定时器
     * 可以自定义回调函数。此函数是swoole_timer_tick的别名
     * worker进程结束运行后，所有定时器都会自动销毁
     *
     * 设置一个间隔时钟定时器，与after定时器不同的是tick定时器会持续触发，直到调用swoole_timer_clear清除。
     * 与swoole_timer_add不同的是tick定时器可以存在多个相同间隔时间的定时器。
     *
     * 定时器仅在当前进程空间内有效
     *
     * $ms 最大不得超过 86400000
     * tick定时器在1.7.14以上版本可用
     * tick定时器即将要取代swoole_timer_add
     *
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
        if (is_dir($this->c('server.run_dir')) == false) {
            mkdir($this->c('server.run_dir')); //创建运行目录
        }
        $pidFile = $this->c('server.pid_file');
        $pidStr = '';
        $pidStr .= "master_pid={$server->master_pid}\n";
        $pidStr .= "manager_pid={$server->manager_pid}";
        file_put_contents($pidFile, $pidStr);
        swoole_set_process_name($this->c('server.server_name') . "_master");
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
        $this->serverLog('server shutdown');
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
        if ($workerId == 0) {
            if (is_dir($this->c('server.log_dir')) == false) {
                mkdir($this->c('server.log_dir'));
            }

            if (is_dir($this->c('server.data_dir')) == false) {
                mkdir($this->c('server.data_dir'));
            }
        }
        $name = $this->c('server.server_name');
        if($workerId >= $server->setting['worker_num']) {
            swoole_set_process_name("{$name}_task_worker");
        } else {
            swoole_set_process_name("{$name}_event_worker");
        }
    }

    /**
     * 此事件在worker进程终止时发生。在此函数中可以回收worker进程申请的各类资源
     * @param \swoole_server $server
     * @param $workerId
     */
    public function onWorkerStop(\swoole_server $server, $workerId)
    {

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
     * @param \swoole_server $server
     * @param $fd
     * @param $fromId
     * @param $data
     */
    public function onReceive(\swoole_server $server, $fd, $fromId, $data)
    {
        $this->send($fd, $data, $fromId);
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
     * @param intv $taskId 任务ID，由swoole扩展内自动生成，用于区分不同的任务。$task_id和$from_id组合起来才是全局唯一的
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
        $name = $this->c('server.server_name');
        swoole_set_process_name("{$name}_manager");
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
        $file = $this->c('serverd.log_file');
        $this->log($file, $msg);
    }

    /**
     * 记录访问错误日志
     * @param $msg
     */
    public function errorLog($msg)
    {
        $file = $this->c('server.error_log');
        $this->log($file, $msg);
    }

    /**
     * 记录访问日志
     * @param $msg
     */
    public function accessLog($msg)
    {
        $file = $this->c('server.access_log');
        $this->log($file, $msg);
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
        $pidArr = parse_ini_file($this->c('server.pid_file'));
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
     * 根据命令字执行对应的方法
     * @param $method
     */
    public function exec($method)
    {
        $allowMethod = array('status', 'start', 'stop', 'restart', 'reload');
        if (in_array($method, $allowMethod) == false) {
            die("Usage: server {start|stop|restart|reload|status}\n");
        }
        $this->$method();
    }

    public function status()
    {
        echo "status\n";
    }
}