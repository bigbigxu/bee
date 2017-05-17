<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/3/30
 * Time: 10:49
 * 基于redis 的缓存系统
 */

namespace bee\cache;

use bee\core\TComponent;

class RedisCache implements ICache
{
    use TComponent;
    /**
     * 使用的redis组件
     * @var string|\bee\core\BeeRedis
     */
    protected $redis = 'redis';
    /**
     * key 前缀
     * @var string
     */
    protected $prefix = 'bee_cache_';
    /**
     * 数据结构化函数
     * @var string
     */
    protected $serializer;
    /**
     * 默认的过期时间
     * @var null
     */
    protected $expire = 3600;
    /**
     * 版本号，用于刷新cache
     * @var int
     */
    protected $version = 0;

    public function init()
    {
        $this->sureComponent($this->redis);
    }

    /**
     * 创建一个key
     * @param $key
     * @return string
     */
    public function buildKey($key)
    {
        if (!is_array($key)) {
            $arr = [
                __CLASS__,
                $key,
            ];
        } else {
            $arr = $key;
        }
        return $this->prefix . md5(json_encode($arr) . $this->version);
    }

    /**
     * 获取一个过期时间
     * @param $expire
     * @return int|null
     */
    public function getExpire($expire)
    {
        $expire = $expire ?: $this->expire;
        if ($expire <= 0) {
            $expire = 31536000; /* 1年 */
        }
        return $expire;
    }

    /**
     * 判断key 是否存在
     * @param $key
     * @return bool
     */
    public function exists($key)
    {
        $key = $this->buildKey($key);
        return $this->redis->exists($key);
    }

    /**
     * 获取一个key
     * @param $key
     * @return bool|mixed|string
     */
    public function get($key)
    {
        $key = $this->buildKey($key);
        $value = $this->redis->get($key);
        if ($value === false) { /* key不存在，表示已经过期 */
            return false;
        }
        if ($this->serializer === null) {
            $value = json_decode($value, true);
        } else {
            $value = call_user_func($this->serializer[1], $value);
        }
        return $value;
    }

    /**
     * 设置一个key
     * @param array|string $key
     * @param mixed $value
     * @param null $expire
     * @return bool
     */
    public function set($key, $value, $expire = null)
    {
        $key = $this->buildKey($key);
        if ($this->serializer === null) {
            $value = json_encode($value);
        } else {
            $value = call_user_func($this->serializer[0], $value);
        }
        return $this->redis->setex($key, $this->getExpire($expire), $value);
    }

    public function gc($force = false, $expiredOnly = true)
    {
    }

    /**
     * 获取key过期时间
     * @param $key
     * @return bool
     */
    public function ttl($key)
    {
        $key = $this->buildKey($key);
        return $this->redis->ttl($key);
    }

    public function del($key)
    {
        $key = $this->buildKey($key);
        return $this->redis->del($key);
    }
}