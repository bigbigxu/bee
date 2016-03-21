<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/3/9
 * Time: 10:50
 * 此server用于php 5.2版本。
 * 使用的swoole版本为 1.6.10.
 */
class BaseServerLower
{
    /**
     * 服务器资源链接句柄
     * @var swoole_server
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
    protected $eof = "\r\n";
    /**
     * server对象
     * @var BaseServer
     */
    private static $_instance;

    /**
     * 加载配置文件
     * BaseServer constructor.
     * @param string $configPath
     * @throws Exception
     */
    public function __construct($configPath = '')
    {
        self::checkEnv();
        if (!file_exists($configPath)) {
            $configPath = dirname(__FILE__) . '/config.php';
        }
        $this->config = include $configPath;
        if (!$this->config) {
            throw new Exception('配置文件不存在');
        }
        if($this->c('server.debug') == true) { //debug模式下设置非后台运行
            $this->config['serverd']['daemonize'] = false;
        }
        error_reporting(E_ALL & ~E_NOTICE);
        ini_set('error_log', $this->c('server.error_log'));
        $this->init();
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
        if (PHP_VERSION < '5.2') {
            die('php版本不能小于5.2');
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
        $this->s = new swoole_server($host, $port, $mode, $type);

        $this->s->set($this->c('serverd'));
        $this->registerCallback();
        return $this->s->start();
    }

    /**
     * 重启所有worker进程
     * 一台繁忙的后端服务器随时都在处理请求，如果管理员通过kill进程方式来终止/重启服务器程序，
     * 可能导致刚好代码执行到一半终止。
     * 这种情况下会产生数据的不一致。如交易系统中，支付逻辑的下一段是发货，假设在支付逻辑之后进程被终止了。
     * 会导致用户支付了货币，但并没有发货，后果非常严重。
     *
     * Swoole提供了柔性终止/重启的机制，管理员只需要向SwooleServer发送特定的信号，
     * Server的worker进程可以安全的结束。
     *
     *  SIGTERM: 向主进程发送此信号服务器将安全终止，在PHP代码中可以调用$serv->shutdown()完成此操作
     *
     *  SIGUSR1: 向管理进程发送SIGUSR1信号，将平稳地restart所有worker进程，
     *  在PHP代码中可以调用$serv->reload()完成此操作
     *  swoole的reload有保护机制，当一次reload正在进行时，收到新的重启信号会丢弃
     *
     *  重启所有worker进程 kill -SIGUSR1 主进程PID
     *  仅重启task进程 kill -SIGUSR2 主进程PID
     *
     * 平滑重启只对onWorkerStart或onReceive等在Worker进程中include/require的PHP文件有效，
     * Server启动前就已经include/require的PHP文件，不能通过平滑重启重新加载
     *
     * 对于Server的配置即$serv->set()中传入的参数设置，必须关闭/重启整个Server才可以重新加载
     * Server可以监听一个内网端口，然后可以接收远程的控制命令，去重启所有worker
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
     * @param int $formId
     * @return bool
     */
    public function close($fd, $formId = 0)
    {
        return $this->s->close($fd, $formId);
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
            return FALSE;
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
     *  * $data要投递的任务数据，可以为除资源类型之外的任意PHP变量
     *  * $taskWorkerId可以制定要给投递给哪个task进程，传入ID即可，范围是0 - serv->task_worker_num
     *  * 返回值为整数($task_id)，表示此任务的ID。如果有finish回应，onFinish回调中会携带$task_id参数
     *
     * 此功能用于将慢速的任务异步地去执行，比如一个聊天室服务器，可以用它来进行发送广播。
     * 当任务完成时，在task进程中调用$serv->finish("finish")告诉worker进程此任务已完成。
     * 当然swoole_server->finish是可选的。
     *
     *  * AsyncTask功能在1.6.4版本增加，默认不启动task功能，需要在手工设置task_worker_num来启动此功能
     *  * task_worker的数量在swoole_server::set参数中调整，如task_worker_num => 64，表示启动64个进程来接收异步任务
     *
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
     * $result为任务执行的结果，由$serv->finish函数发出。如果此任务超时，这里会返回false。
     *
     * taskwait是阻塞接口，如果你的Server是全异步的请使用swoole_server::task和swoole_server::finish,不要使用taskwait
     * 第3个参数可以制定要给投递给哪个task进程，传入ID即可，范围是0 - serv->task_worker_num
     * $dst_worker_id在1.6.11+后可用，默认为随机投递
     * taskwait方法不能在task进程中调用
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
        return swoole_strerror($errno);
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
     * 注意不能存在2个相同间隔时间的定时器
     * @param $interval
     */
    public function addTimer($interval)
    {
        $this->s->addtimer($interval);
    }

    /**
     * @param $interval
     */
    public function delTimer($interval)
    {
        $this->s->deltimer($interval);
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
     * 得到一个配置参数
     * @param string $path vod_db.xxx.xxx的形式
     * @return mixed
     */
    public function c($path = '')
    {
        if ($path == '') {
            return $this->config;
        }
        $pathArr = explode('.', $path);
        $tmp = $this->config;
        foreach ($pathArr as $row) {
            $tmp = $tmp[$row];
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
     * @param swoole_server $server
     */
    public function onStart(swoole_server $server)
    {
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
     * @param swoole_server $server
     */
    public function onShutdown(swoole_server $server)
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
     * @param swoole_server $server
     * @param $workerId
     */
    public function onWorkerStart(swoole_server $server, $workerId)
    {
        $name = $this->c('server.server_name');
        if($workerId >= $server->setting['worker_num']) {
            swoole_set_process_name("{$name}_task_worker");
        } else {
            swoole_set_process_name("{$name}_event_worker");
        }
    }

    /**
     * 此事件在worker进程终止时发生。在此函数中可以回收worker进程申请的各类资源
     * @param swoole_server $server
     * @param $workerId
     */
    public function onWorkerStop(swoole_server $server, $workerId)
    {

    }

    /**
     * 有新的连接进入时，在worker进程中回调
     * onConnect/onClose这2个回调发生在worker进程内，而不是主进程。
     * @param swoole_server $server
     * @param $fd
     * @param $fromId
     */
    public function onConnect(swoole_server $server, $fd, $fromId)
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
     * @param swoole_server $server
     * @param $fd
     * @param $fromId
     * @param $data
     */
    public function onReceive(swoole_server $server, $fd, $fromId, $data)
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
     * @param swoole_server $server
     * @param $fd
     * @param $fromId
     */
    public function onClose(swoole_server $server, $fd, $fromId)
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
     * @param swoole_server $server
     * @param intv $taskId 任务ID，由swoole扩展内自动生成，用于区分不同的任务。$task_id和$from_id组合起来才是全局唯一的
     * @param $fromId
     * @param $data
     */
    public function onTask(swoole_server $server, $taskId, $fromId, $data)
    {

    }


    /**
     * 当worker进程投递的任务在task_worker中完成时，
     * task进程会通过swoole_server->finish()方法将任务处理的结果发送给worker进程。
     *
     * task进程的onTask事件中没有调用finish方法或者return结果。worker进程不会触发onFinish
     * @param swoole_server $server
     * @param $taskId
     * @param $data
     */
    public function onFinish(swoole_server $server, $taskId, $data)
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
        $masterPid = $this->getManagerPidByFile();
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
     * 从pid文件中得到主进程id
     */
    public function getManagerPidByFile()
    {
        $pidArr = parse_ini_file($this->c('server.pid_file'));
        return $pidArr['manager_pid'];
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
}