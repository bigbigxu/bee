<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/7/22
 * Time: 9:48
 */

namespace bee\cache;

use bee\core\TComponent;

class RedisAnalysis
{
    use TComponent;
    /**
     * @var \bee\core\BeeRedis
     */
    public $redis;
    public $keyArr; //随机key
    public $keyReg = '/^[0-9a-zA-Z]+[\-_|:]{1}/';//key前缀匹配正则表达式

    public function init()
    {
        $this->redis = $this->sureComponent($this->redis);
    }

    /**
     * 设定key前缀分析表达式
     * @param $reg
     * @return $this
     */
    public function setKeyReg($reg)
    {
        $this->keyReg = $reg;
        return $this;
    }

    /**
     * 设置分析的key
     * @param int $num
     * @return $this
     */
    public function setRandomKey($num = 500)
    {
        for($i = 0; $i < $num; $i++) {
            $this->keyArr[] = $this->redis->randomKey();
        }
        return $this;
    }

    /**
     * 分析redis中各个key所占比例
     * @return array
     */
    public function keyRate()
    {
        $rate = array();
        foreach($this->keyArr as $row) {
            $index = $this->getIndex($row);
            $rate[$index] = intval($rate[$index]) + 1;
        }

        $res = array();
        arsort($rate);
        $total = array_sum($rate);
        foreach($rate as $key => $row) {
            $res[$key] = round($row/$total, 2);
        }
        arsort($res);
        return $res;
    }

    public function keyMemory()
    {
        $memory = array();
        foreach($this->keyArr as $row) {
            $index = $this->getIndex($row);
            $show = $this->showKey($row);
            $memory[$index] = intval($memory[$index]) + $show['size'];
        }
        $res = array();
        arsort($memory);
        $total = array_sum($memory);
        foreach($memory as $key => $row) {
            $res[$key] = round($row/$total, 2);
        }
        return $res;
    }

    /**
     * 分析任意一个key
     * @param $key
     * @return array|int|mixed
     */
    public function showKey($key)
    {
        $type = $this->redis->type($key);
        $r = array();
        switch($type) {
            case \REDIS::REDIS_STRING :
                $r = $this->showString($key);
                break;
            case \REDIS::REDIS_LIST :
                $r = $this->showList($key);
                break;
            case \REDIS::REDIS_SET :
                $r = $this->showSet($key);
                break;
            case \REDIS::REDIS_ZSET :
                $r = $this->showZset($key);
                break;
            case \REDIS::REDIS_HASH :
                $r = $this->showHash($key);
                break;
        }
        return $r;
    }

    /**
     * 根据指定正则表达式得到key的前缀
     * @param $key
     * @param null $reg
     * @return string
     */
    public function getIndex($key, $reg = null)
    {
        $reg = $reg === null ? $this->keyReg : $reg;
        if(preg_match($reg, $key, $ma) != false) {
            $index = $ma[0];
        } else {
            $index = $key;
        }
        return $index;
    }

    public function info($configFile)
    {
        $r = array();
        $info = $this->redis->info();
        $r['use_memory'] = $info['used_memory_human']; //使用的内存数量。
        $r['keys'] = $info['db0'];
        $str = file_get_contents($configFile); //得到配置文件内容
        preg_match('/\s*maxmemory\s*(\d+)/', $str, $ma);
        $r['max_memory'] = $ma[1];
        $r['free_memory'] = $ma[1] - $r['use_memory'];

        preg_match_all('/\s*(save\s*\d+\s*\d+)/', $str, $ma);
        $r['save'] = $ma[1]; //得到保存配置
    }

    /**
     * 计算字符串类型字节长度，仅做估值
     * @param $key
     * @return int
     */
    public function showString($key)
    {
        $r['value'] = $this->redis->get($key);
        $r['size'] =  $this->sizeof($r['value']);
        return $r;
    }

    /**
     * list数据类型分析
     * @param $key
     * @return array
     */
    public function showList($key)
    {
        $r = array();
        $r['num'] = $this->redis->lLen($key);
        $r['size'] = 0;
        for($i = 0; $i < $r['num']; $i++) {
            $v = $this->redis->lIndex($key, $i);
            $r['size'] +=  $this->sizeof($v);
        }
        return $r;
    }

    /**
     * 无序集合
     * @param $key
     * @return mixed
     */
    public function showSet($key)
    {
        $r['num'] = $this->redis->scard($key);
        $r['size'] = 0;
        $members = $this->redis->sMembers($key);
        foreach ($members as $row) {
            $r['size'] += $this->sizeof($row);
        }
        return $r;
    }

    /**
     * 有序集合
     * @param $key
     * @return mixed
     */
    public function showZset($key)
    {
        $r['num'] = $this->redis->zCard($key);
        $r['size'] = 0;
        $members = $this->redis->zRange($key, 0, -1);
        foreach ($members as $row) {
            $r['size'] += $this->sizeof($row);
        }
        return $r;
    }

    /**
     * hash表
     * @param $key
     * @return mixed
     */
    public function showHash($key)
    {
        $r['num'] = $this->redis->hLen($key);
        $r['size'] = 0;
        $members = $this->redis->hGetAll($key);
        foreach ($members as $row) {
            $r['size'] += $this->sizeof($row);
        }
        return $r;
    }

    public function sizeof($string)
    {
        $len = strlen($string);
        return $len;
    }
}