<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/11/20
 * Time: 9:58
 */
namespace bee\server;
class WebSocketServer extends BaseServer
{
    public function start()
    {
        $this->eof = $this->c('serverd.package_eof');
        $host = $this->c('server.host');
        $port = $this->c('server.port');
        $this->s = new \swoole_websocket_server($host, $port);

        $this->s->set($this->c('serverd'));
        $this->registerCallback();
        return $this->s->start();
    }

    /**
     * 当WebSocket客户端与服务器建立连接并完成握手后会回调此函数
     * @param \swoole_websocket_server $svr
     * @param \swoole_http_request $req
     */
    public function onOpen(\swoole_websocket_server $svr, \swoole_http_request $req)
    {
        $this->push($req->fd, 'welcome!');
    }

    /**
     *
     * $frame->fd，客户端的socket id，使用$server->push推送数据时需要用到
     * $frame->data，数据内容，可以是文本内容也可以是二进制数据，可以通过opcode的值来判断
     * $frame->opcode，WebSocket的OpCode类型，可以参考WebSocket协议标准文档
     * $frame->finish， 表示数据帧是否完整，一个WebSocket请求可能会分成多个数据帧进行发送

     * 当服务器收到来自客户端的数据帧时会回调此函数。
     * @param \swoole_server $server 包含了客户端发来的数据帧信息
     * @param \swoole_websocket_frame $frame
     */
    public function onMessage(\swoole_server $server, \swoole_websocket_frame $frame)
    {
        $this->push($frame->fd, $frame->data);
    }

    /**
     * 向客户端发送数据
     * @param $fd
     * @param $data
     * @return mixed
     */
    public function push($fd, $data)
    {
        return $this->s->push($fd, $data);
    }
}