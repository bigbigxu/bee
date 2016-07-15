<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/5/16
 * Time: 15:40
 */
namespace bee\server;
class CrontabServer extends BaseServer
{
    protected $name = 'crontab_server';
    protected $lastCrontabTime;

    public function onReceive(\swoole_server $server, $fd, $fromId, $data)
    {
        parent::onReceive($server, $fd, $fromId, $data);
    }

    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        if ($workerId == 0) {
            $this->lastCrontabTime = time() - date('s') - 1;
            $this->tick(1000, array($this, 'crontab'));
        }
        parent::onWorkerStart($server, $workerId);
    }

    /**
     * 执行定时器脚本。会动态加载config.php配置文件
     */
    public function crontab()
    {
        $config = require $this->baseDir . '/config.php';
        $this->lastCrontabTime = $time = time();
        $timerTask = $config['timer_task'];
        $model = new \LinuxCrontab();
        foreach ($timerTask['linux_crontab'] as $key => $row) {
            $tmp = preg_split('/[\s]+/', trim($row));
            $cron = implode(' ', array_slice($tmp, 0, 5));
            $cmd = implode(' ', array_slice($tmp, 5));
            $flag = $model->check($time, $cron);
            if ($model->getErrno() != 0) {
                $this->errorLog("定时器 [{$key}] ：" . $model->getError());
            }
            if ($flag == true) {
                $cmd = rtrim($cmd, '&') . ' &'; //添加&,将脚本后台执行。防止阻塞。
                $str = shell_exec($cmd);
                if ($timerTask['open_log']) {
                    $this->crontabLog("定时器 [{$key}] 已经执行: {$str}");
                }
            }
        }
    }

    public function crontabLog($msg)
    {
        $file = $this->c("server.log_dir") . '/crontab.log';
        $this->log($file, $msg);
    }
}