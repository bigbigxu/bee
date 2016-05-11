<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/5/12
 * Time: 16:39
 */
namespace bee\server;
class Bench
{
    public $processNum; //进程数，相当于并发数
    public $requestNum; //请求数
    public $serverUrl; //请求的url
    public $serverConfig; //server的配置文件
    public $sendData; //当前要发送的数据
    public $mainPid; //主进程pid
    public $shmKey = '/dev/shm/t.log';
    public $childPid = array(); //子进程的pid
    public $timeEnd;
    public $showDetail;
    public $pid;
    public $processReqNum; //每个进程平均发起的请求数量。

    public function run()
    {
        $this->mainPid = posix_getpid();
        $this->processReqNum = intval($this->requestNum / $this->processNum);
        for ($i = 0; $i < $this->processNum; $i++) {
            $this->childPid[] = $this->start(array($this,'worker'));
        }
        for ($i = 0; $i < $this->processNum; $i++) {
            $status = 0;
            $pid = pcntl_wait($status);
        }
        $this->timeEnd = microtime(true);
    }

    public function worker()
    {
        $lost = 0;
        if (!file_exists($this->shmKey)) {
            file_put_contents($this->shmKey, microtime(true));
        }
        if ($this->showDetail) {
            $start = microtime(true);
        }
        $this->pid = posix_getpid();
        for ($i = 0; $i <= $this->processNum; $i++) {

        }
    }

    public function start()
    {
        $pid = pcntl_fork();
        if ($pid > 0) {
            return $pid;
        } elseif ($pid == 0) {
            $this->worker();
        } else {
            die("fork fail\n");
        }
    }
}