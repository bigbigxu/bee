<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/4/13
 * Time: 14:32
 */
namespace bee\core;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\WriteConcern;

class Mongo
{
    /**
     * 默认连接选项
     * @var array
     */
    protected $options = array(
        'ssl' => false, //是否开启ssl
        'connectTimeoutMS' => 500, //连接超时时间，mongodb默认不超时。
        'socketTimeoutMS' => 30000, //收发数据超时时间
        //'replicaSet' => '', //指定的副本集名称

    );

    /**
     * 写入相关的配置先项
     * @var array
     */
    protected $writeConcern = array(
        /**
         *  w= 0,表示，写入后，马上返回结果。以性能是最好的，但是可靠性是最差
         *  w = 1,写入成功后返回错误。默认级别是，可靠(不绝对可靠)，只是内存写入成功
         *  w = n  只在副本集下有用。完成数据复制后，才返回结果
         *  w = majority 表示多数结点写入成功
         */
        'w' => 1,

        /**
         * 如果设置为 true, 同步到 journal (在提交到数据库前写入到实体中),才返回结果
         * majority 表示多数结点写入成功
         */
        'j' => 1,
        'wtimeout' => 30000, //写入超时时间，系统默认不超时。w>1才会起作用
    );

    protected $mongo;
    protected $dbName;
    protected $connString; //连接字符串
    protected $collection; //当前集合名称
    protected $namespace; //当前使用命名空间
    protected $query = array(
        '$' => array(), //查询相关的配置操作符
        'p' => array(
            'limit' => 1, //查询数量
            'skip' => 0, //偏移量
        ),
    );
    protected $write = array(
        '$' => array(), //更新操作符
        'p' => array(
            'multi' => false, //是否可以更新多条记录
            'upsert' => false, //记录不存在，是否插入
        ),
    );

    const SORT_ASC = 1;
    const SORT_DESC = -1;
    /**
     * 配置文件格式如下
     * array(
     *  'username' => '用户名',
     *  'password' => '密码',
     *  'server' => array(
     *      'ip:port'
     *  ),
     *  'options' => array(
     *      '选荐key' => '选项value'
     *  ),
     *  'db_name' => '数据库名称'
     * )
     * Mongo constructor.
     * @param $config
     */
    public function __construct($config)
    {
        if (!is_array($config)) {
            $config = \App::c($config);
        }
        $str = 'mongodb://';
        if ($config['username']) {
            $str .= "{$config['username']}:{$config['password']}@";
        }
        $str .= implode(',', $config['server']) . '/';
        if ($config['db_name']) {
            $str .= $config['db_name'];
            $this->dbName = $config['db_name'];
        }
        $this->options = array_merge($this->options, (array)$config['options']);
        $str .= '?' . http_build_query($this->options);
        $this->mongo = new Manager($str);
        $this->connString = $str;

        if (isset($config['options']['w'])) {
            $this->writeConcern['w'] = $config['options']['w'];
        }
        if (isset($config['options']['j'])) {
            $this->writeConcern['j'] = $config['options']['j'];
        }
        if (isset($config['options']['wtimeout'])) {
            $this->writeConcern['wtimeout'] = $config['options']['wtimeout'];
        }
    }

    /**
     * 设置当前集合名称
     * @param $collection
     * @return $this
     */
    public function collection($collection)
    {
        $this->collection = $collection;
        if ($this->dbName) {
            $this->namespace = "{$this->dbName}.{$this->collection}";
        } else {
            $this->namespace = $this->collection;
        }
        return $this;
    }

    /**
     * 执行数据插入
     * @param array|BulkWrite $data 一个key-value数组或一个BulkWrite对象
     * @param array $writeConcern 一个数组，可以包含w,j,wtimeout 3个参数
     * @return \MongoDB\Driver\WriteResult
     */
    public function insert($data, $writeConcern = array())
    {
        if (!($data instanceof BulkWrite)) {
            $bulk = new BulkWrite();
            $bulk->insert($data);
        } else {
            $bulk = $data;
        }

        $writeConcern = array_merge($this->writeConcern, $writeConcern);
        $writeConcernObject = $this->getWriteConcernObject($writeConcern);
        $result = $this->mongo->executeBulkWrite($this->namespace, $bulk, $writeConcernObject);
        return $result;
    }

