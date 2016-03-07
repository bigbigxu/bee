<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/1/19
 * Time: 10:16
 * 如果使用无序集合来保存iD，使用sort命令来取得数据。需要要求
 * set hash数据不能有过期时间。不然数据过期，会影响sort命令返回结果的正确性。
 * sort排序方式，会占用大量内存，所以此类放弃了这种方式。
 *
 * 设计说明：
 *    1.使用hash表来保存一数据库中对应的一条记录。
 *    2.使用有序集合来保存索引。就是id为value，对应字段值为score。
 *    3.这里都是先更新cache，然后更新mysql。如果mysql更新失败，会回滚cache，但是并不保证cache回滚成功。
 *    4.同时保存hash,set可能会占用较多内存。过大数据可能不适合。
 *    5.这里为索引设定的过期时间。如果索引过期，需要遍历整个mysql表来重建索引。
 *    6.异步更新mysql,最好基于swoole写个server来实现，定时器也是可选的方案，但不可靠。
 *    7.索引的更新，最好使用其它服务来定时检查。
 *    8.大数据下，冷热数据的区分，目前没有想到比较好的实现方法。也许可以基本规则判断数据是否进入cache
 *
 * 此类的作用：
 *    1.统一cache ,mysql操作，减少代码量，增加可复用性,，并保证Mysql数据和redis数据的一致性。
 *    2.统一管理的cache ，key。
 * @TODO 未经测试，不要使用
 */
class CacheRedis extends CoreModel
{
    /**
     * 创建索引的有序集合的过期时间
     * @var int
     */
    protected $indexExpire = 7 * 24 * 3600;
    /**
     * 数据hash表的过期时间
     * @var int
     */
    protected $dataExpire = 3600;
    /**
     * 索引key的前缀
     * @var string
     */
    protected $indexKeyPrefix = 'auto_index_';
    /**
     * 数据key的前缀
     * @var string
     */
    protected $dataKeyPrefix = 'auto_data_';
    /**
     * 全局的key前缀。默认使用表名
     * @var string
     */
    protected $globalKeyPrefix = '';
    /**
     * 保存下一个自增长id的key
     * @var string
     */
    protected $nextIdKey = 'next_id';
    /**
     * 获取id 锁
     * @var string
     */
    protected $idLockKey = 'next_id_lock';
    /**
     * 数据库更新模式，默认使用同步模式
     * @var string
     */
    public $mysqlMode = self::MYSQL_SYNC;
    protected $redisQuery = array(
        'field' => '', //查询的字段，数组形式
        'sort' => self::SORT_ASC, //排序类型
        'limit' => array(0, 1), //开始位置
        'min' => '-inf', //索引最小值
        'max' => '+inf', //索引最大值
    );
    /**
     * 最后一次查询的相关选面
     * @var array
     */
    protected $lastQuery = array();
    const OP_INSERT = 1; //插入数据
    const OP_UPDATE = 2; //更新数据
    const OP_DEL = 3; //删除数据
    const OP_FIND = 4; //查找数据
    const OP_CACHE = 5; //更新cache

    const MYSQL_ASYNC = 'async'; //当前使用异步mysql更新模式
    const MYSQL_SYNC = 'sync'; //同步模式

    const ERR_OP_CACHE = 1; //更新cache失败
    const ERR_OP_MYSQL = 2; //更新数据库失败

    const SORT_DESC = 'desc';
    const SORT_ASC = 'asc';

    /**
     * 异步mysql回调函数。函数接受3个参数，主键,数据,操作类型
     * @var mixed
     */
    protected $mysqlCallback;

    /**
     * 定义数据字段，如果为空，表示所有字段
     * @return array
     */
    public function dataField()
    {
        return array();
    }

    /**
     * 索引字段。这将产生多个有续集合。
     * @return array
     */
    public function indexField()
    {
        return array();
    }

