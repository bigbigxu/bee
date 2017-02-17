<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/12/29
 * Time: 11:09
 *
 * redis server 不支持onReceive回调。
 * 只可以设置命令处理函数。提供默认的set, get命令处理函数
 * @example
 * public function handlerSet($fd, $data)
 * {
 *      echo implode(' ', $data), $fd;
 *      return $this->format(self::REPLY_STATUS, 'sfsd');
 * }
 * 以handler开始的函数会自动注册为命令处理函数，后面的为所有redis支持的命令，也可以是自定义命令
 * 处理函数接收2个参数，一个为连接描述符，一个为命令参数(不包含命令本身)。
 * 处理函数需要返回format格式化的redis协议数据，不能直接send。
 *
 *
 *
 * redis 回复协议说明
 *
 * 【状态回复】 +OK\r\n 第一个字符为+, 后面的为字符串。对应 REPLY_STATUS
 *
 * 【错误回复】 -ERR\r\n  第一个字符为-, 后面的为字符串。对应 REPLY_ERROR.
 *             在 "-" 之后,直到遇到第一个空格或新行为止
 *
 * 【整数回复】 :1\r\n 返回一个整数，对应REPLY_INT
 *
 * 【批量回复】  $6\r\nfoobar\r\n, 对应REPLY_STRING
 *              如果没有值 $-1\r\n, 对应 REPLY_NIL
 *
 * 【多条批量回复】  *1\r\n$3\r\nenv\r\n$3dev\r\n 对应 REPLY_SET，REPLY_MAP
 *                 多条批量回复中的元素可以将自身的长度设置为 -1，从而表示该元素不存在，
 *                 并且也不是一个空白字符串（nli）
 *                 多条批量回复也是redis的请求协议
 *
 *
 * 注意事项：
 *    php redis 扩展在连接swoole redis server的时候，会发送一个quit命令“QUIT\r\nGET\r\n$1\r\nx\r\n”
 *    此命令 swoole redis server 会解析失败，但是连接可以正常关闭，
 *    报一个“redis protocol error” 错误。此错误不影响使用
 *
 * 使用说明
 *   redis 协议中的key被当做路由， 调用parseRoute解析路由，得到控制器名称和方法。默认的形式为'A.b'；
 *   的route方法中，会实例化控制器，通知方法，并将参赛传递（除key以外的所有参赛，一个是一个数组）；
 *   GET，SET命令用于处理实时请求。
 *   RPUSH 命令用于处理日志，异步入库或其他。
 *
 */
namespace bee\server;
class RedisServer extends BaseServer
{
    protected $serverType = self::SERVER_REDIS;
    protected $handler = []; /* redis 命令处理句柄 */

    const REPLY_ERROR = 0; /*  错误回复 */
    const REPLY_NIL = 1; /* 空回复, $-1\r\n */
    const REPLY_STATUS = 2; /* 状态回复 */
    const REPLY_INT = 3; /* 整数回复 */
    const REPLY_STRING = 4; /* 批量回复 */
    const REPLY_SET = 5; /* 多条批量回复，必须为索引数组*/
    const REPLY_MAP = 6; /* 多条批量回复，必须为关联数组*/

    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        parent::onWorkerStart($server, $workerId);

