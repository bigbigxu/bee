<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/10/11
 * Time: 17:32
 *
 * 说明：使用 php-rdkafka 向kafka发送消息。
 *
 * rdkafka 会开启一个多个线程来发送消息（一个topic一个线程），发送是异步的，批量提交。
 * 如果发送队列满了，会报异常。
 * rdkafka，如果kafka server挂了，会自动重连，连接上后会发送队列的消息。
 */
namespace bee\bigdata;

use bee\App;
use bee\core\Log;
use bee\core\TComponent;

class KafkaProducer
{
    use TComponent;
    /**
     * 使用常量，使用rdkafka来确定消息的分区
     */
    const DEFAULT_PARTITION = RD_KAFKA_PARTITION_UA;
    /**
     * 消息标志，总是0
     */
    const MESSAGE_FLAG = 0;
    /**
     * kafka服务器
     * @var string
     */
    protected $brokers;
    /**
     * 生产者配置属性
     * @var array
     */
    protected $conf = [
        /* 配置消息压缩 */
        "compression.codec" => 'gzip',
        /* 消息发送确认级别， 1表是主分区写入成功 */
        'acks' => 1
    ];
    /**
     * kafka每一个主题一个连接
     * @var array
     */
    protected $topicConn = [];
    /**
     * rdkafka Producer对象
     * @var null
     */
    protected $_rd = null;
    /**
     * 连接实例数组
     * @var KafkaProducer[]
     */
    private static $_instance = [];
    /**
     * 连接标识符
     * @var string
     */
    protected $k;

    /**
     * 连接kafka server 获取 topic 实例
     * @param $topic
     * @return mixed
     */
    public function connect($topic)
    {
        if ($this->_rd === null) {
            $conf = new \RdKafka\Conf();
            foreach ($this->conf as $key => $value) {
                $conf->set($key, $value);
            }
            //$conf->setDrMsgCb([$this, 'drMsgCallback']);
            $conf->setErrorCb([$this, 'errorCallback']);
            $this->_rd = new \RdKafka\Producer($conf);
            $this->_rd->addBrokers($this->brokers);
        }
        if (!isset($this->topicConn[$topic])) {
            $this->topicConn[$topic] = $this->_rd->newTopic($topic);
        }
        return $this->topicConn[$topic];
    }

    /**
     * 发送消息
     * @param $topic
     * @param $msg
     */
    public function send($topic, $msg)
    {
        $this->connect($topic)->produce(self::DEFAULT_PARTITION, self::MESSAGE_FLAG, $msg);
    }

    /**
     * 获取连接实例
     * @param $config
     * @return KafkaProducer
     * @throws \Exception
     */
    public static function getInstance($config)
    {
        if (is_string($config)) {
            $config = App::c($config);
        }
        $k = md5($config['brokers'] . getmypid());
        if (!isset(self::$_instance[$k])) {
            self::$_instance[$k] = new self($config);
            self::$_instance[$k]->k = $k;
        }
        return self::$_instance[$k];
    }

    /**
     * 消息发送结果回调
     * 调用RdKafka :: poll() 获取回调
     * @param $kafka
     * @param $message
     */
    public function drMsgCallback($kafka, $message)
    {
        if ($message->err) {
            $errmsg = $message->errstr();
            Log::error("{$message->topic_name}-{$message->partition}: $errmsg");
        }
    }
    /**
     * kafka使用错误回调。
     * @param $kafka
     * @param $err
     * @param $reason
     */
    public function errorCallback($kafka, $err, $reason)
    {
        Log::error("kafak error: {$err} (reason: {$reason})");
    }

    /**
     * 等待一个固定时间获取消息发送结果
     * 需要设置消息回调
     * @param int $ms
     */
    public function poll($ms = 1000)
    {
        $this->_rd->poll($ms);
    }

    /**
     * 获取当前待发送队列长度
     * @return mixed
     */
    public function getLen()
    {
        return $this->_rd->getOutQLen();
    }

    /**
     * 获取全部发送结果，不建议使用
     * @param int $ms
     */
    public function pollAll($ms = 1000)
    {
        while (($len = $this->getLen()) > 0) {
            $this->poll($ms);
        }
    }
}