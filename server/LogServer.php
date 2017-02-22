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
 *      'dir' => '日志标识',
 *      'msg' => '日志内容',
 *      'time' => '日志时间'
 * )
 *
 * 日志保存于 $this->dateDir目录下。
 * dir：日志标识，解析为日志目录。 'a/b',认为是多极目录
 * msg: 消息内容，回自动加上时间和换行符。
 * time: 日志时间，时间戳格式。
 *
 * 关于错误日志等级，附加在msg上。
 */
namespace bee\server;
class LogServer extends BaseServer
{
    public $serverType = self::SERVER_BASE;
    protected $fdMap = []; /* 文件描述符表 */
    protected $fdCount = 0; /* 当前文件总数 */
    public $config = array(
        /**
         * 运行时配置。swoole::set需要设置的参数
         */
        'serverd' => [
            'worker_num' => 2,
            'max_request' => 100240,
            'max_conn' => 1000,
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

    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        parent::onWorkerStart($server, $workerId);
        if ($workerId == 0) {
            $this->tick(6000, array($this, 'gcClear'), $this->dataDir);
        }
    }

    public function onReceive(\swoole_server $server, $fd, $fromId, $data)
    {
        $arr = json_decode($data, true);
        if ($arr == false || !is_array($arr)) {
            $this->errorLog("{$data} json解析失败：". json_last_error_msg());
            return null;
        }
        $dir = trim($arr['dir'], ' \t\n\r\0\x0B/\\');
        $time = $arr['time'] ?: time();
        $msg = $arr['msg'];
        $ip = $arr['ip'] ?: 'unknow';
        if ($dir == false || $msg == false) {
            $this->errorLog("receive：{$data} 缺少参数");
            return null;
        }
        $this->saveLog($dir, $time, $msg, $ip);
    }

    /**
     * 记录日志。
     * @param string $dir 目录
     * @param int $time 时间戳
     * @param string $msg 日志内容
     * @param string $ip ip地址
     * @return null
     */
    public function saveLog($dir, $time, $msg, $ip)
    {
        $dir = sprintf("%s/%s/%s", $this->dataDir, $dir, date('Y/m', $time));
        $file = sprintf("%s/%s.log", $dir, date('d', $time));
        $fd = $this->getFd($file);
        if ($fd == false) {
            return null;
        }
        $msg = sprintf("[%s]  [%s]  %s\n", date('Y-m-d H:i:s'), $ip, $msg);
        fwrite($fd, $msg);
    }

    /**
     * 获取一个文件描述符。
     * @param $file
     * @return bool|resource
     */
    public function getFd($file)
    {
        if ($this->fdMap[$file]) {
            return $this->fdMap[$file];
        }

        /* 目录不存在，创建目录 */
        $dir = dirname($file);
        if(!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fd = fopen($file, 'a+');
        if ($fd == false) {
            return false;
        }
        $this->gcFd();
        $this->fdMap[$file] = $fd;
        $this->fdCount++;
        return $fd;
    }

    /**
     * 回收文件描述符。防止无限增加。
     * 最大可以打开100个文件。
     */
    public function gcFd()
    {
        if ($this->fdCount >= 100) {
            foreach ($this->fdMap as $fd) {
                fclose($fd);
            }
            $this->fdMap = [];
            $this->fdCount = 0;
        }
    }

    public function gcClear($timerId, $path)
    {
        $handle = opendir($path);
        while (($file = readdir($handle)) !== false) {
            if ($file[0] === '.') { /* 过滤 . ..*/
                continue;
            }
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($fullPath)) { /* 目录 */
                $this->gcClear($timerId, $fullPath);
            } elseif (filemtime($fullPath) + 30 * 24 * 3600 < time()) {
                unlink($fullPath);
            }
        }
        closedir($handle);
    }
}