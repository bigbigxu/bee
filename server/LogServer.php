<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/5/4
 * Time: 17:46
 *
 */
namespace bee\server;
class LogServer extends BaseServer
{
    const OP_INSERT = 'insert'; //插入数据
    const OP_UPDATE = 'update'; //更新数据
    const OP_DEL = 'delete'; //删除数据
    const OP_FIND = 'select'; //查找数据
    public $splQueue;

    protected $canOpType = array(
        self::OP_INSERT,
        self::OP_UPDATE,
        self::OP_DEL,
        self::OP_FIND
    );

    const ERR_DATA = 10;
    const ERR_TOKEN = 11;
    const ERR_DB_SET = 12;

    protected $errArr = array(
        self::ERR_DATA => '数据格式错误',
        self::ERR_TOKEN => 'token错误',
        self::ERR_DB_SET => '数据库表名或格式错误'
    );

    protected $maxQueue = 10; //队列个数

    /**
     * @var \CoreRedis
     */
    public $redis;
    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        $this->redis = \App::redis('db.main');
        parent::onWorkerStart($server, $workerId);
    }

    public function insert()
    {

    }

    public function onReceive(\swoole_server $server, $fd, $fromId, $data)
    {
        $data = json_decode(rtrim($data, $this->eof), true);
        if (in_array($data['op'], $this->canOpType) == false) {
            $this->send($fd, 'op type error');
            return null;
        }
        if ($this->c('server.auth')) {
            if ($this->c('server.token') != $data['token']);
            $this->send($fd, 'token error');
            return null;
        }

        if ($data['db'] == false || $data['table_name'] == false) {
            $this->send($fd, 'token error');
            return null;
        }
        $this->push($data);
    }

    public function push($data)
    {
        $key = $this->getQueueKey($data['db'], $data['table_name']);
        $this->redis->rPush($key, json_encode($data));
    }

    public function d()
    {

    }

    public function getQueueKey($db, $table)
    {
        $num = sprintf('%u', crc32($db . $table));
        $k = $num % $this->maxQueue;
        return "queue_{$k}";
    }
}