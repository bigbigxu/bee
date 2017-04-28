<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/4/21
 * Time: 15:12
 * tcp socket 客服端
 * @TODO 此类未完成
 */

namespace bee\client;

use bee\core\TComponent;

class TcpSocket
{
    use TComponent;
    /**
     * socket 资源
     * @var resource
     */
    protected $sock;
    /**
     * 主机
     * @var string
     */
    protected $host;
    /**
     * 端口
     * @var int
     */
    protected $port;
    /**
     * socket错误码
     * @var int
     */
    public $errno = 0;
    /**
     * socket错误消息
     * @var string
     */
    public $error = '';

    /**
     * 错误消息赋值
     */
    public function setError()
    {
        $this->errno = socket_last_error($this->sock);
        $this->error = socket_strerror($this->errno);
        socket_clear_error($this->sock);
    }

    public function connect($force = false)
    {
        if ($this->sock !== null && $force == false) {
            return $this->sock;
        }
        /* 创建socket */
        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->sock === false) {
            $this->setError();
            throw new \Exception($this->error, $this->errno);
        }

        $flag = socket_connect($this->sock, $this->host, $this->port);
        if ($flag) {
            $this->setError();
            throw new \Exception($this->error, $this->errno);
        }
        return $this->sock;
    }

    public function write($data)
    {
        for ($i = 0; $i < 2; $i++) {
            $this->connect($i);
            $n = socket_send($this->sock, $data, strlen($data), null);
            if ($n === false) {
                $this->setError();
                if ($this->errno == 1) {}
            }
        }
    }
}