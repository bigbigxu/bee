<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/11/25
 * Time: 11:12
 * cache接口。只是一个可选项
 */
namespace bee\cache;
interface Cache
{
    public function get($key);
    public function set($key, $value, $timeout);
    public function exists($key);
    public function ttl($key);
    public function gc($force = false, $expiredOnly = true);
}