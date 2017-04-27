<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/2/16
 * Time: 18:17
 * 向远程日志服务器发送日志
 */
namespace bee\client;

class RemoteLog extends Stream
{
    const LOG_TYPE_ERROR = 'error_log'; /* 错误日志 */
    const LOG_TYPE_ACCESS = 'access_log'; /* 访问日志 */
    const LOG_TYPE_DEBUG = 'debug_log'; /* 调试日志 */

    protected $appId; /* 应用ID，不同应用保存目录不一样 */

    /**
     * 写入日志
     * @param $mark
     * @param $msg
     * @throws \Exception
     */
    public function log($mark, $msg)
    {
        $arr = [
            'mark' => $mark,
            'app_id' => $this->appId,
            'msg' => $msg,
            'time' => time(),
        ];
        $str = json_encode($arr);
        $this->write($str);
    }

    /**
     * 错误日志
     * @param $msg
     */
    public function errorLog($msg)
    {
        $this->log(self::LOG_TYPE_ERROR, $msg);
    }

    /**
     * 访问日志
     * @param $msg
     */
    public function accessLog($msg)
    {
        $this->log(self::LOG_TYPE_ACCESS, $msg);
    }

    /**
     * 调试日志
     * @param $msg
     */
    public function debugLog($msg)
    {
        $this->log(self::LOG_TYPE_DEBUG, $msg);
    }
}