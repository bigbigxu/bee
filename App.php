<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/4/1
 * Time: 14:42
 * 系统核心入口文件
 *
 * 必须指定一个数组或配置文件。App会加载如下核心配置
 * array(
 *  'env' => '设置系统当前运行环境',
 *  'base_dir' => '应用的相关文件根目录',
 *  'config_dir' => '配置文件目录'
 *  'debug' => '是否为调试模式'
 *  'crontab_dir' => '定时器脚本目录'
 *  'runtime_dir' => '运行时目录，比如日志',
 *  'class_map' => '类地图，用于减少自动加载的开销',
 *  'package_map' => '自动加载的包（目录加载）',
 *  'namespace' => '需要加载的命名空间'
 * );
 */

namespace bee;

use bee\core\BeeMysql;
use bee\core\BeeRedis;
use bee\core\Log;
use bee\core\ServiceLocator;
use Exception;

class App
{
    private static $_instance;
    /**
     * 类的加载路径，为包名=>目录名
     * @var array
     */
    protected $packageMap = [];
    /**
     * 类的加载地址，为类名,别名=>文件名
     * @var array
     */
    protected $classMap = [];
    /**
     * 框架目录
     * @var string
     */
    protected $sysDir;
    /**
     * 全部配置
     * @var []
     */
    protected $config;
    /**
     * run方法是否已经运行。run只能运行一次
     * @var int
     */
    protected $isRun = 0;
    /**
     * 配置文件目录
     * @var string
     */
    protected $configDir;
    /**
     * 是否为debug模式
     * @var int
     */
    protected $isDebug = 0;
    /**
     * 项目根目录
     * @var string
     */
    protected $baseDir;
    /**
     * 运行文件目录
     * @var string
     */
    protected $runtimeDir;
    /**
     * 命令行程序目录
     * @var string
     */
    protected $crontabDir;
    /**
     * 所有注册的命令空间
     * @var array
     */
    protected $namespace = [];
    /**
     * 环境类型
     * @var string
     */
    protected $env;
    /**
     * 当前运行的模块
     * @var \bee\core\Module
     */
    protected $module;
    /**
     * @var ServiceLocator
     */
    protected $services;
    const ENV_TEST = 'test'; /* 开发环境 */
    const ENV_DEV = 'dev'; /* 测试环境 */
    const ENV_PRO = 'pro'; /* 生产环境 */

    private function __construct($config = null)
    {
        $this->sysDir = dirname(__FILE__);

        /* 得到配置文件。*/
        if ($config === null) {
            /*加载默认配置文件 */
            $configFile = $this->sysDir . '/../config/main.php';
            $this->config = include $configFile;
        } elseif (is_array($config)) {
            $this->config = $config;
        } elseif (is_file($config)) {
            $this->config = include $config;
        } else {
            throw new Exception('配置错误');
        }

        $this->isRun = 1;
        $this->baseDir = $this->config['base_dir'] ?: $this->sysDir . '/../';
        $this->configDir = $this->config['config_dir'] ?: $this->baseDir . '/config';
        $this->isDebug = (int)$this->config['debug'];
        $this->crontabDir = $this->config['crontab_dir'] ?: $this->baseDir . '/crontab';
        $this->runtimeDir = $this->config['runtime_dir'] ?: $this->baseDir . '/runtime';
        $this->env = $this->config['env'] ?: self::ENV_DEV;
        $this->loadCore();
        /* 注册自动加载函数 */
        spl_autoload_register(array($this, 'autoLoad'), true, true);
    }

    /**
     * 用于完成运行的一些参数初始化。
     */
    protected function init()
    {
        $this->loadClass(self::c('class_map')); /* 加载类地图 */
        $this->loadPackage(self::c('package_map')); /* 加载目录包 */
        $this->loadNamespace(self::c('namespace')); /* 加载命名空间 */

        $this->services = new ServiceLocator(self::getComponents()); /* 加载组件 */
        $this->services->getError()->register(); /* 注册错误处理 */
        $this->services->getEnv()->exec($this->env, $this->config['env_set'] ?: []); /* 设置环境 */
    }

