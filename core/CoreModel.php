<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/4/9
 * Time: 11:35
 * 基本模型类
 * 1. ERR_开始的常量用于这定义错误码 errArr变量用于定义错误码对应的错误消息。
 * 2. SCENE_开始的常量用于定义模型执行操场景
 * 3. EVENT_开始的常量用于定义事件。
 */
class CoreModel
{
    private static $_instance;
    protected $errmsg = ''; //错误消息
    protected $errno = 0; //错误码
    protected $scene; //模型当前使用场景。
    protected $errArr = array(); // 当前错误消息对应的错误码

    const SCENE_INSERT = 'insert'; //数据插入场景
    const SCENE_UPDATE = 'update'; //数据更新场景
    const SCENE_DELETE = 'delete'; //灵气删除场景
    const SCENE_SELECT = 'select'; //数据查询场景
    const SCENE_ANY = 'any'; //表示任意场景

    const EVENT_BEFORE_INSERT = 'before_insert'; //插入之前的事件
    const EVENT_AFTER_INSERT = 'after_insert'; //插入之后的事件。
    const EVENT_BEFORE_UPDATE = 'before_update'; //更新之前的事件
    const EVENT_AFTER_UPDATE = 'after_update'; //更新之后的事件
    const EVENT_BEFORE_DEL = 'before_del'; //删除之前的事件
    const EVENT_AFTER_DEL = 'after_del'; //删除之后的事件
    const EVENT_BEFORE_SAVE = 'before_save'; //执行save操作之间的事件
    const EVENT_AFTER_SAVE = 'after_save'; //执行save操作这后的事件

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {

    }

    /**
     * 得到表名,这个方法其实是CoreModel的别名。
     * 这里单独一个方法，主要是基于分表的考量
     * @return CoreMysql
     * @desc 此方法是模型的核心方法，用于设置查询表名
     * 设置模型场景，调用数据验证，字段设置等
     */
    public function from()
    {
        /**
         * 调用 tableName方法，基于分表的考虑,tableName
         * 方法可能有参数。这个参数，由from方法传递给tablName方法
         * php 5.2 func_get_args 不能直接作为函数的参数。
         */
        $params = func_get_args();
        $tableName = call_user_func_array(array($this, 'tableName'), $params);
        $fieldMap = call_user_func_array(array($this, 'fieldMap'), $params);
        if($fieldMap == false) {
            return $this->db()->from($tableName);
        } else {
            return $this->db()->from($tableName)->fieldAlias($fieldMap);
        }

    }

    /**
     * 设置表名
     * 说明，tableName方法支持传参。但是并不建议这样做。
     * 有些地方会自动调用tableName方法，这时候是没法传参的
     * @return string
     */
    public function tableName()
    {
        return get_class($this);
    }

    /**
     * 设置模型当前场景
     * @param $name
     * @return $this
     */
    public function scene($name)
    {
        $this->scene = $name;
        return $this;
    }

    /**
     * 得到模型的实例。所有实例都会保存在$_instance中。
     * 由于php 5.2不支持static延迟绑定。
     * 所以需要在子类中重载此方法。如果不重载，此函数只能返回
     * CoreModel类的对象，而不是子类的对象
     *  public static function getInstacne($className = __CLASS__)
     * {
     *   return parent::getInstacne($className);
     * }
     * @param string $name
     * @return static
     */
    public static function getInstance($name = __CLASS__)
    {
        if(!isset(self::$_instance[$name])) {
            self::$_instance[$name] = new $name();
        }
        return self::$_instance[$name];
    }

    /**
     * 得到mysql数据库连接
     * @param $name
     * @return CoreMysql
     */
    public function db($name = null)
    {
        $name = $name === null ? 'db.main' : $name;
        return CoreMysql::getInstance($name);
    }

    /**
     * 得到redis数据库连接
     * @param null $name
     * @return CoreRedis
     */
    public function redis($name = null)
    {
        $name = $name === null ? 'redis.main' : $name;
        return CoreRedis::getInstance($name);
    }

    /**
     * 得到数据库错误或模型错误消息
     * @return string
     */
    public function getDbErrmsg()
    {
        $error = $this->db()->getError(); //尝试获取数据库错误
        if ($error == '') {
            $error = $this->errmsg; //尝试得到显示设置的错误
        }
        return $error;
    }

