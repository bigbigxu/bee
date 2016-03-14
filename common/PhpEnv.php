<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/6/10
 * Time: 15:13
 * php环境配置相关
 */
class PhpEnv
{
    private static $_instance;

    public function __construct()
    {}

    /**
     * @return self
     */
    public static function getInstance()
    {
        if(!is_object(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * 关闭或都打开php错误报告
     * @param $allow
     * @return $this
     */
    public function displayError($allow = null)
    {
        if($allow === null) {
            if(App::getInstance()->isDebug()) {
                $allow = 0;
            } else {
                $allow = 0;
            }
        }
        ini_set('display_errors', $allow); //是否报告错误显示
        return $this;
    }

    /**
     * 设置错误报告级别
     * @param int|null $level
     * @return $this
     */
    public function errorReporting($level = null)
    {
        if($level === null) {
            $level = E_ALL & ~E_NOTICE;
        }
        error_reporting($level);
        return $this;
    }

    /**
     * 设置php错误文件位置
     * 说明，错误文件不能记录语法错误
     * @param $file
     * @return $this
     */
    public function errorLog($file)
    {
        ini_set('log_errors', 1);
        ini_set('error_log', $file);
        return $this;
    }
}