        /**
         * task 进程加一个定时器，用来处理日志数据。
         */
        if ($this->isTaskWorker()) {
            if (!($this->processData['tick_list'] instanceof \SplQueue)) {
                $this->processData['tick_list'] = new \SplQueue();
            }
            $this->tick(1000, array($this, 'tickList'));
        }
    }
    /**
     * 注册回调函数和命令处理句柄
     */
    public function registerCallback()
    {
        $methods = get_class_methods($this);
        foreach ($methods as $row) {
            if (preg_match('/^on(\w+)$/', $row, $ma)) {
                $event = ucfirst($ma[1]);
                if ($event == 'Receive') {
                    continue; /* redis server　没有 onReceive回调 */
                }
                if (!isset($this->callback[$event])) {
                    $this->callback[$event] = array($this, $row);
                }
            }

            if (preg_match('/^handler(\w+)$/', $row, $ma)) {
                $cmd = strtoupper($ma[1]);
                if (!isset($this->handler[$cmd])) {
                    $this->handler[$cmd] = array($this, $row);
                }
            }
        }
        foreach ($this->callback as $event => $fun) {
            $this->s->on($event, $fun);
        }
        $this->callback = [];
        foreach ($this->handler as $cmd => $fun) {
            $this->s->setHandler($cmd, $fun);
        }
        $this->handler = [];
    }

    /**
     * 设置Redis命令字的处理器
     * @param string $cmd 命令的名称
     * @param mixed $callback 命令的处理函数，返回的数据必须为Redis格式
     */
    public function setHandler($cmd, $callback)
    {
        $cmd = strtoupper($cmd);
        $this->handler[$cmd] = $callback;
    }

    /**
     * 格式化命令响应数据
     * @param $type
     * @param null $value
     * @return mixed
     */
    public function format($type, $value = null)
    {
        return $this->s->format($type, $value);
    }

    /**
     * 将redis 的key 当做路由来解析，进行控制器调用。
     *
     * @param int $fd
     * @param array $data
     * @return bool|mixed
     */
    public function route($fd, $data)
    {
        if ($this->c('server.load_bee') == false) {
            return null;
        }
        $key = $data[0];
        $data = array_slice($data, 1);

        /* redis key 被认为是一个路由 */
        list($class, $method) = $this->parseRoute($key);
        if (!class_exists($class)) {
            $this->errorLog("redis-server：{$class}不存在");
            return false;
        }
        /* @var $object \SwooleController */
        $object = new $class;
        $object->server = $this;
        $object->fd = $fd;
        if (!is_callable(array($object, $method))) {
            $this->errorLog("redis-server：{$class}.{$method}不可调用");
            return false;
        }
        /* 按照redis 命令顺序传递参数 */
        return call_user_func_array(array($object, $method), $data);
    }

    /**
     * 统计当进程使用情况
     * @param $fd
     * @param $data
     * @return mixed
     */
    public function handlerInfo($fd, $data)
    {
        $this->beforeAction($fd, $data);
        $res = $this->s->stats();
        $reply =  $this->format(self::REPLY_MAP, $res);
        $this->afterAction($fd, $reply);
        return $reply;
    }

    /**
     * SET 命令处理函数
     * set 命令只能返回成功和失败，无法返回数据。
     * @param $fd
     * @param $data
     * @return mixed
     */
    public function handlerSet($fd, $data)
    {
        $this->beforeAction($fd, $data);
        if (count($data) != 2) { /* 参数不足 */
            $reply = $this->format(self::REPLY_ERROR, $this->getErrorParamMsg('SET'));
        } else {
            $res = $this->route($fd, $data);
            if ($res === false || $res === null) {
                $reply = $this->format(self::REPLY_ERROR, 'SET ERROR');
            } else {
                $reply = $this->format(self::REPLY_STATUS, 'OK');
            }
        }
        $this->afterAction($fd, $data);
        return $reply;
    }

    /**
     * GET 命令处理函数
     * get 命令只有一个KEY参数，无法携带其他数据。
     * @param $fd
     * @param $data
     * @return mixed
     */
    public function handlerGet($fd, $data)
    {
        $this->beforeAction($fd, $data);
        if (count($data) != 1) {
            $reply = $this->format(self::REPLY_ERROR, $this->getErrorParamMsg('GET'));
        } else {
            $r = $this->route($fd, $data);
            if ($r === false || $r === null) {
                $reply = $this->format(self::REPLY_NIL);
            } else {
                if (is_array($r)) {
                    $r = json_encode($r);
                } else {
                    $r = (string)$r;
                }
                $reply = $this->format(self::REPLY_STRING, $r);
            }
        }
        $this->afterAction($fd, $data);
        return $reply;
    }

    /**
     * rpush 命令处理函数。通常用于日志异步处理
     * @param $fd
     * @param $data
     * @return mixed
     */
    public function handlerRpush($fd, $data)
    {
        $this->beforeAction($fd, $data);
        if (count($data) != 2) {
            $reply = $this->format(self::REPLY_ERROR, $this->getErrorParamMsg('RPUSH'));
        } else {
            $this->task($data);
            $reply = $this->format(self::REPLY_INT, 1);
        }
        $this->afterAction($fd, $reply);
        return $reply;
    }

    /**
     * HGET 命令，可以接受一个参数并返回参数。
     * 此命令可以满足大多数业务需求。
     * 接受一个字符串，并返回一个字符串
     * @param $fd
     * @param $data
     * @return mixed
     */
    public function handlerHGet($fd, $data)
    {
        $this->beforeAction($fd, $data);
        if (count($data) != 3) {
            $reply = $this->format(self::REPLY_ERROR, $this->getErrorParamMsg('HGET'));
        } else {
            $r = $this->route($fd, $data);
            if ($r === false || $r === null) {
                $reply = $this->format(self::REPLY_NIL);
            } else {
                if (is_array($r)) {
                    $r = json_encode($r);
                } else {
                    $r = (string)$r;
                }
                $reply = $this->format(self::REPLY_STRING, $r);
            }
        }
        $this->afterAction($fd, $reply);
        return $reply;
    }

    public function onTask(\swoole_server $server, $taskId, $fromId, $data)
    {
        /* @var  \SplQueue $spl */
        $spl = $this->processData['tick_list'];
        $spl->push($data);
    }

    /*
     * 定时器处理key回调函数
     */
    public function tickList()
    {
        /* @var  \SplQueue $spl */
        $spl = $this->processData['tick_list'];
        $n = $spl->count();
        for ($i = 0; $i < $n; $i++) {
            $this->route(0, $spl->pop());
        }
    }

    /**
     * 命令处理函数调用前的方法。 用于打印日志
     * @param $fd
     * @param $data
     */
    public function beforeAction($fd, $data)
    {
        if ($this->isDebug()) {
            $this->accessLog("fd={$fd}, request=" . json_encode($data));
        }
    }

    /**
     * 命令处理函数调用后的方法。 用于打印日志
     * @param $fd
     * @param $data
     */
    public function afterAction($fd, $data)
    {
        if ($this->isDebug()) {
            $this->accessLog("fd={$fd}, response=" . json_encode($data));
        }
    }

    public function getErrorParamMsg($cmd)
    {
        return "ERR wrong number of arguments for '{$cmd}' command";
    }

    /**
     * 从路由中解析 class method。
     * 这个方法用于将route 转换为Call需要的形式。并且可以在这里做权限控制。
     * @param $route
     * @return array
     */
    public function parseRoute($route)
    {
        list($class, $method) = explode('.', $route);
        $class .= 'Controller';
        return [$class, $method];
    }
}