    /**
     * 得到最后一次执行的sql语句
     * @return string
     */
    public function getSql()
    {
        return $this->db()->getSql();
    }

    /**
     * 得到最后插入的sql
     * @return string
     */
    public function lastId()
    {
        return $this->db()->lastId();
    }

    /**
     * 根据主键查找记录
     * @param $id
     * @return array 返回一维或二维数组
     */
    public function findById($id)
    {
        return $this->from()->findById($id);
    }

    /**
     * 更新记录
     * @param array $data 数组，key为字段，value为值
     * @param string $where where条件
     * @param array $params where的绑定参数
     * @return bool
     */
    public function update($data, $where, $params = array())
    {
        return $this->from()->update($data, $where, $params);
    }

    /**
     * @param $data
     * @param $id
     * @return bool
     */
    public function updateById($data, $id)
    {
        return $this->from()->updateById($data, $id);
    }

    public function updateByAttr($data, $findAttr, $multi = false)
    {
        return $this->from()->updateByAttr($data, $findAttr, $multi);
    }

    /**
     * 删除记录
     * @param $where
     * @param array $params
     * @return bool
     */
    public function delete($where, $params = array())
    {
        return $this->from()->delete($where, $params);
    }

    /**
     * @param $findAttr
     * @param bool $multi
     * @return bool|int
     * @throws Exception
     */
    public function deleteByAttr($findAttr, $multi = false)
    {
        return $this->from()->deleteBydAttr($findAttr, $multi);
    }

    /**
     * 根据主键删除记录
     * @param $ids
     * @return 返回受影响行数或false
     */
    public function delById($ids)
    {
        return $this->from()->delById($ids);
    }

    /**
     * 插入数据
     * @param $data
     * @return bool|int
     */
    public function insert($data)
    {
        return $this->from()->insert($data);
    }

    /**
     * 根据where条件查找一条记录
     * @param $where
     * @param array $params
     * @return bool|mixed
     */
    public function one($where, $params = array())
    {
        return $this->from()->where($where)->params($params)->one();
    }

    /**
     * 查找多条记录
     * @param $where
     * @param string $order
     * @param string $limit
     * @param array $params
     * @return array|bool
     */
    public function all($where = '1', $order = '', $limit = '', $params = array())
    {
        return $this->from()
            ->where($where)
            ->order($order)
            ->limit($limit)
            ->params($params)
            ->all();
    }

    /**
     * 根据sql查找一条记录
     * @param $sql
     * @return bool|mixed
     */
    public function findOneBySql($sql)
    {
        return $this->from()->one($sql);
    }

    /**
     * 根据sql查找多条记录
     * @param $sql
     * @return array|bool
     */
    public function findAllBySql($sql)
    {
        return $this->from()->all($sql);
    }

    /**
     * 得到记录总数。
     * @param $where
     * @param array $params
     * @return bool|int
     */
    public function count($where = '', $params = array())
    {
        return $this->from()->count($where, $params);
    }

    /**
     * 计算一个字段值的和
     * @param $field
     * @param string $where
     * @param string $params
     * @return string
     */
    public function sum($field, $where = '', $params = '')
    {
        return $this->from()->sum($field, $where, $params);
    }

    /**
     * @param $data
     * @param array $findAttr
     * @param bool $mulit
     * @return bool
     */
    public function save($data, $findAttr = array() , $mulit = false)
    {
        return $this->from()->save($data, $findAttr, $mulit);
    }

    /**
     * @param $attr
     * @param int $num
     * @return array|bool
     */
    public function findByAttr($attr, $num = 1)
    {
        return $this->from()->findByAttr($attr, $num);
    }

    /**
     * 开启一个复杂的查询。
     * @return CoreMysql
     */
    public function beginQuery()
    {
        return $this->from();
    }

    /**
     * @param $data
     * @param string $type
     * @return mixed
     */
    public function bindNull($data, $type = 'del')
    {
        return $this->db()->bindNull($data, $type);
    }

    /**
     * 开始一个事务
     * @return bool
     */
    public function beginTransaction()
    {
        return $this->db()->beginTransaction();
    }

    /**
     * 提交
     * @return bool
     */
    public function commit()
    {
        return $this->db()->commit();
    }

    /**
     * 回滚事务
     * @return bool
     */
    public function rollBack()
    {
        return $this->db()->rollBack();
    }

    /**
     * 得到数据库的错误消息
     * @return string
     */
    public function getDbError()
    {
        return $this->db()->getError();
    }

