<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/2/24
 * Time: 14:17
 * 服务定位器
 */
namespace bee\core;
use bee\cache\ICache;
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


    public function __construct($defines = [])
    {
        $this->_defines = $defines;
    }

    /**
     * 通过对象配置创建一个对象
     * 对象配置可以有如下2种情况
     * 1. 一个匿名函数或者回调结构
     *    function() {
     *       return new App;
     *    }
     *   ['App', 'g']
     * 2. 一个数组
     *    [
     *     'class' => '类名',
     *     'config' => '配置参数，可以是一个回调函数或数组',
     *     'path' => '路径',
     *     'params' => '类构造函数参数或回调函数参数'
     *   ]
     * 3. 一个对象
     * 4. 一个字符串，解析为一个BEE配置节
     *
     * @param string|array|callable $objConfig 当前对象的配置文件
     * @return object
     */
    public static function create($objConfig)
    {
        if (is_string($objConfig)) { /* 字符串认为是一个bee配置节 */
            $objConfig = \App::c($objConfig);
        }
        if (is_array($objConfig) && isset($objConfig['class'])) {
            return self::build($objConfig);
        } elseif (is_callable($objConfig)) { /* 如果配置是一个回调函数 */
            return call_user_func($objConfig);
        } elseif (is_object($objConfig)) { /* 对象 */
            return $objConfig;
        } else {
            return null;
        }
    }


    /**
     * 获取一个组件
     * @param string $id 组件id
     * @param bool $throw 如果组件不存在是否抛出异常
     * @throws \Exception
     * @return mixed
     */
    public function get($id, $throw = true)
    {
        if (isset($this->_components[$id])) {
            return $this->_components[$id];
        }
        if (isset($this->_defines[$id])) {
            return $this->_components[$id] = self::create($this->_defines[$id]);
        } elseif ($throw) {
            throw new \Exception("没有定义的组件：{$id}");
        } else {
            return null;
        }
    }

    /**
     * 设置一个对象。
     * 设置的时候，不会实例化对象。只会设置参数
     * @param $id
     * @param $config
     * @return mixed
     */
    public function set($id, $config)
    {
        unset($this->_components[$id]);
        $this->_defines[$id] = $config;
        return true;
    }

    /**
     * 删除一个对象
     * @param $id
     */
    public function del($id)
    {
        unset($this->_components[$id], $this->_defines[$id]);
    }

    /**
     * 判断一个对象是否存在
     * @param $key
     * @return bool
     */
    public function isExists($key)
    {
        return (bool)$this->_defines[$key];
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
     * @return ICache
     */
    public function getCache()
    {
        return $this->get('cache');
    }

    /**
     * 获取curl组件
     * @return \Curl
     */
    public function getCurl()
    {
        return $this->get('curl');
    }

    /**
     * 获取一个redis 组件
     * @return \CoreRedis
     */
    public function getRedis()
    {
        return $this->get('redis');
    }

    /**
     * 获取一个mysql组件
     * @return \CoreMysql
     */
    public function getDb()
    {
        return $this->get('db');
    }

    /**
     * 根据对象配置文件创建一个对象
     * @param array $objConfig
     * @return object|null
     */
    public static function build($objConfig)
    {
        $className = $objConfig['class']; /* 类名 */
        $params = $objConfig['params'] ?: []; /* 构造函数参数 */
        $config = $objConfig['config'] ?: []; /* 对象属性配置 */
        $classFile = $objConfig['path'] ?: ''; /* 对象文件路径 */
        if ($classFile) { /* 加载类文件 */
            \App::getInstance()->loadClass([$className => $classFile]);
        }
        $re = new \ReflectionClass($className);
        if ($re->isSubclassOf('bee\core\Component')) {
            $params[] = $config; /* Component 类型对象，构造参数最后一个配置数组 */
            $o = $re->newInstanceArgs($params);
        } else {
            $o = $re->newInstanceArgs($params);
            $vars = get_object_vars($o);
            foreach ($config as $key => $row) {
                if (isset($vars[$key])) {
                    $o->$key = $row;
                }
            }
        }
        return $o;
    }

    /**
     * 如果一个对象的属性是个一个组件配置，调用此方法获取对象
     * @param $id
     * @return bool|object
     * @throws \Exception
     */
    public function sure(&$id)
    {
        if (!$id) { /* 如果id不是一个有效值 */
            return false;
        } elseif (is_object($id) && (!$id instanceof \Closure)) { /* 是一个对象，但不是回调函数*/
            return $id;
        } elseif (is_string($id) || is_int($id)) { /* 是一个组件对象 */
            $id = $this->get($id);
        } else { /* 创建一个对象 */
            $id = self::create($id);
        }
        return $id;
    }
}