<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/6/9
 * Time: 16:30
 * memcache 封装类
 */
namespace bee\core;

use bee\App;

class BeeMemcache
{
    /**
     * 异常处理模式
     */
    const ERR_MODE_EXCEPTION = 'exception';
    /**
     * 警告处理模式
     */
    const ERR_MODE_WARNING = 'warning';
    /**
     * @var \Memcached
     */
    protected $memcache;
    /**
     * 所有连接实例
     * @var BeeMemcache[]
     */
    private static $_instance = [];
    /**
     * 连接标识
     * @var string
     */
    protected $k;
    /**
     * 保存全部配置文件
     * @var array
     */
    protected $memConfig = [];
    /**
     * 连接主机
     * @var string
     */
    protected $host;
    /**
     * 连接端口
     * @var string
     */
    protected $port;
    /**
     * memcached选项
     * @var array
     */
    protected $options = [];
    /**
     * 错误处理模式
     * @var string
     */
    protected $errMode = self::ERR_MODE_WARNING;

    /**
     * 配置文件包含如下参数
     * [
     *  'host' => 'ip',
     *  'port'=> '端口'，
     *  'options' => '选项',
     *  'err_mode' => '错误处理模式'
     * ]
     * BeeMemcache constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $map = [
            'host' => 'host',
            'port' => 'port',
            'err_mode' => 'errMode',
            'options' => 'options'
        ];
        $this->memConfig = $config;

        /* 对象成员赋值 */
        foreach ($map as $key => $row) {
            if (isset($config[$key])) {
                $this->$row = $config[$key];
            }
        }
    }

    /**
     * 获取一个memcache 对象实例。
     * @param $config
     * @return BeeMemcache
     * @throws \Exception
     */
    public static function getInstance($config)
    {
        if (is_string($config)) {
            $config = App::c($config);
        }
        if (!isset($config['port'])) {
            $config['port'] = '11211';
        }
        $pid = intval(getmypid());
        $k = md5($config['host'] . $config['port'] . $pid);
        if (!isset(self::$_instance[$k])) {
            self::$_instance[$k] = null;
            self::$_instance[$k] = new self($config);
            self::$_instance[$k]->k = $k;
        }
        return self::$_instance[$k];
    }

    /**
     * 连接memcache
     * @param bool $force 是否强制重连
     * @return \Memcached
     */
    public function connect($force = false)
    {
        if ($this->memcache !== null && $force == false) {
            return $this->memcache;
        }
        $this->memcache = null;
        $this->memcache = new \Memcached();
        $this->memcache->addServer($this->host, $this->port);
        if (!empty($this->options)) {
            $this->memcache->setOptions($this->options);
        }
        return $this->memcache;
    }

    /**
     * 获取memcached对象
     * @return \Memcached
     */
    public function getMemcache()
    {
        $this->connect();
        return $this->memcache;
    }

    /**
     * 执行memcache指令
     * @param string $cmd 命令名称
     * @param array $params 参数列表
     * @throws \Exception
     * @return mixed
     */
    private function _execForMemcache($cmd, $params = [])
    {
        for ($i = 0; $i < 2; $i++) {
            try {
                $memcache = $this->connect($i);
                $r = call_user_func_array([$memcache, $cmd], $params);
                $errCode = $memcache->getResultCode();
                if ($errCode == \Memcached::RES_NOTFOUND || $errCode == \Memcached::RES_SUCCESS) {
                    return $r;
                } elseif ($errCode == 3) {
                    throw new \MemcachedException("memcache链接失败");
                } else {
                    $errMsg = $memcache->getResultMessage();
                    if ($this->errMode == self::ERR_MODE_EXCEPTION) {
                        throw new \Exception($errMsg);
                    } else {
                        trigger_error($errMsg, E_USER_WARNING);
                    }
                }
                return $r;
            } catch (\MemcachedException $e) {
                if ($i == 0) {
                    continue;
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * @see \Memcached::getResultCode()
     * 获取最后一次操作的错误码
     * @return mixed
     */
    public function getResultCode()
    {
        return $this->_execForMemcache(__FUNCTION__);
    }

    /**
     * @see \Memcached::getResultMessage()
     * 获取最后一次操作的错误消息
     * @return mixed
     */
    public function getResultMessage()
    {
        return $this->_execForMemcache(__FUNCTION__);
    }

    /**
     * 代理执行memcached命令
     * @param string $cmd 命令字符串
     * @return mixed
     */
    public function proxy($cmd)
    {
        $params = preg_split('/\s+/', trim($cmd));
        $first = array_shift($params);
        return $this->_execForMemcache($first, $params);
    }

    /**
     * 获取一个key
     * 返回存储在服务端的元素的值或者在其他情况下返回FALSE。
     * 如果key不存在，Memcached::getResultCode()返回Memcached::RES_NOTFOUND
     *
     * @see \Memcached::get()
     * @param $key
     * @param null|string|array $callback 数据处理的回调函数
     * @param null $cas
     * @return mixed
     */
    public function get($key, $callback =  null, $cas = null)
    {
        return $this->_execForMemcache(__FUNCTION__, [$key, $callback, $cas]);
    }

    /**
     * @see \Memcached::set()
     * @param string $key 存储key
     * @param mixed $value 值，是任何有效的非资源型php类型。非int或string会被序列化。
     * @param int $expire 过期时间，0表示永不过期。单位秒
     * @return bool 成功时返回 TRUE， 或者在失败时返回 FALSE。 如需要则使用 Memcached::getResultCode()
     */
    public function set($key, $value, $expire = 0)
    {
        return $this->_execForMemcache(__FUNCTION__, [$key, $value, $expire]);
    }

    /**
     * @see \Memcached::getStats()
     * 获取服务器统计信息数组
     * @return array
     */
    public function getStats()
    {
        return $this->_execForMemcache(__FUNCTION__);
    }

    /**
     * 关闭所有打开的连接
     * @return bool
     */
    public function quit()
    {
        return $this->_execForMemcache(__FUNCTION__);
    }

    /**
     * @see \Memcached::addServer()
     * 增加指定服务器到服务器池中。此时不会建立与服务端的连接
     * @param string $host 主机
     * @param int $port 端口
     * @param int $weight 此服务器相对于服务器池中所有服务器的权重
     * @return mixed
     */
    public function addServer($host, $port, $weight = 0)
    {
        return $this->_execForMemcache(__FUNCTION__, [$host, $port, $weight]);
    }

    /**
     * see \Memcached::addServers()
     * servers中的每一条都是一个包含主机名，端口以及可选的权重等服务器参数。
     * 此时并不会与这些服务端建立连接
     * @param array $servers
     * @return mixed
     */
    public function addServers($servers)
    {
        return $this->_execForMemcache(__FUNCTION__, [$servers]);
    }

    /**
     * 增加一个key，和set一样。不同的是，如果key存在，返回false。
     * @param $key
     * @param $value
     * @param int $expire
     * @return mixed
     */
    public function add($key, $value, $expire = 0)
    {
        return $this->_execForMemcache(__FUNCTION__, [$key, $value, $expire]);
    }

    /**
     * @see \Memcached::delete()
     * 从服务端删除key对应的元素
     * @param string $key
     * @param int $time 如果为0，立即删除
     * @return mixed
     */
    public function delete($key, $time = 0)
    {
        return $this->_execForMemcache(__FUNCTION__, [$key, $time]);
    }

    /**
     * @see \Memcached::flush()
     * 作废缓存中的所有元素，flush不会 真正的释放已有元素的内存， 而是逐渐的存入新元素重用那些内存。
     * @param int $delay 在作废所有元素之前等待的时间（单位秒）
     * @return mixed
     */
    public function flush($delay = 0)
    {
        return $this->_execForMemcache(__FUNCTION__, [$delay]);
    }

    /**
     * @see \Memcached::increment()
     * 增加数值元素的值，如果元素的值不是数值类型，将其作为0处理。
     * @param string $key 存储key
     * @param int $value 值
     * @param int $init 如果key不存在，初始化的值
     * @param int $expire 过期时间
     * @return int|bool 返回当前key的值。
     */
    public function increment($key, $value = 1, $init = null, $expire = 0)
    {
        if ($init === null) {
            $init = $value;
        }
        return $this->_execForMemcache(__FUNCTION__, [$key, $value, $init, $expire]);
    }

    /**
     * @see \Memcached::decrement()
     * 减少数值元素的值，如果元素的值不是数值类型，将其作为0处理。
     * @param string $key 存储key
     * @param int $value 值
     * @param int $init 如果key不存在，初始化的值
     * @param int $expire 过期时间
     * @return int|bool 返回当前key的值。
     */
    public function decrement($key, $value = 1, $init = null, $expire = 0)
    {
        if ($init === null) {
            $init = $value;
        }
        return $this->_execForMemcache(__FUNCTION__, [$key, $value, $init, $expire]);
    }

    /**
     * @see \Memcached::getOption()
     * 这个方法返回option指定的Memcached选项的值
     * @param int $option Memcached::OPT_*系列常量中的一个
     * @return mixed
     */
    public function getOption($option)
    {
        return $this->_execForMemcache(__FUNCTION__, [$option]);
    }

    /**
     * @see \Memcached::setOption()
     * 设置memcached选项
     * @param $option
     * @param $value
     * @return mixed
     */
    public function setOption($option, $value)
    {
        return $this->_execForMemcache(__FUNCTION__, [$option, $value]);
    }

    /**
     * @see \Memcached::setOptions()
     * 设置属性
     * @param $options
     * @return mixed
     */
    public function setOptions($options)
    {
        return $this->_execForMemcache(__FUNCTION__, [$options]);
    }

    /**
     * 获取主机
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * 获取端口
     * @return string
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * 获取配置项
     * @param null $key
     * @return array
     */
    public function getConfig($key = null)
    {
        if ($key === null) {
            return $this->memConfig[$key];
        } else {
            return $this->memConfig;
        }
    }

    /**
     * 获取当前错误处理方式
     * @return string
     */
    public function getErrMode()
    {
        return $this->errMode;
    }
}