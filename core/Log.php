<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/2/22
 * Time: 11:22
 * 日志系统
 */

namespace bee\core;

use bee\App;
use bee\client\RemoteLog;
use bee\common\Json;

class Log
{
    use TComponent;
    /**
     * @var bool 是否使用远程发送日志。必须配置remote_log 组件
     */
    protected $enableRemote = false;
    /**
     * @var bool 日志是否写文件
     */
    protected $enableFile = true;
    /**
     * 目录权限
     * @var int
     */
    protected $dirMode = 0755;
    /**
     * 文件权限
     * @var int
     */
    protected $fileMode = 0664;
    /**
     * 远程日志发送组件
     * @var bool|RemoteLog
     */
    protected $remoteLog = 'remote_log';

    public function init()
    {
        if ($this->enableRemote) {
            $this->remoteLog = $this->sureComponent($this->remoteLog);
        }
    }

    /**
     * 记录错误日志
     * @param $msg
     */
    public static function error($msg)
    {
        $file = self::getErrorLogFile();
        self::_log($file, $msg);
    }

    /**
     * 访问日志
     * @param $msg
     */
    public static function access($msg)
    {
        $file = self::getAccessLogFile();
        self::_log($file, $msg);
    }

    /**
     * 调试日志
     * @param $msg
     */
    public static function debug($msg)
    {
        $file = self::getDebugLogFile();
        self::_log($file, $msg);
    }

    /**
     * 向指定文件记录日志。
     * @param $msg
     * @param $fileName
     */
    public static function log($fileName, $msg)
    {
        $file = trim(App::getInstance()->getRuntimeDir()) . '/' . $fileName;
        self::_log($file, $msg);
    }

    /**
     * 日志方法
     * @param $file
     * @param $msg
     */
    private static function _log($file, $msg)
    {
        if (is_array($msg)) {
            $msg = Json::encode($msg);
        }
        $time = date('Y-m-d H:i:s');
        $zone = date_default_timezone_get();
        $msg = "[{$time} {$zone}] {$msg}";
        $o = App::s()->getLog();
        if ($o->enableFile) {
            $o->createLogFile($file);
            file_put_contents($file, "{$msg}\n", FILE_APPEND);
        }
        if ($o->enableRemote && $o->remoteLog) {
            $mark = explode('_', basename($file, '.log'))[0];
            $o->remoteLog->log($mark, $msg);
        }
    }

    /**
     * 日志和文件的创建
     * @param $file
     */
    public function createLogFile($file)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, $this->dirMode, true);
        }
        if (!is_file($file)) {
            touch($file);
            @chmod($file, $this->fileMode);
        }
    }

    /**
     * 得到当前错误日志文件路径
     * @return string
     */
    public static function getErrorLogFile()
    {
        $o = App::getInstance();
        $dir = $o->getRuntimeDir() . '/error_log/' . date('Y/m');
        $file = $dir . '/error_' . date('d') . '.log';
        return $file;
    }

    /**
     * 得到当前访问日志的文件路径
     * @return string
     */
    public static function getAccessLogFile()
    {
        $o = App::getInstance();
        $dir = $o->getRuntimeDir() . '/access_log/' . date('Y/m');
        $file = $dir . '/access_' . date('d') . '.log';
        return $file;
    }

    /**
     * 得到当前调试日志的文件路径
     * @return string
     */
    public static function getDebugLogFile()
    {
        $o = App::getInstance();
        $dir = $o->getRuntimeDir() . '/debug_log/' . date('Y/m');
        $file = $dir . '/debug_' . date('d') . '.log';
        return $file;
    }
}