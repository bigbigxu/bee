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
    {}

    public function init()
    {

    }

    /**
     * 实例化对象
     * @param bool $single 是否返回单例对象。
     * @return Curl
     */
    public static function getInstance($single = true)
    {
        if ($single) {
            if(!is_object(self::$_instance)){
                self::$_instance = new self();
            }
            return self::$_instance;
        } else {
            return new self();
        }
    }
}