<?php
/**
 * redis操作类
 * 说明，任何为false的串，存在redis中都是空串。
 * 只有在key不存在时，才会返回false。
 * 这点可用于防止缓存穿透
 * @author xuen
 */

namespace bee\core;

use bee\App;
use Exception;
use Redis;
use RedisException;

class BeeRedis
{
    /**
     * 异常处理模式
     */
    const ERR_MODE_EXCEPTION = 'exception';
    /**
     * 警告处理模式
     */
    const ERR_MODE_WARNING = 'warning';
    /**
     * @var Redis
     */
    protected $redis;
    /**
     * 当前数据库ID号
     * @var int
     */
    protected $dbId = 0;
    /**
     * 当前权限认证码
     * @var string
     */
    protected $auth;
    /**
     * 所有连接实例
     * @var BeeRedis[]
     */
    static private $_instance = [];
    /**
     * 连接标识户
     * @var string
     */
    protected $k;
    /**
     * 默认配置文件数组
     * @var array
     */
    protected $config = [];
    /**
     * 连接主机
     * @var string
     */
    protected $host;
    /**
     * 连接端口
     * @var string
     */
    protected $port = 6379;
    /**
     * 连接超时时间
     * @var float
     */
    protected $timeout = 30;
    /**
     * 错误处理模式
     * @var string
     */
    protected $errMode = self::ERR_MODE_WARNING;

    /**
     * 配置文件包含如下参数
     * [
     *  'host' => 'ip',
     *  'port'=> '端口'，
     *  'auth' => '密码',
     *  'timeout' => '连接超时时间',
     *  'dbId' => '数据库ID',
     *  'errMode' => '错误处理模式'
     * ]
     * CoreRedis constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
        foreach ($config as $key => $row) {
            $this->$key = $row;
        }
    }

    /**
     * 连接redis
     * @param bool $force 是否强制重连
     * @return Redis
     */
    public function connect($force = false)
    {
        if ($this->redis !== null && $force == false) {
            return $this->redis;
        }
        $this->redis = null;
        $this->redis = new Redis();
        $this->redis->connect($this->host, $this->port, $this->timeout);
        if ($this->dbId != 0) {
            $this->redis->select($this->dbId);
        }
        if ($this->auth) {
            $this->redis->auth($this->auth);
        }
        return $this->redis;
    }

    /**
     * 得到实例化的对象.
     * 为每个数据库建立一个连接
     * 如果连接超时，将会重新建立一个连接
     * @param array $config
     * @return BeeRedis
     */
    public static function getInstance($config)
    {
        if (!is_array($config)) {
            $config = App::c($config);
        }
        if (!isset($config['dbId'])) {
            $config['dbId'] = 0;
        }
        $pid = intval(getmypid());
        $k = md5($config['host'] . $config['port'] . $config['dbId'] . $pid);

        if (!isset(self::$_instance[$k])) {
            self::$_instance[$k] = null;
            self::$_instance[$k] = new self($config);
            self::$_instance[$k]->k = $k;
        }

        return self::$_instance[$k];
    }

    /**
     * 执行原生的redis操作
     * @return Redis
     */
    public function getRedis()
    {
        $this->connect();
        return $this->redis;
    }

    /*****************hash表操作函数*******************/

