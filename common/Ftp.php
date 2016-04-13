<?php

/**
 * ftp上传相关的操作类。
 * 默认将使用随机文件名基于年月的创建目录。
 * 自动创建目录。
 * @author xuen
 *
 * @example
 * $ftp=new Ftp(array(
 *     'username'=>'123',
 *     'password'=>'123',
 *     'host'=>'192.168.100.11',
 *     'dir'=>'/',
 *     'random_name'=>false,
 * ));
 * $ftp->upload('test.php');
 * echo $ftp->error;
 *
 */
class Ftp
{
    protected $ch;
    protected $option = array(
        'username' => '', //用户名
        'password' => '', //用户密码,
        'port' => '21', //端口
        'host' => '127.0.0.1',//主机名称
        'dir' => '/', //需要登录的目录
        'random_name' => true, //使用随机文件名称,其它值使用本身的名称。
        'time_out' => 30,//链接超时时间
    );
    protected $homeDir;  //登录后ftp的根目录。
    protected $host;
    protected $port;
    protected $username;
    protected $password;
    protected $dir;
    public $error;

    /**
     * Ftp constructor.
     * @param $option
     * @throws Exception
     */
    public function __construct($option)
    {
        $this->option = array_merge($this->option, $option);
        $this->host = $this->option['host'];
        $this->port = $this->option['port'];
        $this->username = $this->option['username'];
        $this->password = $this->option['password'];

        $this->ch = ftp_connect($this->host, $this->port, $this->option['time_out']);
        //登录ftp
        if (!$this->ch) {
            throw new Exception($this, '无法链接ftp服务器');
        }
        if (!ftp_login($this->ch, $this->username, $this->password)) {
            throw new Exception('无法登录ftp服务器');
        }

        //得到根目录
        $this->homeDir = ftp_pwd($this->ch);
        $this->dir = trim($this->option['dir'], '/');

        //打开被动传输出模式
        ftp_pasv($this->ch, true);
    }

    /**
     * 创建目录
     * 需要创建的目录，注意如果为空
     * 创建$this->dir的目录
     * 创建目录的时候会自动选择到录前目录
     * 目录权限不包含前面的0
     * @param $dir
     * @return bool
     */
    public function mkdir($dir = '', $mode = 755)
    {
        $dir = $dir ? $dir : $this->dir;
        $dir = dirname($dir);
        $dirArr = explode('/', $dir);
        foreach ($dirArr as $row) {
            if ($row == '/' || $row == '\\' || $row == '') {
                continue;
            }
            if (!ftp_chdir($this->ch, $row)) {//递归创建和选择目录

                if (ftp_mkdir($this->ch, $row)) {  //执行ftp命令修改目录权限。
                    ftp_raw($this->ch, "site chmod {$mode} {$row}");
                    ftp_chdir($this->ch, $row);
                } else {
                    $this->error = '无法创建ftp目录';
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 解析ftp字符串
     * @param string $ftp
     */
    public function parseFtp($ftp)
    {
        if ($ftp{strlen($ftp) - 1} != '/')
            $ftp .= '/';
        preg_match('/ftp:\/\/(.*?):(.*?)@(.*?)\/(.*)/i', $ftp, $ma);
        $data['username'] = $ma[1];
        $data['password'] = $ma[2];
        $arr = explode(':', $ma[3]);
        $data['host'] = $arr[0];
        $data['port'] = @$arr[1] ? $arr[1] : 21;
        $data['dir'] = $ma[4];
        return $data;
    }

    /**
     * 得到新随机文件目录名称。
     * 基于年月
     * @param $sourceFileName 要上传的文件名称。
     * @param $dirFormat 目录格式。
     * @return string
     */
    public function getRandFileName($sourceFileName, $dirFormat = 'Y/m')
    {
        $dir = date(trim($dirFormat, '/'));
        $pathinfo = pathinfo($sourceFileName);
        $ext = $pathinfo['extension'];
        $rand = date('YmdHis') . mt_rand(1, 1000);
        return "{$this->option['dir']}/{$dir}/$rand.{$ext}";
    }

    /**
     * 执行文件上传　
     * @param string $localFile 本地文件路径
     * @param int $mode 上传后文件的权限，默认为0644;
     * 文件权限要包含前面的0
     * @return string  上传后的文件路径名称。
     */
    public function upload($localFile, $mode = 0755)
    {
        if (!file_exists($localFile)) {
            $this->error = "本地文件不存";
            return false;
        }

        //得到上传后的文件名。
        if ($this->option['random_name'] == true) {
            $newFileName = $this->getRandFileName($localFile);
        } else {
            $newFileName = "{$this->option['dir']}/" . basename($localFile);
        }
        if (!$this->mkdir($newFileName)) {
            return false;
        }

        $ftpType = $this->_setFtpType($localFile);
        $baseName = basename($newFileName);
        //开始上传
        if (ftp_put($this->ch, $baseName, $localFile, $ftpType)) {
            ftp_chmod($this->ch, $mode, $baseName);
            return $newFileName;
        }
        $this->error = '上传失败';
        return FALSE;
    }

    /**
     * 根据文件后缀名设定上传模式
     * @param  $fileName
     * @return int
     */
    public function _setFtpType($fileName)
    {
        $arr = pathinfo($fileName);
        $ext = $arr['extension'];
        $text_type = array(
            'txt',
            'text',
            'php',
            'phps',
            'php4',
            'js',
            'css',
            'htm',
            'html',
            'phtml',
            'shtml',
            'log',
            'xml'
        );
        return (in_array($ext, $text_type)) ? FTP_ASCII : FTP_BINARY;
    }
}