    /**
     * 更新记录
     * @param array $data key=>value值或更新操作符组成的数组
     * @param array $filter 记录查找条件
     * @param array $options 更新选项。multi, upsert2个值
     * @param array $writeConcern 写入安全相关
     * @return \MongoDB\Driver\WriteResult
     */
    public function update($data = array(), $filter = array(), $options = array(), $writeConcern = array())
    {
        $this->write['$'] = array_merge($this->write['$'], $data);
        $this->write['p'] = array_merge($this->write['p'], $options);
        $this->query['$'] = array_merge($this->query['$'], $filter);

        if (!($data instanceof BulkWrite)) {
            $bulk = new BulkWrite();
            $bulk->update($this->query['$'], $this->write['$'], $this->write['p']);
        } else {
            $bulk = $data;
        }

        $writeConcern = array_merge($this->writeConcern, $writeConcern);
        $writeConcernObject = $this->getWriteConcernObject($writeConcern);
        $result = $this->mongo->executeBulkWrite($this->namespace, $bulk, $writeConcernObject);
        \Functions::showArr($result);
        return $result;
    }


    /**
     * 删除记录
     * @param array $filter 条件查询操作符
     * @param int $num 可以删除的数量
     * @param array $writeConcern 写入安全相关
     * @return \MongoDB\Driver\WriteResult
     */
    public function delete($filter = array(), $num = 1, $writeConcern = array())
    {
        $this->query['$'] = array_merge($this->query['$'], $filter);
        $bulk = new BulkWrite();
        $bulk->delete($this->query['$'], array('limit' => $num));

        $writeConcern = array_merge($this->writeConcern, $writeConcern);
        $writeConcernObject = $this->getWriteConcernObject($writeConcern);
        $result = $this->mongo->executeBulkWrite($this->namespace, $bulk, $writeConcernObject);
        \Functions::showArr($result);
        return $result;
    }

    public function query($filter = array(), $options = array())
    {
        $this->query['p'] = array_merge($this->query['p'], $options);
        $this->query['$'] = array_merge($this->query['$'], $filter);
        $query = new Query($this->query['$'], $this->query['p']);
        $cursor = $this->mongo->executeQuery($this->namespace, $query);
        return $cursor;
    }

    public function one($filter = array(), $options = array())
    {
        $this->limit(1);
        $cursor = $this->query($filter, $options);
        $res = $cursor->toArray()[0];
        return (array)$res;
    }

    public function all($filter = array(), $options = array())
    {
        $cursor = $this->query($filter, $options);
        $r = array();
        foreach ($cursor as $row) {
            $r[] = (array)$row;
        }
        return $r;
    }

    public function count($filter = array(), $options = array())
    {
        $this->query['p'] = array_merge($this->query['p'], $options);
        $this->query['$'] = array_merge($this->query['$'], $filter);
        $cursor = $this->execCommand(array(
            'count' => $this->collection,
            'query' => $this->query['$'],
        ));
        $res = $cursor->toArray()[0]->n;
        return $res;
    }

    /**
     * 创建一个 WriteConcern对象
     * @param $writeConcern
     * @return WriteConcern
     */
    public function getWriteConcernObject($writeConcern)
    {
        $o = new WriteConcern($writeConcern['w'], $writeConcern['wtimeout'], $writeConcern['j']);
        return $o;
    }

    /**
     * 返回一个写入，删除，更新的管理器
     * @return BulkWrite
     */
    public function getBulk()
    {
        return new BulkWrite();
    }

    public function execCommand($command)
    {
        $o = new Command($command);
        return $this->mongo->executeCommand($this->dbName, $o);
    }

    /**
     * == 查询操作符
     * @param $name
     * @param $value
     * @return $this
     */
    public function _eq($name, $value)
    {
        $this->query['$'][$name] = $value;
        return $this;
    }

    /**
     * (>) 大于操作符 - $gt
     * @param string $name 对象空间
     * @param string $value 值
     * @return $this
     */
    public function _gt($name, $value)
    {
        $this->query['$'][$name]['$gt'] = $value;
        return $this;
    }

    /**
     * （>=）大于等于操作符 - $gte
     * @param $name
     * @param $value
     * @return $this
     */
    public function _gte($name, $value)
    {
        $this->query['$'][$name]['$gte'] = $value;
        return $this;
    }

    /**
     *  (<) 小于操作符 - $lt
     * @param $name
     * @param $value
     * @return $this
     */
    public function _lt($name, $value)
    {
        $this->query['$'][$name]['$lt'] = $value;
        return $this;
    }

    /**
     * (<=) 小于操作符 - $lte
     * @param $name
     * @param $value
     * @return $this
     */
    public function _lte($name, $value)
    {
        $this->query['$'][$name]['$lte'] = $value;
        return $this;
    }

    /**
     * 设置读取记录条数
     * @param $num
     * @return $this
     */
    public function limit($num)
    {
        $this->query['limit'] = $num;
        return $this;
    }

    /**
     * 跳过的数据量
     * @param $num
     * @return $this
     */
    public function skip($num)
    {
        $this->query['skip'] = $num;
        return $this;
    }

    /**
     * 设置排序
     * @param $name
     * @param $type
     * @return $this
     */
    public function _sort($name, $type)
    {
        $this->query['sort'][$name] = $type;
        return $this;
    }

