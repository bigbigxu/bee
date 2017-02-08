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
     * 返回nil
     */
    public function replyNil()
    {
        return $this->format(self::REPLY_NIL);
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
        $object = new $class;
        $object->server = $this;
        $object->fd = $fd;
        if (!method_exists($object, $method)) {
            $this->errorLog("redis-server：{$class}.{$method}不存在");
            return false;
        }
        return $object->$method($data);
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
     * @param $fd
     * @param $data
     * @return mixed
     */
    public function handlerSet($fd, $data)
    {
        $this->beforeAction($fd, $data);
        if ($data[0] == false) { /* 参数不足 */
            $reply = $this->format(self::REPLY_ERROR, $this->getErrorParamMsg('SET'));
        } else {
            $res = $this->route($fd, $data);
            if ($res != false) {
                $reply = $this->format(self::REPLY_STATUS, 'ok');
            } else {
                $reply =  $this->format(self::REPLY_ERROR, 'set error');
            }
        }

        $this->afterAction($fd, $data);
        return $reply;
    }

    /**
     * GET 命令处理函数
     * @param $fd
     * @param $data
     * @return mixed
     */
    public function handlerGet($fd, $data)
    {
        $this->beforeAction($fd, $data);
        if ($data[0] == false) {
            $reply = $this->format(self::REPLY_ERROR, $this->getErrorParamMsg('GET'));
        } else {
            $r = $this->route($fd, $data);
            if ($r == false) {
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