    /**
     * @see Redis::hGet()
     * 得到hash表中一个字段的值
     * @param string $key 缓存key
     * @param string $field 字段
     * @return string|false
     */
    public function hGet($key, $field)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $field]);
    }

    /**
     * @see Redis::hSet()
     * 为hash表设定一个字段的值
     * @param string $key 缓存key
     * @param string $field 字段
     * @param string $value 值。
     * @return bool
     */
    public function hSet($key, $field, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $field, $value]);
    }

    /**
     * @see Redis::hExists()
     * 判断hash表中，指定field是不是存在
     * @param string $key 缓存key
     * @param string $field 字段
     * @return bool
     */
    public function hExists($key, $field)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $field]);
    }

    /**
     * @see Redis::hDel()
     * 删除hash表中指定字段 ,支持批量删除
     * @param string $key 缓存key
     * @param string $hashKey1 字段
     * @param string|null $hashKey2
     * @param string|null $hashKeyN
     * @return int 删除字段的数量
     */
    public function hDel($key, $hashKey1, $hashKey2 = null, $hashKeyN = null)
    {
        return $this->_execForRedis(__FUNCTION__, func_get_args());
    }

    /**
     * @see Redis::hLen()
     * 返回hash表元素个数
     * @param string $key 缓存key
     * @return int|bool，key不存在返回false
     */
    public function hLen($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::hSetNx
     * 为hash表设定一个字段的值,如果字段存在，返回false
     * @param string $key 缓存key
     * @param string $field 字段
     * @param string $value 值。
     * @return bool
     */
    public function hSetNx($key, $field, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $field, $value]);
    }

    /**
     * @see Redis::hMset()
     * 为hash表多个字段设定值。
     * @param string $key
     * @param array $value
     * @return array|bool
     */
    public function hMset($key, $value)
    {
        if (!is_array($value)) {
            return false;
        }
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::hMget()
     * 为hash表多个字段设定值。
     * @param string $key
     * @param array|string $field string以','号分隔字段
     * @return array|bool
     */
    public function hMget($key, $field)
    {
        if (!is_array($field)) {
            $field = explode(',', $field);
        }
        return $this->_execForRedis(__FUNCTION__, [$key, $field]);
    }

    /**
     * @see Redis::hIncrBy()
     * 为hash表设这累加，可以负数
     * @param string $key
     * @param int $field
     * @param int $value
     * @return bool
     */
    public function hIncrBy($key, $field, $value = 1)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $field, (int)$value]);
    }

    /**
     * @see Redis::hKeys()
     * 返回所有hash表的所有字段
     * @param string $key
     * @return array|bool
     */
    public function hKeys($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::hVals()
     * 返回所有hash表的字段值，为一个索引数组
     * @param string $key
     * @return array|bool
     */
    public function hVals($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::hgetAll()
     * 返回所有hash表的字段值，为一个关联数组
     * @param string $key
     * @return array|bool
     */
    public function hGetAll($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /*********************有序集合操作*********************/

    /**
     * @see Redis::zAdd()
     * 给当前集合添加一个元素
     * 如果value已经存在，会更新order的值。
     * @param string $key
     * @param string $score 序号
     * @param string $value 值
     * @return bool
     */
    public function zAdd($key, $score, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $score, $value]);
    }

    /**
     * @see Redis::zinCrBy()
     * 给$value成员的order值，增加$num,可以为负数
     * @param string $key
     * @param string $num 序号
     * @param string $value 值
     * @return float 返回新的order
     */
    public function zinCrBy($key, $num, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $num, $value]);
    }

    /**
     * @see Redis::zRem()
     * 删除值为value的元素
     * @param string $key
     * @param string $value
     * @return bool
     */
    public function zRem($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::zRemRangeByRank()
     * 删除指定排名之间的数据。
     * 下标参数 start 和 end 都以 0 为底，也就是说，以 0 表示有序集第一个成员，
     * 以 1 表示有序集第二个成员，以此类推。
     * 你也可以使用负数下标，以 -1 表示最后一个成员， -2 表示倒数第二个成员，以此类推。
     * rank排名就是倒序
     * @param $key
     * @param $start
     * @param $end
     * @return int
     */
    public function zRemRangeByRank($key, $start, $end = -1)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $start, $end]);
    }

    /**
     * 将集合倒序排名后，删除提定排名内的成员。
     * @param $key
     * @param $start
     * @param $end
     * @return int
     */
    public function zRevRemRangeByRank($key, $start, $end = -1)
    {
        $card = $this->zCard($key);
        if ($end < 0) {
            $end = $card - abs($end);
        }
        if ($start < 0) {
            $start = $card - abs($start);
        }
        $realEnd = $card - $start - 1;
        $realStart = $card - $end - 1;

        if ($realStart < 0 || $realEnd < 0) {
            return 0;
        } else {
            return $this->zRemRangeByRank($key, $realStart, $realEnd);
        }
    }

    /**
     * @see Redis::zRange()
     * 集合以order递增排列后，0表示第一个元素，-1表示最后一个元素
     * @param string $key
     * @param int $start
     * @param int $end
     * @param $withscore
     * @return array|bool、
     * @example
     * $redis->zRange('key1', 0, -1); // array('val0', 'val2', 'val10')
     * // with scores
     * $redis->zRange('key1', 0, -1, true); // array('val0' => 0, 'val2' => 2, 'val10' => 10)
     */
    public function zRange($key, $start, $end, $withscore = null)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $start, $end, $withscore]);
    }

    /**
     * @see Redis::zRevRange()
     * 集合以order递减排列后，0表示第一个元素，-1表示最后一个元素
     * 成员的位置按 score 值递减
     * @param string $key
     * @param int $start
     * @param int $end
     * @param $withscore
     * @return array|bool 返回的数组key为值,vaule为score
     */
    public function zRevRange($key, $start, $end, $withscore = null)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $start, $end, $withscore]);
    }

    /**
     * 根据分页请求得到集合中的数据
     * @param $key
     * @param $page
     * @param $pageSize
     * @param null $withscore
     * @return array|bool
     */
    public function zRevRangeByPage($key, $page, $pageSize, $withscore = null)
    {
        $page = $this->page($page, $pageSize);
        $start = $page['offset'];
        $end = $start + $page['limit'] - 1;
        return $this->zRevRange($key, $start, $end, $withscore);
    }

    /**
     * 分页的方法。
     * @param $page
     * @param $pageSize
     * @return array
     */
    public function page($page, $pageSize)
    {
        $pageSize = $pageSize <= 0 ? 20 : $pageSize;
        $page = $page <= 0 ? 1 : $page;
        $offset = ($page - 1) * $pageSize;
        return array(
            'offset' => $offset,
            'limit' => $pageSize
        );
    }

    /**
     * @see Redis::zRangeByScore()
     * 集合以递增排列后，返回指定score之间的元素。
     * min和max可以是-inf和+inf　表示最大值，最小值
     * @param string $key
     * @param mixed $start
     * @param mixed $end
     * @param array $option 参数
     *     withscores=>true，表示数组下标为Order值，默认返回索引数组
     *     limit=>array(0,1) 表示从0开始，取一条记录。
     * @return array|bool
     */
    public function zRangeByScore($key, $start = '-inf', $end = "+inf", $option = [])
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $start, $end, $option]);
    }

    /**
     * @see Redis::zRevRangeByScore()
     * 集合以order递减排列后，返回指定order之间的元素。
     * min和max可以是-inf和+inf　表示最大值，最小值
     * @param string $key
     * @param string $start
     * @param string $end
     * @param array $option 参数
     *     withscores=>true，表示数组下标为Order值，默认返回索引数组
     *     limit=>array(0,1) 表示从0开始，取一条记录。
     * @return array|bool
     */
    public function zRevRangeByScore($key, $start = '-inf', $end = "+inf", $option = array())
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $start, $end, $option]);
    }

    /**
     * @see Redis::zCount()
     * 返回score值在start end之间的数量
     * @param $key
     * @param $start
     * @param $end
     * @return int
     */
    public function zCount($key, $start, $end)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $start, $end]);
    }

    /**
     * @see Redis::zScore()
     * 返回值为value的order值
     * @param $key
     * @param $value
     * @return float
     */
    public function zScore($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::zRank()
     * 返回集合以score递增加排序后，指定成员的排序号，从0开始。
     * @param $key
     * @param $value
     * @return int
     */
    public function zRank($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::zRevRank()
     * 返回集合以score递减排序后，指定成员的排序号，从0开始。
     * @param $key
     * @param $value
     * @return int
     */
    public function zRevRank($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::zRemRangeByScore()
     * 删除集合中，score值在start end之间的元素　包括start end
     * min和max可以是-inf和+inf　表示最大值，最小值
     * @param  $key
     * @param  $start
     * @param  $end
     * @return int 删除成员的数量。
     */
    public function zRemRangeByScore($key, $start, $end)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $start, $end]);
    }

    /**
     * @see Redis::zCard()
     * 返回集合元素个数。
     * @param $key
     * @return int
     */
    public function zCard($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /*********************队列操作命令************************/

    /**
     * @see Redis::rPush()
     * 在队列尾部插入一个元素
     * @param $key
     * @param $value
     * @return int
     */
    public function rPush($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::rPushx()
     * 在队列尾部插入一个元素 如果key不存在，什么也不做
     * @param $key
     * @param $value
     * @return int
     */
    public function rPushx($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::lPush()
     * 在队列头部插入一个元素
     * @param $key
     * @param $value
     * @return int
     */
    public function lPush($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::lPushx()
     * 在队列头插入一个元素
     * @param $key
     * @param $value
     * @return int
     */
    public function lPushx($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::lLen()
     * 返回队列长度
     * @param $key
     * @return int
     */
    public function lLen($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::lLen()
     * @param $key
     * @return int
     */
    public function lSize($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);

    }

    /**
     * @see Redis::lRange()
     * 返回队列指定区间的元素
     * @param $key
     * @param $start
     * @param $end
     * @return array
     */
    public function lRange($key, $start, $end)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $start, $end]);
    }

    /**
     * @see Redis::lIndex()
     * 返回队列中指定索引的元素
     * @param $key
     * @param $index
     * @return String
     */
    public function lIndex($key, $index)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $index]);
    }

    /**
     * @see Redis::lSet()
     * 设定队列中指定index的值。
     * @param string $key
     * @param int $index
     * @param string $value
     * @return bool
     */
    public function lSet($key, $index, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $index, $value]);
    }

    /**
     * @see Redis::lRem()
     * 删除值为vaule的count个元素
     * PHP-REDIS扩展的数据顺序与命令的顺序不太一样，不知道是不是bug
     * count>0 从尾部开始
     *  >0　从头部开始
     *  =0　删除全部
     * @param string $key
     * @param int $count
     * @param string $value
     * @return int
     */
    public function lRem($key, $count, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $count, $value]);
    }

    /**
     * @see Redis::lPop()
     * 删除并返回队列中的头元素。
     * @param $key
     * @return string
     */
    public function lPop($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::rPop()
     * 删除并返回队列中的尾元素
     * @param $key
     * @return string
     */
    public function rPop($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /*************redis字符串操作命令*****************/

    /**
     * @see Redis::set()
     * 设置一个key
     * @param $key
     * @param $value
     * @return bool
     */
    public function set($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::get()
     * 得到一个key
     * @param $key
     * @return bool|string
     */
    public function get($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::incr()
     * 整数增加值
     * @param string $key
     * @param int $num 增加的值 负数等同于Redis::decr操作
     * @return int 返回增加后的值
     */
    public function incr($key, $num = 1)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $num]);
    }

    /**
     * @see Redis::setex()
     * 设置一个有过期时间的key
     * @param $key
     * @param $expire
     * @param $value
     * @return bool
     */
    public function setex($key, $expire, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $expire, $value]);
    }


    /**
     * @see Redis::setnx()
     * 设置一个key,如果key存在,不做任何操作
     * @param $key
     * @param $value
     * @return bool
     */
    public function setnx($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::mset()
     * 批量设置key
     * @param $arr
     * @return bool
     * @example
     * $redis->mset(array('key0' => 'value0', 'key1' => 'value1'));
     */
    public function mset($arr)
    {
        return $this->_execForRedis(__FUNCTION__, [$arr]);
    }

    /*************redis　无序集合操作命令*****************/

    /**
     * @see Redis::sMembers()
     * 返回集合中所有元素
     * @param $key
     * @return array
     */
    public function sMembers($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::sDiff()
     * 求2个集合的差集
     * @param $key1
     * @param $key2
     * @return array
     */
    public function sDiff($key1, $key2)
    {
        return $this->_execForRedis(__FUNCTION__, [$key1, $key2]);
    }

    /**
     * @see Redis::sAdd()
     * 为集合添加元素
     * @param $key
     * @param $value1
     * @param null $value2
     * @param null $valueN
     * @return int 添加的元素数量
     */
    public function sAdd($key, $value1, $value2 = null, $valueN = null)
    {
        return $this->_execForRedis(__FUNCTION__, func_get_args());
    }

    /**
     * @see Redis::sRandMember()
     * 返回一个随机元素
     * @param $key
     * @return string
     */
    public function sRandMember($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::sContains()
     * 检查集合中是否存在指定的值
     * @param $key
     * @param $value
     * @return bool
     */
    public function sContains($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::sIsMember()
     * 检查集合中是否存在指定的值
     * @param $key
     * @param $value
     * @return bool
     */
    public function sIsMember($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /**
     * @see Redis::scard()
     * 返回无序集合的元素个数
     * @param $key
     * @return int
     */
    public function scard($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::srem()
     * 从集合中删除一个元素
     * @param $key
     * @param $value
     * @return int
     */
    public function srem($key, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $value]);
    }

    /*************redis管理操作命令*****************/

    /**
     * @see Redis::select()
     * 选择数据库
     * @param int $dbId 数据库ID号
     * @return bool
     */
    public function select($dbId)
    {
        $this->dbId = $dbId;
        return $this->_execForRedis(__FUNCTION__, [$dbId]);
    }

    /**
     * @see Redis::flushDB()
     * 清空当前数据库
     * @return true
     */
    public function flushDB()
    {
        return $this->_execForRedis(__FUNCTION__);
    }

    /**
     * @see Redis::info()
     * 返回当前库状态
     * @return array
     */
    public function info()
    {
        return $this->_execForRedis(__FUNCTION__);
    }

    /**
     * @see Redis::save()
     * 同步保存数据到磁盘
     * @return bool
     */
    public function save()
    {
        return $this->_execForRedis(__FUNCTION__);
    }

    /**
     * @see Redis::bgSave()
     * 异步保存数据到磁盘
     * @return bool
     */
    public function bgSave()
    {
        return $this->_execForRedis(__FUNCTION__);
    }

    /**
     * @see Redis::lastSave()
     * 返回最后保存到磁盘的时间
     * @return int
     */
    public function lastSave()
    {
        return $this->_execForRedis(__FUNCTION__);
    }

    /**
     * @see Redis::keys()
     * 返回key,支持*多个字符，?一个字符
     * 只有*　表示全部
     * @param string $key
     * @return array
     */
    public function keys($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::type()
     * 得到一个key的类型
     * const REDIS_NOT_FOUND       = 0;
     * const REDIS_STRING          = 1;
     * const REDIS_SET             = 2;
     * const REDIS_LIST            = 3;
     * const REDIS_ZSET            = 4;
     * const REDIS_HASH            = 5;
     * @param $key
     * @return int
     */
    public function type($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::del()
     * 删除指定一个或多个key,可参为多个参数或者一个数组
     * @param $key1
     * @param null $key2
     * @param null $keyN
     * @return int 返回删除key的个数
     */
    public function del($key1, $key2 = null, $keyN = null)
    {
        return $this->_execForRedis(__FUNCTION__, func_get_args());
    }

    /**
     * @see Redis::delete()
     * 删除指定一个或多个key,可参为多个参数或者一个数组
     * @param $key1
     * @param null $key2
     * @param null $keyN
     * @return int 返回删除key的个数
     */
    public function delete($key1, $key2 = null, $keyN = null)
    {
        return $this->_execForRedis(__FUNCTION__, func_get_args());
    }

    /**
     * @see Redis::exists()
     * 判断一个key值是不是存在
     * @param  $key
     * @return bool
     */
    public function exists($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::expire()
     * 为一个key设定过期时间 单位为秒
     * @param string $key
     * @param int $expire
     * @return bool
     */
    public function expire($key, $expire)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $expire]);
    }

    /**
     * @see Redis::ttl()
     * 返回一个key还有多久过期，单位秒
     * @param $key
     * @return bool
     */
    public function ttl($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::pttl()
     * 返回以毫秒的过期时间
     * @param $key
     * @return int
     */
    public function pttl($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::pExpire()
     * 以毫秒为单位设置 key 的生存时间
     * @param $key
     * @param $expire
     * @return bool
     */
    public function pExpire($key, $expire)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $expire]);
    }

    /**
     * @see Redis::pExpireAt()
     * 以毫秒为单位设置 key 的生存时间
     * @param $key
     * @param $time
     * @return bool
     */
    public function pExpireAt($key, $time)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $time]);
    }

    /**
     * @see Redis::expireAt()
     * 设定一个key什么时候过期，time为一个时间戳
     * @param  $key
     * @param  $time
     * @return bool
     */
    public function expireAt($key, $time)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $time]);
    }

    /**
     * @see Redis::close()
     * 关闭服务器链接
     */
    public function close()
    {
        $this->_execForRedis(__FUNCTION__);
    }

    /**
     * 关闭所有连接
     */
    public static function closeAll()
    {
        foreach (self::$_instance as $o) {
            if ($o instanceof self)
                $o->close();
        }
        self::$_instance = array();
    }

    /**
     * @see Redis::dbSize()
     * 返回当前数据库key数量
     */
    public function dbSize()
    {
        return $this->_execForRedis(__FUNCTION__);
    }

    /**
     * @see Redis::randomKey()
     * 返回一个随机key
     */
    public function randomKey()
    {
        return $this->_execForRedis(__FUNCTION__);
    }

    /**
     * 得到当前数据库ID
     * @return int
     */
    public function getDbId()
    {
        return $this->dbId;
    }

    /**
     * 返回当前密码
     */
    public function getAuth()
    {
        return $this->auth;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getConnInfo()
    {
        return array(
            'host' => $this->host,
            'port' => $this->port,
            'auth' => $this->auth,
            'dbId' => $this->dbId
        );
    }

    /*********************事务的相关方法************************/

    /**
     * @see Redis::watch()
     * 监控key,就是一个或多个key添加一个乐观锁
     * 在此期间如果key的值如果发生的改变，刚不能为key设定值
     * 可以重新取得Key的值。
     * @param  $key
     */
    public function watch($key)
    {
        $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::unwatch()
     * 取消当前链接对所有key的watch
     *  EXEC 命令或 DISCARD 命令先被执行了的话，那么就不需要再执行 UNWATCH 了
     */
    public function unwatch()
    {
        $this->_execForRedis(__FUNCTION__);
    }

    /**
     * @see Redis::multi()
     * 开启一个事务
     * 事务的调用有两种模式Redis::MULTI和Redis::PIPELINE，
     * 默认是Redis::MULTI模式，
     * Redis::PIPELINE管道模式速度更快，但没有任何保证原子性有可能造成数据的丢失
     * @param  int $type
     * @return mixed
     */
    public function multi($type = Redis::MULTI)
    {
        return $this->_execForRedis(__FUNCTION__, [$type]);
    }

    /**
     * @see Redis::exec()
     * 执行一个事务
     * 收到 EXEC 命令后进入事务执行，事务中任意命令执行失败，其余的命令依然被执行
     */
    public function exec()
    {
        return $this->_execForRedis(__FUNCTION__);
    }

    /**
     * @see Redis::discard()
     * 回滚一个事务
     */
    public function discard()
    {
        return $this->_execForRedis(__FUNCTION__);
    }

    /**
     * @see Redis::ping()
     * 测试当前链接是不是已经失效
     * 没有失效返回+PONG
     * 失效返回false
     */
    public function ping()
    {
        return $this->_execForRedis(__FUNCTION__);
    }

    /**
     * @see Redis::auth()
     * 校验权限
     * @param $auth
     * @return mixed
     */
    public function auth($auth)
    {
        return $this->_execForRedis(__FUNCTION__, [$auth]);
    }

    /*********************自定义的方法,用于简化操作************************/

    /**
     * 得到条批量删除key的命令。这里不会执行此命令。
     * @param string $keys
     * @param string $cmd 命令路径
     * @return string
     */
    public function delKeysCmd($keys, $cmd = 'redis-cli')
    {
        $redisInfo = $this->getConnInfo();
        if ($redisInfo['auth'] == false) {
            $redisInfo['auth'] = '8888';
        }
        $cmdArr = array(
            $cmd,
            '-a',
            $redisInfo['auth'],
            '-h',
            $redisInfo['host'],
            '-p',
            $redisInfo['port'],
            '-n',
            $redisInfo['dbId'],
        );
        $redisStr = implode(' ', $cmdArr);
        $cmd = "{$redisStr} KEYS \"{$keys}\" | xargs {$redisStr} del";
        return $cmd;
    }

    /**
     * @see Redis::slaveof()
     * SLAVEOF 命令用于在 Redis 运行时动态地修改复制(replication)功能的行为。
     * 可以将当前服务器转变为指定服务器的从属服务器(slave server)。
     * 如果当前服务器已经是某个主服务器(master server)的从属服务器，
     * 那么执行 SLAVEOF host port 将使当前服务器停止对旧主服务器的同步，
     * 丢弃旧数据集，转而开始对新主服务器进行同步。
     *
     * 另外，对一个从属服务器执行命令 SLAVEOF NO ONE 将使得这个从属服务器关闭复制功能，
     * 并从从属服务器转变回主服务器，原来同步所得的数据集不会被丢弃。
     * @param $host
     * @param int $port
     * @return bool
     */
    public function slaveof($host, $port = 6379)
    {
        return $this->_execForRedis(__FUNCTION__, [$host, $port]);
    }

    /**
     * @see Redis::sort()
     * 执行sort排序命令。sort命令可以用多重排序。实现更为复杂的sql功能。
     * 返回或保存给定列表、集合、有序集合 key 中经过排序的元素。
     * 排序默认以数字作为对象，值被解释为双精度浮点数，然后进行比较
     *
     * - 'by' => 'some_pattern_*', 默认情况下，以key中的值排序。使用by后，以外部键的值排序
     *    : SORT uid BY user_level_*  ser_level_* 是一个占位符， 它先取出 uid 中的值
     *    然再用这个值来查找相应的键。
     *    BY not-exists-key 不过，通过将这种用法和 GET 选项配合， 就可以在不排序的情况下，
     *      获取多个外部键， 相当于执行一个整合的获取操作（类似于 SQL 数据库的 join 关键字）。
     *
     * - 'limit' => array(0, 1), 排序之后返回元素的数量可以通过 LIMIT 修饰符进行限制
     * - 'get' => 'some_other_pattern_*' or an array of patterns,
     *   使用 GET 选项， 可以根据排序的结果来取出相应的键值 SORT uid GET user_name_*
     *   排序后，将值替换*后，取出对应key的值
     * - 'sort' => 'asc' or 'desc', 排序类型
     * - 'alpha' => TRUE,  修饰符对字符串进行排序
     * - 'store' => 'external-key' 通过给 STORE 选项指定一个 key 参数，可以将排序结果保存到给定的键上。
     *   如果被指定的 key 已存在，那么原有的值将被排序结果覆盖。
     *   这样就可以避免对 SORT 操作的频繁调用：只有当结果集过期时，才需要再调用一次 SORT 操作
     *
     * @doc  BY 和 GET 选项都可以用 key->field 的格式来获取哈希表中的域的值，
     *       其中 key 表示哈希表键， 而 field 则表示哈希表的域:
     *         SORT uid BY user_info_*->level GET user_info_*->name
     *       *占位符表示从key中取出的每一个值
     * @desc  复杂度O(N+M*log(M))， N 为要排序的列表或集合内的元素数量， M 为要返回的元素数量。
     *
     * @param string $key 要排序的key
     * @param array|null $option 选项
     * @link http://doc.redisfans.com/key/sort.html
     * @return array 没有使用 STORE 参数，返回列表形式的排序结果。
     *               使用 STORE 参数，返回排序结果的元素数量。
     */
    public function sort($key, $option = null)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $option]);
    }

    /**
     * @see Redis::setOption()
     * 设置客户端链接选项
     * @param string $name 选项名称
     * @param string $value 选项值
     * @return bool 设置成功返回true
     * @example
     * <pre>
     * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
     * //设置序列化数据
     * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
     * //use igBinary serialize/unserialize
     * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
     * // use custom prefix on all keys
     * $redis->setOption(Redis::OPT_PREFIX, 'myAppName:');
     * </pre>
     */
    public function setOption($name, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$name, $value]);
    }

    /**
     * 设置key前缀
     * 此方法慎用。这个设置在整个redis会话期间有效。由于redis在实例化的时候
     * 使用了单例模式，这个设会干扰其它模型。
     * @param $prefix
     * @return bool
     */
    public function setPrefix($prefix)
    {
        if ($prefix == false) {
            return false;
        }
        return $this->setOption(Redis::OPT_PREFIX, $prefix);
    }


    /**
     * 设置使用php序列化数据
     * @return bool
     */
    public function setSerialize()
    {
        return $this->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
    }

    /**
     * @see Redis::slowLog()
     * 慢是志相关命令
     * 得到10条慢查询：$redis->slowlog('get', 10);
     * 提到默认数据量的慢查询：$redis->slowlog('get');
     * 重置慢查询：$redis->slowlog('reset');
     * 得到慢查询的数量：$redis->slowlog('len');
     * @param string $cmd 命令类型
     * @return mixed
     */
    public function slowLog($cmd)
    {
        return $this->_execForRedis(__FUNCTION__, func_get_args());
    }

    /**
     * @see Redis::client()
     * 执行一个客户端命令
     * 得到客户端列表：$redis->client('list');
     * 得到客户端名字：$redis->client('getname');
     * 设置名字：$redis->client('setname', 'somename');
     * 强制关闭一个链接：$redis->client('kill', <ip:port>);
     * @param $command
     * @param string $args
     * @return mixed
     */
    public function client($command, $args = '')
    {
        return $this->_execForRedis(__FUNCTION__, [$command, $args]);
    }

    /**
     * @see Redis::config()
     * 得到或设置redis配置
     * @param string $op get 或 set
     * @param string $key 配置名称，可以使用通配符*
     * @param null $value 如果op=set，则为设置的值
     * @return array
     */
    public function config($op, $key, $value = null)
    {
        return $this->_execForRedis(__FUNCTION__, [$op, $key, $value]);

    }

    /**
     * @see Redis::dump()
     * 获取存储在redis的指定键数据的序列化版本。
     * @param $key
     * @return string
     */
    public function dump($key)
    {
        return $this->_execForRedis(__FUNCTION__, [$key]);
    }

    /**
     * @see Redis::restore()
     * 反序列化给定的序列化值，并将它和给定的 key 关联
     * 参数 ttl 以毫秒为单位为 key 设置生存时间；如果 ttl 为 0 ，那么不设置生存时间。
     * @param $key
     * @param int $ttl
     * @param $value
     * @return bool
     */
    public function restore($key, $ttl = 0, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $ttl, $value]);
    }

    /**
     * 执行redis 代理方法
     * @param $cmd
     * @param $params
     * @throws $e
     * @return mixed
     */
    private function _execForRedis($cmd, $params = [])
    {
        for ($i = 0; $i < 2; $i++) {
            try {
                $redis = $this->connect($i);
                $r = call_user_func_array([$redis, $cmd], $params);
                if (($error = $redis->getLastError()) !== null) {
                    if ($this->errMode == self::ERR_MODE_EXCEPTION) {
                        throw new Exception($error);
                    } else {
                        trigger_error($error, E_USER_WARNING);
                    }
                }
                return $r;
            } catch (RedisException $e) {
                if ($i == 0) {
                    continue;
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * @see Redis::rename()
     * 重命名一个key
     * 命令：rename srcKey dstKey
     * 复杂度: O(1)
     * @param string $srcKey 原来的key
     * @param string $dstKey 重命名后的key
     * @return mixed 成功返回true
     */
    public function rename($srcKey, $dstKey)
    {
        return $this->_execForRedis(__FUNCTION__, [$srcKey, $dstKey]);
    }

    /**
     * @see Redis::renameNx()
     * 重命名一个key
     * 命令：renamenx srcKey dstKey
     * 复杂度: O(1)
     * @param string $srcKey 原来的key
     * @param string $dstKey 重命名后的key
     * @return mixed 成功返回true, 如果dstKey存在返回false
     */
    public function renameNx($srcKey, $dstKey)
    {
        return $this->_execForRedis(__FUNCTION__, [$srcKey, $dstKey]);
    }


    /**
     * @see Redis::setBit()
     * 设置一个二进制位的值
     * 命令：setbit key offset value
     * 复杂度: O(1)
     * @param string $key redis key
     * @param int $offset 偏移量
     * @param int $value 值，0或1
     * @return mixed 返回设置之前的值
     */
    public function setBit($key, $offset, $value)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $offset, $value]);
    }

    /**
     * @see Redis::getBit()
     * 获取一个二进制位的值
     * 命令: getbit key offset
     * 复杂度: O(1)
     * @param string $key redis key
     * @param int $offset 偏移量
     * @return mixed 返会0或1
     */
    public function getBit($key, $offset)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $offset]);
    }

    /**
     * @see Redis::bitCount()
     * 计算给定字符串中，被设置为 1 的比特位的数量
     * 命令: bitCount key
     * 复杂度: O(N)
     * @param string $key redis-key
     * @param int $start 开始位置，以字节为单位(8bit)
     * @param int $end 结束位置，以字节为单位(8bit)
     * @return mixed 返回可以可以使用的二进制位数
     */
    public function bitCount($key, $start = null, $end = null)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, $start, $end]);
    }

    /**
     * @see Redis::bitOp()
     * 位运算
     * 命令: bitop and retkey key1 key2
     * 复杂度: O(N)
     * @param string $op 操作类型 "AND", "OR", "NOT", "XOR"
     * @param string $retKey 要改变得key
     * @param string $keys 参数运算的key， 空的 key 也被看作是包含 0 的字符串序列
     *                     除了 NOT 操作之外，其他操作都可以接受一个或多个 key 作为输入
     * @return mixed $retKey大小, 输入 key 中最长的字符串长度相等
     */
    public function bitOp($op, $retKey, $keys)
    {
        return $this->_execForRedis(__FUNCTION__, [$op, $retKey, $keys]);
    }

    /**
     * 代理执行一个redis函数
     * 通过lua来执行任意redis命令
     * @example
     *   $redis->evalCmd('set test 1');
     * 如果命令执行失败，返回空。
     * 调用getLastError 获得错误消息。
     * @param $cmd
     * @return mixed
     */
    public function evalCmd($cmd)
    {
        $params = preg_split('/\s+/', trim($cmd));
        $params = array_map(function ($v) {
            return "'{$v}'";
        }, $params);
        $str = "return redis.pcall(" . implode(',', $params) . ")";
        return $this->_execForRedis('evaluate', [$str]);
    }

    /**
     * @see Redis::getLastError()
     * 获取最后redis的执行错误。如果没有错返回null
     * @return mixed
     * @throws RedisException
     */
    public function getLastError()
    {
        return $this->_execForRedis(__FUNCTION__);
    }

    /**
     * @see Redis::scan()
     * $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
     * 遍历有2中选项。
     * Redis::SCAN_RETRY：一直等到返回数据，或者到达末尾。建议使用此选项
     * Redis::SCAN_NORETRY： 一直等到超时，如果没有数据返回空数组。默认值。
     *
     * redis-cli中，游标0边上表示和结束。
     * php redis 游标 null 表示开始，0表示结束。
     *
     * @param int|null $iterator 游标位置。不能设置为0，设置为null表示开始。
     *        每次遍历都会修改这个变量的值（应用传递）
     * @param string $pattern 匹配表达式，同keys
     * @param int $count 每次变量返回的数量。他不是准确的。参数的默认值为 10
     * @return mixed 有数据返回key的一维数组，如果遍历完成返回false。
     *               SCAN_NORETRY下，超时无数据返回空数组。
     */
    public function scan(&$iterator, $pattern = '', $count = 0)
    {
        return $this->_execForRedis(__FUNCTION__, [&$iterator, $pattern, $count]);
    }

    /**
     * @see scan()
     * 遍历集合中的成员
     * @param $key
     * @param $iterator
     * @param string $pattern
     * @param int $count
     * @return mixed
     */
    public function sScan($key, &$iterator, $pattern = '', $count = 0)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, &$iterator, $pattern, $count]);
    }

    /**
     * @see scan()
     * 遍历有序集合中的成员
     * @param $key
     * @param $iterator
     * @param string $pattern
     * @param int $count
     * @return mixed
     */
    public function zScan($key, &$iterator, $pattern = '', $count = 0)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, &$iterator, $pattern, $count]);
    }

    /**
     * @see scan()
     * 遍历hash表中的成员
     * @param $key
     * @param $iterator
     * @param string $pattern
     * @param int $count
     * @return mixed
     */
    public function hScan($key, &$iterator, $pattern = '', $count = 0)
    {
        return $this->_execForRedis(__FUNCTION__, [$key, &$iterator, $pattern, $count]);
    }
}