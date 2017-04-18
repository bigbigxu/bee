<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/4/13
 * Time: 9:10
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
     * 错误码
     * @var int
     */
    public $errno;
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
    /*
     * 获取请求参数
     * 这种$this->request的值
     * @return mixed 数据
     */
    abstract public function setRequest();

    /**
     * 权限校验
     * @return mixed
     */
    abstract public function checkAuth();

    /**
     * 运行，实例化控制器，调用方法
     * 设置$this->request的值
     * @return mixed
     */
    abstract public function runAction();

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

    public function run()
    {
        $this->setRequest();

        if (!$this->beforeAction()) {
            return null;
        }

        $this->checkAuth();
        $this->runAction();

        if (!$this->afterAction()) {
            return null;
        }

        $this->send();
    }

    /**
     * 发送数据的方法
     */
    public function send()
    {
        header('Content-Type: text/html;charset=utf-8');
        die($this->response);
    }
}