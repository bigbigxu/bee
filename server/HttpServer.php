<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/4/12
 * Time: 17:34
 */
namespace bee\server;
class HttpServer extends BaseServer
{
    public function start()
    {
        $this->eof = $this->c('serverd.package_eof');
        $host = $this->c('server.host');
        $port = $this->c('server.port');
        $mode = $this->c('server.server_mode');
        $type = $this->c('server.socket_type');
        $this->s = new \swoole_http_server($host, $port, $mode, $type);

        $this->s->set($this->c('serverd'));
        $this->registerCallback();
        return $this->s->start();
    }
}