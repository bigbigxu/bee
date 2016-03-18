<?php
/**
 * 使用redis来保存session
 * 建议session使用单一的数据来保存
 * @author xuen
 */
class RedisSession extends Object
{
    protected $expire = 1440; //session过期时间
    protected $prefix = 'sess_'; //session前缀
    protected $sessId = '';
    protected $maxExpire = 2592000; //最大过期时间

    public function __construct($config)
    {
        session_set_save_handler(
            array($this, 'open'),
            array($this, 'close'),
            array($this, 'read'),
            array($this, 'write'),
            array($this, 'destroy'),
            array($this, 'gc')
        );
        parent::__construct($config);
    }

    /**
     * 开启session
     * @param $sessId
     * @return RedisSession
     */
    public function sessionStart($sessId = null)
    {
        if ($_COOKIE[session_name()]) {
            $this->sessId = $_COOKIE[session_name()];
        } elseif ($sessId !== null) {
            $this->sessId = $this->prefix . $sessId; //使用指定的session_id
        } else {
            $id = md5(uniqid() . time() . 'redis_session');
            $this->sessId = $this->prefix . $id; //使用系统session_id
        }
        session_id($this->sessId);
        session_start();
        return $this;
    }

    /**
     * 得到一个对象实例。子类必须重载此方法
     * @param array $config 对象配置数组。key为对象成员名，value为成员性值
     * @param string $name
     * @return static
     */
    public static function getInstance($config = array(), $name = __CLASS__)
    {
        return parent::getInstance($config, $name);
    }

    /**
     * 开启session
     * @param string $savePath
     * @param string $sessName
     * @return true;
     */
    public function open($savePath, $sessName)
    {
        return true;
    }

    /**
     * 关闭session
     * @return boolean
     */
    public function close()
    {
        return true;
    }

    /**
     * 读取session
     * @param string $sessId
     * @return array
     */
    public function read($sessId)
    {
        return $this->redis()->get($sessId);
    }

    /**
     * 写入session
     * @param string $sessId
     * @param string $data 其它要保存的session数据
     * @return bool
     */
    public function write($sessId, $data)
    {
        $this->redis()->setex($sessId, $this->expire, $data);
        return true;
    }

    /**
     * 删除session
     * @param string $sessId
     * @return bool
     */
    public function destroy($sessId)
    {
        return $this->redis()->del($sessId);
    }

    /**
     * 垃圾回收，由于设置过期时间，由redis自动回收。
     * @param int $ttl
     * @return boolean
     */
    public function gc($ttl)
    {
        return true;
    }
}