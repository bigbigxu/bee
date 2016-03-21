<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/4/1
 * Time: 14:42
 * 系统核心入口文件
 */
class App
{
    private static $_instance;
    protected $packageMap = array(); //类的加载路径，为包名=>目录名
    protected $classMap = array();  //类的加载地址，为类名/别名=>文件名
    protected $sysDir; //框架目录
    protected $config; //配置文件
    protected $isRun = 0; //run方法是否已经运行。run只能运行一次。
    protected $configDir; //配置文件目录
    protected $isDebug = 0; //是否为debug模式
    protected $baseDir; //项目根目录
    protected $runtimeDir; //运行文件目录
    protected $crontabDir; //命令行程序目录
    protected static $_container; //对象容器
    protected $namespace = array(); //所有注册的命令空间

    private  function __construct($config = null)
    {
        $this->sysDir = dirname(__FILE__);

        //得到配置文件。
        if($config === null) {
            //加载默认配置文件
            $configFile = $this->sysDir . '/../config/main.php';
            $this->config = include $configFile;
        } elseif(is_file($config)) {
            $this->config = include $config;
        } elseif(is_array($config)) {
            $this->config = $config;
        } else {
            throw new Exception('配置错误');
        }

        $this->isRun = 1;
        $this->baseDir = $this->config['base_dir'];
        $this->configDir = $this->config['config_dir'];
        $this->isDebug = $this->config['debug'];
        $this->crontabDir = $this->config['crontab_dir'];
        $this->runtimeDir = $this->config['runtime_dir'];
        $this->loadCorePackage();
        //注册自动加载函数
        spl_autoload_register(array($this, 'autoLoad'));
    }

    /**
     * 用于完成运行的一些参数初始化。
     */
    protected function init()
    {
        $this->setPhpEnv(); //需要在对象实例化完成之后，进行环境配置
        $this->classMap = self::c('class_map'); //加载类地图
        $this->load(self::c('autoload')); //加载配置文件的包。
        $this->namespace = (array)$this->config['namespace'];
        $this->loadNamespace('bee', $this->sysDir);
        $this->loadNamespace('app', $this->baseDir);
    }

    /**
     * 解析路由
     */
    public function run()
    {
        $route = $_GET['r'];
        $routeArr = explode('/', $route);
        //数组第一个表示控制器名称,第二个是方法名称
        $c = $routeArr[0] == false ? 'Index' : $routeArr[0];
        $a = $routeArr[1] == false ? 'index' : $routeArr[1];
        $c = $c . 'Controller';

        if(!class_exists($c)) {
            new Exception("控制器不存在");
        }

        $re = new ReflectionClass($c);
        if(!$re->hasMethod($a)) {
            new Exception("控制器动作不存在");
        }

        $object = $re->newInstanceArgs(array($_GET, $_POST));
        return $object->$a;
    }

    /**
     * @param mixed $config
     * @return App
     */
    public static function getInstance($config = null)
    {
        if (!is_object(self::$_instance)){
            self::$_instance = new self($config);
            self::$_instance->init(); //对象实例化完成之后进行的操作
        }
        return self::$_instance;
    }

    /**
     * @see getInstance()
     * @param null $config
     * @return App
     */
    public static function g($config = null)
    {
        return self::getInstance($config);
    }

    /**
     * 得到一个配置的值
     * a.b.c表示得到config[a][b][c]的值
     * @TODO 不支持多级文件配置。
     * @param $path
     * @return mixed
     * @throws Exception
     */
    public static function c($path = '')
    {
        /* @var $object App */
        $object = self::$_instance;
        if ($path == '') {
            return $object->config;
        }

        $pathArr = explode('.', $path);
        //如果第一个配置节为.php或.ini表示需要加载一个文件
        //表法目前仅支持一级.php分布式配置。
        if (is_string($object->config[$pathArr[0]])) {
            $res = $object->_loadConfigFile($object->config[$pathArr[0]]);
            if($res === null) {
                throw new Exception('不支持的配置文件格式');
            } else {
                $object->config[$pathArr[0]] = $res;
            }
        }

        $tmp = $object->config;
        foreach ($pathArr as $row) {
            $tmp = $tmp[$row];
        }

        return $tmp;
    }

    /**
     * 得到一个模型类,这个模型类其实是CoreMysql的o类
     * @param string $name 表名
     * @param string $db 数据库连接配置项
     * @return CoreMysql
     */
    public static function m($name, $db= 'db.main')
    {
        return CoreMysql::getInstance($db)->from($name);
    }

    /**
     * 解析一个配置文件，支持ini和php2种格式
     * 如果你使用一个.php的配置项，但是这个文件不存在，
     * 会被当成一个普通的字符串配置。
     * @param $name
     * @return array|bool|mixed
     */
    private function _loadConfigFile($name)
    {
        $file = "{$this->configDir}/{$name}";
        $ext = strtolower(substr($file, -4));
        //如果不是一个文件
        if(!is_file($file)) {
            $res = $name;
        } elseif($ext == '.php') {
            $res = include $file;
        } elseif($ext == '.ini') {
            $res = parse_ini_file($file);
        } else {
            $res = null;
        }

        return $res;
    }

