<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2009 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

/**
 * Crypt 加密实现类
 * @category   ORG
 * @package  ORG
 * @subpackage  Crypt
 * @author    liu21st <liu21st@gmail.com>
 */
class Mcrypt {

    /**
     * 加密字符串
     * @access static
     * @param string $str 字符串
     * @param string $key 加密key
     * @return string
     */
    public static function encrypt($str, $key)
    {
        $r = md5($key);
        $c = 0;
        $v = "";
        $len = strlen($str);
        $l = strlen($r);
        for ($i = 0; $i < $len; $i++) {
            if ($c == $l) {
                $c = 0;
            }
            $v .= substr($r, $c, 1) . (substr($str, $i, 1) ^ substr($r, $c, 1));
            $c++;
        }
        $res =  self::ed($v, $key);
        return base64_encode($res);
    }

    /**
     * 解密字符串
     * @access static
     * @param string $str 字符串
     * @param string $key 加密key
     * @return string
     */
    public static function decrypt($str, $key)
    {
        $str = self::ed(base64_decode($str), $key);
        $v = "";
        $len = strlen($str);
        for ($i=0; $i < $len; $i++) {
            $md5 = substr($str, $i, 1);
            $i++;
            $v .= (substr($str, $i, 1) ^ $md5);
        }
        return $v;
    }


    public static function ed($str, $key)
    {
        $r = md5($key);
        $c = 0;
        $v = "";
        $len = strlen($str);
        $l = strlen($r);
        for ($i=0; $i < $len; $i++) {
            if ($c == $l) {
                $c= 0;
            }
            $v .= substr($str, $i, 1) ^ substr($r, $c, 1);
            $c++;
        }
        return $v;
    }
}