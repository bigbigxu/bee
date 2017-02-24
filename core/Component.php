<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/2/24
 * Time: 14:17
 * 新的对象管理器。统一原先的App::createObject, Object。
 */
namespace bee\core;
use bee\client\LogClient;

class Component
{
    /**
     * 保存所有实例化的对象
     * @var array
     */
    protected $singletons = [];

    /**
     * 对象配置。配置了默认的核心对象
     * @var array
     */
    protected $defines = [
        'log' => [
            'class_name' => 'CoreLog'
        ],
        'error' => [
            'class_name' => 'PhpError'
        ],
        'env' => [
            'class_name' => 'PhpEnv'
        ]
    ];


    public function __construct($defines)
    {
        $this->defines = array_merge($this->defines, (array)$defines);
    }

    /**
     * 通过对象配置创建一个对象
     * 对象配置应该包含如下几个元素
     * class_name 类名
     * params 构造函数参数
     * config 对象属性
     * class_file 类文件地址，如果需要。
     * @param string|array $config 当前对象的配置文件
     * @return object
     */
    public static function create($config)
    {
        if (is_string($config)) {
            $config = \App::c($config);
        }
        $className = $config['class_name']; /* 类名 */
        $params = $config['params'] ?: []; /* 构造函数参数 */
        $config = $config['config'] ?: []; /* 对象属性配置 */
        $classFile = $config['class_file'] ?: ''; /* 对象文件路径 */
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
        if (!$this->singletons[$key]) {
            $config = $config ?: $this->defines[$key];
            $this->singletons[$key] = self::create($config);
        }
        return $this->singletons[$key];
    }

    /**
     * 设置一个对象
     * @param $key
     * @param $config
     */
    public function set($key, $config)
    {
        $this->singletons[$key] = $this->create($config);
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

    public function getSingletons()
    {
        return $this->singletons;
    }

    /**
     * udp 日志发送组件
     * @return LogClient
     */
    public function getUpdLog()
    {
        return $this->get('udp_log');
    }
}