<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/7/24
 * Time: 9:50
 *
 * hadoop webhdfs api
 *
 * 重要说明：
 * webhdfs append操作中，replication的数量不能大于hadoop集群节点的数量，
 * 不然会报错。
 */
namespace bee\bigdata;

use bee\App;
use bee\client\Curl;
use bee\core\TComponent;

class HDFS
{
    use TComponent;
    /**
     * hdfs 主机
     * @var string
     */
    protected $host = '127.0.0.1';
    /**
     * hdfs 端口
     * @var string
     */
    protected $port = '50070';
    /**
     * curl 组件
     * @var Curl|string
     */
    protected $curl = 'curl';
    /**
     * 是否使用debug模式
     * @var bool
     */
    protected $debug = false;
    /**
     * hdfs 上传选项
     * @var array
     */
    protected $options = [
        'op' => '', /* 操作类型 */
        'overwrite' => "false", /* 是否覆盖 */
        'blocksize' => 536870912, /* hdfs 块大小， 默认使用dfs.block.size 配置 */
        'replication' => 3, /* 副本数量 */
        'permission' => null, /* 文件权限 */
        'buffersize' => null, /* 文件缓冲大小，默认使用io.file.buffer.size 配置 */
        'user.name' => null, /* 上传的用户名称 */
    ];
    /**
     * 对象初始化是的选项。
     * @var array
     */
    protected $defaultOptions = [];
    const ERR_FILE = 10;
    const ERR_DATANODE_URL = 11;
    const ERR_REQUEST = 12;

    /**
     * 上传文件
     */
    const OP_CREATE = 'CREATE';
    /**
     * 删除文件
     */
    const OP_DELETE = 'DELETE';
    /**
     * 追加文件
     */
    const OP_APPEND = 'APPEND';
    /**
     * 查看文件状态
     */
    const OP_STATUS = 'GETFILESTATUS';

    public function init()
    {
        $this->curl = App::s()->sure($this->curl);
        $this->defaultOptions = $this->options;
    }

    /**
     * 上传文件。如果hdfs目录不存在，会自动创建
     * @param string $localFile 本地路径
     * @param string|$hdfsFile hdfs路径
     * @return bool 成功返回true
     * @throws \Exception
     */
    public function create($localFile, $hdfsFile = null)
    {
        if (!is_file($localFile)) {
            return $this->setErrno(self::ERR_FILE);
        }

        /* 请求namenode 获取datanode */
        $hdfsFile = $hdfsFile ?: $localFile;
        $this->options['op'] = self::OP_CREATE;
        $datanodeUrl = $this->getDatanodeUrl($this->buildUrl($hdfsFile), Curl::HTTP_PUT);
        if ($datanodeUrl == false) {
            return false;
        }

        /* 向datanode 上传文件 */
        $fp = fopen($localFile, 'r');
        $res = $this->curl
            ->url($datanodeUrl)
            ->type(Curl::HTTP_PUT)
            ->file($fp)
            ->exec();
        $curlInfo = $this->curl->getCurlinfo();
        $this->clear();
        if ($curlInfo['http_code'] == 201) {
            return true;
        } else {
            $this->setErrmsg($res);
            return false;
        }
    }

    /**
     * 删除文件
     * @param string $hdfsFile hdfs 文件路径
     * @param bool $recursive 是否递归删除
     * @return bool
     * @throws \Exception
     */
    public function delete($hdfsFile, $recursive = false)
    {
        $this->options['op'] = self::OP_DELETE;
        $this->options['recursive'] = $recursive ? "true" : "false";
        $url = $this->buildUrl($hdfsFile);
        $res = $this->curl
            ->url($url)
            ->type(Curl::HTTP_DELETE)
            ->exec();
        $curlInfo = $this->curl->getCurlinfo();
        if ($curlInfo['http_code'] == 200) {
            return true;
        } else {
            $this->setErrmsg($res);
            return false;
        }
    }

