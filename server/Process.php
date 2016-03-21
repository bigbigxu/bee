<?php
namespace bee\server;
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/2/22
 * Time: 15:42
 */
class Process
{
    /**
     * @var \swoole_process
     */
    protected $_p;

    /**
     * Process constructor.
     * 创建一个进程
     * @param string|array $callback 子进程创建成功后要执行的函数
     * @param bool $stdin 重定向子进程的标准输入和输出。
     *     启用此选项后，在进程内echo将不是打印屏幕，而是写入到管道。
     *     读取键盘输入将变为从管道中读取数据。 默认为阻塞读取
     * @param bool $pipe 是否创建管道，启用$redirect_stdin_stdout后，
     *     此选项将忽略用户参数，强制为true 如果子进程内没有进程间通信，
     *     可以设置为false
     */
    public function __construct($callback = null, $stdin = false, $pipe = true)
    {
        if ($callback === null) {
            $callback = array($this, 'callback');
        }
        $this->_p = new \swoole_process($callback, $stdin, $pipe);
    }

    public function callback()
    {
        echo 'process' . PHP_EOL;
    }

    /**
     * 执行fork系统调用，启动进程
     * 创建成功返回子进程的PID，创建失败返回false。
     * 可使用swoole_errno和swoole_strerror得到错误码和错误信息
     *
     * 执行后子进程会保持父进程的内存和资源，
     * 如父进程内创建了一个redis连接，那么在子进程会保留此对象，
     * 所有操作都是对同一个连接进行的。
     * @return int
     */
    public function start()
    {
        return $this->_p->start();
    }

    /**
     * 子进程的PID
     * @return int
     */
    public function getPid()
    {
        return $this->_p->pid;
    }

    /**
     * 属性为管道的文件描述符
     * @return int
     */
    public function getPipe()
    {
        return $this->_p->pipe;
    }

    /**
     * 设置进程名称
     * @param $name
     */
    public function name($name)
    {
        $this->_p->name($name);
    }

    /**
     * 执行一个外部程序，此函数是exec系统调用的封装。
     *
     * 执行成功后，当前进程的代码段将会被新程序替换。
     * 子进程脱变成另外一套程序。父进程与当前进程仍然是父子进程关系。
     * @param $cmd
     * @param $args
     * @return bool
     */
    public function exec($cmd, $args)
    {
        return $this->_p->exec($cmd, $args);
    }

    /**
     * 在子进程内调用write，主进程会收到数据
     * 在主进程内调用write，子进程会收到数据
     * 向管道内写入数据
     * @param $data
     * @return int
     */
    public function write($data)
    {
        return $this->_p->write($data);
    }

    /**
     * 从管道中读取数据。
     * @param int $bufferSize
     * @return string
     */
    public function read($bufferSize = 8912)
    {
        return $this->_p->read($bufferSize);
    }

    /**
     * 用于关闭创建的好的管道
     * @return mixed
     */
    public function close()
    {
        return $this->_p->close();
    }

    /**
     * $status是退出进程的状态码，如果为0表示正常结束，
     * 会继续执行PHP的shutdown_function，其他扩展的清理工作。
     * 如果$status不为0，表示异常退出，会立即终止进程。不再执行PHP的shutdown_function，其他扩展的清理工作。
     *
     * 在父进程中，执行swoole_process::wait可以得到子进程退出的事件和状态码。
     * @param int $status
     * @return mixed
     */
    public function _exit($status = 0)
    {
        return $this->_p->exit($status);
    }

    /**
     * 向子进程发送信号
     * $signo=0，可以检测进程是否存在，不会发送信号
     * @param $pid
     * @param int $signo
     * @return int
     */
    public  function kill($pid, $signo = SIGTERM)
    {
        return \swoole_process::kill($pid, $signo);
    }

    /**
     * 回收结束运行的子进程
     * 子进程结束必须要执行wait进行回收，否则子进程会变成僵尸进程
     * @param bool $blocking 参数可以指定是否阻塞等待，默认为阻塞
     * @return array|false
     */
    public function wait($blocking = true)
    {
        return \swoole_process::wait($blocking);
    }

    /**
     * 使当前进程脱变为一个守护进程
     * @param bool $noChdir
     * @param bool $noClose
     */
    public function daemon($noChdir = false, $noClose = false)
    {
        return \swoole_process::daemon($noChdir, $noClose);
    }

    /**
     * 设置异步信号监听
     * 此方法基于signalfd和eventloop是异步IO，不能用于同步程序中
     * @param $signal
     * @param null $callback
     */
    public function signal($signal, $callback = null)
    {
        return \swoole_process::signal($signal, $callback);
    }
}