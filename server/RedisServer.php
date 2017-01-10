<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/12/29
 * Time: 11:09
 *
 * redis server 不支持onReceive回调。
 * 只可以设置命令处理函数。
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
     * 调用key的回调函数。
     * 需要加载bee框架代码。在redis_key_callback配置节中配置回调函数。
     * 此函数需要在命令处理器自行调用
     *
     * 此代码只做示例用。应该在业务server重写此方法，来决定key的回调函数。
     * @param $data
     * @return bool|mixed
     */
    public function keyCallback($data)
    {
        if ($this->c('server.load_bee') == false) {
            return null;
        }
        $key = $data[0];
        $data = array_slice($data, 1);
        $callback = \App::c('redis_key_callback.' . $key);
        if ($callback == false) {
            return true; /* 无回调返回true */
        }
        if (!is_callable($callback)) {
            \CoreLog::error("redis-server：{$key} 的回调函数 {$callback} 不可用");
            return false;
        }
        return call_user_func($callback, $key, $data);
    }

    /**
     * 统计当进程使用情况
     * @param $fd
     * @param $data
     * @return mixed
     */
    public function handlerInfo($fd, $data)
    {
        $res = $this->s->stats();
        return $this->format(self::REPLY_MAP, $res);
    }
}