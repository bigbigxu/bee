<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/8/31
 * Time: 10:15
 * 校验基类
 * 校验使用回调发起调用。所有方法中不能使用this;
 */

namespace bee\core;

class Validate
{
    public static $required = array('CoreValidate', 'required'); //必须参数验证器
    public static $stringLength = array('CoreValidate', 'stringLength');

    public static function className()
    {
        return __CLASS__;
    }

    /**
     * 不能为null ,false,0 ''
     * @param $value
     * @return bool
     */
    public function required($value)
    {
        if ($value == false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 字符串长度校验
     * @param $value
     * @param $min
     * @param $max
     * @param string $encode
     * @return bool
     */
    public function stringLength($value, $min, $max, $encode = 'UTF-8')
    {
        $len = mb_strlen($value, $encode);
        if ($len >= $min && $len <= $max) {
            return true;
        } else {
            return false;
        }
    }
}