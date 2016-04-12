<?php

/**
 * redis操作类
 * 说明，任何为false的串，存在redis中都是空串。
 * 只有在key不存在时，才会返回false。
 * 这点可用于防止缓存穿透
 * @author xuen
 *
 */
class CoreRedis
{
    /**
     * @var Redis
     */
    private $redis;
    protected $dbId = 0;//当前数据库ID号
    protected $auth;//当前权限认证码
    static private $_instance = array();
    private $k;
    //配置文件数组
    protected $config = array(
        'timeout' => 30,  //连接超时时间，redis配置文件中默认为300秒。
        'prefix' => '', //前缀
        'serialize' => false, //是否序列化数据
        'host' => '127.0.0.1',
        'port' => 6379
    );
    protected $expireTime; //什么时候重新建立连接
    protected $host;
    protected $port;
    protected $prefix;
    protected $serialize;

    public function __construct($config)
    {
        $this->config = array_merge($this->config, $config);
        $this->redis = new Redis();
        $this->port = $this->config['port'];
        $this->host = $this->config['host'];
        $this->prefix = $this->config['prefix'];
        $this->serialize = $this->config['serialize'];
        $this->redis->connect($this->host, $this->port, $this->config['timeout']);

        if ($config['auth']) {
            $this->auth($config['auth']);
            $this->auth = $config['auth'];
        }
        $this->expireTime = time() + $this->config['timeout'];
        if ($this->prefix) {
            $this->setPrefix($this->config['prefix']);
        }
        if ($this->serialize) {
            $this->setSerialize();
        }
    }

    /**
     * 得到实例化的对象.
     * 为每个数据库建立一个连接
     * 如果连接超时，将会重新建立一个连接
     * @param array $config
     * @return self
     */
    public static function getInstance($config)
    {
        if (!is_array($config)) {
            $config = App::c($config);
        }
        $config['db_id'] = $config['db_id'] ? $config['db_id'] : 0;
        $k = md5($config['host'] . $config['port'] . $config['db_id']);
        if (!(self::$_instance[$k] instanceof self) || time() > self::$_instance[$k]->expireTime) {
            self::$_instance[$k] = null;
            self::$_instance[$k] = new self($config);
            self::$_instance[$k]->k = $k;
            self::$_instance[$k]->dbId = $config['db_id'];

            //如果不是0号库，选择一下数据库。
            if ($config['db_id'] != 0)
                self::$_instance[$k]->select($config['db_id']);
        }
        return self::$_instance[$k];
    }

    private function __clone()
    {
    }

    /**
     * 执行原生的redis操作
     * @return Redis
     */
    public function getRedis()
    {
        return $this->redis;
    }

    /*****************hash表操作函数*******************/

    /**
     * 得到hash表中一个字段的值
     * @param string $key 缓存key
     * @param string $field 字段
     * @return string|false
     */
    public function hGet($key, $field)
    {
        return $this->redis->hGet($key, $field);
    }

    /**
     * 为hash表设定一个字段的值
     * @param string $key 缓存key
     * @param string $field 字段
     * @param string $value 值。
     * @return bool
     */
    public function hSet($key, $field, $value)
    {
        return $this->redis->hSet($key, $field, $value);
    }

    /**
     * 判断hash表中，指定field是不是存在
     * @param string $key 缓存key
     * @param string $field 字段
     * @return bool
     */
    public function hExists($key, $field)
    {
        return $this->redis->hExists($key, $field);
    }

    /**
     * 删除hash表中指定字段 ,支持批量删除
     * @param string $key 缓存key
     * @param string $field 字段
     * @return int
     */
    public function hDel($key, $field)
    {
        $fieldArr = explode(',', $field);
        $delNum = 0;
        foreach ($fieldArr as $row) {
            $row = trim($row);
            $delNum += $this->redis->hDel($key, $row);
        }
        return $delNum;
    }

    /**
     * 返回hash表元素个数
     * @param string $key 缓存key
     * @return int|bool
     */
    public function hLen($key)
    {
        return $this->redis->hLen($key);
    }

