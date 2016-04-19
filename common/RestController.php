<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/3/28
 * Time: 10:50
 */
class RestController extends CoreController
{
    protected $routeName = 'method'; //路由的名称
    protected $routeType = self::ROUTE_TYPE_DOT; //路由形式
    protected $responseType = self::RESPONSE_TYPE_JSON; //返回数据的形式
    protected $jsonpName = 'callback'; //jsonp数据回调函数名称
    protected $controllerName; //控制器名称
    protected $actionName; //动作名称
    protected $shortControllerName; //不包含controller和控制器名称

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
        $this->controllerName = $tmp[0] . 'Controller';
        $this->actionName = $tmp[1];
        $this->shortControllerName = $tmp[0];
        if (!class_exists($this->controllerName)) {
            throw new Exception("请求的控制器：{$this->controllerName} 不存在");
        }
        if (!method_exists($this->controllerName, $this->actionName)) {
            throw new Exception("请求的动作{$this->controllerName} {$this->actionName}不存在");
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
            'class_name' => $this->controllerName,
            'params' => array(),
            'config' => array(),
        ));
        $controller->beforeAction();
        $action = $this->actionName;
        $controller->$action();
        $controller->afterAction();
    }

    /**
     * 得到控制器名称
     * @return mixed
     */
    public function getControllerName()
    {
        return $this->controllerName;
    }

    public function getShortControllerName()
    {
        return $this->shortControllerName;
    }

    /**
     * 返回josnp函数名称
     * @return string
     */
    public function getJsonpName()
    {
        return $this->jsonpName;
    }

    /**
     * 响应请求之前执行的方法
     */
    public function beforeAction()
    {

    }

    /**
     * 响应结束后执行的方法。
     */
    public function afterAction()
    {

    }


    /**
     * 加载一个视图。
     * view目录要和contoller在同一级目录
     * @param $view
     * @param array $data
     */
    public function render($view, $data = array())
    {
        $dir = $this->getViewDir();
        $viewFile = "{$dir}/{$view}.php";
        if (is_array($data)) {
            extract($data, EXTR_PREFIX_SAME, 'data');
        }
        require $viewFile;
    }

    /**
     * 得到视图子目录
     * PhpTestController 控制器得到的子目是 php_tesst
     * @return string
     */
    public function getViewDir()
    {
        $request = RestRequest::getInstance();
        $controller = $request->getShortControllerName();
        $tmp = str_split($controller, 1);
        $tmp[0] = strtolower($tmp[0]);
        foreach ($tmp as $key => $char) {
            if (strtoupper($char) == $char) {
                $tmp[$key] = '_' . strtolower($char);
            }
        }
        $subDir = implode('', $tmp);
        $fileName = App::getInstance()->getClass($request->getControllerName());
        $viewDir = dirname(dirname($fileName)) . '/view/' . $subDir;
        return $viewDir;
    }

    /**
     * 输出json
     * @param array $data
     */
    public function json($data = array())
    {
        $request = RestRequest::getInstance();
        $name = $request->getJsonpName();
        $callback = $_REQUEST[$name];

        if (App::getInstance()->isDebug()) {
            $str = CoreJson::encode($data, true);
        } else {
            $str = json_encode($data);
        }
        if ($callback) {
            $str = "{$callback}(" . $str . ")";
        }
        echo $str;
    }
}