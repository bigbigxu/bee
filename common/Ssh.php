<?php

/**
 * ssh扩展类
 * 1. 下载 php extension ssh2
 * 下载地址 http://windows.php.net/downloads/pecl/releases/ssh2/0.12/
 * 根据自己PHP的版本去下载，我使用的是线程安全的，所以下载的是php_ssh2-0.12-5.4-ts-vc9-x86.zip
 * 2. 解压完后，会有三个文件，libssh2.dll、php_ssh.dll、php_ssh2.pdb。
 * 3. 将 php_ssh.dll、php_ssh2.pdb 放到你的 php 扩展目录下 php/ext/ 下。
 * 4. 将libssh2.dll 复制到 c:/windows/system32 和 c:/windows/syswow64 各一份
 * 5. php.ini中加入 extension=php_ssh2.dll
 * 6. 重启apache，即可使用php执行ssh连接操作了。
 * 查看phpinfo()，是否有显示php_ssh2扩展加载成功。
 * linux下  apt-get install libssh2-1-dev libssh2-php
 * @author xuen
 * @example
 *     $ssh=new Ssh(array(
 * 'host'=>'192.168.0.206',
 * 'username'=>'newpoyang',
 * 'password'=>'yangliuborealplay'
 * ));
 * echo $ssh->execute('php -m');
 * echo $ssh->error;
 */
class Ssh
{
    //链接参数选项
    public $option = array(
        'username' => '',
        'password' => '',
        'host' => '127.0.0.1',
        'port' => 22
    );

    /**
     * 可以被执行的命令
     * @var unknown
     */
    public $allowCommand = array(
        '/usr/bin/php',
        'php',
        'ls',
        'll',
        'redis-cli',
        'mysql'
    );

    //链接资源句柄。
    public $ch = false;
    public $error;

    public function __construct($option)
    {
        $this->option = array_merge($this->option, $option);

        if (!function_exists('ssh2_connect'))
            throw new Exception($this, '请开启php-ssh扩展');

        //链接ssh服务器
        $this->ch = ssh2_connect($this->option['host'], $this->option['port']);
        if (!$this->ch)
            throw new Exception($this, '链接服务器失败');

        //登录验证
        if (!ssh2_auth_password($this->ch, $this->option['username'], $this->option['password']))
            throw new Exception($this, 'ssh用户名或密码不正确');
    }

    /**
     * @param $command
     * @return bool|string
     */
    public function execute($command)
    {
        if ($this->checkCommand($command) == false) {
            $this->error = '不支持的命令';
            return false;
        }

        $stream = ssh2_exec($this->ch, $command);
        stream_set_blocking($stream, true);
        $str = stream_get_contents($stream);
        if (DIRECTORY_SEPARATOR == '\\' && $GLOBALS['argv'][0])
            $str = mb_convert_encoding($str, 'GBK', 'UTF-8');
        return $str;
    }

    /**
     * 检查命令是不是可以执行。
     * @param $command
     * @return bool
     */
    private function checkCommand($command)
    {
        foreach ($this->allowCommand as $row) {
            if (strpos($command, $row) === 0)
                return true;
        }
        return false;
    }
}