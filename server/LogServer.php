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
    public $queueKey = 'test';

    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        if ($workerId == 0) {
            $this->tick(10, array($this, 'exec'));
        }
        parent::onWorkerStart($server, $workerId);
    }

    public function insert()
    {

    }

    public function onReceive(\swoole_server $server, $fd, $fromId, $data)
    {
        \App::redis('redis.main')->lPush($this->queueKey, $data);
        $this->send($fd, 'ok');
    }


    public function exec()
    {
        $redis = \App::redis('redis.main');
        while (true) {
            $str = $redis->lPop($this->queueKey);
            if ($str === false) {
                break;
            }
            $arr = json_decode($str, true);
            $m = \App::m($arr['db'], $arr['table_name']);
            try  {
                switch ($arr['op']) {
                    case self::OP_INSERT :
                        $m->insert($arr['data']);
                        break;
                    case self::OP_UPDATE :
                        $m->update($arr['data'], $arr['pk']);
                        break;
                    case self::OP_DEL :
                        $m->delById($arr['pk']);
                        break;
                    default:
                        break;
                }
            } catch (\PDOException $e) {
                $this->errorLog($str . '：' . $e->getMessage());
            }
        }
    }
}