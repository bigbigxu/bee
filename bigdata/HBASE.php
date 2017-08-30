<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/7/24
 * Time: 17:18
 * hbase thrift api
 *
 * 此类需要thrift 客服端。
 *
 */
namespace bee\bigdata;

use bee\bigdata\hbase\TColumn;
use bee\bigdata\hbase\TColumnIncrement;
use bee\bigdata\hbase\TColumnValue;
use bee\bigdata\hbase\TGet;
use bee\bigdata\hbase\THBaseServiceClient;
use bee\bigdata\hbase\TIncrement;
use bee\bigdata\hbase\TPut;
use bee\bigdata\hbase\TResult;
use bee\bigdata\hbase\TScan;
use bee\core\TComponent;
use Thrift\Exception\TTransportException;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TBufferedTransport;
use Thrift\Transport\TSocket;

class HBASE
{
    use TComponent;

    /**
     * hbase目录下的类是否已经加载。
     * hbase的thrift api，是多个类在一个文件。需要require才能正常使用。
     * @var int
     */
    protected static $isLoad = false;
    /**
     * 连接主机
     * @var string
     */
    protected $host = '127.0.0.1';
    /**
     * 连接端口
     * @var string
     */
    protected $port = '9090';
    /**
     * 发送超时时间
     * @var int
     */
    protected $sendTimeout = 1000;
    /**
     * 接受超时时间
     * @var int
     */
    protected $recvTimeout = 3000;
    /**
     * @var THBaseServiceClient
     */
    protected $client;
    /**
     * 当前操作的表名
     * @var string
     */
    protected $table;
    /**
     * 列族和列的分割符
     */
    const ROW_CUT = ":";
    /**
     * put的缓冲区
     * @var array
     */
    protected $putBuffer = [
        'size' => 0,
        'data' => []
    ];
    /**
     * 默认缓冲区的大小。64KB
     * @var int
     */
    protected $bufferSize = 65536;
    /**
     * 查询选项
     * @var array
     */
    protected $options = [
        'rowkey' => null, /* hbase 行键 */
        'columns' => null, /* 查询使用的列， 默认为全部 */
        'timestamp' => null, /* 时间戳版本号 */
        'timeRange' => null, /* 时间范围 */
        'filterString' => null, /* 过滤器 */
        'startRowkey' => null, /* 开始的行键 */
        'stopRowKey' => null, /* 结束的行键 */
    ];

    public function init()
    {
        if (!self::$isLoad) {
            require __DIR__ . '/hbase/Types.php';
            require __DIR__ . '/hbase/THBaseService.php';
            self::$isLoad = true;
        }
    }

    /**
     * 创建连接
     * @param bool $force 是否强制重连
     * @return THBaseServiceClient
     */
    public function connect($force = false)
    {
        if ($this->client && $force == false) {
            return $this->client;
        }
        $socket = new TSocket($this->host, $this->port);
        $socket->setSendTimeout($this->sendTimeout);
        $socket->setRecvTimeout($this->recvTimeout);

        $transport = new TBufferedTransport($socket);
        $protocol = new TBinaryProtocol($transport);
        $this->client = new THBaseServiceClient($protocol);
        $transport->open();
        return $this->client;
    }

    /**
     * 根据行键获取一条记录
     * @param $rowkey
     * @return array|bool
     */
    public function findByRowkey($rowkey)
    {
        return $this->rowkey($rowkey)
            ->get();
    }

