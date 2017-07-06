<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/1/19
 * Time: 12:19
 * 打开进程管道
 * 用于使用Php代理和命令行程序进行交互
 * 由于无法知道命令返回结束符。所有每次read这前，需要先关闭write
 */

namespace bee\client;

use bee\core\TComponent;

class ProcOpen
{
    use TComponent;
    /**
     * @var resource 写入管道
     */
    protected $writePipe;
    /**
     * @var resource 读取管道
     */
    protected $readPipe;
    /**
     * @var resource 错误消息管道
     */
    protected $errorPipe;
    /**
     * @var resource 进程管道
     */
    protected $fp;

    /**
     * @var string 命令
     */
    protected $cmd;

    private function _conn()
    {
        if (is_resource($this->fp)) {
            return null;
        }
        $desc = array(
            0 => array("pipe", "r"),  /* 标准输入，子进程从此管道中读取数据 */
            1 => array("pipe", "w"),  /* 标准输出，子进程向此管道中写入数据 */
            2 => array("pipe", "w") /* 标准错误 */
        );
        $this->fp = proc_open($this->cmd, $desc, $pipes);
        if (!$this->fp) {
            throw new \Exception("{$this->cmd}：创建管道失败");
        }
        $this->writePipe = $pipes[0];
        $this->readPipe = $pipes[1];
        $this->errorPipe = $pipes[2];
    }

    /**
     * 得到最后一次的错误消息
     * @return string
     */
    public function getLastError()
    {
        return stream_get_contents($this->errorPipe);
    }

    /**
     * 写入指令
     * @param $cmd
     * @return $this
     */
    public function write($cmd)
    {
        $this->_conn();
        fwrite($this->writePipe, "{$cmd}\n");
        return $this;
    }

    /**
     * 读取返回
     * @return string
     */
    public function read()
    {
        fclose($this->writePipe);
        $str = stream_get_contents($this->readPipe);
        fclose($this->readPipe);
        pclose($this->fp);
        return $str;
    }

    /**
     * 输出返回
     */
    public function show()
    {
        $str = $this->read();
        echo nl2br($str) . "\n";
    }
}