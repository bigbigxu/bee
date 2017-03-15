<?php
/**
 * @desc 对象化描述数据。对象的每个成员，对应一个数据。
 * object 不同于CoreModel 模型对应数据表，并不是共用的。
 * object 是实现更为通用的一些操作
 *
 * object 从配置文件完成对象的实例化。
 * @TODO 此类被 ServiceLocator代替，即将被删除，请勿使用。
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

    /**
     * 根据配置创建一个继承Object类的实例
     * Object::create与App::createObject方法不同。
     * App::createObject可以创建任何类型的对象，但不能设置非public的属性，支持构造函数参数
     * Object::create只能创建继承于Object类的对象，可以在实例化的时候设置任何成员属性，不支持构造函数参数
     * Object::getInstance只能创建已知类，Object::create创建可创建动态的类。
     * @param $objConfig
     * @param bool $single
     * @return object
     * @throws Exception
     */
    public static function create($objConfig, $single = true)
    {
        if (is_string($objConfig)) {
            $objConfig = App::c($objConfig);
        }
        $className = $objConfig['class_name']; //类名
        $config = $objConfig['config']; //对象属性配置
        $classFile = $objConfig['class_file']; //对象文件路径

        if ($single == true && is_object(self::$_instance[$className])) {
            return self::$_instance[$className]; //返回单例对象
        }
        if ($classFile) {
            //加载配置文件。支持非标准类的加载。
            App::getInstance()->loadClass(array(
                $className => $classFile
            ));
        }
        $re = new ReflectionClass($className);
        $o = $re->newInstance($config);
        if ($single == true) {
            self::$_instance[$className] = $o; //单例模式下，保存当前对象
        }
        return $o;
    }
}