    /**
     * 为hash表设定一个字段的值,如果字段存在，返回false
     * @param string $key 缓存key
     * @param string $field 字段
     * @param string $value 值。
     * @return bool
     */
    public function hSetNx($key, $field, $value)
    {
        return $this->redis->hSetNx($key, $field, $value);
    }

    /**
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
        return $this->redis->hMset($key, $value);
    }

    /**
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
        return $this->redis->hMget($key, $field);
    }

    /**
     * 为hash表设这累加，可以负数
     * @param string $key
     * @param int $field
     * @param int $value
     * @return bool
     */
    public function hIncrBy($key, $field, $value = 1)
    {
        $value = intval($value);
        return $this->redis->hIncrBy($key, $field, $value);
    }

    /**
     * 返回所有hash表的所有字段
     * @param string $key
     * @return array|bool
     */
    public function hKeys($key)
    {
        return $this->redis->hKeys($key);
    }

    /**
     * 返回所有hash表的字段值，为一个索引数组
     * @param string $key
     * @return array|bool
     */
    public function hVals($key)
    {
        return $this->redis->hVals($key);
    }

    /**
     * 返回所有hash表的字段值，为一个关联数组
     * @param string $key
     * @return array|bool
     */
    public function hGetAll($key)
    {
        return $this->redis->hGetAll($key);
    }

    /*********************有序集合操作*********************/

    /**
     * 给当前集合添加一个元素
     * 如果value已经存在，会更新order的值。
     * @param string $key
     * @param string $score 序号
     * @param string $value 值
     * @return bool
     */
    public function zAdd($key, $score, $value)
    {
        return $this->redis->zAdd($key, $score, $value);
    }

    /**
     * 给$value成员的order值，增加$num,可以为负数
     * @param string $key
     * @param string $num 序号
     * @param string $value 值
     * @return 返回新的order
     */
    public function zinCrBy($key, $num, $value)
    {
        return $this->redis->zinCrBy($key, $num, $value);
    }

    /**
     * 删除值为value的元素
     * @param string $key
     * @param string $value
     * @return bool
     */
    public function zRem($key, $value)
    {
        return $this->redis->zRem($key, $value);
    }

    /**
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
        return $this->redis->zRemRangeByRank($key, $start, $end);
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
        return $this->redis->zRange($key, $start, $end, $withscore);
    }

    /**
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
        return $this->redis->zRevRange($key, $start, $end, $withscore);
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
     * 集合以递增排列后，返回指定score之间的元素。
     * min和max可以是-inf和+inf　表示最大值，最小值
     * @param string $key
     * @param mixed $start
     * @param mixed $end
     * @option array $option 参数
     *     withscores=>true，表示数组下标为Order值，默认返回索引数组
     *     limit=>array(0,1) 表示从0开始，取一条记录。
     * @return array|bool
     */
    public function zRangeByScore($key, $start = '-inf', $end = "+inf", $option = array())
    {
        return $this->redis->zRangeByScore($key, $start, $end, $option);
    }

    /**
     * 集合以order递减排列后，返回指定order之间的元素。
     * min和max可以是-inf和+inf　表示最大值，最小值
     * @param string $key
     * @param string $start
     * @param string $end
     * @option array $option 参数
     *     withscores=>true，表示数组下标为Order值，默认返回索引数组
     *     limit=>array(0,1) 表示从0开始，取一条记录。
     * @return array|bool
     */
    public function zRevRangeByScore($key, $start = '-inf', $end = "+inf", $option = array())
    {
        return $this->redis->zRevRangeByScore($key, $start, $end, $option);
    }

    /**
     * 返回score值在start end之间的数量
     * @param $key
     * @param $start
     * @param $end
     * @return int
     */
    public function zCount($key, $start, $end)
    {
        return $this->redis->zCount($key, $start, $end);
    }

    /**
     * 返回值为value的order值
     * @param $key
     * @param $value
     * @return float
     */
    public function zScore($key, $value)
    {
        return $this->redis->zScore($key, $value);
    }

    /**
     * 返回集合以score递增加排序后，指定成员的排序号，从0开始。
     * @param $key
     * @param $value
     * @return int
     */
    public function zRank($key, $value)
    {
        return $this->redis->zRank($key, $value);
    }

