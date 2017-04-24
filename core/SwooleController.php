<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/2/7
 * Time: 10:42
 * 基于swoole 运行的服务控制器
 */

namespace bee\core;

use bee\server\BaseServer;

class SwooleController
{
    use TComponent;
    /**
     * @var BaseServer server实例
     */
    public $server;
    /**
     * @var int 当前fd
     */
    public $fd;
    /**
     * @var mixed 参数
     */
    public $data;
}