<?php
/**
 * sphinx数据库操作类。
 * @author xuen
 */

namespace bee\core;
use bee\App;
use Exception;
use SphinxClient;

class BeeSphinx
{
	/**
	 * 数据库链接资源
	 * @var SphinxClient
	 */
	private $_sp;

	/**
	 * sphinx默认的选项参数。
	 * @var array
	 */
	protected $option = array(
		'sort_mode' => SPH_SORT_EXTENDED, //排序模式
		'match_mode' => SPH_MATCH_ALL, //匹配模式，默认完全匹配
		'conn_time' => 30, //链接时间,单位秒
		'return_array' => true, //返回值类型
		'query_time' => 30000,  //最大查询时间，单位毫秒
	);

	static private $_instance = array();
	/**
	 * 默认查询参数，用于生成查询条件
	 * @var array
	 */
	protected $defaultQuery = array(
		'index' => '*', //将要使用的索引
		'keyword' => 'test', //关键词
		'offset' => 0, //设置查询偏移量
		'limit' => 10, //设置查询数量
		'max_matches' => 2000, //最大文档匹配数量。
		'order' => '', //设置排序
		'filter' => '', //属性值是多少
		'id_range' => '', //ID范围
		'filter_range' => '', //整数属性过滤器
		'filter_float_range' => '', //浮点数据属于过滤器
	);
	protected $query; //查询参数
	protected $k;
	protected $total = 0; //当前查询符合条件的记录总数.
	public $expireTime;

	private function __construct($config)
	{
		$this->_sp = new SphinxClient();
		//这里如果不port转为整数，会报一个warning错误
		$port = intval($config['port'] ? $config['port'] : 9312);
		$this->_sp->SetServer($config['host'], $port);
		if (!$this->_sp->Open()) {
			throw new Exception($this->_sp->_error);
		}
		$this->expireTime = time() + $config['conn_time'];
	}

	/**
	 * 得到实例化的对象
	 * @param array $config
	 * @param array $option sphinx查询设置参数
	 * @return BeeSphinx
	 */
	public static function getInstance($config, $option = array())
	{
		if (!class_exists("SphinxClient")) {
			require App::getInstance()->getSysDir() . '/core/sphinxapi.php';
		}
		if (!is_array($config)) {
			$config = App::c($config);
		}
		$k = md5($config['host'] . $config['port']);

		if (!isset(self::$_instance[$k]) || time() > self::$_instance[$k]->expireTime) {
			self::$_instance[$k] = new self($config);
			self::$_instance[$k]->k = $k;
		}
		//设置参数
		self::$_instance[$k]->setOption($option);
		return self::$_instance[$k];
	}

	/**
	 * 设置sphinx相关的选项
	 * @param array $option
	 */
	public function setOption($option)
	{
		//合并选项
		$this->option = array_merge($this->option, $option);
		foreach ($option as $key => $row) {
			switch ($key) {
				//排序模式，默认使用数据库中的字段排序
				case 'sort_mode':
					$this->setSortMode($row);
					break;
				//设置匹配模式,默认全匹配。
				case 'match_mode':
					$this->setMatchMode($row);
					break;
				//设置超时时间,默认30秒
				case 'conn_time':
					$this->setConnectTimeout($row);
					break;
				//设置数据返回类型,默认返回数组。
				case 'return_array':
					$this->setArrayResult(true);
					break;
				//设置最大查询时间
				case 'query_time':
					$this->setMaxQueryTime($row);
					break;
			}
		}
	}

	/**
	 * 设置匹配模式
	 * SPH_MATCH_ALL 匹配所有查询词（默认） ，所有查询词都出现时才会返回
	 * SPH_MATCH_ANY, 匹配查询词中的任意一个
	 * SPH_MATCH_BOOLEAN, 将查询看作一个布尔表达式
	 * SPH_MATCH_PHRASE, 将整个查询看作一个词组，要求按顺序完整匹配
	 * SPH_MATCH_EXTENDED2　将查询看作一个Sphinx/Coreseek内部查询语言的表达式
	 * SPH_MATCH_FULLSCAN, 强制使用下文所述的“完整扫描”模式来对查询进行匹配　过滤器、过滤器范围
	 * @param int $mode
	 * @return $this;
	 */
	public function setMatchMode($mode = SPH_MATCH_ALL)
	{
		$this->_sp->setMatchMode($mode);
		return $this;
	}

	/**
	 * 设置排序模式
	 * SPH_SORT_RELEVANCE 模式, 按相关度降序排列（最好的匹配排在最前面）//默认
	 * SPH_SORT_ATTR_DESC 模式, 按属性降序排列 （属性值越大的越是排在前面）
	 * SPH_SORT_ATTR_ASC 模式, 按属性升序排列（属性值越小的越是排在前面）
	 * SPH_SORT_EXTENDED 模式, 按一种类似 SQL 的方式将列组合起来，升序或降序排
	 * 列。在 SPH_SORT_EXTENDED 模式中，您可以指定一个类似 SQL 的排序表达式， 但
	 * 涉及的属性（包括内部属性）不能超过 5 个
	 * 内部属性以@开始，其它正常使用
	 * 仅有2个内部属性  @id（匹配的 ID）　@weight（匹配权值）
	 * @param int $mode
	 * @return $this
	 */
	public function setSortMode($mode = SPH_SORT_EXTENDED)
	{
		$this->_sp->SetSortMode($mode);
		return $this;
	}

	/**
	 * 设置返回结果模式为数组，默认是一个hash表。
	 * @return $this
	 */
	public function setArrayResult()
	{
		$this->_sp->SetArrayResult(true);
		return $this;
	}

	/**
	 * 设置server连接时间
	 * @param int $time
	 * @return $this
	 */
	public function setConnectTimeout($time = 30)
	{
		$this->_sp->SetConnectTimeout($time);
		return $this;
	}

