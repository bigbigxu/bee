<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/4/1
 * Time: 9:19
 * 组件对象，作用是为bee提供统一的对象实现方法。
 *
 * Component 仅仅提供一种类初始化的功能。
 * 对象属性通过数组传入构造函数，并调用init方法，仅此而已。
 * 继承Component类，脱离Component任然是一个完整的类
 */
namespace bee\core;
use bee\App;

trait TComponent
{
    /**
     * 错误码
     * @var int
     */
    protected $errno = 0;
    /**
     * 错误消息
     * @var string
     */
    protected $errmsg = '';
    /**
     * 组件实例化判断的特殊变量
     * @var int
     */
    protected $__isBeeComponent__ = 1;

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


    /**
     * 为当前类注册一个事件
     * 注册在app对象上的事件是一个全局事件
     * @param string $name 事件名称
     * @param string $callback 事件处理函数
     * @param array $data 事件处理的数据。
     */
    public function on($name, $callback, $data = [])
    {
        Event::on($this, $name, $callback, $data);
    }

    /**
     * 执行当前模型的事件
     * @param $name
     * @param $data
     * @param Event $event
     */
    public function trigger($name, $data = [], $event = null)
    {
        Event::trigger($this, $name, $data, $event);
    }

    /**
     * 如果一个对象的属性是个一个组件配置，调用此方法获取对象
     * @param mixed $id 一个组件ID或者配置
     * @return bool|object
     * @throws \Exception
     */
    public function sureComponent(&$id)
    {
        if (!$id) { /* 如果id不是一个有效值 */
            return false;
        } elseif (is_object($id) && (!$id instanceof \Closure)) { /* 是一个对象，但不是回调函数*/
            return $id;
        } elseif (is_string($id) || is_int($id)) { /* 是一个组件对象 */
            $id = App::s()->get($id);
        } else { /* 创建一个对象 */
            $id = ServiceLocator::create($id);
        }
        return $id;
    }

    /**
     * 设置错误码
     * @param $errno
     * @return bool
     */
    public function setErrno($errno)
    {
        $this->errno = $errno;
        return false;
    }

    /**
     * 设置错误消息
     * @param $errmsg
     */
    public function setErrmsg($errmsg)
    {
        $this->errmsg = $errmsg;
    }

    /**
     * 获取错误码
     * @return int
     */
    public function getErrno()
    {
        return $this->errno;
    }

    /**
     * 获取错误消息
     * @return string
     */
    public function getErrmsg()
    {
        if ($this->errmsg) {
            return $this->errmsg;
        } else {
            return $this->errmsgMap()[$this->errno];
        }
    }

    /**
     * 判断当前是否有错误
     * @return bool
     */
    public function hasError()
    {
        return $this->errno == 0 && $this->errmsg == '';
    }

    /**
     * 获取错误消息数组
     * @return array
     */
    public function errmsgMap()
    {
        return  [];
    }
}