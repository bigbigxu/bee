<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/6/12
 * Time: 11:47
 * sqlite 封装类
 * 使用上和BeeMysql类一样
 */
namespace bee\core;

use bee\App;
use Exception;
use PDO;
use PDOStatement;

class BeeSqlite
{
    /**
     * @var PDO
     */
    private $_pdo;
    protected $tableName; /* 表示当前查询的主表名 */
    protected $tableNameAlias = 't'; /* 当前主表的别名，默认为t */
    private $_sql; /* 当前执行的SQL语句 */
    protected $error = ''; /* 当前执行sql的错误消息 */
    protected $prefix = ''; /* 表前缀 */
    protected $transactions = 0; /* 当前是否在执行事务 */
    protected $sqlQuery = array(
        'field' => '*',
        'where' => '1',
        'join' => '',
        'group' => '',
        'having' => '',
        'order' => '',
        'limit' => '',
        'union' => '',
        'params' => [],
    );
    protected $lastSqlQuery = []; /* 保存上一次执行申请sql参 */
    protected $allTableColumns = array(); /* 当前表所有的字段名称 */
    /**
     * @var static[]
     */
    private static $_instance = array();

    protected $dsn; /* 驱动dsn */
    protected $dbPath; /* 数据库文件名 */
    protected $k; /* 当前数据库连接标识符 */

    /* PDO链接属性数组 */
    protected $attr = array(
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    );
    protected $paramCount = 0; /* 自定义参编号 */

    /**
     * 由本类自动生成的绑定参数前缀，用于区别用户绑定参数
     * @var string
     */
    protected $autoBindPrefix = 'bee_auto_';
    /**
     * getTableColumns中主键的名字
     */
    const PK_MARK = '@pk';

    /**
     * 字段缓存使用的组件名称，如果不想使用，设置为false。
     * @var string|object|array
     */
    protected $cache = false;
    /**
     * 实例化传递的配置文件
     * @var array
     */
    protected $dbConfig;

