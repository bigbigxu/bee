<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/10/9
 * Time: 15:24
 * 自定义php错误处理类
 *
 * 被处理了的错误，将从错误栈中移除。也就是同一个错误，不能被处理2次。
 *
 * 1. 如果异常被自定义函数处理过，shutdown_function不能获取这个错误。
 * 2. 非致命错误，处理函数如果返回false，shutdown_function可以获取这个错误。
 * 3. 当错误被清理的时候，php的相关配置将失去作用(因为没有错误)，这事需要自行处理错误日志
 */

namespace bee\core;

class PhpError
{
    /**
     * 注册异常，错误，退出处理函数
     * register_shutdown_function()函数可重复调用，但执行的顺序与注册的顺序相同
     *
     * 调用register_shutdown_function()函数之前不能有exit()函数调用，
     *  不然register_shutdown_function()函数将不能执行
     */
    public function register()
    {
        set_exception_handler([$this, 'handlerException']);
        set_error_handler([$this, 'handlerError']);
        register_shutdown_function([$this, 'handleFatalError']);
    }

    /**
     * 注册异常处理器。会绕过 PHP 标准错误处理程序。php.ini相关配置将失效。
     * 执行完成后，本次错误将被清理，error_get_last()无法再获取当前错误
     * PHP 在执行完成此函数后，进程自动退出。
     * @param $e
     */
    public function handlerException($e)
    {
        $msg = sprintf(
            "PHP Fatal error: Uncaught %s",
            (string)$e
        );
        Log::error($msg);
        if (ini_get('display_errors')) {
            echo $msg, "\n";
        }
    }

    /**
     * 致命错误处理函数。
     * 此函数必然会执行。如果存在没有处理的错误，将触发 PHP 标准错误处理程序。比如写入日志
     * PHP 在执行完成此函数后，进程自动退出。
     */
    public function handleFatalError()
    {
        $error = error_get_last(); /* 获取最后的错误 */
        if (self::isFatalError($error)) {
            $msg = sprintf(
                "PHP Fatal error: %s in %s on %s.",
                $error['message'],
                $error['file'],
                $error['line']
            );
            Log::error($msg);
        }
    }

    /**
     * 错误处理函数，不能处理致命错误。
     * 除非函数返回了false, 错误类型都会绕过 PHP 标准错误处理程序。比如 error_log 错误日志。
     * 执行完成后，本次错误将被清理，error_get_last()无法再获取当前错误
     * @param int $code 错误码
     * @param string $msg 错误消息
     * @param string $file 文件名
     * @param string $line 文件行
     */
    public function handlerError($code, $msg, $file, $line)
    {
        if (error_reporting() & $code) {
            $msg = sprintf(
                "%s: %s in %s on %s.",
                self::getName($code),
                $msg,
                $file,
                $line
            );
            Log::error($msg);
            if (ini_get('display_errors')) {
                echo $msg, "\n";
            }
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
                E_ERROR => 1,
                E_PARSE => 1,
                E_CORE_ERROR => 1,
                E_CORE_WARNING => 1,
                E_COMPILE_ERROR => 1,
                E_COMPILE_WARNING => 1
            ];
            if (isset($map[$error['type']])) {
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

    /**
     * 获取调用trace
     * @param int $start
     * @param int $option
     * @return string
     */
    public static function stackTrace($start = 1, $option = DEBUG_BACKTRACE_IGNORE_ARGS)
    {
        $trace = debug_backtrace($option);
        $i = 0;
        $msg = "Stack trace:\n";
        foreach ($trace as $key =>$row) {
            if ($key < $start) {
                continue;
            }
            $msg .= sprintf(
                "#%d %s(%d): %s%s%s\n",
                $i,
                $row['file'],
                $row['line'],
                $row['class'] ?: '',
                $row['type'],
                $row['function']
            );
            $i++;
        }
        return $msg;
    }
}