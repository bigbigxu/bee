<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/9/2
 * Time: 14:26
 * php反射的一些封装
 */

namespace bee\core;

use ReflectionClass;
use ReflectionFunction;

class BeeReflection
{
    const PARAM_ASSOC = 1; //传参使用关联数组。
    const PARAM_INDEX = 2; //传参使用索引数组。、

    /**
     * 得到一个函数或对象方法的参数名称和默认值。
     * @param $call
     * @return array
     */
    public static function getMethodParam($call)
    {
        $r = array();
        if (is_array($call)) {
            //如果是一个数组 则是array(obj, method) 的形式。
            $re = new ReflectionClass($call[0]);
            $methodRef = $re->getMethod($call[1]);
        } else {
            //是一个字符串，则是一个函数
            $methodRef = new ReflectionFunction($call);
        }
        $paramRef = $methodRef->getParameters(); //得到方法参数对象。
        foreach ($paramRef as $row) {
            $name = $row->getName(); //得到参数名称
            $value = null;
            if ($row->isDefaultValueAvailable()) {
                $value = $row->getDefaultValue(); //得到参数的默认值。
            }
            $r[$name] = $value;
        }
        return $r;
    }

    /**
     * 创建一个对象
     * @param string $name 类名称
     * @param array $params 构造函数参数
     * @param array $config 对象属性
     * @return object
     */
    public static function create($name, $params = array(), $config = array())
    {
        if (is_object($name)) {
            $o = $name;
        } else {
            $re = new ReflectionClass($name);
            $o = $re->newInstanceArgs($params);
        }
        $vars = get_object_vars($o);
        foreach ($config as $key => $row) {
            if (array_key_exists($key, $vars)) {
                $o->$key = $row;
            }
        }
        return $o;
    }
}