    /**
     * 初始化方法。
     * 用于检查表名配置和设置redis全局前缀
     * @throws Exception
     */
    public function init()
    {
        $name = $this->tableName();
        if ($name == false) {
            throw new Exception("请定义表名！");
        }
        if ($this->globalKeyPrefix == false) {
            $this->globalKeyPrefix = $this->tableName() . '_';
        }
        $this->redis()->setPrefix($this->globalKeyPrefix);
    }

    /**
     * 向数据库插入一条记录。同时更新cache
     * 如果data中，已经有主键的值，将直接使用主键的值。如果没有，将取当前表id的最大值。
     * 对于使用了分表的主键。应该将主键值放在data数组中。
     * @param array $data 要插入的数据
     * @param bool $updateCache 是否将当前数据更新到cache
     * @return bool
     */
    public function cacheInsert($data, $updateCache = true)
    {
        $pk = $data[$this->getPkName()]; //数据中已经有了主键
        if ($pk == false) {
            $pk = $this->getNextId();
        }
        if ($updateCache == true) { //先更新cache
            if ($this->_cache($pk, $data, self::OP_INSERT) == false) {
                return $this->setErrno(self::ERR_OP_CACHE);
            }
        }

        //更新mysql
        if ($this->mysqlMode == self::MYSQL_SYNC) { //同步更新模式
            if ($this->insert($data) === false) {
                if ($updateCache) {
                    $this->cacheDelete($pk); //回滚cache数据。
                }
                return $this->setErrno(self::ERR_OP_MYSQL);
            }
        } else { //异步mysql。调用指定的回调函数
            return call_user_func($this->mysqlCallback, $pk, $data, self::OP_INSERT);
        }
        return true;
    }

    /**
     * 基于主键更新一条记录
     * @param $pk
     * @param $data
     * @param bool|true $updateCache
     * @return array|bool
     */
    public function cacheUpdate($pk, $data, $updateCache = true)
    {
        $beforeData = $this->cacheOne($pk); //之前的数据。
        if ($updateCache == true) {
            if ($this->_cache($pk, $data, self::OP_UPDATE, $beforeData) == false) {
                return $this->setErrno(self::ERR_OP_CACHE);
            }
        }

        //更新mysql
        if ($this->mysqlMode == self::MYSQL_SYNC) { //同步更新模式
            if ($this->updateById($data, $pk) == false) {
                if ($updateCache) {
                    $this->_cache($pk, $beforeData, self::OP_UPDATE); //数据回滚
                }
                return $this->setErrno(self::ERR_OP_MYSQL);
            }
        } else { //异步mysql。调用指定的回调函数
            return call_user_func($this->mysqlCallback, $pk, $data, self::OP_UPDATE);
        }
        return true;
    }


    /**
     * 基于主键删除一条记录
     * @param $pk
     * @param $updateCache
     * @return bool|false|mixed
     */
    public function cacheDelete($pk, $updateCache = true)
    {
        $data = $this->cacheOne($pk);
        if ($updateCache) {
            $this->redis()->del($this->cacheDataKey($pk)); //删除info数据
        }
        //删除索引
        $indexField = $this->indexField();
        if (!$indexField) {
            foreach ($indexField as $field) {
                $cacheKey = $this->cacheIndexKey($field);
                $this->redis()->zRem($cacheKey, $pk);
            }
        }

        //删除mysql
        if ($this->mysqlMode == self::MYSQL_SYNC) { //同步更新模式
            if ($this->delById($pk) == false) {
                if ($updateCache) {
                    $this->cacheInsert($pk, $data); //重新更新cache
                }
                return $this->setErrno(self::ERR_OP_MYSQL);
            }
        } else { //异步mysql。调用指定的回调函数
            return call_user_func($this->mysqlCallback, $pk, $data, self::OP_DEL);
        }
        return true;
    }

    /**
     * 内部hash cache更新函数
     * @param $pk
     * @param array $data
     * @param int $op
     * @param $beforeData
     * @return array
     */
    private function _cache($pk, $data = array(), $op = self::OP_CACHE, $beforeData)
    {
        if ($data == false) {
            $data = $this->findById($pk);
        }
        $this->updateData($pk, $data);
        if ($op != self::OP_FIND) { //查询操作不需要更新索引
            $this->updateIndex($pk, $data, $beforeData);
        }
        return $data;
    }

