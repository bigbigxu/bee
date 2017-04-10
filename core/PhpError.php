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
    /**
     * 注册异常，错误，退出处理函数
     *
     * register_shutdown_function()函数可重复调用，但执行的顺序与注册的顺序相同
     *
     * 调用register_shutdown_function()函数之前有exit()函数调用，
     * register_shutdown_function()函数将不能执行
     */
    public function register()
    {
        set_exception_handler([$this, 'handlerException']);
        set_error_handler([$this, 'handlerError']);
        register_shutdown_function([$this, 'handleFatalError']);
    }

    /**
     * 注册异常处理器。
     * PHP 在执行完成此函数后，进程自动退出。
     * @param $e
     */
    public function handlerException($e)
    {
        $msg = sprintf(
            "PHP Fatal error: Uncaught %s",
            (string)$e
        );
        CoreLog::error($msg);
    }

    /**
     * 致命错误处理函数
     * PHP 在执行完成此函数后，进程自动退出。
     */
    public function handleFatalError()
    {
        $error = error_get_last(); /* 获取最后的错误 */
        if (self::isFatalError($error)) {
            $msg = sprintf(
                "Fatal error: %s in %s on %s.\n",
                $error['message'],
                $error['file'],
                $error['line']
            );
            CoreLog::error($msg);
        }
    }

    /**
     * 错误处理函数，不能处理致命错误。
     * @param int $code 错误码
     * @param string $msg 错误消息
     * @param string $file 文件名
     * @param string $line 文件行
     */
    public function handlerError($code, $msg, $file, $line)
    {
        if (error_reporting() & $code) {
            $msg = sprintf(
                "%s: %s in %s on %s",
                self::getName($code),
                $msg,
                $file,
                $line
            );
            CoreLog::error($msg);
        }
    }

    /**
     * 判断一个错误是否为致命错误
     * @param $error
     * @return bool
     */
    public static function isFatalError($error)
    {
        if (isset($error['type'])) {
            $map = [
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_CORE_WARNING,
                E_COMPILE_ERROR,
                E_COMPILE_WARNING
            ];
            if ($map[$error['type']]) {
                return true;
            }
        }
        return false;
    }


    /**
     * @return string 错误名称
     */
    public static function getName($code)
    {
        $names = [
            E_COMPILE_ERROR => 'PHP Compile Error',
            E_COMPILE_WARNING => 'PHP Compile Warning',
            E_CORE_ERROR => 'PHP Core Error',
            E_CORE_WARNING => 'PHP Core Warning',
            E_DEPRECATED => 'PHP Deprecated Warning',
            E_ERROR => 'PHP Fatal Error',
            E_NOTICE => 'PHP Notice',
            E_PARSE => 'PHP Parse Error',
            E_RECOVERABLE_ERROR => 'PHP Recoverable Error',
            E_STRICT => 'PHP Strict Warning',
            E_USER_DEPRECATED => 'PHP User Deprecated Warning',
            E_USER_ERROR => 'PHP User Error',
            E_USER_NOTICE => 'PHP User Notice',
            E_USER_WARNING => 'PHP User Warning',
            E_WARNING => 'PHP Warning',
        ];

        return $names[$code] ?: 'Error';
    }
}