	/**
	 * 设置最大查询时间
	 * @param int $time
	 * @return $this
	 */
	public function setMaxQueryTime($time = 30)
	{
		$this->_sp->SetMaxQueryTime($time);
		return $this;
	}

	/**
	 * 得到多条记录
	 * @param array $query
	 * @return array 所有文档ID组成的数组
	 */
	public function ids($query = array())
	{
		$arr = $this->query($query);
		if (!isset($arr['matches'])) {
			return false;
		}
		return array_keys($arr['matches']);
	}

	/**
	 * 设置要查询的索引。
	 * @param string $index
	 * @return $this
	 */
	public function index($index = '*')
	{
		$this->query['index'] = $index;
		return $this;
	}

	/**
	 * 设置查询记录
	 * @param string $offset
	 * @param string $limit
	 * @param int $maxMatches 最大文档匹配数
	 * @return $this
	 */
	public function limit($offset, $limit, $maxMatches = 2000)
	{
		$this->query['limit'] = intval($limit);
		$this->query['offset'] = intval($offset);
		$this->query['max_matches'] = intval($maxMatches);
		return $this;
	}

	/**
	 * 这个方法是limit方法的变种
	 * @param int $page 当前页
	 * @param int $pageSize 每页数量
	 * @param int $maxMatches
	 * @return $this
	 */
	public function page($page = 0, $pageSize = 20, $maxMatches = 2000)
	{
		$pageSize = $pageSize <= 0 ? 20 : $pageSize;
		$page = $page <= 0 ? 1 : $page;
		$offset = ($page - 1) * $pageSize;
		$this->limit($offset, $pageSize, $maxMatches);
		return $this;
	}

	/**
	 * 设置排序条件，字符串，将使用扩展排序
	 * @param string $order
	 * @return $this
	 */
	public function order($order = '')
	{
		$this->query['order'] = $order;
		return $this;
	}

	/**
	 * 设置查询关键字
	 * @param  $keyword
	 * @return $this
	 */
	public function keyword($keyword)
	{
		$this->query['keyword'] = $keyword;
		return $this;
	}

	/**
	 * 设置查询的ID范围
	 * @param int $min
	 * @param int $max
	 * @return $this
	 */
	public function idRange($min, $max)
	{
		$this->query['id_range'] = array(
			'min' => $min,
			'max' => $max
		);
		return $this;
	}

	/**
	 * 设置属性过滤
	 * @param string $attr 属性名
	 * @param array $values 属性值数组
	 * @param bool $exclude 　为ture表示排除,false表示包含
	 * @return $this
	 */
	public function filter($attr, $values, $exclude = false)
	{
		$this->query['filter'] = array(
			'attr' => $attr,
			'values' => $values,
			'exclued' => $exclude
		);
		return $this;
	}

	/**
	 * 设置整数属性范围过滤
	 * @param string $attr 属性名
	 * @param int $min 属性最小值
	 * @param int $max 属性最大值
	 * @param bool $exclude 　为ture表示排除,false表示包含
	 * @return $this
	 */
	public function filterRange($attr, $min, $max, $exclude = false)
	{
		$this->query['filter_range'] = array(
			'attr' => $attr,
			'min' => $min,
			'max' => $max,
			'exclued' => $exclude
		);
		return $this;
	}

	/**
	 * 设置浮点数属性范围过滤
	 * @param string $attr 属性名
	 * @param float $min 属性最小值
	 * @param float $max 属性最大值
	 * @param bool $exclude 　为ture表示排除,false表示包含
	 * @return $this
	 */
	public function filterFloatRange($attr, $min, $max, $exclude = false)
	{
		$this->query['filter_float_range'] = array(
			'attr' => $attr,
			'min' => $min,
			'max' => $max,
			'exclued' => $exclude
		);
		return $this;
	}

	/**
	 * 执行一个查询
	 * @param array $query
	 * @return array
	 */
	public function query($query = array())
	{
		$query = array_merge($this->defaultQuery, $this->query, $query);

		//设定limit
		if ($query['limit']) {
			$this->_sp->SetLimits($query['offset'], $query['limit'], $query['max_matches']);
		}
		//设置排序,如果有排序字段,使用扩展排序
		if ($query['order']) {
			$this->_sp->SetSortMode(SPH_SORT_EXTENDED, $query['order']);
		}
		//设置属性值
		if ($query['filter']) {
			$this->_sp->SetFilter(
				$query['filter']['attr'],
				$query['filter']['values'],
				$query['filter']['exclued']
			);
		}
		//设置ID排序范围
		if ($query['id_range']) {
			$this->_sp->SetIDRange($query['id_range']['min'], $query['id_range']['max']);
		}
		//设置整数属性过滤
		if ($query['filter_range']) {
			$this->_sp->SetFilterFloatRange(
				$query['filter_range']['attr'],
				$query['filter_range']['min'],
				$query['filter_range']['max'],
				$query['filter_range']['exclued']
			);
		}
		//设置浮点数属性过滤
		if ($query['filter_float_range']) {
			$this->_sp->SetFilterFloatRange(
				$query['filter_float_range']['attr'],
				$query['filter_float_range']['min'],
				$query['filter_float_range']['max'],
				$query['filter_float_range']['exclued']
			);
		}

		$this->query = '';
		//执行查询
		$res = $this->_sp->Query($query['keyword'], $query['index']);
		$this->total = $res['total'];
		return $res;
	}

	/**
	 * 得到错误消息
	 * @return string
	 */
	public function getError()
	{
		return $this->_sp->GetLastError();
	}

	public function getTotal()
	{
		return $this->total;
	}

	public function close()
	{
		$this->_sp->Close();
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
}