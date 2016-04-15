<?php

/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/10/9
 * Time: 15:24
 * 自定义php错误处理类
 */
class PhpError
{
    const BEFORE_ACTION = 'before';
    const BEFORE_AFTER = 'after';
    private static $_instance;

    public function __construct()
    {
    }

    public function init()
    {

    }

    /**
     * 实例化对象
     * @param bool $single 是否返回单例对象。
     * @return static
     */
    public static function getInstance($single = true)
    {
        if ($single) {
            if (!is_object(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        } else {
            return new self();
        }
    }

    /**
     * 自定义的错误处理函数
     */
    public function handleFatal()
    {
        $error = error_get_last();
        if (isset($error['type'])) {
            switch ($error['type']) {
                case E_ERROR :
                case E_PARSE :
                case E_CORE_ERROR :
                case E_COMPILE_ERROR :
                    $message = $error['message'];
                    $file = $error['file'];
                    $line = $error['line'];
                    $time = date('Y-m-d H:i:s');
                    $log = "[{$time}] {$message} ($file:$line)\nStack trace:\n";
                    $log .= CoreLog::trace();
                    if (isset($_SERVER['REQUEST_URI'])) {
                        $log .= '[QUERY] ' . $_SERVER['REQUEST_URI'] ."\n";
                    }
                    echo $log;
                    error_log($log);
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * 注册致命错误处理函数
     * register_shutdown_function()函数可重复调用，但执行的顺序与注册的顺序相同
     * 调用register_shutdown_function()函数之前有exit()函数调用，register_shutdown_function()函数将不能执行
     * @param mixed $function
     * @return $this
     */
    public function registerShutdownFunction($function = null)
    {
        if ($function === null) {
            $function = array($this, 'handleFatal');
        }
        register_shutdown_function($function);
        return $this;
    }
}