    /**
     * 得到Model的错误消息
     * @return string
     */
    public function getModelErrmsg()
    {
        $msg = $this->errmsg;
        if ($msg == '') {
            $msg = $this->errArr[$this->errno];
        }
        return $msg;
    }

    /**
     * 得到模型的错误码。
     * @return int
     */
    public function getModelErrno()
    {
        return $this->errno;
    }

    /**
     * @see CoreMysql::andFilter
     * @param $field
     * @param $op
     * @param $value
     * @return CoreMysql
     */
    public function andFilter($field, $op, $value)
    {
        return  $this->from()->addFilter($field, $op, $value, 'and');
    }

    /**
     * @see CoreMysql::orFilter
     * @param $field
     * @param $op
     * @param $value
     * @return CoreMysql
     */
    public function orFilter($field, $op, $value)
    {
        return $this->from()->addFilter($field, $op, $value, 'or');
    }

    /**
     * @param $where
     * @param $params
     */
    public function endFilter(&$where, &$params)
    {
        $this->from()->endFilter($where, $params);
    }

    /**
     * 得到模型的错误码
     * @return mixed
     */
    public function getErrno()
    {
        return $this->errno;
    }

    /**
     * 设置错误码
     * @param $no
     * @return false
     */
    public function setErrno($no)
    {
        $this->errno = $no;
        return false;
    }

    /**
     * 执行非查询的sql语句
     * @param $sql
     * @param array $params
     * @return 返回受影响行数或false
     */
    public function exec($sql, $params = array())
    {
        return $this->db()->exec($sql, $params);
    }

    /**
     * 执行一个查询sql语句。这个方法返回PDOStatement对象
     * 而不是数组。返回数组请使用all，one方法
     * @param $sql
     * @param $params
     * @return bool|PDOStatement
     */
    public function query($sql, $params)
    {
        return $this->db()->query($sql, $params);
    }

    /**
     * @return array
     */
    public function label()
    {
        return $this->from()->label();
    }

    /**
     * 设置查询出来的字段映射
     * 此方会被from方法自动发起调用
     * @return array
     */
    public function fieldMap()
    {
        return array();
    }

    /**
     * 清除sql缓存。
     * 正常执行的sql会自动清除，如果出错。需要手动清除
     */
    public function clearSqlQuery()
    {
        $this->db()->clearSqlQuery();
    }

    /**
     * 按周分表。
     * @param $prefix
     * @param $stamp
     * @return string
     */
    public function createTableByWeek($prefix, $stamp = null)
    {
        $stamp = $stamp !== null ? $stamp : time();
        $tableName = $prefix . date('Y_W', $stamp); //按周分表
        if(date('w') != '1') {
            return $tableName;
        }
        //每周星期一创建表
        $key = $tableName . '_create_lock';
        if ($this->redis()->get($key)) {
            return $tableName;
        }
        $prevTableName = $prefix . date('Y_W', Timer::weekStamp(-1));
        $sql = "create table if not exists {$tableName} like {$prevTableName}";
        $this->redis()->setex($key, Timer::yesterdayStamp(), 1);
        $this->db()->exec($sql);
        return $tableName;
    }

    /**
     * 按月分表
     * @param $prefix
     * @param $stamp
     * @return string
     */
    public function createTableByMonth($prefix, $stamp = null)
    {
        $stamp = $stamp !== null ? $stamp : time();
        $tableName = $prefix . date('Y_m', $stamp); //按月分表
        if(date('d') != '01') {
            return $tableName;
        }
        //每周星期一创建表
        $key = $tableName . '_create_lock';
        if ($this->redis()->get($key)) {
            return $tableName;
        }
        $prevTableName = $prefix . date('Y_m', Timer::monthStamp(-1));
        $sql = "create table if not exists {$tableName} like {$prevTableName}";
        $this->redis()->setex($key, Timer::DAY_SECOND, 1);
        $this->db()->exec($sql);
        return $tableName;
    }

