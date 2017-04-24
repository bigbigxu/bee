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
    public $redis = 'redis';
    /**
     * key 前缀
     * @var string
     */
    public $prefix = 'bee_cache_';
    /**
     * 数据结构化函数
     * @var string
     */
    public $serializer;
    /**
     * 默认的过期时间
     * @var null
     */
    public $expire = 0;
    /**
     * 版本号，用于刷新cache
     * @var int
     */
    public $version = 0;

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
     * 获取redis组件
     * @return \bee\core\BeeRedis
     */
    public function getRedis()
    {
        return \bee\App::s()->sure($this->redis);
    }

    /**
     * 判断key 是否存在
     * @param $key
     * @return bool
     */
    public function exists($key)
    {
        $key = $this->buildKey($key);
        return $this->getRedis()->exists($key);
    }

    /**
     * 获取一个key
     * @param $key
     * @return bool|mixed|string
     */
    public function get($key)
    {
        $key = $this->buildKey($key);
        $value = $this->getRedis()->get($key);
        if ($this->serializer === null) {
            $value = json_decode($value, true);
        } else {
            $value = call_user_func($this->serializer[1], $value);
        }
        return $value;
    }

    /**
     * 获取一个key
     * @param array|string $key
     * @param mixed $value
     * @param null $expire
     * @return bool
     */
    public function set($key, $value, $expire = null)
    {
        $expire = $expire ?: $this->expire;
        if ($expire <= 0) {
            $expire = 31536000; /* 1年 */
        }
        $key = $this->buildKey($key);
        if ($this->serializer === null) {
            $value = json_encode($value);
        } else {
            $value = call_user_func($this->serializer[0], $value);
        }
        return $this->getRedis()->setex($key, $this->getExpire($expire), $value);
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
        return $this->getRedis()->ttl($key);
    }

    public function del($key)
    {
        $key = $this->buildKey($key);
        return $this->getRedis()->del($key);
    }
}