    /**
     * 更新hash表数据字段
     * @param $pk
     * @param $data
     * @return bool
     * @throws Exception
     */
    public function updateData($pk, $data)
    {
        $dataField = $this->dataField();
        $dataKey = $this->cacheDataKey($pk);

        if ($dataField != false) { //只保存指定字段的数据
            $data = Functions::arrayFilterKey($data, $dataField);
        }
        $flag = $this->redis()->hMset($dataKey, $data);
        $this->redis()->expire($dataKey, $this->dataExpire);
        return $flag;
    }

    /**
     * 更新索引
     * @param mixed $pk 主键
     * @param array $data 要更新的数据
     * @param array $beforeData 更新之前的数据
     * @return null
     */
    public function updateIndex($pk, $data, $beforeData = array())
    {
        $indexField = $this->indexField();
        if ($indexField == false) {
            return null;
        }
        $flag = true;
        foreach ($indexField as $field) {
            if ($beforeData[$field] != $data[$field]) { //新旧值不一样，才需要更新cache
                $cacheKey = $this->cacheIndexKey($field);
                $flag = $this->redis()->zAdd($cacheKey, $data[$field], $pk);
                $this->redis()->expire($cacheKey, $this->indexExpire);
            }
        }
        return $flag;
    }

    /**
     * 得到记当详情hash表的key
     * @param $id
     * @return string
     */
    public function cacheDataKey($id)
    {
        return $this->dataKeyPrefix . $id;
    }

    /**
     * 得到索引集合的key
     * @param string $field 字段名称
     * @return string
     */
    public function cacheIndexKey($field)
    {
        return "{$this->indexKeyPrefix}{$field}";
    }


    /**
     * 获取自增长id的锁
     * @param int $timeout 超时间，单位ms
     * @return bool
     */
    private function _nextIdLock($timeout = 1000)
    {
        $count = $timeout % 100;
        $i = 0;
        while ($this->redis()->get($this->idLockKey)) {
            $i++;
            usleep(100);
            if ($i > $count) {
                return false;
            }
        }
        return true;
    }

    /**
     * 得到下一次自增加ID。此方法也可以解并发插入自增长id锁表问题
     * 默认会使用当前表的ID。如果使用了其它方法。可能需要重写此方法
     * @param int $timeout 超时时间
     * @return int
     * @throws Exception
     */
    public function getNextId($timeout = 1000)
    {
        if ($this->_nextIdLock($timeout) == false) {
            throw new Exception("获取自增加ID超时");
        }
        $id = $this->redis()->incr($this->nextIdKey);
        if ($id > 1) { //ID大于1表示key存在。
            return $id;
        }

        if ($this->redis()->setnx($this->idLockKey, 1)) { //得到id锁
            $this->redis()->expire($this->idLockKey, $this->indexExpire);
            $id = $this->from()->max($this->getPkName());
            $id++;
            $this->redis()->set($this->nextIdKey, $id);
            $this->redis()->del($this->idLockKey);
            return $id;
        } else {
            throw new Exception("获取自增加失败");
        }
    }

    /**
     * 注册mysql回写函数
     * @param $callback
     * @return $this
     */
    public function registerMysqlCallback($callback)
    {
        $this->mysqlCallback = $callback;
        return $this;
    }


    /**
     * 根据主键查找一条记录
     * @param $pk
     * @param bool|true $checkCache
     * @return array|bool|false|string
     */
    public function cacheOne($pk, $checkCache = true)
    {
        $r = false;
        if ($checkCache) {
            $r = $this->redis()->hGetAll($this->cacheDataKey($pk));
        }
        if ($r === false) { //如果cache不存在
            $r = $this->_cache($pk, array(), self::OP_FIND);
        }
        return $r;
    }

    /**
     * 设置查询的索引字段
     * @param $index
     * @return $this
     */
    public function cacheIndex($index)
    {
        $this->redisQuery['index'] = $index;
        return $this;
    }