    /**
     * 引入一个或多个包。
     * 目前不支持递归添加目录
     * @param $package 包名称
     * @return bool
     */
    public function loadPackage($package)
    {
        if(!is_array($package)) {
            $package = array($package);
        }

        foreach($package as $name => $path) {
            $path = realpath($path);
            //如果不是一个目录
            if(!is_dir($path)) {
                continue;
            }
            //如果包已经存在,以路径区分是不是相同的包。
            if(in_array($path, $this->packageMap)) {
                continue;
            }
            //如果包名不是一个字符串。则取最后一个目录名作为包名。
            if(preg_match('/^[0-9]+$/', $name)) {
                $name = basename($path);
            }
            $this->packageMap[$name] = $path;
        }
    }

    /**
     * 加载一个类
     * @param $class
     */
    public function loadClass($class)
    {
        if(!is_array($class)) {
            $class = array($class);
        }

        foreach($class as $name => $path) {
            $path = realpath($path);
            //如果不是一个文件
            if(!is_file($path)) {
                continue;
            }
            if(in_array($path, $this->classMap)) {
                continue;
            }
            //如果类名不是一个字符串。则取文件名为为类名。
            if(preg_match('/^[0-9]+$/', $name)) {
                $name = basename($path, '.php');
            }
            $this->classMap[$name] = $path;
        }
    }

    /**
     * 加载一个命名空间
     * 和psr4标准不同的是，这里只可以加载一级命名空间。
     * 类似于 bee => __DIR__,这种。其它方式，App.php将不能正常工作。
     * 除去根命名空间的部分，类名与要目录结构名称何保持一致。
     * @TODO psr4标准加载，将在其它类中完成
     * @example
     * 注册 bee => system 那么bee\server\BaseServer类将位于
     * system/server目录下的BaseServer.php文件。
     * @param string $namespace 命名空间名称
     * @param string $baseDir 命名空间根路径
     * @throws Exception
     */
    public function loadNamespace($namespace, $baseDir)
    {
        if (strpos($namespace, '\\') !== false) {
            throw new Exception('只可以注册一级命名空间');
        }
        $baseDir = rtrim(str_replace('\\',  '/', $baseDir), '/');
        $this->namespace[$namespace] = $baseDir;
    }

    /**
     * 加载文件，不区分package和class
     * @param $arr
     */
    public function load($arr)
    {
        if(!is_array($arr)) {
            $arr = array($arr);
        }

        foreach($arr as $key => $row) {
            $load = array(
                $key => $row,
            );
            if(is_dir($row)) {
                $this->loadPackage($load);
            } elseif(is_file($row)) {
                $this->loadClass($load);
            } else {
                continue;
            }
        }
    }


