<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/4/13
 * Time: 9:10
 * 模块代码
 */
namespace bee\core;
abstract class Module
{
    use TComponent;
    /**
     * 模型ID
     * @var string|int
     */
    public $id;
    /**
     * 控制器
     * @var mixed
     */
    public $ctrl;
    /**
     * 当前类名和方法名
     * @var []
     */
    public $route;
    /**
     * 请求数据
     * @var mixed
     */
    public $request;
    /**
     * 响应数据
     * @var mixed
     */
    public $response;
    /**
     * 模块可以加载的控制器命名空间
     * @var array
     */
    public $namespace = [];
    /**
     * 错误是否抛出异常。
     * @var bool
     */
    public $throw = true;
    /*
     * 获取请求参数
     * 设置 $this->request的值
     * @return bool
     */
    abstract public function setRequest();

    /**
     * 权限校验
     * @return bool
     */
    abstract public function checkAuth();

    /**
     * 从路由中解析 class method
     * 返回一个数组，包含class method
     * @return array
     */
    abstract public function parseRoute();

    /**
     * 请求执行前执行的占位方法
     * @return bool
     */
    public function beforeAction()
    {
        return true;
    }

    /**
     * 请求结束后执行的方法
     * @return bool
     */
    public function afterAction()
    {
        return true;
    }

    /**
     * 执行动作请求
     * @return bool
     * @throws \Exception
     */
    public function runAction()
    {
        list($class, $method) = $this->parseRoute();
        $class = $this->findController($class);
        if ($class == false) {
            if ($this->throw == true) {
                throw new \Exception("类 [{$class}] 不存在");
            } else {
                return false;
            }
        }
        if (!method_exists($class, $method)) {
            if ($this->throw == true) {
                throw new \Exception("方法 [{$class}::{$method}] 不存在");
            } else {
                return false;
            }
        }
        $o = new $class;
        $this->ctrl = $o;
        $this->response = $o->$method($this->request);
        return true;
    }

    public function run()
    {
        $this->setRequest()
        && $this->beforeAction()
        && $this->checkAuth()
        && $this->runAction()
        && $this->afterAction()
        && $this->send();

    }

    /**
     * 发送数据的方法
     */
    public function send()
    {
        header('Content-Type: text/html;charset=utf-8');
        if (is_array($this->response)) {
            die(json_encode($this->response, JSON_UNESCAPED_UNICODE));
        } else {
            die($this->response);
        }
    }

    /**
     * 查找controller
     * @param $class
     * @return bool|string
     * @throws \Exception
     */
    public function findController($class)
    {
        if (!$this->namespace) {
            throw new \Exception('模块没有定义namespace');
        }
        $class = ucfirst($class);
        $realClass = false;
        foreach ($this->namespace as $name) {
            $realClass = "{$name}\\{$class}Controller";
            if (class_exists($realClass)) {
                break;
            }
        }
        return $realClass;
    }
}