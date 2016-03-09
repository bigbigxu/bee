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
    public static function error($msg)
    {
        $file = self::getErrorLogFile();
        $time = date('Y-m-d H:i:s');
        file_put_contents($file, "[{$time}] {$msg}\n",  FILE_APPEND);
    }

    /**
     * 访问是日志保持一行一条记录。
     * @param $msg
     */
    public static function access($msg)
    {
        if(is_array($msg)) {
            $msg = json_encode($msg);
        }
        $msg = str_replace(array("\r", "\n"), '', $msg);
        $file = self::getAccessLogFile();
        file_put_contents($file, "{$msg}\n", FILE_APPEND);
    }

    /**
     * 调试日志
     * @param $msg
     */
    public static function debug($msg)
    {
        $file = self::getDebugLogFile();
        $time = date('Y-m-d H:i:s');
        file_put_contents($file, "[{$time}] {$msg}\n", FILE_APPEND);
    }

    /**
     * 向指定文件记当日志。
     * @param $msg
     * @param $fileName
     */
    public static function log($fileName, $msg)
    {
        $fileName = trim(App::getInstance()->getRuntimeDir()) . '/' . $fileName;
        if(is_array($msg)) {
            $msg = json_encode($msg);
        }
        $time = date('Y-m-d H:i:s');
        file_put_contents($fileName, "[{$time}] {$msg}\n", FILE_APPEND);
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

    /**
     * 打印调试堆
     * @param bool $print
     */
    public static function trace($print = false)
    {
        ob_start();
        debug_print_backtrace();
        $msg = ob_get_contents();
        if ($print) {
            echo $msg;
        } else {
            self::error($msg);
        }
        ob_end_clean();
    }
}