    /**
     * @param mixed $config
     * @return App
     */
    public static function getInstance($config = null)
    {
        if (!is_object(self::$_instance)) {
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
            if ($res === null) {
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
     * @return BeeMysql
     */
    public static function m($name, $db = 'db.main')
    {
        return BeeMysql::getInstance($db)->from($name);
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
        /* 如果不是一个文件 */
        if (!is_file($file)) {
            $res = $name;
        } elseif ($ext == '.php') {
            $res = include $file;
        } elseif ($ext == '.ini') {
            $res = parse_ini_file($file);
        } else {
            $res = null;
        }

        return $res;
    }

    /**
     * 引入一个或多个包。
     * 目前不支持递归添加目录
     * @param mixed $package 包名称
     * @return bool
     */
    public function loadPackage($package)
    {
        foreach ((array)$package as $name => $path) {
            /* 如果包名不是一个字符串。则取最后一个目录名作为包名 */
            if (is_int($name)) {
                $name = basename($path);
            }
            if ($this->packageMap[$name] === null) {
                $this->packageMap[$name] = $path;
            }
        }
    }

    /**
     * 加载一个类
     * @param $class
     */
    public function loadClass($class)
    {
        foreach ((array)$class as $name => $path) {
            /* 如果类名不是一个字符串。则取文件名为为类名 */
            if (is_int($name)) {
                $name = basename($path, '.php');
            }
            if ($this->classMap[$name] === null) {
                $this->classMap[$name] = $path;
            }
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
     * @param array $map 命名空间名称
     * @throws Exception
     */
    public function loadNamespace($map)
    {
        foreach ((array)$map as $name => $path) {
            if (strpos($name, '\\') !== false) {
                throw new Exception('只可以注册一级命名空间');
            }
            $baseDir = rtrim(str_replace('\\', '/', $path), '/');
            if ($this->namespace[$name] === null) {
                $this->namespace[$name] = $baseDir;
            }
        }
    }

    /**
     * 加载文件，不区分package和class
     * @param $arr
     */
    public function load($arr)
    {
        if (!is_array($arr)) {
            $arr = array($arr);
        }

        foreach ($arr as $key => $row) {
            $load = array(
                $key => $row,
            );
            if (is_dir($row)) {
                $this->loadPackage($load);
            } elseif (is_file($row)) {
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
        /* 类命名加载。2.0兼容 1.x */
        if ($newClassName = $this->classAlias($className)) {
            if ($newClassName != $className) {
                return class_alias($newClassName, $className);
            }
        }

        $file = $this->classMap[$className];
        if ($file !== null) { /* 类地图中已经存在 */
            if (is_file($file)) {
                require $file;
                return true;
            } else {
                return false;
            }
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
            if (is_file($file)) {
                $this->classMap[$className] = $file;
                require $file;
                return true;
            } else {
                return false;
            }
        } else { //非命名空间，遍历目录
            foreach ($this->packageMap as $package) {
                $file = $className . '.php';
                $file = rtrim($package, '/') . '/' . $file;
                if (!is_file($file)) {
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
     * 加载核心
     */
    private function loadCore()
    {
        $corePackage = array(
            'sys.cache' => $this->sysDir . '/cache',
            'sys.client' => $this->sysDir . '/client',
            'sys.common' => $this->sysDir . '/common',
            'sys.core' => $this->sysDir . '/core',
            'sys.lib' => $this->sysDir . '/lib',
            'sys.object' => $this->sysDir . '/object',
            'sys.server' => $this->sysDir . '/server',

            'app.common' => $this->baseDir . '/common',
            'app.model' => $this->baseDir . '/model',
            'app.controller' => $this->baseDir . '/controller',
        );
        $this->packageMap = $corePackage;
        $this->classMap = require __DIR__ . '/classes.php';
        $this->namespace = [
            'bee' => $this->sysDir,
            'app' => $this->baseDir
        ];
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
        if ($name == '') {
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
        if ($name == '') {
            return $this->classMap;
        } else {
            return $this->classMap[$name];
        }
    }

    /**
     * 通过对象配置创建一个对象
     * 对象配置应该包含如下几个元素
     * class 类名
     * params 构造函数参数
     * config 对象属性
     * path 类文件路径，如果需要。
     * @param string|array $config 当前对象的配置文件
     * @return object
     */
    public static function createObject($config)
    {
        return ServiceLocator::create($config);
    }

    /**
     * 得到mysql数据库连接
     * @param $name
     * @return BeeMysql
     */
    public static function db($name = null)
    {
        $name = $name === null ? 'db.main' : $name;
        return BeeMysql::getInstance($name);
    }

    /**
     * 得到redis数据库连接
     * @param null $name
     * @return BeeRedis
     */
    public static function redis($name = null)
    {
        $name = $name === null ? 'redis.main' : $name;
        return BeeRedis::getInstance($name);
    }

    /**
     * 得到当前错误日志文件路径
     * @return string
     */
    public static function getErrorLogFile()
    {
        $file = Log::getErrorLogFile();
        return $file;
    }

    /**
     * 得到当前访问日志的文件路径
     * @return string
     */
    public static function getAccessLogFile()
    {
        $file = Log::getAccessLogFile();
        return $file;
    }

    /**
     * 得到当前调试日志的文件路径
     * @return string
     */
    public static function getDebugLogFile()
    {
        $file = Log::getDebugLogFile();
        return $file;
    }

    /**
     * 得到当前环境
     * @return mixed
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * 获取对象管理器
     * @return ServiceLocator
     */
    public static function s()
    {
        return self::getInstance()->services;
    }

    /**
     * 获取系统组件
     * @return array
     */
    public function getComponents()
    {
        $core = [
            'log' => ['class' => 'bee\core\Log'], /* 日志组件 */
            'error' => ['class' => 'bee\core\PhpError'], /* 错误处理 */
            'env' => ['class' => 'bee\core\PhpEnv'], /* php环境设置*/
            'cache' => [ /* 缓存 */
                'class' => 'bee\cache\FileCache',
                'config' => [
                    'expire' => 3600,
                    'cachePath' => $this->getRuntimeDir() . '/cache'
                ],
            ],
            'curl' => ['class' => 'Curl'], /* curl 组件 */
            'db' => function () {
                return BeeMysql::getInstance('db.main');
            },
            'redis' => function () {
                return BeeRedis::getInstance('redis.main');
            }
        ];
        return array_merge($core, self::c('component') ?: []);
    }

    /**
     * 运行，加载模块
     * @param $moduleId
     * @throws Exception
     */
    public function run($moduleId)
    {
        $config = self::c('module.' . $moduleId);
        if (!$config) {
            throw new \Exception("未知的模块");
        }
        $config['config']['id'] = $moduleId;
        $this->module = ServiceLocator::create($config);
        $this->module->run();
    }

    /**
     * 获取模块
     * @return \bee\core\Module
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * 类别名。2.0版本兼容1.6方案
     * @param $class
     */
    public function classAlias($class)
    {
        $map = [
            'Curl' => 'bee\client\Curl',
            'CoreSocket' => 'bee\client\CoreSocket',
            'CoreFile' => 'bee\common\File',
            'CoreJson' => 'bee\common\Json',
            'FileUpload' => 'bee\common\FileUpload',
            'Ftp' => 'bee\common\Ftp',
            'Functions' => 'bee\common\Functions',
            'Image' => 'bee\common\Image',
            'LinuxCrontab' => 'bee\common\LinuxCrontab',
            'Mcrypt' => 'bee\common\Mcrypt',
            'Pack' => 'bee\common\Pack',
            'SplitTable' => 'bee\common\SplitTable',
            'StructXml' => 'bee\common\StructXml',
            'Timer' => 'bee\common\Timer',
            'Zlib' => 'bee\common\Zlib',
            'Call' => 'bee\core\Call',
            'CoreController' => 'bee\core\Controller',
            'CoreLog' => 'bee\core\Log',
            'CoreModel' => 'bee\core\Model',
            'CoreMysql' => 'bee\core\BeeMysql',
            'CoreRedis' => 'bee\core\BeeRedis',
            'CoreReflection' => 'bee\core\BeeReflection',
            'CoreSphinx' => 'bee\core\BeeSphinx',
            'PhpEnv' => 'bee\core\PhpEnv',
            'PhpError' => 'bee\core\PhpError',
            'SwooleController' => 'bee\core\SwooleController',
            'WeiXin' => 'bee\object\WeiXin',
            'HttpObject' => 'bee\object\HttpObject',
            'App' => 'bee\App'
        ];
        return $map[$class];
    }

    /**
     * 框架版本号
     * @return string
     */
    public static function version()
    {
        return '2.1';
    }
}