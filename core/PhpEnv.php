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
    use TComponent;
    /**
     * 环境类型
     * @var string
     */
    protected $env;
    /**
     * php.ini 相关配置选项
     * @var array
     */
    protected $phpIni = [];
    /**
     * php环境变量配置
     * @var array
     */
    protected $phpEnv = [];

    const SAPI_CLI = 'cli'; //命令行运行模式
    const SAPI_CGI = 'cgi'; //cgi模式
    const SAPI_FPM = 'fpm';
    const SAPI_APACHE = 'apache';

    public function init()
    {
        $this->env = App::getInstance()->getEnv();
        $this->phpIni = array_merge($this->getPhpIniDefaultSet(), $this->phpIni);
    }

    /**
     * 设置环境
     */
    public function set()
    {
        /* 设置php.ini 相关配置 */
        foreach ($this->phpIni as $key => $value) {
            ini_set($key, $value);
        }
    }

    /**
     * 设置是否在页面显示错误
     * @param $flag
     * @return $this
     */
    public function setDisplayError($flag)
    {
        $this->phpIni['display_errors'] = $flag;
        return $this;
    }

    /**
     * 设置错误报告级别
     * @param $level
     * @return $this
     */
    public function setErrorReporting($level)
    {
        $this->phpIni['error_reporting'] = $level;
        return $this;
    }

    /**
     * 设置错误日志
     * @param $file
     * @return $this
     */
    public function setErrorLog($file)
    {
        $this->phpIni['log_errors'] = 1;
        $this->phpIni['error_log'] = $file;
        return $this;
    }

    /**
     * 默认3种环境的设置
     * @return array
     */
    public function getPhpIniDefaultSet()
    {
        $set = [
            App::ENV_DEV => [
                'display_errors' => 1,
                'error_reporting' => E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED,
            ],
            App::ENV_TEST => [
                'display_errors' => 1,
                'error_reporting' => E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED,
            ],
            App::ENV_PRO => [
                'display_errors' => 0,
                'error_reporting' => E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED,
            ]
        ];
        return $set[$this->env] ?: [];
    }

    public static function isCli()
    {
        if (substr(php_sapi_name(), 0, 3) != 'cli') {
            return false;
        } else {
            return true;
        }
    }

    public function getPhpIni()
    {
        return $this->phpIni;
    }
}