    /**
     * 返回集合以score递减排序后，指定成员的排序号，从0开始。
     * @param $key
     * @param $value
     * @return int
     */
    public function zRevRank($key, $value)
    {
        return $this->redis->zRevRank($key, $value);
    }

    /**
     * 删除集合中，score值在start end之间的元素　包括start end
     * min和max可以是-inf和+inf　表示最大值，最小值
     * @param  $key
     * @param  $start
     * @param  $end
     * @return 删除成员的数量。
     */
    public function zRemRangeByScore($key, $start, $end)
    {
        return $this->redis->zRemRangeByScore($key, $start, $end);
    }

    /**
     * 返回集合元素个数。
     * @param $key
     * @return int
     */
    public function zCard($key)
    {
        return $this->redis->zCard($key);
    }

    /*********************队列操作命令************************/

    /**
     * 在队列尾部插入一个元素
     * @param $key
     * @param $value
     * @return int
     */
    public function rPush($key, $value)
    {
        return $this->redis->rPush($key, $value);
    }

    /**
     * 在队列尾部插入一个元素 如果key不存在，什么也不做
     * @param $key
     * @param $value
     * @return int
     */
    public function rPushx($key, $value)
    {
        return $this->redis->rPushx($key, $value);
    }

    /**
     * 在队列头部插入一个元素
     * @param $key
     * @param $value
     * @return int
     */
    public function lPush($key, $value)
    {
        return $this->redis->lPush($key, $value);
    }

    /**
     * 在队列头插入一个元素
     * @param $key
     * @param $value
     * @return int
     */
    public function lPushx($key, $value)
    {
        return $this->redis->lPushx($key, $value);
    }

    /**
     * 返回队列长度
     * @param $key
     * @return int
     */
    public function lLen($key)
    {
        return $this->redis->lLen($key);
    }

    /**
     * 同lLen()
     * @param $key
     */
    public function lSize($key)
    {
        return $this->redis->lSize($key);
    }

    /**
     * 返回队列指定区间的元素
     * @param $key
     * @param $start
     * @param $end
     * @return array
     */
    public function lRange($key, $start, $end)
    {
        return $this->redis->lrange($key, $start, $end);
    }

    /**
     * 返回队列中指定索引的元素
     * @param $key
     * @param $index
     * @return String
     */
    public function lIndex($key, $index)
    {
        return $this->redis->lIndex($key, $index);
    }

    /**
     * 设定队列中指定index的值。
     * @param unknown $key
     * @param unknown $index
     * @param unknown $value
     * @return bool
     */
    public function lSet($key, $index, $value)
    {
        return $this->redis->lSet($key, $index, $value);
    }

    /**
     * 删除值为vaule的count个元素
     * PHP-REDIS扩展的数据顺序与命令的顺序不太一样，不知道是不是bug
     * count>0 从尾部开始
     *  >0　从头部开始
     *  =0　删除全部
     * @param unknown $key
     * @param unknown $count
     * @param unknown $value
     * @return int
     */
    public function lRem($key, $count, $value)
    {
        return $this->redis->lRem($key, $value, $count);
    }

    /**
     * 删除并返回队列中的头元素。
     * @param $key
     * @return string
     */
    public function lPop($key)
    {
        return $this->redis->lPop($key);
    }

    /**
     * 删除并返回队列中的尾元素
     * @param $key
     * @return string
     */
    public function rPop($key)
    {
        return $this->redis->rPop($key);
    }

    /*************redis字符串操作命令*****************/

    /**
     * 设置一个key
     * @param $key
     * @param $value
     * @return bool
     */
    public function set($key, $value)
    {
        return $this->redis->set($key, $value);
    }

    /**
     * 得到一个key
     * @param $key
     * @return bool|string
     */
    public function get($key)
    {
        return $this->redis->get($key);
    }

    /**
     * 将字符串的值增加值
     * @param string $key
     * @param int $num 增加的值 负数等同于Redis::decr操作
     * @return int 返回增加后的值
     */
    public function incr($key, $num = 1)
    {
        return $this->redis->incr($key, $num);
    }