    /**
     * 获取记录
     * @see THBaseServiceClient::get()
     * @param array $options
     * @return array|bool
     */
    public function get($options = [])
    {
        $get = null;
        if ($options instanceof TGet) {
            $get = $options;
        } elseif (is_array($options)) {
            $this->options = array_merge($this->options, $options);
        } else {
            return false;
        }
        if ($get == null) {
            $get = new TGet();

            /* 设置行键 */
            if ($this->options['rowkey']) {
                $get->row = $this->options['rowkey'];
            }

            /* 设置列 */
            if ($this->options['columns']) {
                foreach ($this->options['columns']  as $colRow) {
                    $tmp = explode(self::ROW_CUT, $colRow);
                    $tCol = new TColumn();
                    $tCol->family = $tmp[0];
                    $tCol->qualifier = $tmp[1];
                    $get->columns[] = $tCol;
                }
            }

            /* 设置过滤器 */
            if ($this->options['filterString']) {
                $get->filterString = $this->options['filterString'];
            }
        }
        $this->clearOptions();
        /* @var $res TResult */
        $res = $this->__execForHbase(__FUNCTION__, [$this->table, $get]);
        if ($res == false) {
            return false;
        }
        $r = ['rowkey' => $res->row];
        $sp = self::ROW_CUT;
        foreach ($res->columnValues as $row) {
            $r["{$row->family}{$sp}{$row->qualifier}"] = $row->value;
        }
        return $r;
    }

    /**
     * 获取一个遍历器
     * @param array $options
     * @return bool|mixed
     */
    public function openScanner($options = [])
    {
        $scan = null;
        if ($options instanceof TScan) {
            $scan = $options;
        } elseif (is_array($options)) {
            $this->options = array_merge($this->options, $options);
        } else {
            return false;
        }
        if ($scan == null) {
            $scan = new TScan();

            /* 设置行键范围 */
            if ($this->options['startRowkey']) {
                $scan->startRow = $this->options['startRowkey'];
                $scan->stopRow = $this->options['stopRowkey'];
            }

            /* 设置列 */
            if ($this->options['columns']) {
                foreach ($this->options['columns']  as $colRow) {
                    $tmp = explode(self::ROW_CUT, $colRow);
                    $tCol = new TColumn();
                    $tCol->family = $tmp[0];
                    $tCol->qualifier = $tmp[1];
                    $scan->columns[] = $tCol;
                }
            }

            /* 设置过滤器 */
            if ($this->options['filterString']) {
                $scan->filterString = $this->options['filterString'];
            }
        }
        $this->clearOptions();
        $scanId = $this->__execForHbase(__FUNCTION__, [$this->table, $scan]);
        return $scanId;
    }

    /**
     * 从遍历器中获取结果
     * @param $scanId
     * @param int $num
     * @return array|bool
     */
    public function getScannerRows($scanId, $num = 10)
    {
        /* @var $res TResult[] */
        $res = $this->__execForHbase(__FUNCTION__, [$scanId, $num]);
        if ($res == false) {
            return false;
        }
        $r = [];
        $sp = self::ROW_CUT;
        foreach ($res as $row) {
            $item = ['rowkey' => $row->row];
            foreach ($row->columnValues as $cv) {
                $item["{$cv->family}{$sp}{$cv->qualifier}"] = $cv->value;
            }
            $r[] = $item;
        }
        return $r;
    }

    /**
     * 关闭遍历器
     * @param $scannerId
     */
    public function closeScanner($scannerId)
    {
        $this->__execForHbase(__FUNCTION__, [$scannerId]);
    }

    /**
     * 插入操作没有返回值，如果有错误，都是抛出异常
     * data 为一个数组，格式：["列族:字段" => "值", ...]
     * attr 为一个属性，格式：['列族:字段' => ['tags' => '标识', 'timestamp' => '版本'], ...]
     *
     * @example
     * $hbase = new \bee\bigdata\Hbase([
     *  'host' => 'h1'
     * ]);
     *
     * $hbase->table('test')
     *  ->put(3, [
     *      't1:name' => 'test2',
     *      "t1:age" => 21,
     *      "t1:sex" => 'woman'
     * ]);
     *
     * @see THBaseServiceClient::put()
     * @param string $rowkey 行键
     * @param array $data 数据
     * @param array $attr
     */
    public function put($rowkey, $data, $attr = [])
    {
        $put = $this->arrayToPutObject($rowkey, $data, $attr);
        $this->__execForHbase(__FUNCTION__, [$this->table, $put]);
    }

    /**
     * 执行缓冲区put。
     * @param $rowKey
     * @param $data
     * @param array $attr
     */
    public function putBuffer($rowKey, $data, $attr = [])
    {
        $this->putBuffer['data'][$this->table][] = $this->arrayToPutObject($rowKey, $data, $attr);
        $this->putBuffer['size'] += $rowKey + strlen(implode('', $data)) + 1;
        if ($this->putBuffer['size'] >= $this->bufferSize) {
            $this->putFlush();
        }
    }

