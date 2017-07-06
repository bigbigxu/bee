<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/4/20
 * Time: 10:31
 * php stream 系列函数封装。
 */

namespace bee\client;

use bee\App;
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
     * 连接超时时间
     * @var float
     */
    protected $connTimeout = 0.1;
    /**
     * 读取超时时间
     * @var int
     */
    protected $readTimeout = 0;
    /**
     * 连接标识符
     * @var string
     */
    protected $k;

    /**
     * 连接实例
     * @var self[]
     */
    private static $_instance;

    public function connect($force = false)
    {
        if ($this->fp !== null && $force == false) {
            return $this->fp;
        }
        $this->fp = stream_socket_client($this->remote, $this->errno, $this->errmsg, $this->readTimeout);
        if (!$this->fp) {
            throw new \Exception("{$this->remote} 连接失败：{$this->errno} -- {$this->errmsg}");
        }
        return $this->fp;
    }

    /**
     * 获取实例
     * @param $config
     * @return static
     * @throws \Exception
     */
    public static function getInstance($config)
    {
        if (is_string($config)) {
            $config = App::c($config);
        }
        $pid = intval(getmypid());
        $k = md5($config['remote'] . $pid);

        if (!isset(self::$_instance[$k])) {
            self::$_instance[$k] = null;
            self::$_instance[$k] = new static($config);
            self::$_instance[$k]->k = $k;
        }

        return self::$_instance[$k];
    }

    /**
     * 获取资源描述符
     * @return resource
     */
    public function getSock()
    {
        $this->connect();
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
     * 读取数据
     * @param int $length 读取的长度
     * @param int $timeout 读取超时时间
     * @return string 读取的数据
     */
    public function read($length = 8192, $timeout = 0)
    {
        $timeout = $timeout ?: $this->readTimeout;
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