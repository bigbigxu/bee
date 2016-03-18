<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/6/30
 * Time: 8:57
 */
class CoreSocket
{
    const PROTOCOL_TCP = 'tcp';
    const PROTOCOL_UDP = 'udp';

    private static $_instance;
    protected $connString;
    protected  $fp; //socket资源连接
    protected $pack;

    /**
     * @param $ip
     * @param $port
     * @param string $type
     * @throws Exception
     */
    public function __construct($ip, $port, $type = self::PROTOCOL_TCP)
    {
        $this->connString = "{$type}://{$ip}:{$port}";
        $this->fp = stream_socket_client($this->connString, $errno, $errstr, 0.5);
        if($this->fp == false) {
            throw new Exception($errstr, $errno);
        }
        $this->pack = new Pack();
    }

    /**
     * 创建实例
     * 配置文件为一个数组 第一个为host,第二个为port第三个为协议类型
     * @param $config
     * @param $class
     * @return self
     * @throws Exception
     */
    public static function getInstance($config, $class = __CLASS__)
    {
        if(!is_array($config)) {
            $config = App::c($config);
        }
        $k = md5($config[0] . $config[1]);
        if(!isset(self::$_instance[$k])){
            if($config[2] == false) {
                //默认使用tcp协议
                $config[2] = self::PROTOCOL_TCP;
            }
            self::$_instance[$k] = new $class($config[0], $config[1], $config[2]);
        }
        return self::$_instance[$k];
    }

    /**
     * 向服务器发送数据
     * @param $str
     * @return $this
     */
    public function fwrite($str)
    {
        fwrite($this->fp, $str, strlen($str));
        return $this;
    }

    /**
     * 读取server数据，如果len为空则读取所有。
     * @param null $len
     * @return string
     */
    public function fread($len = null)
    {
        $content = '';
        if($len !== null) {
            $content = fread($this->fp, $len);
        } else {
            //读取全部
            while($r = fread($this->fp, 1024)) {
                $content .= $r;
            }
        }
        return $content;
    }
}