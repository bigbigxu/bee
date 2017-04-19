<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/6/10
 * Time: 15:13
 * php环境配置相关
 */

namespace bee\core;

use bee\App;

class PhpEnv
{
    protected $env = null; //环境类型
    protected $options = array();

    const SAPI_CLI = 'cli'; //命令行运行模式
    const SAPI_CGI = 'cgi'; //cgi模式
    const SAPI_FPM = 'fpm';
    const SAPI_APACHE = 'apache';

    /**
     * 设置运行环境
     * @param $env
     * @return $this
     */
    public function setEnv($env)
    {
        $this->env = $env;
        return $this;
    }

    /**
     * 设置是否在页面显示错误
     * @param $flag
     * @return $this
     */
    public function setDisplayError($flag)
    {
        $this->options['display_errors'] = $flag;
        return $this;
    }

    /**
     * 设置错误报告级别
     * @param $level
     * @return $this
     */
    public function setErrorReporting($level)
    {
        $this->options['error_reporting'] = $level;
        return $this;
    }

    /**
     * 设置错误日志
     * @param $file
     * @return $this
     */
    public function setErrorLog($file)
    {
        $this->options['log_errors'] = 1;
        $this->options['error_log'] = $file;
        return $this;
    }

    /**
     * 默认3种环境的设置
     * @return array
     */
    public function getDefaultSet()
    {
        $set = array(
            App::ENV_DEV => array(
                'display_errors' => 1,
                'error_reporting' => E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED,
                'log_errors' => 1,
                'error_log' => Log::getErrorLogFile()
            ),
            App::ENV_TEST => array(
                'display_errors' => 1,
                'error_reporting' => E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED,
                'log_errors' => 1,
                'error_log' => Log::getErrorLogFile()
            ),
            App::ENV_PRO => array(
                'display_errors' => 0,
                'error_reporting' => E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED,
                'log_errors' => 1,
                'error_log' => Log::getErrorLogFile()
            ),
        );
        $r = $set[$this->env];
        if ($r == false) {
            $r = $set[App::ENV_DEV];
        }
        return $r;
    }

    /**
     * 执行选项
     * @param bool $env 环境
     * @param array $options
     * @return null
     */
    public function exec($env = false, $options = array())
    {
        if ($env !== false) {
            $this->env = $env;
        }
        $this->options = $options ?: $this->getDefaultSet();
        foreach ($this->options as $key => $value) {
            ini_set($key, $value);
        }
        return true;
    }

    public static function isCli()
    {
        if (substr(php_sapi_name(), 0, 3) != 'cli') {
            return false;
        } else {
            return true;
        }
    }
}