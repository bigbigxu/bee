<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/4/20
 * Time: 10:31
 * php stream 系列函数封装。
 */

namespace bee\client;

use bee\core\TComponent;

class Stream
{
    use TComponent;
    /**
     * 远程主机描述字符串
     * @example   udp://127.0.0.1:1001
     * @var string
     */
    protected $remote;
    /**
     * 连接资源描述符
     * @var resource
     */
    protected $fp;
    /**
     * socket 错误码
     * @var int
     */
    protected $errno = 0;
    /**
     * socket 错误消息
     * @var string
     */
    protected $error = '';
    /**
     * 连接超时时间
     * @var float
     */
    protected $timeout = 0.1;

    public function connect($force = false)
    {
        if ($this->fp !== null && $force == false) {
            return $this->fp;
        }
        $this->fp = stream_socket_client($this->remote, $this->errno, $this->error, $this->timeout);
        if (!$this->fp) {
            throw new \Exception("{$this->remote} 连接失败：{$this->errno} -- {$this->error}");
        }
        return $this->fp;
    }

    /**
     * 获取资源描述符
     * @return resource
     */
    public function getSock()
    {
        return $this->fp;
    }

    /**
     * 关闭连接
     * @return bool
     */
    public function close()
    {
        if ($this->fp) {
            return fclose($this->fp);
        }
        return false;
    }

    /**
     * 获取主机配置
     * @return string
     */
    public function getRemote()
    {
        return $this->remote;
    }

    /**
     * 获取错误码
     * @return int
     */
    public function getErrno()
    {
        return $this->errno;
    }

    /**
     * 获取错误消息
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 读取数据
     * @param int $length 读取的长度
     * @param int $timeout 读取超时时间
     * @return string 读取的数据
     */
    public function read($length = 8192, $timeout = 0)
    {
        $this->connect();
        if ($timeout) {
            stream_set_timeout($this->fp, $timeout);
        }
        return fread($this->fp, $length);
    }

    /**
     * 写数据
     * @param $data
     * @return int
     * @throws \Exception
     */
    public function write($data)
    {
        $this->connect();
        return fwrite($this->fp, $data);
    }
}