<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/3/29
 * Time: 10:44
 * 使用mysql get_lock(str,timeout) 模拟记录锁定
 * 设法使用字符串str 给定的名字得到一个锁， 超时为timeout 秒。若成功得到锁，则返回 1，
 * 若操作超时则返回0 (例如,由于另一个客户端已提前封锁了这个名字 ),
 * 若发生错误则返回NULL (诸如缺乏记忆或线程mysqladmin kill 被断开 )。
 * 假如你有一个用GET_LOCK()得到的锁，当你执行RELEASE_LOCK()或你的连接断开(正常或非正常)时，这个锁就会解除。
 *
 * get_lock 锁是会话级别。同一会话，多次get_lock 都是真。
 * 当连接断开的时候，锁会自动回收
 */
namespace bee\mutex;
use bee\core\Component;

class MysqlMutex extends Component implements IMutex
{
    /**
     * 使用的db组件
     * @var string|\CoreMysql
     */
    public $db = 'db';
    /**
     * 前缀
     * @var string
     */
    public $prefix = 'bee_lock_';
    /**
     * 当前锁
     * @var array
     */
    private $_locks = [];

    /**
     * 获取DB组件
     * @return \CoreMysql
     */
    public function getDb()
    {
        return \App::s()->sure($this->db);
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
     * 申请一个锁
     * @param array|string $name
     * @param int $timeout
     * @return bool
     */
    public function acquire($name, $timeout = 0)
    {
        $key = $this->getLockName($name);
        $flag = $this->getDb()->acquireLock($name, $timeout);
        if ($flag) {
            $this->_locks[$key] = $name;
        }
        return $flag;
    }

    /**
     * 释放一个锁
     * @param $name
     * @return bool
     */
    public function release($name)
    {
        $key = $this->getLockName($name);
        $flag = $this->getDb()->releaseLock($key);
        if ($flag) {
            unset($this->_locks[$key]);
        }
        return $flag;
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