    /**
     * 向hdfs追加文件
     * @param $localFile
     * @param null $hdfsFile
     * @return bool
     * @throws \Exception
     */
    public function append($localFile, $hdfsFile = null)
    {
        if (!is_file($localFile)) {
            return $this->setErrno(self::ERR_FILE);
        }

        /* 请求namenode 获取datanode */
        $hdfsFile = $hdfsFile ?: $localFile;
        $this->options['op'] = self::OP_APPEND;
        $datanodeUrl = $this->getDatanodeUrl($this->buildUrl($hdfsFile), Curl::HTTP_POST);
        if ($datanodeUrl == false) {
            return $this->setErrno(self::ERR_DATANODE_URL);
        }

        /* 向datanode 上传文件 */
        $res = $this->curl
            ->url($datanodeUrl)
            ->type(Curl::HTTP_POST)
            ->setOption(CURLOPT_POSTFIELDS, ['file' => new \CURLFile($localFile)])
            ->exec();
        $curlInfo = $this->curl->getCurlinfo();
        $this->clear();
        if ($curlInfo['http_code'] == 200) {
            return true;
        } else {
            $this->setErrmsg($res);
            return false;
        }
    }

    /**
     * 获取文件状态
     * @param $hdfsFile
     * @return array|bool|mixed
     * @throws \Exception
     */
    public function status($hdfsFile)
    {
        $this->options['op'] = self::OP_STATUS;
        $res = $this->curl
            ->url($this->buildUrl($hdfsFile))
            ->returnType(Curl::RETURN_BODY)
            ->type(Curl::HTTP_GET)
            ->dataType(Curl::DATA_JSON)
            ->exec();
        $this->clear();
        return $res;
    }

    /**
     * 判断文件是否存在
     * @param $hdfsFile
     * @return bool
     */
    public function exists($hdfsFile)
    {
        $res = $this->status($hdfsFile);
        if ($res == false || !isset($res['FileStatus'])) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 创建或者追加文件。
     * @param $localFile
     * @param $hdfsFile
     * @return bool
     */
    public function createOrAppend($localFile, $hdfsFile)
    {
        if ($this->exists($hdfsFile)) {
            return $this->append($localFile, $hdfsFile);
        } else {
            return $this->create($localFile, $hdfsFile);
        }
    }

    /**
     * 获取namenode 返回的重定向 datanode 的url
     * @param $url
     * @param $type
     * @return null|string
     */
    public function getDatanodeUrl($url, $type)
    {
        $header = $this->curl
            ->returnType(Curl::RETURN_HEADER)
            ->url($url)
            ->type($type)
            ->exec();
        if (preg_match('/Location:(.*?)\n/', $header, $matches)) {
            $redirectUrl = trim($matches[1]);
            return $redirectUrl;
        }
        return $this->setErrmsg($header);
    }

    /**
     * 设置文件是否覆盖
     * @param $bool
     * @return $this
     */
    public function overwrite($bool)
    {
        $bool = $bool ? "true" : "false";
        $this->options[__FUNCTION__] = $bool;
        return $this;
    }

    /**
     * 定义块大小
     * @param $size
     * @return $this
     */
    public function blocksize($size)
    {
        $this->options[__FUNCTION__] = $size;
        return $this;
    }

    /**
     * 设置副本数量。副本数为1，表示只有1份数据，没有备份。
     * @param $num
     * @return $this
     */
    public function replication($num)
    {
        $this->options[__FUNCTION__] = $num;
        return $this;
    }

    /**
     * 设置文件权限
     * @param $mode
     * @return $this
     */
    public function permission($mode)
    {
        $this->options[__FUNCTION__] = $mode;
        return $this;
    }

    /**
     * 设置文件缓冲
     * @param $size
     * @return $this
     */
    public function buffersize($size)
    {
        $this->options[__FUNCTION__] = $size;
        return $this;
    }

    /**
     * 设置用户名，用户名称不对，会有权限问题
     * @param $username
     * @return $this
     */
    public function username($username)
    {
        $this->options['user.name'] = $username;
        return $this;
    }

    public function buildUrl($hdfsFile)
    {
        $hdfsFile = trim($hdfsFile, '/');
        $this->options = array_filter($this->options, function($v) {
            if ($v === null) {
                return false;
            } else {
                return true;
            }
        });
        $url =  "http://{$this->host}:{$this->port}/webhdfs/v1/";
        $url .= "{$hdfsFile}?" . http_build_query($this->options);
        return $url;
    }

    public function clear()
    {
        $this->options = $this->defaultOptions;
        $this->errmsg = "";
        $this->errno = 0;
    }

    public function errmsgMap()
    {
        return [
            self::ERR_FILE => "文件不存在",
            self::ERR_DATANODE_URL => '没有得到datanode的url',
            self::ERR_REQUEST => '请求失败'
        ];
    }
}