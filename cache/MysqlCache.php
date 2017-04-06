<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/3/30
 * Time: 11:30
 * 基于mysql 的缓存系统
 * mysql 表包含如下3个字段
 * id varchar(64) 组件，
 * data text 数据
 * expire int 过期时间
 */
namespace bee\cache;
use bee\core\Component;

class MysqlCache extends Component implements ICache
{
    /**
     * 使用的redis组件
     * @var string|\CoreRedis
     */
    public $db = 'db';
    /**
     * cache使用的表
     * @var string
     */
    public $table = 'bee_cache';
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
    public $expire = 3600;
    /**
     * 版本号，用于刷新cache
     * @var int
     */
    public $version = 0;
    /**
     * 过期文件回收概率。最大为 1000000
     * @var int
     */
    public $gcProbability = 10;

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
     * 获取db组件
     * @return \CoreMysql
     */
    public function getDb()
    {
        return \App::s()->sure($this->db);
    }

    /**
     * 获取一个key
     * @param $key
     * @return bool|mixed
     */
    public function get($key)
    {
        $key = $this->buildKey($key);
        $res = $this->getDb()
            ->from($this->table)
            ->andFilter('id', '>=', $key)
            ->andFilter('expire', '>', time())
            ->one();
        if ($res == false) {
            return false;
        }
        $value = $res['data'];
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
        $data = [
            'id' => $key,
            'data' => $value,
            'expire' => time() + $this->getExpire($expire)
        ];
        $flag = $this->getDb()
            ->from($this->table)
            ->upsert($data, ['data', 'expire']);
        if ($flag) {
            $this->gc();
        }
        return (bool)$flag;
    }

    /**
     * 获取过期时间
     * @param $key
     * @return int
     */
    public function ttl($key)
    {
        $key = $this->buildKey($key);
        $res = $this->getDb()
            ->from($this->table)
            ->findById($key);
        if ($res == false) {
            return 0;
        } else {
            return time() - $res['expire'];
        }
    }

    /**
     * 判断一个key 是否存在
     * @param $key
     * @return bool
     */
    public function exists($key)
    {
        $key = $this->buildKey($key);
        $key = $this->buildKey($key);
        $res = $this->getDb()
            ->from($this->table)
            ->andFilter('id', '>=', $key)
            ->andFilter('expire', '>', time())
            ->one();
        return (bool)$res;
    }

    /**
     * 垃圾回收
     * @param bool $force
     * @param bool $expiredOnly
     */
    public function gc($force = false, $expiredOnly = true)
    {
        if ($force || mt_rand(0, 1000000) < $this->gcProbability) {
            $this->getDb()
                ->from($this->table)
                ->andFilter('expire', '<' , time())
                ->delete();
        }
    }
}