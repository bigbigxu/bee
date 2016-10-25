<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/9/25
 * Time: 15:28
 * curl操作类，支持链式操作。
 * @desc 在php.ini 安全模式开局的情况下，CURLOPT_FOLLOWLOCATION不可用
 * @author xuen
 * @version 1.0
 */
class Curl
{
    /**
     * curl请求发起的默认配置。
     * @var array
     */
    protected $defaultOptions = array(
        CURLOPT_URL => '', // 请求的URL
        CURLOPT_RETURNTRANSFER => 1, //设置有返回信息，以流的形式返回，非不是直接输出
        CURLOPT_HTTPGET => 1, //设定为GET请求。
        CURLOPT_CONNECTTIMEOUT => 30, // 设置默认链接超时间为30秒
        CURLOPT_TIMEOUT => 30, //设置下载时间最多30秒。
        //CURLOPT_FOLLOWLOCATION => true, //自动跟踪重定向。
        CURLOPT_ENCODING => 'gzip',  // 设置客户端支持gzip压缩, 用于节省流量。
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; rv:23.0) Gecko/20100101 Firefox/23.0',
        CURLOPT_SSL_VERIFYPEER => false
    );
    protected $options; //发起请求的配置
    private $_ch; //curl指针
    private static $_instance;

    protected $dataType = self::DATA_TYPE_HTML; //设定返回的数据类型
    protected $url; //请求的Url
    protected $data; //请求的数据
    protected $type = self::HTTP_GET; //类型

    /**
     * 定义数据返回类型常量
     */
    const DATA_XML = 'xml';
    const DATA_JSON = 'json';
    const DATA_TYPE_HTML = 'HTML';
    const DATA_JSONP = 'jsonp';

    const HTTP_GET = 'get';
    const HTTP_POST = 'post';
    const HTTP_PUT = 'put';

    const RETURN_ALL = 'all'; //返回header+body
    const RETURN_BODY = 'body'; //返回body
    const RETURN_HEADER = 'header'; //返回header

    const CLIENT_ANDROID = 'android';
    const CLIENT_PC = 'pc';
    const CLIENT_IOS = 'ios';

    const CHARSET_UTF8 = 'UTF8'; //utf8编码
    const CHARSET_GBK = 'GBK'; //gbk, gb2312编码
    protected $lastOptions = array(); //上一次请求的相关参数

    public function __construct()
    {
        $this->options = $this->defaultOptions;
        if (extension_loaded('curl') == false) {
            throw new Exception('当前环境不支持curl');
        }
        $this->_ch = curl_init();
    }

    /**
     * 实例化对象
     * @param bool $single 是否返回单例对象。
     * @return Curl
     */
    public static function getInstance($single = true)
    {
        if ($single) {
            if(!is_object(self::$_instance)){
                self::$_instance = new self();
            }
            return self::$_instance;
        } else {
            return new self();
        }
    }

    /**
     * 设定请求返回的数据类型。
     * @param $type
     * @return $this;
     */
    public function dataType($type)
    {
        if ($type == false) {
            return $this;
        }
        $this->dataType = $type;
        return $this;
    }

    /**
     * 执行一个get请求
     * @param string $url 请求的url
     * @param array $data 请求的数据
     * @param string $dataType 返回数据类型
     * @param array $options curl执行请求选项。
     * @return array|bool|mixed
     */
    public function get($url = '', $data = array(), $dataType = '', $options = array())
    {
        $r = $this
            ->type(self::HTTP_GET)
            ->url($url)
            ->data($data)
            ->dataType($dataType)
            ->exec($options);
        return $r;
    }

    /**
     * 执行一个简单get请求, 静态方法。
     * @param string $url 请求的url
     * @param array $data 请求的数据
     * @param string $dataType 返回数据类型
     * @param array $options curl执行请求选项。
     * @return array|bool|mixed
     */
    public static function simpleGet($url, $data = array(), $dataType = '', $options = array())
    {
        $r = self::getInstance()
            ->get($url, $data, $dataType, $options);
        return $r;
    }

    /**
     * 执行一个post请求
     * @param string $url 请求的url
     * @param array $data 请求的数据
     * @param string $dataType 返回数据类型
     * @param array $options curl执行请求选项。
     * @return array|bool|mixed
     */
    public function post($url = '', $data = array(), $dataType = '', $options = array())
    {
        $r = $this
            ->type(self::HTTP_POST)
            ->url($url)
            ->data($data)
            ->dataType($dataType)
            ->exec($options);
        return $r;
    }


    /**
     * 执行一个简单 post请求。静态方法。
     * @param string $url 请求的url
     * @param array $data 请求的数据
     * @param string $dataType 返回数据类型
     * @param array $options curl执行请求选项。
     * @return array|bool|mixed
     */
    public static function simplePost($url, $data = array(), $dataType = '', $options = array())
    {
        $r = self::getInstance()
            ->post($url, $data, $dataType, $options);
        return $r;
    }

    /**
     * 执行一个curl请求
     * @param array $options
     * @return array|bool|mixed
     * @throws Exception
     */
    public function exec($options = array())
    {
        $this->_beforeExec();
        $this->options = $options + $this->options;
        curl_setopt_array($this->_ch, $this->options);;
        $str = curl_exec($this->_ch);

        if ($this->getErrno() != 0) {
            throw new Exception('curl请求失败: ' . $this->getErrmsg());
        }
        if ($this->dataType == self::DATA_JSON) {
            $r = json_decode($str, true);
        } elseif ($this->dataType == self::DATA_XML) {
            $r = StructXml::getTreeArray($str);
        } elseif ($this->dataType == self::DATA_JSONP) {
            preg_match('/\(([\s\S]+)\)/', $str, $ma);
            $r = json_decode($ma[1], true);
        } else {
            $r = &$str;
        }
        $this->_clear();
        return $r;
    }

    /**
     * 执行curl请求前的操作
     * @return $this
     */
    public function _beforeExec()
    {
        //设置请求url
        $this->options[CURLOPT_URL] = $this->url;

        //设置请求类型
        $type = $this->type;
        if ($type == self::HTTP_POST) {
            $this->options[CURLOPT_POST] = 1;
        } elseif ($type == self::HTTP_PUT) {
            $this->options[CURLOPT_PUT] = 1;
        } else {
            $this->options[CURLOPT_HTTPGET] = 1;
        }

        //设置请求数据
        $data = $this->data;
        if ($data != false) {
            if ($this->options[CURLOPT_POST] == 1) {
                if (is_array($data)) {
                    $this->options[CURLOPT_POSTFIELDS] = http_build_query($data);
                } else {
                    $this->options[CURLOPT_POSTFIELDS] = $data;
                }
            } else {
                $this->url = self::createUrl($this->url, $data);
                $this->options[CURLOPT_URL] = $this->url;
            }
        }
        return $this;
    }

    /**
     * 执行一次http请求后，需要清理一些数据。
     * 以免对下一次请求有影响。
     */
    private function _clear()
    {
        $this->lastOptions = $this->options;
        $this->options = $this->defaultOptions;
        $this->dataType = self::DATA_TYPE_HTML;
        $this->url = '';
        $this->data = '';
        $this->type = self::HTTP_GET;
    }

    /**
     * 使用phpquery来进行html页面采集
     * @desc 如果没有声明charset，phpquery会自动匹配编码。这个有可能出错。
     * 比如不支持gb2312,会导至创建dom对象失败。
     * 建议在使用先将编码转成utf8,创建对象时显示声明文档所使用编码。
     *
     * @param string $str 字符串
     * @param $charset
     * @return phpQueryObject
     */
    public static function pq($str, $charset = self::CHARSET_UTF8)
    {
        $app = App::getInstance();
        $app->loadClass(array(
            'phpQuery' => $app->getSysDir() . '/extension/phpQuery/phpQuery.php'
        ));
        if (count(phpQuery::$documents) > 512) {
            phpQuery::$documents = array(); //清空html文档缓存
        }
        $domObject = phpQuery::newDocumentHTML($str, $charset);
        return $domObject;
    }

    /**
     * 设置请求的url
     * @param $url
     * @return $this
     */
    public function url($url)
    {
        if ($url == false) {
            return $this;
        }
        $this->url = rtrim($url, '?');
        return $this;
    }

    /**
     * 设定请求类型。
     * @param $type
     * @return $this
     */
    public function type($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * 设定请求的数据
     * @param $data
     * @return $this
     */
    public function data($data)
    {
        if ($data == false) {
            return $this;
        }
        $this->data = $data;
        return $this;
    }

    /**
     * 得到错误消息
     * @return string
     */
    public function getErrmsg()
    {
        return curl_error($this->_ch);
    }

    /**
     * 得到错误码。
     * @return int
     */
    public function getErrno()
    {
        return curl_errno($this->_ch);
    }

    /**
     * 得到执行curl的相关情况
     * @return mixed
     */
    public function getCurlinfo()
    {
        return curl_getinfo($this->_ch);
    }

    /**
     * 设置header头
     * @param $header
     * @return $this
     */
    public function header($header)
    {
        if ($header != false) {
            $this->options[CURLOPT_HTTPHEADER] = $header;
        }
        return $this;
    }

    /**
     * 设置请求来源
     * @param $referer
     * @return $this
     */
    public function referer($referer)
    {
        if ($referer != false) {
            $this->options[CURLOPT_REFERER] = $referer;
        }
        return $this;
    }

    /**
     * 设置请求用户名和密码
     * @param $username
     * @param $password
     * @return $this
     */
    public function auth($username, $password)
    {
        $this->options[CURLOPT_USERPWD] = "{$username}:{$password}";
        return $this;
    }

    /**
     * 设置请求最大链接时间
     * @param $time
     * @return $this
     */
    public function connTime($time)
    {
        if ($time != false) {
            $this->options[CURLOPT_CONNECTTIMEOUT] = $time;
        }
        return $this;
    }

    /**
     * 设置最大下载时间
     * @param $time
     * @return $this
     */
    public function loadTime($time)
    {
        if ($time != false) {
            $this->options[CURLOPT_TIMEOUT] = $time;
        }
        return $this;
    }

    /**
     * 设置请求代理
     * @param string $ip 代理ip
     * @param int $port 端口
     * @param bool|string $auth 用户密码 a:b形势
     * @return $this
     */
    public function proxy($ip, $port = 3128, $auth = false)
    {
        if ($ip != false) {
            $this->options[CURLOPT_PROXY] = $ip;
            $this->options[CURLOPT_PROXYPORT] = $port;
            if ($auth) {
                $this->options[CURLOPT_PROXYUSERPWD] = $auth;
            }
        }
        return $this;
    }

    /**
     * 设置ftp上传的文件
     * @param $fp
     * @return $this
     */
    public function file($fp)
    {
        if ($fp != false) {
            $this->options[CURLOPT_VERBOSE] = 1;
            $this->options[CURLOPT_INFILE] = $fp; // 上传句柄
            $this->options[CURLOPT_NOPROGRESS] = false;
            $this->options[CURLOPT_FTP_USE_EPRT] = true;
            $this->options[CURLOPT_FTP_USE_EPSV] = true;
        }
        return $this;
    }

    /**
     * 设定返回结果类型
     * @param $type
     * @return $this
     */
    public function returnType($type)
    {
        if ($type == false) {
            return $this;
        }
        if ($type == self::RETURN_ALL) {
            $this->options[CURLOPT_HEADER] = 1;
        } elseif ($type == self::RETURN_HEADER) {
            $this->options[CURLOPT_NOBODY] = 1;
            $this->options[CURLOPT_HEADER] = 1;
        }
        return $this;
    }

    /**
     * 设置是否跟踪重定向。
     * @param $location
     * @return $this
     */
    public function location($location)
    {
        $this->options[CURLOPT_FOLLOWLOCATION] = $location;
        return $this;
    }

    /**
     * 设置客户端类型
     * @param $type
     * @return $this
     */
    public function clientType($type)
    {
        $userAgent =array(
            self::CLIENT_PC => 'Mozilla/5.0 (Windows NT 6.1; rv:23.0) Gecko/20100101 Firefox/23.0',
            self::CLIENT_IOS => 'Mozilla/5.0 (iPad; U; CPU OS 3_2_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B500 Safari/531.21.10',
            self::CLIENT_ANDROID => 'Mozilla/5.0 (Linux; U; Android 2.2; en-us; Nexus One Build/FRF91) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1'
        );
        if (array_key_exists($type, $userAgent)) {
            $this->options[CURLOPT_USERAGENT] = $userAgent[$type];
        }
        return $this;
    }

    /**
     * 设置用户COOKIE
     * 可以多次调用设置
     * @param string|array $name 可以是一个cookie名称，也可是一个cookie数组
     * @param string $value cookie值，此参数可选
     * @return $this
     */
    public function cookie($name, $value = null)
    {
        if ($name == false) {
            return $this;
        }
        $str = '';
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $str .= "{$k}={$v}; ";
            }
            $str = rtrim($str, '; ');
        } else {
            $str = "{$name}={$value}; ";
        }
        $this->options[CURLOPT_COOKIE] .= $str;
        return $this;
    }

    /**
     * 设置客户端ip
     * @param $ip
     * @return $this
     */
    public function clientIp($ip)
    {
        if ($ip != false) {
            $this->options[CURLOPT_HTTPHEADER][] = 'X-FORWARDED-FOR: ' . $ip;
            $this->options[CURLOPT_HTTPHEADER][] = 'CLIENT-IP: '. $ip;
        }
        return $this;
    }

    /**
     * 设置cookie保存，发送文件
     * @param string $fileName
     * @return $this
     */
    public function cookieFile($fileName = '')
    {
        if (!is_file($fileName)) {
            $fileName = App::getInstance()->getRuntimeDir() . '/cookie.txt';
        }
        $this->options[CURLOPT_COOKIEFILE] = $fileName; //发送COOKIE;
        $this->options[CURLOPT_COOKIEJAR] = $fileName; //设置cookie
        return $this;
    }

    /**
     * 定义curl在下载过程中，调用的回调函数。
     * 回调函数接收2个参数。第一个是$this->_ch，
     * 第二个是：当前下载的字符串部分。
     * @param $callback
     * @return $this
     */
    public function callback($callback)
    {
        if (is_callable($callback) != false) {
            $this->options[CURLOPT_WRITEFUNCTION] = $callback;
        }
        return $this;
    }

    /**
     * 生成一个url。用于合并url上的参数和data参数。
     * 如果有相同参数名，data中会覆盖。
     * @param $url
     * @param array $data
     * @return string
     */
    public static function createUrl($url, $data = array())
    {
        $parse = parse_url($url);
        if (isset($parse['query'])) {
            //如果url存在get参数，需要做参数合并
            parse_str($parse['query'], $params);
            $data = array_merge($params, $data);
        }
        //删除?后面的部分
        $pos = strpos($url, '?');
        if ($pos !== false) {
            $url = substr($url, 0, $pos);
        }

        $url .= '?' . http_build_query($data);
        return $url;
    }

    /**
     * 得到上一次请求的url
     * @return mixed
     */
    public function getLastUrl()
    {
        return $this->lastOptions[CURLOPT_URL];
    }

    public function getLastOptions()
    {
        return $this->lastOptions;
    }
}