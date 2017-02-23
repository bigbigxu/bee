<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/2/16
 * Time: 18:17
 */
namespace bee\client;
class LogClient
{
    const LOG_TYPE_ERROR = 'error_log'; /* 错误日志，年月日自动分目录 */
    const LOG_TYPE_ACCESS = 'access_log'; /* 访问日志，年月日自动分目录 */
    const LOG_TYPE_DEBUG = 'debug_log'; /* 调试日志，年月日自动分目录 */

    protected $connString; /* 连接字符串 udp://127.0.0.1:8000 */
    protected $appId; /* 应用ID，不同应用保存目录不一样 */
    protected $fp; /* 连接描述符 */
    private static $_instance; /* 当前对象实例 */

    public function __construct($appId, $connString)
    {
        $this->connString = $connString;
        $this->appId = $appId;
    }

    public static function getInstance($appId, $connString)
    {
        if (!self::$_instance) {
            self::$_instance = new self($appId, $connString);
        }
        return self::$_instance;
    }

    private function _connect()
    {
        $fp = stream_socket_client($this->connString, $error_code, $error_message);
        if (!$fp) {
            throw new \Exception("socket连接{$this->connString} 失败");
        }
        $this->fp = $fp;
    }


    /**
     * 写入日志
     * @param $mark
     * @param $msg
     * @param null $time
     * @throws \Exception
     */
    public function log($mark, $msg, $time = null)
    {
        $time = $time ?: time();
        $this->_connect();
        $arr = [
            'mark' => $mark,
            'app_id' => $this->appId,
            'msg' => $msg,
            'time' => $time,
            'ip' => $_SERVER['HTTP_HOST']
        ];
        $str = json_encode($arr);
        fwrite($this->fp, $str);
    }

    /**
     * 错误日志
     * @param $msg
     * @param null $time
     */
    public function errorLog($msg, $time = null)
    {
        $this->log(self::LOG_TYPE_ERROR, $msg, $time);
    }

    /**
     * 访问日志
     * @param $msg
     * @param null $time
     */
    public function accessLog($msg, $time = null)
    {
        $this->log(self::LOG_TYPE_ACCESS, $msg, $time);
    }

    /**
     * 调试日志
     * @param $msg
     * @param null $time
     */
    public function debugLog($msg, $time = null)
    {
        $this->log(self::LOG_TYPE_DEBUG, $msg, $time);
    }
}