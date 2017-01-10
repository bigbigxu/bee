<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/1/10
 * Time: 9:35
 * 使用udp server 来汇总记录多台服务器的日志。
 *
 * 协议说明，一个json字符串。格式如下
 * array(
 *      'file' => '文件名',
 *      'log' => '日志内容',
 *      'level' => '日志等级'
 * )
 */
namespace bee\server;
class LogServer extends BaseServer
{
    public $serverType = self::SERVER_BASE;

    const LOG_NOTICE = 'notice'; /* 普通日志 */
    const LOG_WARN = 'warn'; /* 警告日志 */
    const LOG_INFO = 'info'; /* 描述信息日志 */
    const LOG_ERROR = 'error'; /* 错误日志 */
    const LOG_DEBUG = 'debug'; /* 调试日志 */

    public $config = array(
        /**
         * 运行时配置。swoole::set需要设置的参数
         */
        'serverd' => [
            'worker_num' => 4,
            'max_request' => 100240,
            'max_conn' => 10240,
            'dispatch_mode' => 2,
            'daemonize' => true,
            'backlog' => 128,
        ],

        /**
         * server实例化和其他自定义参数
         */
        'server' => [
            'host' => '0.0.0.0',
            'port' => 9601,
            'server_mode' => SWOOLE_PROCESS,
            'socket_type' => SWOOLE_SOCK_UDP,
            'env' => 'pro',
            'debug' => false,
            'server_name' => 'log_server',
        ]
    );

    public function onReceive(\swoole_server $server, $fd, $fromId, $data)
    {
        $arr = json_encode($data, true);
        if ($arr == false) {
            $this->errorLog("{$data} json解析失败：". json_last_error_msg());
            return null;
        }
        $file = trim($arr['file']);
        if ($file == false) {
        }
        $realFile = $this->c('server.data_dir') . '/' . $file;
        $dir = dirname($realFile);
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true) == false) {
                $this->errorLog("{$dir} 目录创建失败");
                return null;
            }
        }
        file_put_contents($file, $arr['log'], FILE_APPEND);
    }
}