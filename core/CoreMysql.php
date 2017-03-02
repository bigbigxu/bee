<?php

/**
 * 数据库操作基类 基于pdo
 * @author xuen
 *
 * 1. 所有关于主键的方法都不支持联合主键
 * 2. 不能在异步模式下使用。
 */
class CoreMysql
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
	protected $charset = 'utf8';
	protected $forTransaction = false; /* 当前是否在执行事务 */
	protected $sqlQuery = array(
		'field' => '*',
		'where' => '1',
		'join' => '',
		'group' => '',
		'having' => '',
		'order' => '',
		'limit' => '',
		'union' => '',
		'params' => array()
	);
	protected $lastSqlQuery = []; /* 保存上一次执行申请sql参 */
	protected $allTableColumns = array(); /* 当前表所有的字段名称 */
	/**
	 * @var static[]
	 */
	private static $_instance = array();

	protected $driver; /* 驱动类型 */
	protected $dbName; /* 当前数据库名 */
	protected $username; /* 用户名 */
	protected $dsn; /* 驱动dsn */
	protected $k; /* 当前数据库连接标识符 */
	protected $password; /* 密码 */
	protected $host = 'localhost'; /* 主机名 */
	protected $port = '3306'; /* 端口号 */
	/* PDO链接属性数组 */
	protected $attr = array(
		/* 这个超时参数，实际上mysql服务器上的配置为准的 */
		PDO::ATTR_TIMEOUT => 30,
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_PERSISTENT => false /* 是否使用长链接 */
	);
	protected $paramCount = 0; /* 自定义参编号 */
	/**
	 * 实际完成sql执行的db的key。
	 * 这里不直接保存对象，是因为对象的循环引用会引起对象无发释放，
	 * 导致内存泄漏和mysql连接无法关闭
	 * @var string
	 */
	protected $realDbKey;
	/**
	 * 由本类自动生成的绑定参数前缀，用于区别用户绑定参数
	 * @var string
	 */
	protected $autoBindPrefix = 'iphp_auto_';
	/**
	 *  当前程ID
	 * @var int
	 */
	protected $pid = 0;

	/*
	 * mysql常用错误码定义
	 * 驱动错误码保存在 errInfo[1]中
	*/
	const ERR_DRIVER_TABLE = 1146; /* 表不存存 */
	const ERR_DRIVER_CONN = 2006; /* 连接已经断开 */
	const ERR_DRIVER_INTERRUP = 70100; /* 查询执行被中断 */

	const DB_MASTER = 'master'; /* 主DB */
	const DB_SLAVE = 'slave'; /* 从DB */

	/*
	 * 定义驱动类型
	 */
	const DRIVER_MYSQL = 'mysql';
	const DRIVER_SQLITE = 'sqlite';

	/**
	 * getTableColumns中主键的名字
	 */
	const PK_MARK = '@pk';

	/**
	 * 字段缓存使用的组件名称，如果不想使用，设置为false。
	 * @var string
	 */
	public $cache;

	/**
	 * 从库配置文件。是一个标准的bee配置节。
	 * 目前只支持了一主多从
	 *
	 * slave 会继承主库的配置（参数合并）
	 * 如果slave为一个字符串，解析为配置的dsn选项，其他参数从主库继承。
	 * 如果是一个数组，和主库参数进行合并。
	 * @example
	 *   slaves => [
	 *     'mysql:host=localhost;dbname=test',
	 *     [
	 * 			'dsn' => 'mysql:host=localhost;dbname=test'
	 *          'username' => 'xx'
	 * 	   ]
	 * 	]
	 * @var array
	 */
	public $slaves = [];
	/**
	 * 配置文件保存
	 * @var array
	 */
	public $dbConfig;

	/**
	 * 构造方法
	 * config 应该包含如下配置：
	 * [
	 * 	'dsn' => '数据库驱动',
	 * 	'username' => '用户名',
	 *	'password' => '用户密码',
	 *  'slaves' => '从库列表，数组',
	 *	'cache' => '字段缓存使用的组件名',
	 * 	'charset' => '字符集',
	 * 	'attr' => '属性',
	 *  'prefix' => '表前缀'
	 * ]
	 * @param array $config 配置文件
	 */
	public function __construct($config)
	{
		$this->dbConfig = $config;
		$this->attr = (array)$config['attr'] + $this->attr;
		$this->prefix = (string)$config['prefix'];
		$this->charset = $config['charset'] ?: 'utf8';
		$this->dsn = (string)$config['dsn'];
		$this->username = (string)$config['username'];
		$this->password = (string)$config['password'];
		$this->slaves = (array)$config['slaves'];
		$this->cache = $config['cache'] ?: false;
	}

	/**
	 * 打开pdo mysql连接，并且返回pdo对象
	 * @param bool $force 是否强制重新打开连接
	 * @return PDO
	 */
	public function connect($force = false)
	{
		if ($this->_pdo !== null && $force == false) {
			return $this->_pdo; /* 连接已经打开 */
		}
		$this->_pdo = null;
		$this->setParamByDSN($this->dsn);
		$this->_pdo = new PDO($this->dsn, $this->username, $this->password, $this->attr);
		$this->_pdo->exec("set names {$this->charset}");
		return $this->_pdo;
	}

	/**
	 * 从dsn中解析相关配置
	 * @param $dsn
	 */
	public function setParamByDSN($dsn)
	{
		list($this->driver, $str) = explode(':', $dsn);
		$temp = explode(";", $str);
		foreach ($temp as $row) {
			list($key, $value) = explode('=', $row);
			$key = strtolower(trim($key));
			$value = trim($value);
			if ($value === '') {
				continue;
			}
			switch ($key) {
				case 'host' :
					$this->host = $value;
					break;
				case 'port' :
					$this->port = $value;
					break;
				case 'dbname' :
					$this->dbName = $value;
					break;
			}
		}
	}

	/**
	 * @param $config
	 * @return self
	 */
	public static function getInstance($config)
	{
		if (!is_array($config)) {
			$config = App::c($config);
		}
		$pid = intval(getmypid());
		$k = md5($config['dsn'] . $config['username'] . $config['password'] . $pid);

		/* 如果连接没有创建 */
		if (!(self::$_instance[$k] instanceof self)) {
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
		return $this->_pdo;
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
			//删除非法字段信息
			if (!in_array($key, $columns)) {
				continue;
			}
			$params[':' . $key] = $row;
			$field[] = "`{$key}`";
			$placeholder[] = ':' . $key;
		}
		//插入当前记录
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
		$where = 1;
		foreach ($findAttr as $row) {
			$this->andFilter($row, '=', $data[$row]);
		}
		$this->endFilter($where, $params);
		$count = $this->count($where, $params);
		if ($count == 1) {
			return $this->update($data, $where, $params);
		} elseif ($count == 0) {
			return $this->insert($data);
		} else {
			if ($multi) {
				return $this->update($data, $where, $params);
			} else {
				throw new Exception('可能更新多条记录');
			}
		}
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
	public function deleteBydAttr($findAttr, $multi = false)
	{
		$where = '1';
		$params = array();
		//基于安全考虑,做一个强制转换.
		$findAttr = (array)$findAttr;
		if ($findAttr == false) {
			return false;
		}
		foreach ($findAttr as $key => $row) {
			$this->andFilter($key, '=', $row);
		}
		$this->endFilter($where, $params);
		if ($this->count($where, $params) > 1 && $multi == false) {
			throw new Exception('可能删除多条记录');
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
			//不合法的字段不要
			if (!in_array($key, $columns)) {
				continue;
			}
			//自动组织的params参数要避免与用户传的绑定参数一样。
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
	 * 数据自增加。如果数据存在更新，否则插入
	 * @param array $incr key为要增加的字段, value为值
	 * @param array $find 查询条件
	 * @param bool $multi 是否可以更新多条记录
	 * @return bool|int
	 * @throws Exception
	 */
	public function incrByAttr($incr, $find, $multi = false)
	{
		if ($find == false) {
			return false;
		}
		$res = $this->findByAttr($find);
		$data = array_merge($find, $incr);
		if ($data == false) {
			return false;
		}
		if ($res == false) {
			$flag =  $this->insert($data);
		} else {
			foreach ($incr as $key => $value) {
				$data[$key] += $res[$key];
			}
			$flag = $this->updateByAttr($data, array_keys($find), $multi);
		}
		if ($flag === false) {
			return $flag;
		} else {
			return $data;
		}
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
		//基于安全考虑,做一个强制转换.
		$findAttr = (array)$findAttr;
		if ($findAttr == false) {
			return false;
		}
		$where = 1;
		foreach ($findAttr as $row) {
			$this->andFilter($row, '=', $data[$row]);
			unset($data[$row]); //如果属性在findAttr中那么不需要更新。
		}
		$this->endFilter($where, $params);
		$count = $this->count($where, $params);
		if ($count > 1 && $multi == false) {
			throw new Exception('可能更新多条记录');
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
			$this->dbName,
			$this->tableName
		);
		$cache = $this->getCache();
		/* 尝试从缓存中找到数据 */
		if ($cache) {
			$field = $cache->get($key);
		}

		/* 从db查数据，并保存缓存 */
		if ($field == false) {
			$sql = "desc {$this->tableName} ";
			$res = $this->_execForMysql($sql)->fetchAll();
			foreach ($res as $row) {
				if ($row['Key'] == 'PRI') {
					$field[self::PK_MARK] = $row['Field'];
				}
				$field[] = $row['Field'];
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
	 * 使用DISTINCT使用计算计录总数,在使用group by后，需要使用此方法
	 * @param string $field 需要去重的字段 例"a,b"
	 * @param string $where where 条件
	 * @param array $params 绑定参数
	 * @return int
	 */
	public function distinctCount($field, $where = '', $params = array())
	{
		$this->sqlQuery['field'] = "COUNT(DISTINCT {$field}) as total";
		if ($where != '') {
			$this->sqlQuery['where'] = $where;
		}
		if (!empty($params)) {
			$this->sqlQuery['params'] = $params;
		}
		$res = $this->one();
		return intval($res['total']);
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

	//得到sql执行错误
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
	private function _execForMysql($sql, $params = array())
	{
		/* 事务状态下，只能在主库执行 */
		if ($this->forTransaction == false && $this->isReadSql($sql)) {
			$db = $this->getSlaveDb();
		} else {
			$db = $this->getMasterDb();
		}
		$this->realDbKey = $db->k;
		for ($i = 0; $i < 2; $i++) {
			try {
				$pdo = $db->connect($i);
				$stmt = $pdo->prepare($sql);
				$stmt->execute($params);

				/* 此处用于静默错误模式下的断线重连 */
				$errorInfo = $stmt->errorInfo();
				if ($errorInfo[1] == self::ERR_DRIVER_CONN) {
					var_dump($errorInfo);
					$e = new PDOException('mysql server has gone away');
					$e->errorInfo = $stmt->errorInfo();
					throw $e;
				}

				/* 返回错误或stmt对象 */
				if ($errorInfo[0] != '00000') {
					$this->setError($errorInfo[2]);
					return false;
				} else {
					return $stmt;
				}
			} catch(PDOException $e) {
				/* 事务状态下，不可以使用断线重连。应该直接报错，rollback事务。 */
				if ($this->forTransaction == false && $e->errorInfo[1] == self::ERR_DRIVER_CONN) {
					continue;
				} else {
					throw $e;
				}
			}
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
	 * 设置开启查询缓存。
	 * 在使用游标读取记录，未读取完成又想进行其它操作
	 * 必须开启此选项
	 * @param bool $bool
	 * @return $this
	 */
	public function setQueryBuffer($bool = true)
	{
		$this->attr[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = $bool;
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
		//本身就是一个sql语句
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
	 * @return CoreMysql
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
			//如果是一个整数，表示不取别名
			if (is_int($key)) {
				$fieldArr[] = $this->AddUnquoteForField($value);
			} else {
				$key = $this->AddUnquoteForField($key);
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
	public function AddUnquoteForField($field)
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
		foreach ($this->sqlQuery as $key => $row) {
			if ($key == 'where') {
				$this->sqlQuery[$key] = '1';
			} elseif ($key == 'field') {
				$this->sqlQuery[$key] = '*';
			} elseif ($key == 'params') {
				$this->sqlQuery[$key] = array();
			} else {
				$this->sqlQuery[$key] = '';
			}
		}
	}

	/**
	 * @return mixed 返回组合的sql语句
	 */
	public function getSqlCache()
	{
		$sql = $this->joinSql('');
		if (!empty($this->sqlQuery['params'])) {
			foreach ($this->sqlQuery['params'] as $key => $param)
				$sql = str_replace($key, '"' . $param . '"', $sql);
		}
		return $sql;
	}

	/**
	 * 得到当前数据库名称
	 */
	public function getDbName()
	{
		return $this->dbName;
	}

	/**
	 * 得到用户名
	 */
	public function getUser()
	{
		return $this->username;
	}

	/**
	 * 得到密码
	 */
	public function getPass()
	{
		return $this->password;
	}

	public function getHost()
	{
		return $this->host;
	}

	public function getPort()
	{
		return $this->port;
	}

	/**
	 * 得到连接相关的详细信息。
	 */
	public function getConnInfo()
	{
		return array(
			'host' => $this->host,
			'port' => $this->port,
			'username' => $this->username,
			'password' => $this->password,
			'dbname' => $this->dbName,
		);
	}

	/**
	 * 关闭连接
	 */
	public function close()
	{
		$this->clearSqlQuery();
		$this->forTransaction = false;
		$this->allTableColumns = [];
		$this->_pdo = null;

		/* 关闭从库 */
		if ($this->realDbKey != $this->k) {
			$this->getDb()->close();
		}
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
	 * 得到当前表的下一次自增长ID
	 * @param $tableName ;
	 */
	public function getNextAutoIncrement($tableName = null)
	{
		if ($tableName === null) {
			$tableName = $this->tableName;
		}
		$sql = "show table status where name ='{$tableName}'";
		$res = $this->one($sql);
		return $res['Auto_increment'];

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
	 * 为一个表增加一个TIMESTAMP字段
	 * @param $tableName 　表名
	 * @param $name 　字段名
	 * @return bool|int
	 */
	public function addTIMESTAMP($tableName, $name = 'utime')
	{
		$addSql = "alter table {$tableName} add {$name} TIMESTAMP
                 NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;";
		$addSql .= "ALTER TABLE {$tableName} ADD index {$name}($name)";

		return $this->exec($addSql);
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
		$this->connect();
		$this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$flag = $this->_pdo->beginTransaction();
		$this->forTransaction = true;
		return $flag;
	}

	/**
	 * 提交
	 * @return bool
	 */
	public function commit()
	{
		$flag = $this->_pdo->commit();
		$this->forTransaction = false;
		return $flag;
	}

	/**
	 * 回滚事务
	 * @return bool
	 */
	public function rollBack()
	{
		$flag = $this->_pdo->rollBack();
		$this->forTransaction = false;
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
				//删除null,如果字段设定了默认值
				unset($data[$key]);
			} elseif ($type == 'int') {
				//转成0
				$data[$key] = 0;
			} elseif ($type == 'string') {
				//转成空字符串
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
		//只清除 where params 2个参数
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
	 * 得到数据表与字段对应的注释
	 * @return array
	 */
	public function label()
	{
		$res = $this->all("show full fields from `{$this->tableName}`");
		$label = array();
		foreach ($res as $row) {
			$label[$row['Field']] = $row['Comment'];
		}
		return $label;
	}

	/**
	 * 判断一个sql是否为读取操作
	 * @param $sql
	 * @return bool
	 */
	public function isReadSql($sql)
	{
		$pattern = '/^\s*(SELECT|SHOW|DESCRIBE|DESC)\b/i';
		return preg_match($pattern, $sql) > 0;
	}

	/**
	 * 获取主库
	 * @return $this
	 */
	public function getMasterDb()
	{
		return $this;
	}

	/**
	 * 得到从库
	 * 从库会继承主库的配置文件选项。
	 * @return $this|CoreMysql
	 */
	public function getSlaveDb()
	{
		if ($this->slaves == false) {
			return $this; /* 没有配置从库 */
		}
		$config = $this->slaves[array_rand($this->slaves)];
		if (is_string($config)) {
			$config = ['dsn' => $config]; /* 如果为一个字符串，认为是一个dsn */
		}
		/* 合并主库和从库的配置文件 */
		$config = array_merge($this->dbConfig, $config);
		unset($config['slaves']);
		$db = self::getInstance($config);
		return $db;
	}

	/**
	 * 获取cache组件
	 * @return bool|\bee\cache\Cache
	 */
	public function getCache()
	{
		if ($this->cache == false) {
			return false;
		} else {
			return App::s()->get($this->cache);
		}
	}

	/**
	 * 获取实际执行的db
	 * @return CoreMysql
	 */
	public function getDb()
	{
		return self::$_instance[$this->realDbKey];
	}

	/**
	 * 获取字段描述信息。
	 * 这个是个给代码生成器用的。
	 * @return array
	 */
	public function fieldInfo()
	{
		$res = $this->all("show full fields from `{$this->tableName}`");
		$data = array();
		foreach ($res as $row) {
			$data['label'][$row['Field']] = $row['Comment'];
			if ($row['Key'] == 'PRI') {
				$data['pk'] = $row['Field'];
			}
			if ($row['Default']) {
				$data['default'][$row['Field']] = $row['Default'];
			}
		}

		return $data;
	}

	/**
	 * 获取一个db实例
	 * @param string $type
	 * @param int $k
	 * @return CoreMysql
	 */
	public function selectDB($type = self::DB_MASTER, $k = 0)
	{
		if ($type == self::DB_MASTER || $this->slaves == false) {
			$db = self::getInstance($this->dbConfig);
		} else {
			$config = $this->slaves[$k];
			if ($config == false) {
				return false;
			}
			if (is_string($config)) {
				/* 如果为一个字符串，认为是一个dsn */
				$config = ['dsn' => $config];
			}
			/* 合并主库和从库的配置文件 */
			$config = array_merge($this->dbConfig, $config);
			unset($config['slaves']);
			$db = self::getInstance($config);
		}
		return $db;
	}

	public function getLastSqlQuery()
	{
		return $this->lastSqlQuery;
	}
}