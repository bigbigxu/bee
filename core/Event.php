<?php
/**
 * Created by PhpStorm.
 * User: 123
 * Date: 14-10-23
 * Time: 上午11:13
 * 事件处理程序格式如下
 * function ($event) {
 *  $event 就是前面提到的
 * }
 */
class Event extends Object
{
    public $name; //事件名称
    public $sender; //事件发布者，通常是调用了 trigger() 的对象或类。
    public $handled = false; //是否终止事件
    public $data; //这个参数是事件发生的时候动态传递的参数
    private static $_events = array(); //这里保存的所有事件。

    /**
     * 注册一个事件，这里注册的是类级别的事件
     * @param string|object $class 要注册的事件类
     * @param string $name 事件名称
     * @param string|array $handler 事件处理函数，一个可回调的结构
     * @param null|array $data 会传给事件处理函数的数据
     * @param bool $append 是否添加前面。
     */
    public static function on($class, $name, $handler, $data = null, $append = true)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        $class = ltrim($class, '\\');
        if ($append || empty(self::$_events[$name][$class])) {
            self::$_events[$name][$class][] = array($handler, $data);
        } else {
            array_unshift(self::$_events[$name][$class], array($handler, $data));
        }
    }

    /**
     * 删除事件或事件处理程序
     * @param $class
     * @param $name
     * @param null $handler
     * @return bool
     */
    public static function off($class, $name, $handler = null)
    {
        $class = ltrim($class, '\\');
        if (empty(self::$_events[$name][$class])) {
            return false;
        }
        if ($handler === null) {
            unset(self::$_events[$name][$class]);
            return true;
        } else {
            $removed = true;
            foreach (self::$_events[$name][$class] as $i => $event) {
                if ($event[0] === $handler) {
                    unset(self::$_events[$name][$class][$i]);
                    $removed = true;
                }
            }
            if ($removed) {
                self::$_events[$name][$class] = array_values(self::$_events[$name][$class]);
            }
            return $removed;
        }
    }

    /**
     * 判断一个事件是否定义了处理程序。
     * 如果子类没有，需要判断父类有没有
     * 事件是可以被继承的
     * @param $class
     * @param $name
     * @return bool
     */
    public static function hasHandlers($class, $name)
    {
        if (empty(self::$_events[$name][$class])) {
            return false;
        }
        if (is_object($class)) {
            $class = get_class($class);
        } else {
            $class = ltrim($class, '\\');
        }
        do {
            if (!empty(self::$_events[$name][$class])) {
                return true;
            }
        } while (($class = get_parent_class($class)) !== false);
        return false;
    }

    /**
     * 执行事件，子类父类的事件都会别执行
     * @param $class
     * @param $name
     * @param $data
     * @param Event $event
     */
    public static function trigger($class, $name, $data = array(), $event = null)
    {
        if (empty(self::$_events[$name])) {
            return;
        }
        if ($event === null) {
            $event = new self;
        }
        $event->handled = false;
        $event->name = $name;

        if (is_object($class)) {
            if ($event->sender === null) {
                $event->sender = $class;
            }
            $class = get_class($class);
        } else {
            $class = ltrim($class, '\\');
        }
        do {
            if (!empty(self::$_events[$name][$class])) {
                foreach (self::$_events[$name][$class] as $handler) {
                    $event->data = $handler[1];
                    $methodParam = CoreReflection::getMethodParam($handler[0]);
                    if (current($methodParam) == 'event') {
                        array_unshift($data, $event); //事件对象只在在参第一位
                    }
                    call_user_func_array($handler[0], $data);
                    if ($event->handled) {
                        return;
                    }
                }
            }
        } while (($class = get_parent_class($class)) !== false);
    }
}