    /**
     * 这个方法是limit方法的变种
     * @param int $page 当前页
     * @param int $pageSize 每页数量
     * @return $this
     */
    public function cachePage($page = 0, $pageSize = 20)
    {
        $pageSize = $pageSize <= 0 ? 20 : $pageSize;
        $page = $page <= 0 ? 1 : $page;
        $offset = ($page - 1) * $pageSize;
        $this->cacheLimit($offset, $pageSize);
        return $this;
    }

    /**
     * 设置获取数据偏移量
     * @param int $offset
     * @param int $limit
     * @return $this
     */
    public function cacheLimit($offset = 0, $limit = 10)
    {
        $this->redisQuery['limit'] = array($offset, $limit);
        return $this;
    }

    /**
     * 设置排序类型
     * @param $type
     * @return $this
     */
    public function cacheSort($type)
    {
        $this->redisQuery['sort'] = $type;
        return $this;
    }

    /**
     * 设置查询的字端
     * @param $field
     * @return $this
     */
    public function cacheField($field)
    {
        $this->redisQuery['field'] = $field;
        return $this;
    }

    /**
     * 从cache查找所有数据
     * @param array $query
     * @return array
     */
    public function cacheAll($query = array())
    {
        $this->redisQuery = array_merge($this->redisQuery, $query);
        $r = array();
        $indexKey = $this->cacheIndexKey($this->redisQuery['index']);
        if ($this->redis()->exists($indexKey)) {
            $this->buildIndex($this->redisQuery['index']);
        }
        $min = $this->redisQuery['min'];
        $max = $this->redisQuery['max'];
        $limit = $this->redisQuery['limit'];
        if ($this->redisQuery['sort'] == self::SORT_DESC) {
            $idArr = $this->redis()->zRevRangeByScore($indexKey, $min, $max, array('limit' => $limit));
        } else {
            $idArr = $this->redis()->zRangeByScore($indexKey, $min, $max, array('limit' => $limit));
        }
        foreach ((array)$idArr as $id) {
            $item = $this->cacheOne($id);
            if ($this->redisQuery['field']) {
                $item = Functions::arrayFilterKey($item, $this->redisQuery['field']);
            }
            $r[] = $item;
        }
        $this->clearQuery();
        return $r;
    }

    public function clearQuery()
    {
        $this->lastQuery = $this->redisQuery;
        $this->redisQuery = array(
            'field' => '', //查询的字段，数组形式
            'sort' => self::SORT_ASC, //排序类型
            'limit' => array(0, 1), //开始位置
            'min' => '-inf', //索引最小值
            'max' => '+inf', //索引最大值
        );
    }

    /**
     * 创建指定字段的索引
     * @param string $field 要创建索引的字段
     * @param PDOStatement()|null $stmt 一个stmt对象。如果为空，将遍历整个表。
     */
    public function buildIndex($field, $stmt = null)
    {
        if ($stmt === null) {
            $stmt = $this->from()->query();
        }
        $indexKey = $this->cacheIndexKey($field);
        $pkName = $this->getPkName();
        foreach ($stmt as $row) {
            $this->redis()->zAdd($indexKey, $row[$field], $row[$pkName]);
        }
        $this->redis()->expire($indexKey, $this->indexExpire);
    }

    /**
     * 设置选项。
     * @param $key
     * @param $value
     * @return $this
     */
    public function setOption($key, $value)
    {
        $vars = get_class_vars($this);
        if (in_array($key, $vars)) {
            $this->$key = $value;
        }
        return $this;
    }

    public function setOptions($options)
    {
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }
        return $this;
    }

    public function setGlobalKeyPrefix($key)
    {
        $this->globalKeyPrefix = $key;
        return $this;
    }

    public function getGlobalKeyPrefix()
    {
        return $this->globalKeyPrefix;
    }

    public function setIndexKeyPrefix($key)
    {
        $this->indexKeyPrefix = $key;
        return $key;
    }

}