<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/2/22
 * Time: 11:22
 * 日志系统
 */
class CoreLog
{
    /**
     * @var string 发送日志的组件
     */
    public $target;
    /**
     * @var bool 日志是否写文件
     */
    public $enableFile = true;

    public static function error($msg)
    {
        $file = self::getErrorLogFile();
        self::_log($file, $msg);
    }

    /**
     * 访问是日志保持一行一条记录。
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
        if(is_array($msg)) {
            $msg = CoreJson::encode($msg);
        }
        $time = date('Y-m-d H:i:s');
        $zone = date_default_timezone_get();
        $msg = "[{$time} {$zone}] {$msg}\n";
        $o = App::p()->getLog();
        if ($o->enableFile) {
            file_put_contents($file, $msg, FILE_APPEND);
        }
        if ($o->target) {
            $mark = explode('_', basename($file, '.log'))[0];
            App::p()->get($o->target)->log($mark, $msg);
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
        if(!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/error_' . date('d') . '.log';
        if (!is_file($file)) {
            touch($file);
            @chmod($file, 0664);
        }
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
        if(!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
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
        if(!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/debug_' . date('d') . '.log';
        return $file;
    }
}