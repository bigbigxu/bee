<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/4/1
 * Time: 9:19
 * 组件对象，作用是为bee提供统一的对象实现方法。
 * 本类用于取代Object类
 *
 * Component 仅仅提供一种类初始化的功能。
 * 对象属性通过数组传入构造函数，并调用init方法，仅此而已。
 * 继承Component类，脱离Component任然是一个完整的类
 */
namespace bee\core;
class Component
{
    public function __construct($config = [])
    {
        /* 对象属性赋值 */
        if (!empty($config)) {
            $this->_configure($config);
        }
        /* 初始化 */
        $this->init();
    }

    public function init()
    {

    }

    private function _configure($config)
    {
        foreach ($config as $key => $row) {
            $this->$key = $row;
        }
    }

    /**
     * 获取类名
     * @return string
     */
    public static function className()
    {
        return get_called_class();
    }
}