    /**
     * (!=) 不等于操作符 - $ne
     * @param $name
     * @param $value
     * @return $this
     */
    public function _ne($name, $value)
    {
        $this->query['$'][$name]['$ne'] = $value;
        return $this;
    }

    /**
     * (in) 指定值的操作符 - $in
     * @param $name
     * @param $value
     * @return $this
     */
    public function _in($name, $value)
    {
        $this->query['$'][$name]['$in'] = $value;
        return $this;
    }

    /**
     * (nin) 排除值操作符 - $nin
     * @param $name
     * @param $value
     * @return $this
     */
    public function _nin($name, $value)
    {
        $this->query['$'][$name]['$nin'] = $value;
        return $this;
    }

    /**
     * 求余操作符 - $mod
     * @example {
     *  'age': {
     *      '$mode' : {10, 1
     *   }
     * }表示 查找age除10模等于1的
     * @param $name
     * @param array $value 第一个为除数，第二个为余数
     * @return $this
     */
    public function _mod($name, $value)
    {
        $this->query['$'][$name]['$mod'] = $value;
        return $this;
    }

    /**
     * all操作，取name包含所有$value的信息
     * @param $name
     * @param $value
     * @return $this
     */
    public function _all($name, $value)
    {
        $this->query['$'][$name]['$all'] = $value;
        return $this;
    }

    /**
     * size操作字符，取name元素数和$size数相同的信息
     * @param $name
     * @param $value
     * @return $this
     */
    public function _size($name, $value)
    {
        $this->query['$'][$name]['$size'] = $value;
        return $this;
    }

    /**
     * 取name存在的信息
     * @param $name
     * @param $value
     * @return $this
     */
    public function _exists($name, $value)
    {
        $this->query['$'][$name]['$exists'] = $value;
        return $this;
    }

    /**
     * 值为指定类型
     * @param $name
     * @param $value
     * @return $this
     */
    public function _type($name, $value)
    {
        $this->query['%'][$name]['$type'] = $value;
        return $this;
    }

    public function _or($name, $value, $sign = null)
    {
        $this->query['where']['$and'][$name]['$or'] = 'tes';
    }


    /**
     * 排除法
     */
    public function not()
    {

    }

    /**
     * 正则表达式
     */
    public function regex()
    {

    }

    /**
     * set操作符。创建指定的键
     * 如果记录不存在， $set不会起作用
     * @param $name
     * @param $value
     * @return $this
     */
    public function _set($name, $value)
    {
        $this->write['$']['$set'][$name] = $value;
        return $this;
    }

    /**
     * 从文档中移出指定的键
     * @param $name
     * @param $value
     * @return $this
     */
    public function _unset($name, $value)
    {
        $this->write['$']['$unset'][$name] = $value;
        return $this;
    }

    /**
     * 文档指定的键，自增或自减。如果字段不存在，会创建。
     * @param $name
     * @param $value
     * @return $this
     */
    public function _inc($name, $value)
    {
        $this->write['$']['$inc'][$name] = $value;
        return $this;
    }

    /**
     * 重命名 value，key为现名，value为修改后的名
     * 事实，rename也可以执行移动操作
     * 如果字段不存在，数据无影响。更新操作会执行
     * @param $name
     * @param $value
     * @return $this
     */
    public function _rename($name, $value)
    {
        $this->write['$'][$name]['$rename'] = $value;
        return $this;
    }

    /**
     * 如果设置为true, 如果找不到匹配的记当。会执行插入操作
     * @param $value
     * @return $this
     */
    public function upsert($value)
    {
        $this->write['p']['upsert'] = $value;
        return $this;
    }

    /**
     * upsert选项执行insert操作时，$setOnInsert操作符给相应的字段赋值
     * @param $value
     * @return $this
     */
    public function _setOnInsert($value)
    {
        $this->write['$']['$setOnInsert'] = $value;
        return $this;
    }

    /**
     * 是否可以批量更新
     * 注意，如果为false, 但是查询到多条记录。会更新第一条。
     * @param $value
     * @return $this
     */
    public function multi($value)
    {
        $this->write['p']['multi'] = $value;
        return $this;
    }

    /**
     * 设置w值
     * @param $value
     * @return $this
     */
    public function w($value)
    {
        $this->writeConcern['w'] = $value;
        return $this;
    }

    /**
     * 设置j值
     * @param $value
     * @return $this
     */
    public function j($value)
    {
        $this->writeConcern['j'] = $value;
        return $this;
    }

    /**
     * 写入超时时间
     * @param $value
     * @return $this
     */
    public function wtimeout($value)
    {
        $this->writeConcern['wtimeout'] = $value;
        return $this;
    }
}