    /**
     * 设置一个有过期时间的key
     * @param $key
     * @param $expire
     * @param $value
     * @return bool
     */
    public function setex($key, $expire, $value)
    {
        return $this->redis->setex($key, $expire, $value);
    }


    /**
     * 设置一个key,如果key存在,不做任何操作
     * @param $key
     * @param $value
     * @return bool
     */
    public function setnx($key, $value)
    {
        return $this->redis->setnx($key, $value);
    }

    /**
     * 批量设置key
     * @param $arr
     * @return bool
     */
    public function mset($arr)
    {
        return $this->redis->mset($arr);
    }

    /*************redis　无序集合操作命令*****************/

    /**
     * 返回集合中所有元素
     * @param $key
     * @return array
     */
    public function sMembers($key)
    {
        return $this->redis->sMembers($key);
    }

    /**
     * 求2个集合的差集
     * @param $key1
     * @param $key2
     * @return array
     */
    public function sDiff($key1, $key2)
    {
        return $this->redis->sDiff($key1, $key2);
    }

    /**
     * 为集合添加元素
     * @return int;
     */
    public function sAdd()
    {
        $params = func_get_args();
        $n = call_user_func_array(array($this->redis, 'sAdd'), $params);
        return $n;
    }

    /**
     * 返回一个随机元素
     * @param $key
     * @return string
     */
    public function sRandMember($key)
    {
        return $this->redis->sRandMember($key);
    }

    /**
     * 检查集合中是否存在指定的值
     * @param $key
     * @param $value
     * @return bool
     */
    public function sContains($key, $value)
    {
        return $this->redis->sIsMember($key, $value);
    }

    /**
     * 检查集合中是否存在指定的值
     * @param $key
     * @param $value
     * @return bool
     */
    public function sIsMember($key, $value)
    {
        return $this->redis->sIsMember($key, $value);
    }

    /**
     * 返回无序集合的元素个数
     * @param $key
     * @return int
     */
    public function scard($key)
    {
        return $this->redis->scard($key);
    }

    /**
     * 从集合中删除一个元素
     * @param $key
     * @param $value
     * @return int
     */
    public function srem($key, $value)
    {
        return $this->redis->srem($key, $value);
    }

    /*************redis管理操作命令*****************/

    /**
     * 选择数据库
     * @param int $dbId 数据库ID号
     * @return bool
     */
    public function select($dbId)
    {
        $this->dbId = $dbId;
        return $this->redis->select($dbId);
    }

    /**
     * 清空当前数据库
     * @return bool
     */
    public function flushDB()
    {
        return $this->redis->flushDB();
    }

    /**
     * 返回当前库状态
     * @return array
     */
    public function info()
    {
        return $this->redis->info();
    }

    /**
     * 同步保存数据到磁盘
     */
    public function save()
    {
        return $this->redis->save();
    }

    /**
     * 异步保存数据到磁盘
     */
    public function bgSave()
    {
        return $this->redis->bgSave();
    }

    /**
     * 返回最后保存到磁盘的时间
     */
    public function lastSave()
    {
        return $this->redis->lastSave();
    }

    /**
     * 返回key,支持*多个字符，?一个字符
     * 只有*　表示全部
     * @param string $key
     * @return array
     */
    public function keys($key)
    {
        return $this->redis->keys($key);
    }

    /**
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
        return $this->redis->type($key);
    }

    /**
     * 删除指定一个或多个key,可参为多个参数或者一个数组
     * @return int 返回删除key的个数
     */
    public function del()
    {
        $params = func_get_args();
        return call_user_func_array(array($this->redis, 'delete'), $params);
    }

    /**
     * @see del()
     * @return mixed
     */
    public function delete()
    {
        $params = func_get_args();
        return call_user_func_array(array($this->redis, 'delete'), $params);
    }

    /**
     * 判断一个key值是不是存在
     * @param  $key
     * @return bool
     */
    public function exists($key)
    {
        return $this->redis->exists($key);
    }

    /**
     * 为一个key设定过期时间 单位为秒
     * @param string $key
     * @param int $expire
     * @return bool
     */
    public function expire($key, $expire)
    {
        return $this->redis->expire($key, $expire);
    }

