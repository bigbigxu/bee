<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/11/25
 * Time: 11:12
 * cache接口。只是一个可选项
 */
namespace bee\cache;
interface ICache
{
    /**
     * 获取一个key
     * @param $key
     * @return mixed
     */
    public function get($key);

    /**
     * 设置一个key
     * @param string|array $key 缓存的key
     * @param mixed $value 值
     * @param int $timeout 过期时间
     * @return mixed
     */
    public function set($key, $value, $timeout = null);

    /**
     * 判断一个key是否存在
     * @param $key
     * @return mixed
     */
    public function exists($key);

    /**
     * 获取一个key的剩余过期时间
     * @param $key
     * @return mixed
     */
    public function ttl($key);

    /**
     * 垃圾回收
     * @param bool $force 是否强制回收
     * @param bool $expiredOnly 是服只回收过期的key
     */
    public function gc($force = false, $expiredOnly = true);
}