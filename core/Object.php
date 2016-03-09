<?php
//namespace iphp\core;
/**
 * @desc 对象化描述数据。对象的每个成员，对应一个数据。
 * object 不同于CoreModel 模型对应数据表，并不是共用的。
 * object 是实现更为通用的一些操作
 *
 * object 从配置文件完成对象的实例化。
 * @author xuen
 */
class Object
{
    protected $redisConfig; //redis的配置文件，如果object想执行redis操作
    protected $dbConfig; //db配置，如果object想执行db操作
    private static $_instance;

    /**
     * 所有对象的实例化构造方法
     * @param array|string $config 对象属性数组
     */
    public function __construct($config = array())
    {
        if (is_string($config)) {
            $config = App::c($config);
        }
        if (!empty($config)) {
            $this->set($config);
        }
        $this->init();
    }

    /**
     * 得到redis操作类。
     * @return CoreRedis
     */
    public function redis()
    {
        if (is_object($this->redisConfig)) {
            $redis = $this->redisConfig;
        } else {
            $redis = App::redis($this->redisConfig);
        }
        return $redis;
    }

    /**
     * @return CoreMysql
     */
    public function db()
    {
        if (is_object($this->dbConfig)) {
            $db = $this->dbConfig;
        } else {
            $db = App::redis($this->dbConfig);
        }
        return $db;
    }

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
     * 对象的构造方法调用后，需要执行此方法。
     */
    public function init()
    {

    }

    /**
     * 得到对象属性
     * @param $name
     * @throws Exception
     */
    public function __get($name)
    {
        $getter = "get{$name}";
        if(method_exists($this, $getter)) {
            return $this->$getter;
        }
        else {
            throw new Exception("无法访问对象属性");
        }
    }

    /**
     * 设置对象属性。
     * @param  $name
     * @param  $value
     * @throws Exception
     */
    public function __set($name, $value)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            $this->$setter($value);
        }
        else {
            throw new Exception($this,"无法设置对象属性");
        }
    }

    /**
     * 清除对象状态。相当于重新创建对象
     */
    public function clear()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $row) {
            $this->$row = null;
        }
    }

    /**
     * 设置成员属性
     * @param $config
     * @param null $value
     */
    public function set($config, $value = null)
    {
        $attr = array();
        if (is_array($config) == false) {
            //如果不是一个数组。config为成员名称，value为值
            $attr[$config] = $value;
        } else {
            $attr = $config;
        }

        $vars = get_object_vars($this);
        foreach ($attr as $key => $row) {
            if (array_key_exists($key, $vars)) {
                $this->$key = $row;
            }
        }
    }
}