    /**
     * 构造方法
     * config 应该包含如下配置：
     * [
     *    'dsn' => '数据库驱动',
     *    'cache' => '字段缓存使用的组件名',
     *    'attr' => '属性',
     *    'prefix' => '表前缀'
     * ]
     * @param array $config 配置文件
     */
    public function __construct($config)
    {
        $this->dbConfig = $config;
        $map = [
            'dsn' => 'dsn',
            'cache' => 'cache',
            'attr' => 'attr',
            'prefix' => 'prefix',
        ];

        /* 对象成员赋值 */
        foreach ($map as $key => $row) {
            if (isset($config[$key])) {
                if ($key == 'attr') {
                    $this->attr = $config[$key] + $this->attr;
                } else {
                    $this->$row = $config[$key];
                }
            }
        }

        /* 获取数据库路径 */
        $tmp = explode(':', $this->dsn);
        $this->dbPath = $tmp[1];
        /*　如果使用磁盘系统，创建目录 */
        if ($this->dbPath != ':memory:') {
            $dir = dirname($this->dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
    }

    /**
     * 打开pdo mysql连接，并且返回pdo对象
     * @return PDO
     */
    public function connect()
    {
        if ($this->_pdo !== null) {
            return $this->_pdo; /* 连接已经打开 */
        }
        $this->_pdo = null;
        $this->_pdo = new PDO($this->dsn, '', '', $this->attr);
        return $this->_pdo;
    }

    /**
     * @param $config
     * @return BeeSqlite
     */
    public static function getInstance($config)
    {
        if (!is_array($config)) {
            $config = App::c($config);
        }
        $pid = intval(getmypid());
        $k = md5($config['dsn'] . $pid);

        /* 如果连接没有创建 */
        if (!isset(self::$_instance[$k])) {
            self::$_instance[$k] = null;
            self::$_instance[$k] = new self($config);
            self::$_instance[$k]->k = $k;
        }
        return self::$_instance[$k];
    }

    /**
     *
     * @param string $tableName
     * @param string $alias 表别名
     * @return $this
     */
    public function from($tableName, $alias = 't')
    {
        $this->tableName = $this->prefix . $tableName;
        $this->tableNameAlias = $alias;
        return $this;
    }

    /**
     * @return PDO
     */
    public function getPdo()
    {
        return $this->connect();
    }

    /**
     * 得到当前sql语句，并完成参数替换
     * @return string
     */
    public function getSql()
    {
        return $this->_sql;
    }

    /**
     * 查找符合条件的所有记录
     * @param string $sql
     * @param array $params
     * @return array|bool
     */
    public function all($sql = '', $params = array())
    {
        $stmt = $this->query($sql, $params);
        if (!$stmt) {
            return false;
        }
        return $stmt->fetchAll();
    }

    /**
     * 查找一条记录
     * @param string $sql
     * @param array $params
     * @return bool|mixed
     */
    public function one($sql = '', $params = array())
    {
        $this->sqlQuery['limit'] = 1;
        $stmt = $this->query($sql, $params);
        if (!$stmt) {
            return false;
        }
        return $stmt->fetch();
    }

    /**
     * 根据主键查找记录
     * @param mixed $ids
     * @return array 返回一维或二维数组
     */
    public function findById($ids)
    {
        $pk = $this->getTableColumns(self::PK_MARK);
        if (!is_array($ids)) {
            $res = $this->andFilter($pk, '=', $ids)->one();
        } else {
            $res = $this->andFilter($pk, 'in', $ids)->all();
        }
        return $res;
    }

    /**
     * 插入数据的方法，自动完成参数绑定
     * @param  array $data 一维数组 array(Field=>value)
     * @return boolean | int
     */
    public function insert($data)
    {
        $columns = $this->getTableColumns();
        $params = array();
        $field = array();
        $placeholder = array();
        foreach ($data as $key => $row) {
            /* 删除非法字段信息 */
            if ($columns[$key] === null) {
                continue;
            }
            $params[':' . $key] = $row;
            $field[] = "`{$key}`";
            $placeholder[] = ':' . $key;
        }
        /* 插入当前记录 */
        $sql = "insert into {$this->tableName} (" . implode(', ', $field) . ') values (' .
            implode(', ', $placeholder) . ')';
        $this->sqlQuery['params'] = $params;
        $this->_sql = $sql;
        return $this->exec($sql, $this->sqlQuery['params']);
    }

    /**
     * 保存或者更新记录。
     * @TODO 不支持联合主键
     * @param array $data 要更新的数据
     * @param array $findAttr 需要匹配的记录，说明只能是and =的匹配
     * @param bool $multi 是否可以批量更新
     * @throws Exception
     * @return bool|int
     */
    public function save($data, $findAttr = array(), $multi = false)
    {
        $pk = $this->getTableColumns(self::PK_MARK);
        $findAttr = (array)$findAttr;
        if ($findAttr == false) {
            $findAttr = array($pk);
        }
        foreach ($findAttr as $row) {
            $this->andFilter($row, '=', $data[$row]);
        }
        $this->endFilter($where, $params);

        $count = $this->count($where, $params);
        if ($count == 1) {
            $rowCount = $this->update($data, $where, $params);
        } elseif ($count == 0) {
            $rowCount = $this->insert($data);
        } else {
            if ($multi) {
                $rowCount = $this->update($data, $where, $params);
            } else {
                throw new Exception('可能更新多条记录：sql=' . $this->getSql());
            }
        }
        return $rowCount;
    }

    /**
     * 根据一个或多个属性查找记录
     * @param $attr
     * @param int $num 查找的数量，如果为1，将返回一个一维数据，
     * @return array|bool
     */
    public function findByAttr($attr, $num = 1)
    {
        foreach ($attr as $key => $row) {
            $this->andFilter($key, '=', $row);
        }
        if ($num == 1) {
            $method = 'one';
        } else {
            $method = 'all';
        }
        return $this->$method();
    }

    /**
     * 删除记录
     * @param string $where where条件
     * @param array $params 绑定参数
     * @return bool
     */
    public function delete($where = '', $params = array())
    {
        if ($where != '') {
            $this->sqlQuery['where'] = $where;
        }
        if ($params != false) {
            $this->sqlQuery['params'] = $params;
        }
        $sql = "delete from {$this->tableName} where {$this->sqlQuery['where']}";
        $this->_sql = $sql;
        return $this->exec($sql, $this->sqlQuery['params']);
    }

    /**
     * 根据属性值删除记录
     * @param array $findAttr 要查找的key value属性
     * @param bool $multi 是否可以删除多个
     * @throws Exception
     * @return bool|int
     */
    public function deleteByAttr($findAttr, $multi = false)
    {
        /* 基于安全考虑,做一个强制转换 */
        $findAttr = (array)$findAttr;
        if ($findAttr == false) {
            return false;
        }
        foreach ($findAttr as $key => $row) {
            $this->andFilter($key, '=', $row);
        }
        $this->endFilter($where, $params);

        $count = $this->count($where, $params);
        if ($count > 1 && $multi == false) {
            throw new Exception('可能删除多条记录：sql=' . $this->getSql());
        }
        return $this->delete($where, $params);
    }

    /**
     * 简化的delete()方法，基于主键的删除
     * @param mixed $ids
     * @return mixed
     */
    public function delById($ids)
    {
        $pk = $this->getTableColumns(self::PK_MARK);
        if (!is_array($ids)) {
            $this->andFilter($pk, '=', $ids)->endFilter($where, $params);
        } else {
            $this->andFilter($pk, 'in', $ids)->endFilter($where, $params);
        }
        return $this->delete($where, $params);
    }

    /**
     * 得到插入的最后ID号
     */
    public function lastId()
    {
        return $this->_pdo->lastInsertId();
    }

    /**
     * 修改数据 update 支持参数绑定 只支持where参数
     * @param array $data 要改变的列的值数组 array(列名=>值)
     * @param  string $where where条件
     * @param  array $params 绑定参数
     * @return boolean ｜ int 受影响的行数
     */
    public function update($data, $where = '', $params = array())
    {
        $columns = $this->getTableColumns();
        if (!is_array($data)) {
            return false;
        }
        if ($where != '') {
            $this->sqlQuery['where'] = $where;
        }
        if (!empty($params)) {
            $this->sqlQuery['params'] = $params;
        }
        $updateField = array();
        foreach ($data as $key => $value) {
            /* 不合法的字段不要 */
            if ($columns[$key] === null) {
                continue;
            }
            /* 自动组织的params参数要避免与用户传的绑定参数一样。*/
            $bindName = $this->getAutoBindParam();
            $updateField[] = "`{$key}`={$bindName}";
            $this->sqlQuery['params'][$bindName] = $value;
        }
        $sql = "update {$this->tableName} set " . implode(',', $updateField)
            . " where {$this->sqlQuery['where']}";
        $this->_sql = $sql;
        return $this->exec($sql, $this->sqlQuery['params']);
    }

    /**
     * 根据主键值更新记录
     * @param $data
     * @param $id
     * @return bool
     */
    public function updateById($data, $id)
    {
        $pk = $this->getTableColumns(self::PK_MARK);
        return $this->andFilter($pk, '=', $id)->update($data);
    }

    /**
     * 根据属性值更新记录
     * @param $data
     * @param array $findAttr 要查找的数组。值在data数组中
     * @param bool $multi
     * @throws Exception
     * @return bool
     */
    public function updateByAttr($data, $findAttr, $multi = false)
    {
        /* 基于安全考虑,做一个强制转换 */
        $findAttr = (array)$findAttr;
        if ($findAttr == false) {
            return false;
        }
        foreach ($findAttr as $row) {
            $this->andFilter($row, '=', $data[$row]);
            unset($data[$row]); /* 如果属性在findAttr中那么不需要更新。 */
        }
        $this->endFilter($where, $params);
        $count = $this->count($where, $params);

        if ($count > 1 && $multi == false) {
            throw new Exception('可能更新多条记录：sql=' . $this->getSql());
        }
        return $this->update($data, $where, $params);
    }

    /**
     * 设置数据表的所有字段信息
     * @param $name
     * @return array|string
     */
    public function getTableColumns($name = null)
    {
        $field = [];
        $key = array(
            __CLASS__,
            $this->dsn,
            $this->tableName,
        );
        $cache = $this->getCache();
        /* 尝试从缓存中找到数据 */
        if ($cache) {
            $field = $cache->get($key);
        }

        /* 从db查数据，并保存缓存 */
        if ($field == false) {
            $sql = "PRAGMA table_info(`{$this->tableName}`)";
            $res = $this->_execForMysql($sql)->fetchAll();
            foreach ($res as $row) {
                if ($row['pk'] == '1') {
                    $field[self::PK_MARK] = $row['name'];
                }
                $field[$row['name']] = $row['name'];
            }
            if ($cache) {
                $cache->set($key, $field);
            }
        }
        if ($name === null) {
            return $field;
        } else {
            return $field[$name];
        }
    }

    //得到记录总数
    public function count($where = '', $params = array())
    {
        $this->sqlQuery['field'] = 'count(*) as c';
        if ($where != false) {
            $this->sqlQuery['where'] = $where;
        }
        if ($params != false) {
            $this->sqlQuery['params'] = $params;
        }
        $res = $this->one();
        return intval($res['c']);
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
        $this->field("sum($field) as c");
        if ($where) {
            $this->where($where);
        };
        if ($params) {
            $this->params($params);
        }
        $res = $this->one();
        return (int)$res['c'];
    }

    public function getError()
    {
        return $this->error;
    }

    public function setError($error)
    {
        $this->error = $error;
    }

    /**
     * 扫行有结果集的查询，支持参数绑定
     * 如果你需要遍历数据库，请使用query方法，然后foreach 返回的stmt对象便可。
     * @param mixed $sql
     * @param array $params
     * @return boolean|PDOStatement
     */
    public function query($sql = '', $params = array())
    {
        $sql = $this->joinSql($sql);
        if ($params != false) {
            $this->sqlQuery['params'] = $params;
        }
        $params = $this->sqlQuery['params'];
        $this->clearSqlQuery();
        $stmt = $this->_execForMysql($sql, $params);
        return $stmt;
    }

    /**
     * 执行一个mysql语句
     * @param $sql
     * @param array $params
     * @return bool|PDOStatement
     */
    private function _execForMysql($sql, $params = [])
    {
        $pdo = $this->connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $errorInfo = $stmt->errorInfo();
        if ($errorInfo[0] != '00000') {
            $this->setError($errorInfo[2]);
            return false;
        } else {
            return $stmt;
        }
    }

    /**
     * 执行没有结果集的查询,支持参数绑定
     * @param string $sql
     * @param array $params
     * @return bool|int 返回受影响行数或false
     */
    public function exec($sql, $params = array())
    {
        if ($params != false) {
            $this->sqlQuery['params'] = $params;
        }
        $params = $this->sqlQuery['params'];
        $this->clearSqlQuery();
        $stmt = $this->_execForMysql($sql, $params);
        if ($stmt == false) {
            return false;
        }
        return $stmt->rowCount();
    }

    /**
     * 设置pdo的相关属性
     * @param $name
     * @param $value
     * @return $this
     */
    public function setAttr($name, $value)
    {
        $this->attr[$name] = $value;
        return $this;
    }


    /**
     * 设置绑定参数
     * @param $params
     * @return $this
     */
    public function params($params)
    {
        $this->sqlQuery['params'] = empty($params) ? array() : $params;
        return $this;
    }

    /**
     * 组合sql语句
     * @param mixed $sql
     * @return string 返回组合的sql语句
     */
    public function joinSql($sql)
    {
        /* 本身就是一个sql语句 */
        if (is_string($sql) && $sql != '') {
            $this->_sql = $sql;
            return $sql;
        } elseif (is_array($sql) && $sql != '') {
            foreach ($sql as $key => $row) {
                if (!array_key_exists($key, $this->sqlQuery)) {
                    continue;
                }
                $this->sqlQuery[$key] = $row;
            }
        }
        $nameString = "{$this->tableName} {$this->tableNameAlias}";
        $this->_sql = "select {$this->sqlQuery['field']} from {$nameString}\n";
        if ($this->sqlQuery['join'] != '') {
            $this->_sql .= "{$this->sqlQuery['join']} ";
        }
        $this->_sql .= "where {$this->sqlQuery['where']}\n";
        if ($this->sqlQuery['group'] != '') {
            $this->_sql .= "group by {$this->sqlQuery['group']}\n";
        }
        if ($this->sqlQuery['having'] != '') {
            $this->_sql .= "having {$this->sqlQuery['having']}\n";
        }
        if ($this->sqlQuery['order'] != '') {
            $this->_sql .= "order by {$this->sqlQuery['order']}\n";
        }
        if ($this->sqlQuery['limit'] != '') {
            $this->_sql .= "limit {$this->sqlQuery['limit']}\n";
        }
        if ($this->sqlQuery['union'] != '') {
            $this->_sql .= "union {$this->sqlQuery['union']}\n";
        }
        if ($this->sqlQuery['lock'] != '') {
            $this->_sql .= " {$this->sqlQuery['lock']}";
        }
        return $this->_sql;
    }

    /**
     * 设置select字段
     * @param $field
     * @return $this
     */
    public function field($field)
    {
        $this->sqlQuery['field'] = $field ?: '*';
        return $this;
    }

    /**
     * 设置字段映射
     * @param array $map
     * @return BeeMysql
     * @example $map = array(
     *  'id' => 'mid', //id使用mid表示
     *  'name', //仅表示查询此字段
     * )
     * @desc 此方法会调用map()方法。
     */
    public function fieldAlias($map = array())
    {
        if ($map == false) {
            $map = $this->fieldMap();
        }
        if ($map == false) {
            return $this;
        }
        $fieldArr = '';
        foreach ($map as $key => $value) {
            /* 如果是一个整数，表示不取别名 */
            if (is_int($key)) {
                $fieldArr[] = $this->addUnquoteForField($value);
            } else {
                $key = $this->addUnquoteForField($key);
                $fieldArr[] = "{$key} as `{$value}`";
            }
        }
        $field = implode(',', $fieldArr);
        return $this->field($field);
    }

    /**
     * 给一个字段加上反引号
     * 此方法主要为了解决a.name之类的形式需要变为a.`name`
     * @param $field
     * @return mixed|string
     */
    public function addUnquoteForField($field)
    {
        $field = str_replace('.', '.`', $field, $count);
        if ($count >= 1) {
            $field .= '`';
        } else {
            $field = "`{$field}`";
        }
        return $field;
    }

    /**
     * 定义字段别名
     * @return array
     */
    public function fieldMap()
    {
        return array();
    }

    /**
     * 设置where条件
     * @param $where
     * @return $this
     */
    public function where($where)
    {
        $this->sqlQuery['where'] = $where;
        return $this;
    }

    /**
     * @param $tableName
     * @param $condition
     * @param $alias
     * @return $this
     */
    public function join($tableName, $condition, $alias = '')
    {
        $this->sqlQuery['join'] .= "join {$tableName} {$alias} on {$condition}\n";
        return $this;
    }

    /**
     * @param $tableName
     * @param $condition
     * @param $alias
     * @return $this
     */
    public function leftjoin($tableName, $condition, $alias = '')
    {
        $this->sqlQuery['join'] .= "left join {$tableName} {$alias} on {$condition}\n";
        return $this;
    }

    /**
     * @param $tableName
     * @param $condition
     * @param $alias
     * @return $this
     */
    public function rightjoin($tableName, $condition, $alias = '')
    {
        $this->sqlQuery['join'] .= "right join {$tableName} {$alias} on {$condition}\n";
        return $this;
    }

    /**
     * @param $group
     * @return $this
     */
    public function group($group)
    {
        $this->sqlQuery['group'] = $group;
        return $this;
    }

    /**
     * @param $having
     * @return $this
     */
    public function having($having)
    {
        $this->sqlQuery['having'] = $having;
        return $this;
    }

    /**
     * @param $order
     * @return $this
     */
    public function order($order)
    {
        $this->sqlQuery['order'] = $order;
        return $this;
    }

    /**
     * @param $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->sqlQuery['limit'] = $limit;
        return $this;
    }


    /**
     * 这个方法是limit方法的变种
     * @param int $page 当前页
     * @param int $pageSize 每页数量
     * @return $this
     */
    public function page($page = 0, $pageSize = 20)
    {
        $pageSize = $pageSize <= 0 ? 20 : $pageSize;
        $page = $page <= 0 ? 1 : $page;
        $offset = ($page - 1) * $pageSize;
        $this->limit("{$offset},{$pageSize}");
        return $this;
    }

    /**
     * @param $union
     * @return $this
     */
    public function union($union)
    {
        $this->sqlQuery['union'] = $union;
        return $this;
    }

    /**
     * 清除sql缓存
     */
    public function clearSqlQuery()
    {
        /* 清除缓存前，先保存当前sql语句 */
        if (!empty($this->sqlQuery['params'])) {
            foreach ($this->sqlQuery['params'] as $key => $param) {
                $this->_sql = str_replace($key, '"' . $param . '"', $this->_sql);
            }
        }
        $this->lastSqlQuery = $this->sqlQuery;
        $this->sqlQuery = [
            'field' => '*',
            'where' => '1',
            'join' => '',
            'group' => '',
            'having' => '',
            'order' => '',
            'limit' => '',
            'union' => '',
            'params' => [],
        ];
    }

    /**
     * 关闭连接
     */
    public function close()
    {
        $this->_pdo = null;
    }

    /**
     * 关闭所有连接
     */
    public static function closeAll()
    {
        foreach (self::$_instance as $o) {
            if ($o instanceof self) {
                $o->close();
            }
        }
        self::$_instance = [];
    }


    /**
     * 得到当前最大的id
     * @param $field
     * @return mixed
     */
    public function max($field)
    {
        $sql = "select max({$field})as max_id from {$this->tableName}";
        $res = $this->one($sql);
        return $res['max_id'];
    }

    /**
     * 每数组每一个元素添加引号
     * @param $item
     * @return string
     */
    public function addQuot($item)
    {
        return "'{$item}'";
    }

    /**
     * 为数组每一个元素添加反引号
     * @param $item
     * @return string
     */
    public function addRquot($item)
    {
        return "`{$item}`";
    }

    /***************事务相关******************/

    /**
     * 开启事务，并设置错误模式为异常
     * 使用try cacth 来回滚或提交
     * beginTransaction()方法将会关闭自动提交（autocommit）模式，
     * 直到事务提交或者回滚以后才能恢复为pdo设置的模式
     */
    public function beginTransaction()
    {
        $this->transactions++; /* 事务层次加1 */
        if ($this->transactions == 1) {
            $pdo = $this->connect();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo->beginTransaction();
        }
        return true;
    }

    /**
     * 提交
     * @return bool
     */
    public function commit()
    {
        $flag = true;
        if ($this->transactions == 1) {
            $flag = $this->_pdo->commit();
        }
        $this->transactions = max(0, $this->transactions - 1);
        return $flag;
    }

    /**
     * 回滚事务
     * @return bool
     */
    public function rollBack()
    {
        $flag = true;
        if ($this->transactions == 1) {
            $flag = $this->_pdo->rollBack();
        }
        $this->transactions = max(0, $this->transactions - 1);
        return $flag;
    }

    /**
     * 在使用pdo参数绑定的时候，null值可能有问题
     * 将null转变成其它值
     * @param $data
     * @param string $type
     * @return mixed
     */
    public function bindNull($data, $type = 'del')
    {
        foreach ($data as $key => $row) {
            if ($row !== null) {
                continue;
            }

            if ($type == 'del') {
                /* 删除null,如果字段设定了默认值 */
                unset($data[$key]);
            } elseif ($type == 'int') {
                /* 转成0 */
                $data[$key] = 0;
            } elseif ($type == 'string') {
                /* 转成空字符串 */
                $data[$key] = '';
            } else {
                unset($data[$key]);
            }
        }
        return $data;
    }

    /**
     * 增加and搜索条件
     * @param $field
     * @param $op
     * @param $value
     * @return $this
     */
    public function andFilter($field, $op, $value)
    {
        return $this->addFilter($field, $op, $value, 'and');
    }

    /**
     * 增加where搜索条件
     * @param $field
     * @param $op
     * @param $value
     * @return $this
     */
    public function orFilter($field, $op, $value)
    {
        return $this->addFilter($field, $op, $value, 'or');
    }

    /**
     * 返回一个系统自动绑定的参数
     * @return string
     */
    public function getAutoBindParam()
    {
        /* 防止常驻内存无限增加，超过PHP_INT_MAX */
        if ($this->paramCount >= 1000000) {
            $this->paramCount = 0;
        }
        $bind = ":{$this->autoBindPrefix}{$this->paramCount}";
        $this->paramCount++;
        return $bind;
    }

    /**
     * 增加过滤搜索条件，用于自动完成参数绑定
     * 支持 = >= <= in like not in 操作符。in操作符需要传递一个数组
     * 值有无判断，where条件拼接
     * 如果filter产生的where条件只使用一次，可以直接开始查询
     * 可能会使用多次,调用endFilter方法得到where条件和params参数。
     * @TODO 不支持 a = 1 and (b=2 or c=3) 多重条件使用mergeWhere来链接
     * @param string $field 字段名称
     * @param string $op 条件类型 = >= like等
     * @param string|array $value 值 like 并不会自加上%。
     *        如果是一个数组，转为 in (1,2)这种形式。只有in not in支持数组。其它会报错
     * @param string $type where 条件类型 or and
     * @return $this
     * @throws Exception
     */
    public function addFilter($field, $op, $value, $type)
    {
        if ($op == false) {
            throw new Exception('操作符不能为空');
        }
        if (is_array($value)) {
            $tmp = array();
            foreach ($value as $item) {
                $bindName = $this->getAutoBindParam();
                $tmp[$bindName] = $bindName;
                $this->sqlQuery["params"][$bindName] = $item;
            }
            $where = $field . " {$op} (" . implode(', ', $tmp) . ')';
        } else {
            $bindName = $this->getAutoBindParam();
            $where = "{$field} {$op} {$bindName}";
            $this->sqlQuery["params"][$bindName] = $value;
        }
        $this->sqlQuery['where'] = "{$this->sqlQuery['where']} {$type} {$where} ";
        return $this;
    }

    /**
     * 结束filter查询，并设定where和params值
     * 会清理sqlQuery cache where params的值
     * @param $where
     * @param $params
     */
    public function endFilter(&$where, &$params)
    {
        $where = $this->sqlQuery['where'];
        $params = $this->sqlQuery['params'];
        /* 只清除 where params 2个参数 */
        $this->sqlQuery['where'] = '1';
        $this->sqlQuery['params'] = array();
    }

    /**
     * 多重复合条件的where 使用此方法来连接
     * 使用endFilter来返回where,params来链接多个where条件
     * @param $where
     * @param $params
     * @param string $op
     * @return $this
     */
    public function mergeWhere($where, $params = array(), $op = 'and')
    {
        $this->sqlQuery['where'] = "($this->sqlQuery['where']) {$op} ($where)";
        $this->sqlQuery['params'] = array_merge($this->sqlQuery['params'], $params);
        return $this;
    }

    /**
     * 设置自动绑定参数的前缀
     * @param $prefix
     */
    public function setAutoBindPrefix($prefix)
    {
        if ($prefix != false) {
            $this->autoBindPrefix = $prefix;
        }
    }

    /**
     * 获取cache组件
     * @return bool|\bee\cache\ICache
     */
    public function getCache()
    {
        return App::s()->sure($this->cache);
    }

    /**
     * 获取字段描述信息。
     * 这个是个给代码生成器用的。
     * @return array
     */
    public function fieldInfo()
    {
        $res = $this->all("PRAGMA table_info(`{$this->tableName}`)");
        $data = array();
        foreach ($res as $row) {
            $data['label'][$row['name']] = $row['name'];
            if ($row['pk'] == '1') {
                $data['pk'] = $row['name'];
            }
            if ($row['dflt_value']) {
                $data['default'][$row['name']] = $row['dflt_value'];
            }
        }

        return $data;
    }

    /**
     * 获取上一次查询参数
     * @return array
     */
    public function getLastSqlQuery()
    {
        return $this->lastSqlQuery;
    }

    /**
     * 获取当期事务等级
     * @return int
     */
    public function getTransactionLevel()
    {
        return $this->transactions;
    }

    /**
     * 判断当前是否处于事务中
     * @return bool
     */
    public function inTransaction()
    {
        if ($this->_pdo === null) {
            return false;
        } else {
            return $this->_pdo->inTransaction();
        }
    }

    /**
     * 查找获取一条记录指定列的值
     * @param string $name 列名称
     * @param string $sql
     * @param array $params
     * @return bool|mixed
     */
    public function column($name, $sql = '', $params = array())
    {
        $res = $this->one($sql, $params);
        if ($res == false) {
            return false;
        } else {
            return $res[$name];
        }
    }

    /**
     * 获取配置项
     * @param null $key
     * @return array
     */
    public function getConfig($key = null)
    {
        if ($key === null) {
            return $this->dbConfig[$key];
        } else {
            return $this->dbConfig;
        }
    }
}