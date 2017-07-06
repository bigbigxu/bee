<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/6/27
 * Time: 14:34
 */
namespace bee\client;

use bee\core\TComponent;

class Shell
{
    use TComponent;
    /**
     * 当前执行的命令
     * @var
     */
    protected $cmd;
    /**
     * 要执行的命令所使用的环境变量。
     * 设置此参数为 NULL 表示使用和当前 PHP 进程相同的环境变量
     * @var array
     */
    protected $env = null;
    /**
     * 其他选项，设置为null
     * @var null
     */
    protected $otherOptions = null;
    /**
     * 保存proc_open返回的管道对端
     * 0 => 可以向子进程标准输入写入的句柄
     * 1 => 可以从子进程标准输出读取的句柄
     * 2 => 可以从子进程标准错误读取的句柄
     * @var array
     */
    protected $pipes = [];
    /**
     * 要执行命令的初始工作目录。
     * 必须是绝对路径，设置此参数为 NULL 表示使用默认值（当前 PHP 进程的工作目录）
     * @var null
     */
    protected $cwd = null;
    /**
     * 一个索引数组。
     * 数组的键表示描述符，数组元素值表示 PHP 如何将这些描述符传送至子进程。
     * r,w是shell子经常的操作。
     * r，子进程读，父进程写
     * w，子进程读，父进程写
     * 0 表示标准输入（stdin），
     * 1 表示标准输出（stdout），
     * 2 表示标准错误（stderr）
     *
     * @var array
     */
    protected $descriptorSpec = [
        0 => ["pipe", "r"], /* 标准输入 */
        1 => ["pipe", "w"], /* 标准输出 */
        2 => ["pipe", "w"], /* 标准错误输出 */
    ];
    /**
     * proc_open返回值
     * @var resource
     */
    protected $fp;
    /**
     * @param array $env
     * @return $this
     */
    public function setEnv($env)
    {
        $this->env = $env;
        return $this;
    }

    /**
     * @param null $otherOptions
     * @return $this;
     */
    public function setOtherOptions($otherOptions)
    {
        $this->otherOptions = $otherOptions;
        return $this;
    }

    /**
     * @param null $cwd
     * @return $this;
     */
    public function setCwd($cwd)
    {
        $this->cwd = $cwd;
        return $this;
    }

    /**
     * 设置命令
     * @param $cmd
     * @return $this
     */
    public function setCmd($cmd)
    {
        $this->cmd = $cmd;
        return $this;
    }

    /**
     * @return array
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * @return null
     */
    public function getOtherOptions()
    {
        return $this->otherOptions;
    }

    /**
     * @return null
     */
    public function getCwd()
    {
        return $this->cwd;
    }

    /**
     * @return mixed
     */
    public function getCmd()
    {
        return $this->cmd;
    }

    /**
     * 执行命令
     * @return bool|string
     * @throws \Exception
     */
    public function exec()
    {
        $this->fp = proc_open(
            $this->cmd,
            $this->descriptorSpec,
            $this->pipes,
            $this->cwd,
            $this->env,
            $this->otherOptions
        );
        if (!is_resource($this->fp)) {
            throw new \Exception("proc_open失败，cmd = {$this->cmd}");
        }
        $this->error = stream_get_contents($this->pipes[2]);
        /* 执行失败 */
        if ($this->error != '') {
            $this->errno = 2;
            return false;
        } else {
            $str = stream_get_contents($this->pipes[1]);
            return $str;
        }
    }

    /**
     * php proc_open 的代理方法
     * @param $cmd
     * @param $descriptorSpec
     * @param $pipes
     * @param null $cwd
     * @param null $env
     * @param null $otherOptions
     * @return bool|string
     * @throws \Exception
     */
    public function procExec($cmd, $descriptorSpec, &$pipes, $cwd = null, $env = null, $otherOptions = null)
    {
        $this->cmd = $cmd;
        $this->descriptorSpec = $descriptorSpec;
        $this->pipes = &$pipes;
        $this->cwd = $cwd;
        $this->env = $env;
        $this->otherOptions = $otherOptions;
        return $this->exec();
    }
    /**
     * 关闭相应的文件描述符
     *
     * 如果有连接到进程的已经打开的管道， 那么需要在调用此函数之前调用 fclose() 函数来关闭管道，
     * 否则会引发死锁 - 在管道处于打开状态时，子进程将不能退出
     */
    public function clear()
    {
        fclose($this->pipes[0]);
        fclose($this->pipes[1]);
        fclose($this->pipes[2]);
        proc_close($this->fp);

        $this->errno = 0;
        $this->errmsg = '';
    }

    /**
     * 简单执行shell命令
     * @param string $cmd 命令名称
     * @param string $errmsg 用来保存错误消息，
     * @return mixed
     * @throws \Exception
     */
    public static function simpleExec($cmd, &$errmsg)
    {
        $o = new static;
        $o->cmd = $cmd;
        $str = $o->exec();
        $errmsg = $o->errmsg;
        $o->clear();
        return $str;
    }

    /**
     * 从shell子进程读取数据
     * @param int $length
     * @return string
     */
    public function read($length = -1)
    {
        return stream_get_contents($this->pipes[1], $length);
    }

    /**
     * 向shell子进程写入数据
     * @param $str
     * @return int
     */
    public function write($str)
    {
        return fwrite($this->pipes[0], $str);
    }
}