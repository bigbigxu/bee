<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/10/16
 * Time: 16:42
 * 请求处理类
 */
class CoreRequest extends Object
{
    protected $routeName = 'method'; //路由的名称
    protected $routeType = self::ROUTE_TYPE_DOT; //路由形式
    protected $responseType = self::RESPONSE_TYPE_JSON; //返回数据的形式
    protected $jsonpName = 'callback'; //jsonp数据回调函数名称
    protected $controller; //控制器名称
    protected $action; //动作名称

    const ROUTE_TYPE_DOT = '.'; //使用a.b来标识路由
    const ROUTE_TYPE_SLASH = '/'; //使用 a/b来标识路由

    const RESPONSE_TYPE_XML = 'xml';
    const RESPONSE_TYPE_JSON = 'json';
    const RESPONSE_TYPE_JSONP = 'jsonp';
    const RESPONSE_TYPE_HTML = 'html';
    private static $_instance;

    /**
     * 得到一个对象实例。子类必须重载此方法
     * @param array $config 对象配置数组。key为对象成员名，value为成员性值
     * @param string $name
     * @return static
     */
    public static function getInstance($config = array(), $name = __CLASS__)
    {
        if (!isset(self::$_instance[$name])) {
            self::$_instance[$name] = new $name($config);
        }
        return self::$_instance[$name];
    }

    /**
     * 解析路由，得到class,method
     * @return array
     * @throws Exception
     */
    public function parseRoute()
    {
        $tmp = explode($this->routeType, $_REQUEST[$this->routeName]);
        $this->controller = $tmp[0] . 'Controller';
        $this->action = $tmp[1];
        if (class_exists($this->controller)) {
            throw new Exception("请求的控制器：{$this->controller} 不存在");
        }
        if (method_exists($this->controller, $this->action)) {
            throw new Exception("请求的动作{$this->controller} {$this->action}不存在");
        }
    }

    /**
     * 执行请求
     * @throws Exception
     */
    public function exec()
    {
        $this->parseRoute();
        /* @var controller CoreController */
        $controller = App::createObject(array(
            'class_name' => $this->controller
        ));
        $controller->beforeAction();
        $action = $this->action;
        $controller->$action();
        $controller->afterAction();
    }

    /**
     * 得到控制器名称
     * @return mixed
     */
    public function getControllerName()
    {
        return $this->controller;
    }

    /**
     * 返回josnp函数名称
     * @return string
     */
    public function getJsonpName()
    {
        return $this->jsonpName;
    }
}