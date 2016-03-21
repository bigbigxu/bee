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
        $mode = $this->c('server.server_mode');
        $type = $this->c('server.socket_type');
        $this->s = new \swoole_websocket_server($host, $port, $mode, $type);

        $this->s->set($this->c('serverd'));
        $this->registerCallback();
        return $this->s->start();
    }

    /**
     * WebSocket建立连接后进行握手。WebSocket服务器已经内置了handshake，
     * 如果用户希望自己进行握手处理，可以设置onHandShake事件回调函数。
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     */
    public function onHandShake(\swoole_http_request $request, \swoole_http_response $response)
    {
        $this->accessLog('hello');
    }

    /**
     * 当WebSocket客户端与服务器建立连接并完成握手后会回调此函数
     * @param \swoole_websocket_server $svr
     * @param \swoole_http_request $req
     */
    public function onOpen(\swoole_websocket_server $svr, \swoole_http_request $req)
    {

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
        $this->send($frame->fd, $frame->data);
    }

}