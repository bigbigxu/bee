<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/3/22
 * Time: 11:29
 */
namespace bee\mutex;
interface IMutex
{
    /**
     * @param string|array $name 锁名称
     * @param int $timeout 获取锁的超时时间
     * @return bool 获取成功返回true
     */
    public function acquire($name, $timeout = 0);

    /**
     * 释放锁
     * @param $name
     * @return mixed
     */
    public function release($name);
}