    /**
     * 刷新put缓冲区
     */
    public function putFlush()
    {
        if ($this->putBuffer['size'] > 0) {
            foreach ($this->putBuffer as $table => $data) {
                $this->__execForHbase(__FUNCTION__, [$table, $data]);
                $this->putBuffer = [
                    'size' => 0,
                    'data' => []
                ];
            }
        }
    }

    /**
     * 执行批量hbase数据更新
     * @param $data
     */
    public function putMultiple($data)
    {
        $this->__execForHbase(__FUNCTION__, [$this->table, $data]);
    }

    /**
     * 将一个数组转换为TPut
     * @param $rowkey
     * @param $data
     * @param $attr
     * @return TPut
     */
    public function arrayToPutObject($rowkey, $data, $attr = [])
    {
        $put = new TPut();
        $put->row = $rowkey;
        foreach ($data as $key => $value) {
            $tmp = explode(self::ROW_CUT, $key);
            if (!isset($tmp[0]) || !isset($tmp[1])) {
                continue;
            }
            $item = new TColumnValue();
            $item->family = $tmp[0];
            $item->qualifier = $tmp[1];
            $item->value = $value;

            if (isset($attr[$key]['tags'])) {
                $item->tags = $attr[$key]['tags'];
            }

            if (isset($attr[$key]['timestamp'])) {
                $item->timestamp = $attr[$key]['timestamp'];
            }
            $put->columnValues[] = $item;
        }
        return $put;
    }

    /**
     * increment字段必须使用increment创建和更新
     * 看起来，没有卵用啊。。。
     * @param $rowkey
     * @param $data
     * @return mixed
     */
    public function increment($rowkey, $data)
    {
        $increment = new TIncrement();
        $increment->row = $rowkey;
        foreach ($data as $key => $value) {
            $tmp = explode(self::ROW_CUT, $key);
            if (!isset($tmp[0]) || !isset($tmp[1])) {
                continue;
            }
            $item = new TColumnIncrement();
            $item->family = $tmp[0];
            $item->qualifier = $tmp[1];
            $item->amount = $value;
            $increment->columns[] = $item;
        }
        return $this->__execForHbase(__FUNCTION__, [$this->table, $increment]);
    }

    /**
     * 当前使用的表名
     * @param $table
     * @return $this
     */
    public function table($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * 设置当前使用的行键
     * @param $key
     * @return $this
     */
    public function rowkey($key)
    {
        $this->options['rowkey'] = $key;
        return $this;
    }

    /**
     * 设置行键范围
     * @param $start
     * @param null $end
     * @return $this
     */
    public function rowKeyRange($start, $end = null)
    {
        $this->options['startRowkey'] = $start;
        $this->options['stopRowkey'] = $end;
        return $this;
    }

    /**
     * 设置查询的列
     * 格式为一个数组，["t1:a1", ...]
     * @param $columns
     * @return $this
     */
    public function columns($columns)
    {
        $this->options['columns'] = $columns;
        return $this;
    }

    /**
     * 设置过滤器
     * @param $filter
     * @return $this
     */
    public function filterString($filter)
    {
        $this->options['filterString'] = $filter;
        return $this;
    }

    /**
     * 清理选项
     */
    public function clearOptions()
    {
        $this->options = [
            'rowkey' => null, /* hbase 行键 */
            'columns' => null, /* 查询使用的列， 默认为全部 */
            'timestamp' => null, /* 时间戳版本号 */
            'timeRange' => null, /* 时间范围 */
            'filterString' => null, /* 过滤器 */
            'startRowkey' => null, /* 开始的行键 */
            'stopRowKey' => null, /* 结束的行键 */
        ];
    }

    public function __execForHbase($method, $params = [])
    {
        for ($i = 0; $i < 2; $i++) {
            try {
                $client = $this->connect($i);
                $res = call_user_func_array([$client, $method], $params);
                return $res;
            } catch (TTransportException $e) {
                continue;
            }
        }
    }
}