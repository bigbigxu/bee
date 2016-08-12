<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/8/11
 * Time: 11:03
 */
namespace bee\client;
class BaseClient
{
    protected $host = ''; //主机地址
    protected $port = 0; //端口
    protected $protocol = ''; //当前协议类型
    protected $async = 1; //是否异步
    protected $uri = ''; //请求协议字符串
    protected $callback = array();
    private static $_instance = array();
    /**
     * @var \swoole_client
     */
    private $_fd = null;

    const PROTOCOL_TCP = 'tcp';
    const PROTOCOL_UDP = 'udp';
    const PROTOCOL_HTTP = 'http';
    const PROTOCOL_WS = 'ws';

    public function __construct($uri, $async = 1)
    {
        $this->uri = $uri;
        $params = parse_url($uri);
        $this->host = $params['host'];
        $this->port = $params['port'];
        $this->protocol = $params['scheme'];
        $this->async = $async;
        $this->_getFd();
        $this->init();
    }

    /**
     * 返回一个实例对象
     * @param $uri
     * @param int $async
     * @return mixed
     */
    public static function g($uri, $async = 1)
    {
        $key = md5($uri);
        if (!self::$_instance[$key]) {
            self::$_instance[$key] = new static($uri, $async);
        }
        return self::$_instance[$key];
    }

    protected function _getFd()
    {
        switch ($this->protocol) {
            case self::PROTOCOL_TCP :
                $this->_fd = new \swoole_client(SWOOLE_TCP, $this->async);
                break;
            case self::PROTOCOL_UDP :
                $this->_fd = new \swoole_client(SWOOLE_UDP, $this->async);
                break;
            default:
                throw new \Exception('目前不支持的协议');
        }
    }

    public function init()
    {

    }

    /**
     * 设置客户端参数，必须在connect前执行
     * 主要用设置分包协议，和server的配置项一样。
     * @param $config
     * @return $this
     */
    public function set($config)
    {
        $this->_fd->set($config);
        return $this;
    }

    /**
     * 注册异步事件回调函数
     * 同步阻塞客户端一定不要使用on方法
     * 调用swoole_client->close()时会自动退出事件循环
     * @param string $event 事件类型，支持connect/error/receive/close 4种
     * @param mixed $callback 回调函数
     * @return $this
     */
    public function on($event, $callback)
    {
        $this->callback[$event] = $callback;
        return $this;
    }

    /**
     * 完成回调函数的注册
     * 所有使用on开始的函数都被认为是回调函数
     * 使用this注册，那可回调函数中可使用this　
     */
    public function registerCallback()
    {
        if ($this->async == false) {
            return null;
        }
        $methods = get_class_methods($this);
        foreach ($methods as $row) {
            if (preg_match('/^on(\w+)$/', $row, $ma)) {
                if ($this->callback[$ma[1]]) {
                    $this->_fd->on($ma[1], $this->callback[$ma[1]]);
                } else {
                    $this->_fd->on($ma[1], array($this, $row));
                }
            }
        }
        $this->callback = array();
    }

    /**
     * 连接到远程服务器
     *
     * $flag参数在TCP类型,$flag=1表示设置为非阻塞socket，
     *  connect会立即返回。如果将$flag设置为1，
     *  那么在send/recv前必须使用swoole_client_select来检测是否完成了连接
     *
     * @param string $host 远程服务器的地址
     * @param int $port 远程服务器端口
     * @param float $timeout 网络IO的超时，包括connect/send/recv，单位是s，支持浮点数。默认为0.1s，即100ms
     * @param int $flag
     * @return bool
     */
    public function connect($timeout = 0.1, $flag = 0)
    {
        $this->registerCallback();
        return $this->_fd->connect($this->host, $this->port, $timeout, $flag);
    }

    /**
     * 返回swoole_client的连接状态
     * @return bool
     */
    public function isConnected()
    {
        return $this->_fd->isConnected();
    }

    /**
     * 用于获取客户端socket的本地host:port，必须在连接之后才可以使用。
     * @return array|bool
     */
    public function getsockname()
    {
        return $this->_fd->getsockname();
    }

    /**
     * UDP协议通信客户端向一台服务器发送数据包后，
     * 可能并非由此服务器向客户端发送响应。
     * 可以使用getpeername方法获取实际响应的服务器IP:PORT。
     * @return array|bool
     */
    public function getpeername()
    {
        return $this->_fd->getpeername();
    }

    /**
     * 发送数据到远程服务器
     * @param $data
     * @return bool 返回数据长度或false
     */
    public function send($data)
    {
        return $this->_fd->send($data);
    }

    /**
     * 向任意IP:PORT的主机发送UDP数据包，不得超过64K
     * @param $ip
     * @param $port
     * @param $data
     */
    public function sendto($ip, $port, $data)
    {
        $this->_fd->sendto($ip, $port, $data);
    }

    /**
     * 发送文件到服务器，本函数是基于sendfile操作系统调用的
     * @param $filename
     * @return mixed
     */
    public function sendfile($filename)
    {
        return $this->client->sendfile($filename);
    }

    /**
     * recv方法用于从服务器端接收数据
     * 客户端启用了EOF/Length检测后，无需设置$size和$waitall参数
     * @param int $size 接收数据的缓存区最大长度
     * @param int $flags
     * @return string
     */
    public function recv($size = 65535, $flags = 0)
    {
        return $this->_fd->recv($size, $flags);
    }

    /**
     * 关闭连接
     * @return bool
     */
    public function close()
    {
        return $this->_fd->close();
    }

    /**
     * 调用此方法会从事件循环中移除当前socket的可读监听，停止接收数据。
     * 此方法仅停止从socket中接收数据，但不会移除可写事件，所以不会影响发送队列
     * @return mixed
     */
    public function sleep()
    {
        $this->_fd->sleep();
    }

    /**
     * 调用此方法会重新监听可读事件，将socket连接从睡眠中唤醒。
     * 如果socket并未进入sleep模式，wakeup操作没有任何作用
     */
    public function wakeup()
    {
        $this->_fd->wakeup();
    }

    /**
     * 客户端连接服务器成功后会回调此函数
     * 如果是异步方式，业务逻辑必须写在此回调中
     * @param \swoole_client $client
     */
    public function onConnect(\swoole_client $client)
    {

    }

    /**
     * 连接服务器失败时会回调此函数
     * UDP客户端没有onError回调
     * @param \swoole_client $client
     */
    public function onError(\swoole_client $client)
    {

    }
}