    /**
     * 类的自动加载函数
     * 要想类成功自动加载，有如下要求
     * 非命名空间，要类名和文件名一致。一个文件一个类。不支持目录递归加载
     * 命名空间，类名和文件名一致，路径和命名空间一致。包名为根空间，支持目录递归加载
     * @param $className
     * @return bool
     * @throws Exception
     */
    public function autoLoad($className)
    {
        $file = $this->classMap[$className];
        if(is_file($file)) {
            require $file;
            return true;
        }
        $pos = strpos($className, '\\');
        if ($pos !== false) {  //如果类名包含命令空间
            if ('\\' == $className[0]) {
                $className = substr($className, 1); //解决5.3部分版本的一个bug
            }
            $namespace = substr($className, 0, $pos);
            $baseName = substr($className, $pos + 1);
            if ($this->namespace[$namespace] == false) {
                throw new Exception("没有注册的命名空间");
            }
            $file = $this->namespace[$namespace]
                . '/'
                . str_replace('\\', '/', $baseName)
                . '.php';
            $this->classMap[$className] = $file;
            require $file;
            return true;
        } else { //非命名空间，遍历目录
            foreach($this->packageMap as $package) {
                $file = $className . '.php';
                $file = rtrim($package, '/') . '/' . $file;
                if(!is_file($file)) {
                    continue;
                } else {
                    $this->classMap[$className] = $file;
                    require $file;
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 加载核心类
     */
    private  function loadCorePackage()
    {
        $corePackage = array(
            'sys.core' => $this->sysDir . '/core',
            'sys.common' => $this->sysDir . '/common',
            'sys.object' => $this->sysDir . '/object',
            'sys.validate' => $this->sysDir . '/validate',

            'app.common' => $this->baseDir . '/common',
            'app.model' => $this->baseDir . '/model',
            'app.controller' => $this->baseDir . '/controller',
        );
        $this->loadPackage($corePackage);
    }

    /**
     * 得到框架根目录
     * @return string
     */
    public function getSysDir()
    {
        return $this->sysDir;
    }

    /**
     * 得到项目根目录
     * @return mixed
     */
    public function getBaseDir()
    {
        return $this->baseDir;
    }

    /**
     * 得到运行文件目录
     * @return mixed
     */
    public function getRuntimeDir()
    {
        return $this->runtimeDir;
    }

    /**
     * 得到控制台程序目录
     * @return mixed
     */
    public function getCrontabDir()
    {
        return $this->crontabDir;
    }

    /**
     * 得到配置文件目录
     * @return mixed
     */
    public function getConfigDir()
    {
        return $this->configDir;
    }

    /**
     * 得到是否为debug模式
     * @return int
     */
    public function isDebug()
    {
        return $this->isDebug;
    }

    /**
     * 设置为debug模式
     */
    public function setDebug()
    {
        $this->isDebug = 1;
    }

    /**
     * 得到一个包
     * @param string $name
     * @return array
     */
    public function getPackage($name = '')
    {
        if($name == '') {
            return $this->packageMap;
        } else {
            return $this->packageMap[$name];
        }
    }

    /**
     * 得到一个类
     * @param string $name
     * @return array
     */
    public function getClass($name = '')
    {
        if($name == '') {
            return $this->classMap;
        } else {
            return $this->classMap[$name];
        }
    }

    /**
     * 通过对象配置创建一个对象
     * 对象配置应该包含如下几个元素
     * class_name 类名
     * params 构造函数参数
     * config 对象属性
     * class_file 类文件地址，如果需要。
     * @TODO 基于简化使用的考虑，没有实现当前对象的成员也来自配置。也就是对象配置文件不支持递归配置
     * @param string|array $objConfig 当前对象的配置文件
     * @param bool $single 是否返回单例对象
     * @return object
     */
    public static function createObject($objConfig, $single = true)
    {
        if (is_string($objConfig)) {
            $objConfig = App::c($objConfig);
        }
        $className = $objConfig['class_name']; //类名
        $params = $objConfig['params']; //构造函数参数
        $config = $objConfig['config']; //对象属性配置
        $classFile = $objConfig['class_file']; //对象文件路径

        if ($single == true && is_object(self::$_container[$className])) {
            return self::$_container[$className]; //返回单例对象
        }
        if ($classFile) {
            //加载配置文件。支持非标准类的加载。
            App::getInstance()->loadClass(array(
                $className => $classFile
            ));
        }
        $re = new ReflectionClass($className);
        $o = $re->newInstanceArgs($params);
        $vars = get_object_vars($o);
        foreach ($config as $key => $row) {
            if (array_key_exists($key, $vars)) {
                $o->$key = $row;
            }
        }
        if ($single == true) {
            self::$_container[$className] = $o; //单例模式下，保存当前对象
        }
        return $o;
    }

    /**
     * 得到mysql数据库连接
     * @param $name
     * @return CoreMysql
     */
    public static function db($name = null)
    {
        $name = $name === null ? 'db.main' : $name;
        return CoreMysql::getInstance($name);
    }

    /**
     * 得到redis数据库连接
     * @param null $name
     * @return CoreRedis
     */
    public static function redis($name = null)
    {
        $name = $name === null ? 'redis.main' : $name;
        return CoreRedis::getInstance($name);
    }

    /**
     * 得到当前错误日志文件路径
     * @return string
     */
    public static function getErrorLogFile()
    {
        $o = self::$_instance;
        $dir = $o->runtimeDir . '/error_log/' . date('Y/m');
        if(!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/error_' . date('d') . '.log';
        self::changeLogFileMode($file);
        return $file;
    }

    /**
     * 得到当前访问日志的文件路径
     * @return string
     */
    public static function getAccessLogFile()
    {
        $o = self::$_instance;
        $dir = $o->runtimeDir . '/access_log/' . date('Y/m');
        if(!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/access_' . date('d') . '.log';
        self::changeLogFileMode($file);
        return $file;
    }

    /**
     * 得到当前调试日志的文件路径
     * @return string
     */
    public static function getDebugLogFile()
    {
        $o = self::$_instance;
        $dir = $o->runtimeDir . '/debug_log/' . date('Y/m');
        if(!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/debug_' . date('d') . '.log';
        self::changeLogFileMode($file);
        return $file;
    }

    /**
     * 修改日志文件的权限
     * @param $file
     * @param int $mode
     */
    public static function changeLogFileMode($file, $mode = 0660)
    {
        if (!is_file($file)) {
            file_put_contents($file, '');
            @chmod($file, $mode);
        }
    }

    public function setPhpEnv()
    {
        $file = self::getErrorLogFile();
        PhpEnv::getInstance()
            ->displayError()
            ->errorReporting()
            ->errorLog($file);
    }
}