    /**
     * 按每隔几天创建分表。
     * @param $prefix
     * @param $mod
     * @param $stamp
     * @return string
     */
    public function createTableByDay($prefix, $mod = 1, $stamp = null)
    {
        $stamp = $stamp !== null ? $stamp : time();
        $tableName = $prefix . date('Y_z', $stamp);
        if(date('z') % $mod != 0) {
            return $tableName;
        }
        //每隔固定天数创建表
        $key = $tableName . '_create_lock';
        if ($this->redis()->get($key)) {
            return $tableName;
        }
        $prevTableName = $prefix . date('Y_z', Timer::dayStamp(-1 * $mod));
        $sql = "create table if not exists {$tableName} like {$prevTableName}";
        $this->redis()->setex($key, Timer::yesterdayStamp(), 1);
        $this->db()->exec($sql);
        return $tableName;
    }

    public function getNextAutoIncrement()
    {
        $name = $this->tableName();
        return $this->db()->getNextAutoIncrement($name);
    }

    /**
     * 定义校验规则。格式为一个二维数组。
     * key为要校验的元素名称 callback回调
     * errno为错误码
     * errmsg为错误消息
     * 其它参数为回调函数的参数，这参名字必须和回调函数一样。顺序可以打乱。
     * @example $rules = array(
     * array(
     * 'name' => 'mid', //要校验的元素下标
     * 'callback' => array('CoreValidate', 'required') , //校验回调函数
     * 'errno' => 10, //错误码
     * 'errmsg' => '用户ID不能为空', //错误消息
     * 'on' => self::SCENE_INSERT, //执行场景
     * )
     * )
     * @param string $scene 当前场景，不同的场景，校验的元素可能会有所不同。
     * @param array $data 需要校验的数据，有些情况，需要比较data中参数时需要传入
     * @return array
     */
    public function rules($scene = null, &$data = array())
    {
        return array();
    }

    /**
     * 执行校验证
     * 如果当前元素没有声明规则。则认为元素值是安全的，不用校验
     * @TODO 没有实现一个参数，多个验证方法。也没实现多个字段共同验证一个方法
     * @TODO 以上2种需要在模型层实现数据对象化后才好实现。
     * @param array $data 要校验的数据。key为元素名，value为值。
     * @param string $scene
     * @param bool $batch 是否批量校验数据
     * @return bool
     */
    public function check(&$data, $scene = self::SCENE_ANY, $batch = false)
    {
        $rules = $this->rules($scene, $data); //得到当前场景的验证。
        $flag = true; //除非验证失败，不然都是成功
        foreach ($rules as $name => $config) {
            $name = $config['name'];
            $on = (array)$config['on'];
            $config['value'] = $data[$name]; //得到当前校验值参数
            if (in_array($scene, $on) == false && $scene != self::SCENE_ANY) {
                continue; //不在当前场景中
            }

            //组织校验参数，这一步主要为了保证函数参数按顺序传递
            $params = CoreReflection::getMethodParam($config['callback']);
            foreach ($params as $key => $item) {
                if (isset($config[$key])) {
                    $params[$key] = $config[$key];
                }
            }

            $checkFlag = call_user_func_array($config['callback'], $params);
            if ($checkFlag == false) {
                $flag = false;
                $this->errmsg = $config['errmsg'];
                $this->errno = $config['errno'];
                $this->errArr[$name] = array(
                    'errmsg' => $this->errmsg,
                    'errno' => $this->errno
                );
                if ($batch == false) {
                    return false; //如果不是批量验证。那么返回错误
                }
            }
        }
        return $flag;
    }

    /**
     * 得到模型的最后一次错误
     * @param $code
     * @param $msg
     * @return array
     */
    public function getLastError(&$code, &$msg)
    {
        $code = $this->errno;
        $msg = $this->errmsg;
        return compact('code', 'msg');
    }

    /**
     * 为当前类注册一个事件
     * @param string $name 事件名称
     * @param string $callback 事件处理函数
     * @param array $data 事件处理的数据。
     */
    public function on($name, $callback, $data = array())
    {
        Event::on($this, $name, $callback, $data);
    }

    /**
     * 执行当前模型的事件
     * @param $name
     * @param Event $event
     */
    public function trigger($name, $event = null)
    {
        Event::trigger($this, $name, $event);
    }

    public function setErrMode($mode)
    {
        $this->db()->setAttr(PDO::ATTR_ERRMODE, $mode);
    }

    /**
     * 得到当前表主键的名称
     * @return mixed
     */
    public function getPkName()
    {
        $field = $this->from()->getField();
        return $field['pk'];
    }

    /**
     * @param $name
     * @return string
     */
    public function _prefix($name)
    {
        return $this->redis()->_prefix($name);
    }
}