<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/5/12
 * Time: 9:58
 */
namespace bee\client;
class BaseClient
{
    /**
     * @var \swoole_client
     */
    protected $client;
    protected $eof = ''; //结束符
    protected $config = array(); //当前配置文件
    protected $callback = array(); //缓存的回调函数
    protected $isAsync = 0; //是否为异步

    /**
     * BaseClient constructor.
     * @desc 关于tcp_keep  加入SWOOLE_KEEP标志后，
     *  创建的TCP连接在PHP请求结束或者调用$cli->close时并不会关闭。
     *  下一次执行connect调用时会复用上一次创建的连接。
     *  长连接保存的方式默认是以ServerHost:ServerPort为key的。
     *  可以再第3个参数内指定key。
     *  只允许用于同步客户端
     *
     * @param int $sock 指定socket的类型，支持TCP/UDP、TCP6/UDP64种
     * @param int $sync SWOOLE_SOCK_SYNC/SWOOLE_SOCK_ASYNC  同步/异步
     * @param string $key 用于长连接的Key，默认使用IP:PORT作为key。相同key的连接会被复用
     */
    public function __construct($sock = SWOOLE_TCP, $sync = SWOOLE_SOCK_SYNC, $key = '')
    {
        $this->isAsync = $sync;
        $this->client = new \swoole_client($sock, $sync, $key);
    }

    /**
     * 设置客户端参数，必须在connect前执行
     * 主要用设置分包协议，和server的配置项一样。
     * @param $config
     * @return $this
     */
    public function set($config)
    {
        $this->client->set($config);
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
        if ($this->isAsync == false) {
            return null;
        }
        $methods = get_class_methods($this);
        foreach ($methods as $row) {
            if (preg_match('/^on(\w+)$/', $row, $ma)) {
                if ($this->callback[$ma[1]]) {
                    $this->client->on($ma[1], $this->callback[$ma[1]]);
                } else {
                    $this->client->on($ma[1], array($this, $row));
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
    public function connect($host, $port, $timeout = 0.1, $flag = 0)
    {
        $this->registerCallback();
        return $this->client->connect($host, $port, $timeout, $flag);
    }

    /**
     * 返回swoole_client的连接状态
     * @return bool
     */
    public function isConnected()
    {
        return $this->client->isConnected();
    }

    /**
     * 用于获取客户端socket的本地host:port，必须在连接之后才可以使用。
     * @return array|bool
     */
    public function getsockname()
    {
        return $this->client->getsockname();
    }

    /**
     * UDP协议通信客户端向一台服务器发送数据包后，
     * 可能并非由此服务器向客户端发送响应。
     * 可以使用getpeername方法获取实际响应的服务器IP:PORT。
     * @return array|bool
     */
    public function getpeername()
    {
        return $this->client->getpeername();
    }

    /**
     * 发送数据到远程服务器
     * @param $data
     * @return bool 返回数据长度或false
     */
    public function send($data)
    {
        return $this->client->send($data);
    }

    /**
     * 向任意IP:PORT的主机发送UDP数据包，不得超过64K
     * @param $ip
     * @param $port
     * @param $data
     */
    public function sendto($ip, $port, $data)
    {
        $this->client->sendto($ip, $port, $data);
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
        return $this->client->recv($size, $flags);
    }

    /**
     * 关闭连接
     * @return bool
     */
    public function close()
    {
        return $this->client->close();
    }

    /**
     * 调用此方法会从事件循环中移除当前socket的可读监听，停止接收数据。
     * 此方法仅停止从socket中接收数据，但不会移除可写事件，所以不会影响发送队列
     * @return mixed
     */
    public function sleep()
    {
        $this->client->sleep();
    }

    /**
     * 调用此方法会重新监听可读事件，将socket连接从睡眠中唤醒。
     * 如果socket并未进入sleep模式，wakeup操作没有任何作用
     */
    public function wakeup()
    {
        $this->client->wakeup();
    }

    /**
     * 客户端连接服务器成功后会回调此函数
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

    /**
     * 户端收到来自于服务器端的数据时会回调此函数
     * swoole_client启用了eof/length检测后，onReceive一定会收到一个完整的数据包
     * @param \swoole_client $client
     * @param $data
     */
    public function onReceive(\swoole_client $client, $data)
    {
        echo $data;
    }

    /*
     * Server端关闭或Client端主动关闭，都会触发onClose事件
     */
    public function onClose(\swoole_client $client)
    {

    }

    /**
     * errCode的值等于Linux errno。可使用socket_strerror将错误码转为错误信息。
     * @return mixed
     */
    public function getErrCode()
    {
        return $this->client->errCode;
    }

    public function getErrMsg()
    {
        $code = $this->getErrCode();
        return \swoole_strerror($code);
    }

    /**
     * sock属性是此socket的文件描述符
     * @return int
     */
    public function getSock()
    {
        return $this->client->sock;
    }

    /**
     * 表示此连接是新创建的还是复用已存在的。与SWOOLE_KEEP配合使用
     * @return mixed
     */
    public function isReuse()
    {
        return $this->client->reuse;
    }
}