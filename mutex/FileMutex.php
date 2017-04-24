<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/3/28
 * Time: 17:40
 * 基于文件的互斥锁
 *
 * 文件锁值能用于单服务器
 */
namespace bee\mutex;
use bee\core\TComponent;

class FileMutex implements IMutex
{
    use TComponent;
    /**
     * 锁文件保存位置
     * @var string
     */
    protected $mutexPath = '';
    /**
     * 文件权限
     * @var int
     */
    protected $fileMode = 0755;
    /**
     * 目录权限
     * @var int
     */
    protected $dirMode = 0755;

    /**
     * 锁的文件前缀
     * @var string
     */
    protected $prefix = 'bee_lock_';

    /**
     * 锁定的文件
     * 包含3个元素的二为维数组
     * [
     *  'fp' => '文件描述符',
     *  'path' => '文件名称',
     *  'name' => '原来的key'
     * ]
     * @var array
     */
    private $_locks = [];
    protected $autoRelease = true;

    public function init()
    {
        if ($this->autoRelease) {
            register_shutdown_function(function () {
                foreach ($this->_locks as $row) {
                    $this->release($row['name']);
                }
            });
        }
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
     * 申请锁
     * @param string|array $name 锁名称
     * @param int $timeout 超时时间
     * @return bool 获取成功返回true
     */
    public function acquire($name, $timeout = 0)
    {
        $key = $this->getLockName($name);
        $path = "{$this->mutexPath}/{$key}.lock";
        $dir = dirname($path);
        if (@!is_dir($dir)) {
            @mkdir($dir, $this->dirMode, true);
        }
        $fp = fopen($path, 'w+');
        if ($fp === false) { /* 文件打开失败 */
            return false;
        }
        if ($this->fileMode !== null) {
            @chmod($path, $this->fileMode);
        }
        $waitTime = 0;
        while (!flock($fp, LOCK_EX | LOCK_NB)) {
            $waitTime++;
            if ($waitTime > $timeout) {
                fclose($fp);
                return false;
            }
            sleep(1);
        }
        $this->_locks[$key] = [
            'fp' => $fp,
            'path' => $path,
            'name' => $name
        ];
        return true;
    }

    /**
     * 释放锁
     * @param $name
     * @return bool
     */
    public function release($name)
    {
        $key = $this->getLockName($name);
        /* 锁不存在*/
        if ($this->_locks[$key] === null) {
            return false;
        }
        if (flock($this->_locks[$key]['fp'], LOCK_UN) == false) {
            return false;
        } else {
            fclose($this->_locks[$key]['fp']);
            unlink($this->_locks[$key]['path']);
            unset($this->_locks[$key]);
            return true;
        }
    }

    /**
     * 获取当前所有的锁
     * @return array
     */
    public function getAllLocks()
    {
        return $this->_locks;
    }
}