    /**
     * 返回一个key还有多久过期，单位秒
     * @param $key
     * @return bool
     */
    public function ttl($key)
    {
        return $this->redis->ttl($key);
    }

    /**
     * 设定一个key什么时候过期，time为一个时间戳
     * @param  $key
     * @param  $time
     * @return bool
     */
    public function expireAt($key, $time)
    {
        return $this->redis->expireAt($key, $time);
    }

    /**
     * 关闭服务器链接
     */
    public function close()
    {
        return $this->redis->close();
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
    }

    /**
     * 返回当前数据库key数量
     */
    public function dbSize()
    {
        return $this->redis->dbSize();
    }

    /**
     * 返回一个随机key
     */
    public function randomKey()
    {
        return $this->redis->randomKey();
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
            'db_id' => $this->dbId
        );
    }

    /*********************事务的相关方法************************/

    /**
     * 监控key,就是一个或多个key添加一个乐观锁
     * 在此期间如果key的值如果发生的改变，刚不能为key设定值
     * 可以重新取得Key的值。
     * @param  $key
     */
    public function watch($key)
    {
        $this->redis->watch($key);
    }

    /**
     * 取消当前链接对所有key的watch
     *  EXEC 命令或 DISCARD 命令先被执行了的话，那么就不需要再执行 UNWATCH 了
     */
    public function unwatch()
    {
        $this->redis->unwatch();
    }

    /**
     * 开启一个事务
     * 事务的调用有两种模式Redis::MULTI和Redis::PIPELINE，
     * 默认是Redis::MULTI模式，
     * Redis::PIPELINE管道模式速度更快，但没有任何保证原子性有可能造成数据的丢失
     */
    public function multi($type = Redis::MULTI)
    {
        return $this->redis->multi($type);
    }

    /**
     * 执行一个事务
     * 收到 EXEC 命令后进入事务执行，事务中任意命令执行失败，其余的命令依然被执行
     */
    public function exec()
    {
        $this->redis->exec();
    }

    /**
     * 回滚一个事务
     */
    public function discard()
    {
        $this->redis->discard();
    }

    /**
     * 测试当前链接是不是已经失效
     * 没有失效返回+PONG
     * 失效返回false
     */
    public function ping()
    {
        return $this->redis->ping();
    }

    public function auth($auth)
    {
        return $this->redis->auth($auth);
    }
    /*********************自定义的方法,用于简化操作************************/

    /**
     * 得到一组的ID号
     * @param  $prefix
     * @param  $ids
     * @return array
     */
    public function hashAll($prefix, $ids)
    {
        if ($ids == false) {
            return false;
        }
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        $arr = array();
        foreach ($ids as $id) {
            $key = $prefix . '.' . $id;
            $res = $this->hGetAll($key);
            if ($res != false)
                $arr[] = $res;
        }

        return $arr;
    }

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
            $redisInfo['db_id'],
        );
        $redisStr = implode(' ', $cmdArr);
        $cmd = "{$redisStr} KEYS \"{$keys}\" | xargs {$redisStr} del";
        return $cmd;
    }

    /**
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
     */
    public function slaveof($host, $port = 6379)
    {
        $this->redis->slaveof($host, $port);
    }

    /**
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
        return $this->redis->sort($key, $option);
    }

    /**
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
        return $this->redis->setOption($name, $value);
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
        return $this->redis->setOption(Redis::OPT_PREFIX, $prefix);
    }

    /**
     * 得到redis前缀
     * @return int
     */
    public function getPrefix()
    {
        return $this->redis->getOption(Redis::OPT_PREFIX);
    }

    /**
     * 设置使用php序列化数据
     * @return bool
     */
    public function setSerialize()
    {
        return $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
    }

    /**
     * @param string $cmd redis-cli 命令路径
     * @return ProcOpen
     */
    public function proc($cmd = 'redis-cli')
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
        );
        $redisStr = implode(' ', $cmdArr);
        $proc = new ProcOpen($redisStr);
        return $proc;
    }

    /**
     * 得到添加的前缀后的key
     * @param $key
     * @return string
     */
    public function _prefix($key)
    {
        return $this->redis->_prefix($key);
    }
}