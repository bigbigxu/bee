<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/3/29
 * Time: 11:46
 */
namespace bee\mutex;
use bee\core\TComponent;

class RedisMutex implements IMutex
{
    use TComponent;
    /**
     * redis 组件
     * @var string|\bee\core\BeeRedis
     */
    protected $redis = 'redis';
    /**
     * 最大锁定时间
     * @var int
     */
    protected $expire = 30;
    /**
     * 前缀
     * @var string
     */
    protected $prefix = 'bee_lock_';
    /**
     * 进程结束后是否自动释放锁
     * @var bool
     */
    protected $autoRelease = true;

    /**
     * 当前锁住的key
     * @var array
     */
    private $_locks = [];

    public function init()
    {
        if ($this->autoRelease) {
            register_shutdown_function(function() {
                foreach ($this->_locks as $name) {
                    $this->release($name);
                }
            });
        }
        $this->redis = $this->sureComponent($this->redis);
    }

    /**
     * 获取一个锁的文件名
     * @param $name
     * @return string
     */
    protected function getLockName($name)
    {
        if (!is_array($name)) {
            $arr = [
                __CLASS__,
                $name
            ];
        } else {
            $arr = $name;
        }
        return $this->prefix . md5(json_encode($arr));
    }

    /**
     * 申请一个锁。使用setnx方法，设置redis一个key来当锁。
     * @param array|string $name
     * @param int $timeout
     * @return bool
     */
    public function acquire($name, $timeout = 0)
    {
        $key = $this->getLockName($name);
        $waitTime = 0;
        while (!$this->redis->setnx($key, 1)) {
            $waitTime++;
            if ($waitTime > $timeout) {
                return false;
            }
            sleep(1);
        }
        $this->redis->expire($key, $this->expire);
        $this->_locks[$key] = $name;
        return true;
    }

    /**
     * 释放一个锁，删除redis对应的key
     * @param $name
     * @return bool
     */
    public function release($name)
    {
        $key = $this->getLockName($name);
        if ($this->_locks[$key] === null) {
            return false;
        }
        if ($this->redis->del($key)) {
            unset($this->_locks[$key]);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取当前所有锁
     * @return array
     */
    public function getAllLocks()
    {
        return $this->_locks;
    }
}