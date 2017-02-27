<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/2/24
 * Time: 14:17
 * 新的对象管理器。统一原先的App::createObject, Object。
 */
namespace bee\core;
use bee\cache\Cache;
use bee\client\LogClient;

class ServiceLocator
{
    /**
     * 保存所有实例化的对象
     * @var array
     */
    private $_components = [];

    /**
     * 对象配置。配置了默认的核心对象
     * @var array
     */
    private $_defines = [];


    public function __construct($defines)
    {
        $this->_defines = array_merge($this->coreComponents(), $defines);
    }

    /**
     * 通过对象配置创建一个对象
     * 对象配置应该包含如下几个元素
     * class 类名
     * params 构造函数参数
     * config 对象属性
     * path 类文件地址，如果需要。
     * @param string|array $config 当前对象的配置文件
     * @return object
     */
    public static function create($config)
    {
        if (is_string($config)) {
            $config = \App::c($config);
        }
        $className = $config['class']; /* 类名 */
        $params = $config['params'] ?: []; /* 构造函数参数 */
        $config = $config['config'] ?: []; /* 对象属性配置 */
        $classFile = $config['path'] ?: ''; /* 对象文件路径 */
        if ($classFile) { /* 加载类文件 */
            \App::getInstance()->loadClass([$className => $classFile]);
        }

        /* 创建对象 */
        $re = new \ReflectionClass($className);
        $o = $re->newInstanceArgs($params);
        $vars = get_object_vars($o);
        foreach ($config as $key => $row) {
            if (array_key_exists($key, $vars)) {
                $o->$key = $row;
            }
        }
        return $o;
    }


    /**
     * 获取一个对象。
     * 获取的对象必须已经配置。如果没有，使用create创建。
     * @param string $key 类名
     * @param array $config 配置
     * @return mixed
     */
    public function get($key, $config = [])
    {
        if (!$this->_components[$key]) {
            if (!$this->_defines[$key]) { /* 如果没有定义配置 */
                $this->_defines[$key] = $config;
            }
            $this->_components[$key] = self::create($this->_defines[$key]);
        }
        return $this->_components[$key];
    }

    /**
     * 设置一个对象。
     * 设置的时候，不会实例化对象。只会设置参数
     * @param $key
     * @param $config
     * @return mixed
     */
    public function set($key, $config)
    {
        unset($this->_components[$key]);
        $this->_defines[$key] = $config;
        return true;
    }

    /**
     * 删除一个对象
     * @param $key
     */
    public function del($key)
    {
        unset($this->_components[$key], $this->_defines[$key]);
    }

    /**
     * 判断一个对象是否存在
     * @param $key
     * @return bool
     */
    public function isExists($key)
    {
        return (bool)$this->_components[$key];
    }

    /**
     * 获取日志对象
     * @return \CoreLog
     */
    public function getLog()
    {
        return $this->get('log');
    }

    /**
     * 获取日志对象
     * @return \PhpError
     */
    public function getError()
    {
        return $this->get('error');
    }


    /**
     * 获取日志对象
     * @return \PhpEnv
     */
    public function getEnv()
    {
        return $this->get('env');
    }

    /**
     * 获取所有组件或拒组件定义
     * @param bool $define
     * @return array
     */
    public function getComponents($define = false)
    {
        return $define ? $this->_defines : $this->_components;
    }

    /**
     * udp 日志发送组件
     * @return LogClient
     */
    public function getUpdLog()
    {
        return $this->get('udp_log');
    }

    /**
     * 获取缓存组件
     * @return Cache
     */
    public function getCache()
    {
        return $this->get('cache');
    }

    /**
     * 定义的系统核心组件
     * @return array
     */
    public function coreComponents()
    {
        return [
            'log' => ['class' => 'CoreLog'], /* 日志组件 */
            'error' => ['class' => 'PhpError'], /* 错误处理 */
            'env' => ['class' => 'PhpEnv'], /* php环境设置*/
            'cache' => [
                'class' => 'bee\cache\FileCache'] /* 缓存 */
        ];
    }
}