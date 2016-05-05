<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/4/29
 * Time: 9:51
 */
namespace bee\lib;
class LibEvent
{
    protected $base;
    protected $event;
    public function __construct()
    {
        $this->base = event_base_new(); //创建并且初始事件
    }

    /**
     * 创建并且初始事件
     * @return bool|resource
     */
    public function eventBaseNew()
    {
        return event_base_new();
    }

    /**
     * 创建一个新的事件
     * @return bool|resource
     */
    public function eventNew()
    {
        return event_new();
    }

    /**
     * 关联事件到事件base
     * @param $event
     * @param $base
     * @return bool
     */
    public function eventBaseSet($event, $base)
    {
        return event_base_set($event, $base);
    }

    /**
     * 向指定的设置中添加一个执行事件
     * @param $event
     * @param int $timeout ptional timeout (in microseconds).
     * @return bool
     */
    public function eventAdd($event, $timeout)
    {
        return event_add($event, $timeout);
    }

    /**
     * 处理事件，根据指定的base来处理事件循环
     * @param $base
     * @return int
     */
    public function eventBaseLoop($base)
    {
        return event_base_loop($base);
    }

    /**
     * @param resource $event 事件资源
     * @param int $fd 链接编号
     * @param int $t 事件类型
     * @param mixed $callback 回调函数
     * @param array $arg 回调函数参数
     * @return bool
     */
    public function eventSet($event, $fd = 0, $t = EV_TIMEOUT, $callback, $arg = [])
    {
        return event_set($event, $fd, $t, $callback);
    }
}