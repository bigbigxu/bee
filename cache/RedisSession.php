<?php
/**
 * 使用redis来保存session
 * 建议session使用单一的数据来保存
 * @author xuen
 */

namespace bee\cache;

use bee\core\BeeRedis;
use bee\core\TComponent;

class RedisSession
{
    use TComponent;
    /**
     * session过期时间
     * @var int
     */
    protected $expire = 1440;
    /**
     * session前缀
     * @var string
     */
    protected $prefix = 'sess_';
    /**
     * 使用的redis组件
     * @var BeeRedis
     */
    protected $redis = 'redis';

    public function init()
    {
        session_set_save_handler(
            array($this, 'open'),
            array($this, 'close'),
            array($this, 'read'),
            array($this, 'write'),
            array($this, 'destroy'),
            array($this, 'gc')
        );
        $this->sureComponent($this->redis);
    }


    /**
     * 开启session
     * @param $sessId
     * @return RedisSession
     */
    public function sessionStart($sessId = null)
    {
        if ($_COOKIE[session_name()]) {
            $id = $_COOKIE[session_name()];
        } elseif ($sessId !== null) {
            $id = $this->prefix . $sessId;
        } else {
            $id = $this->prefix . md5(uniqid() . time() . 'redis_session');
        }
        session_id($id);
        session_start();
        return $this;
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
        return $this->redis->get($sessId);
    }

    /**
     * 写入session
     * @param string $sessId
     * @param string $data 其它要保存的session数据
     * @return bool
     */
    public function write($sessId, $data)
    {
        $this->redis->setex($sessId, $this->expire, $data);
        return true;
    }

    /**
     * 删除session
     * @param string $sessId
     * @return bool
     */
    public function destroy($sessId)
    {
        return $this->redis->del($sessId);
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