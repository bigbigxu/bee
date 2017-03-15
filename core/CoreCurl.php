<?php
/**
 * 封装了的curl http请求类
 * @desc 在php.ini 安全模式开局的情况下，CURLOPT_FOLLOWLOCATION不可用
 * @author xuen
 * @TODO 此类已经被 Curl 代替。
 */
class CoreCurl
{
    public $ch; // 当前链接对象
    public $str = ''; // 当前串
    public $match; // 正则表达式
    /**
     * 将curl常量属性转变成一个数组，便于使用
     * @var string
     */
    public $defaultOpt = array(
        CURLOPT_URL => '', // 请求的URL
        CURLOPT_RETURNTRANSFER => 1, // 设置有返回信息，以流的形式返回，非不是直接输出
        CURLOPT_HTTPGET => 1, // 设定为GET请求。
        // 定义默认的回调函数,这样exec()将在成功后返回1
        CURLOPT_CONNECTTIMEOUT => 30, // 设置默认链接超时间为30秒
        CURLOPT_TIMEOUT=>30,//设置下载时间最多30秒。
        // 自动跟踪重定向。
        //CURLOPT_FOLLOWLOCATION => false,
        // 设置客户端支持gzip压缩,默认不开启，用于节省流量。
        CURLOPT_ENCODING => 'gzip',
        // 设定默认header头
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; rv:23.0) Gecko/20100101 Firefox/23.0',
        CURLOPT_SSL_VERIFYPEER => false
    );
    
    public $opt;
    
    public $cookieFile = false; // 保存的COOKIE文件

    private static $_instance;
    /**
     * http请求响应的缓存，默认缓存在数组中，最大为512个
     * @var array
     */
    public $cache = array();

    public function __construct ()
    {
        // 完成CH的初始化
        $this->ch = curl_init();
        if (!$this->ch) {
            die('当前环境不支持CURL');
        }
        $this->opt = $this->defaultOpt;
        //curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
    }

    /**
     * @return self
     */
    public static function getInstance()
    {
        if(!is_object(self::$_instance)){
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * @param $opt
     * @return mixed|string
     */
    public function exec ($opt)
    {
        //如查参数只是一个串，就认为是一个url
        if(!is_array($opt)) {
            $url = $opt;
            $opt = array();
            $opt['url'] = $url;
        }
        // 如果存在缓存，则返回缓存的字符串。
        $key = md5($opt['url'] . $opt['post'] . $opt['match']. $opt['return']);
        if (($str = $this->getCache($key)) != false) {
            return $str;
        }

        // 每次执行前，清空当前str,每次请求，返回值可能不一样
        $this->str = '';
        // 参数设置失败和执行行失败都将返回 fasle;
        if (!$this->setOptions($opt)) {
            return false;
        }
        $flag = curl_exec($this->ch);
        $this->setCache($key, $this->str ? $this->str : $flag);
        
        //执行完成后，基于长驻内存和考虑，上一次连接的选项可以对下一次靠成影响
        $this->opt=$this->defaultOpt;

        // 表示没有执行回调函数
        if ($this->str == '') {
            return $flag;
        } else {
            return $this->str;
        }
    }
    
    /**
     * 简单封装的file_get_contents函数
     * 只是使用的缓存而已。
     * @param string $url
     * @return string $str
     */ 
    public function getContent ($url)
    {
        $key = md5($url);
        if (($str = $this->getCache($key)) != false)  {
            return $str;
        }
        $str = file_get_contents($url);
        $this->setCache($key, $str);
        return $str;
    }

    /**
     * 设置curl属性
     * @param $opt
     * @return bool
     */
    public function setOptions ($opt)
    {
        foreach ($opt as $key => $value) {
            // 以下值为可以设定的值。如果没有，将使用默认值；
            switch ($key) {
                case 'url': // 设定当前请求的URL
                    $this->opt[CURLOPT_URL] = $value;
                    break;
                case 'str': // 设定信息返方式，默认为1
                    $this->opt[CURLOPT_RETURNTRANSFER] = $value;
                    break; // 默认值也为1
                case 'method': // 设定请求方式,默认为POST
                    if ($value == 'get')
                        $this->opt[CURLOPT_HTTPGET] = 1; // 设为GET请求
                    elseif ($value == 'put')
                        $this->opt[CURLOPT_PUT] = 1; // ftp文件上传
                    else
                        $this->opt[CURLOPT_POST] = 1;
                    break;
                case 'post': // 设定POST请求主体，数组形式,如果上传文件，文件名前加@
                    $this->opt[CURLOPT_POST] = 1;
                    $this->opt[CURLOPT_POSTFIELDS] = $value;
                    break;
                case 'header': // 设定HEADER请求头；默认不使用，数组形式
                    $this->opt[CURLOPT_HTTPHEADER] = $value;
                    break;
                case 'referer': // 设定REFERER信息,默认为空
                    $this->opt[CURLOPT_REFERER] = $value;
                    break;
                case 'auth': // 设定要请求的用户密码[username]:[password]
                    $this->opt[CURLOPT_USERPWD] = $value;
                    break;
                case 'connect_time': // 发起链接前的等待时间
                    $this->opt[CURLOPT_CONNECTTIMEOUT] = $value;
                    break;
                case 'load_time'://文件下载最长时间。
                    $this->opt[CURLOPT_TIMEOUT]=$value;
                    break;
                case 'callback': // 定义回调函数
                    $this->opt[CURLOPT_WRITEFUNCTION] = $value;
                    break;
                case 'match': // 寻找指定段的正则表达式
                    $this->opt[CURLOPT_WRITEFUNCTION] = array(
                        'self',
                        'callback'
                    );
                    $this->match = $value;
                case 'proxy':
                    $this->opt[CURLOPT_PROXY] = $value;
                    break;
                case 'file': // 用FTP上传的文件名柄
                    $this->opt[CURLOPT_VERBOSE] = 1;
                    $this->opt[CURLOPT_INFILE] = $value; // 上传句柄
                    $this->opt[CURLOPT_NOPROGRESS] = false;
                    $this->opt[CURLOPT_FTP_USE_EPRT] = true;
                    $this->opt[CURLOPT_FTP_USE_EPSV] = true;
                    break;
                case 'cookie_file': // 设置cookie文件。
                     $this->cookieFile =APP_PATH . '/runtime/cookie.txt';
                     // 发送COOKIE
                     $this->opt[CURLOPT_COOKIEFILE] = $this->cookieFile;
                     // 设置cookie
                    $this->opt[CURLOPT_COOKIEJAR] = $this->cookieFile;
                    break;
                case 'cookie'://设置访问cookie为一个字符串
                    $this->opt[CURLOPT_COOKIE]=$value;
                    break;
                case 'return': // 设定返回的类型
                    if ($value == 'head') // 表示只得到header头
                    {
                        $this->opt[CURLOPT_NOBODY] = 1;
                        $this->opt[CURLOPT_HEADER] = 1;
                    } 
                    elseif ($value == 'body') {}// 默认值，只返回body体
                    elseif ($value == 'all')
                        $this->opt[CURLOPT_HEADER] = 1;
                    break;
                case 'location'://是不是跟踪重定向
                    $this->opt[CURLOPT_FOLLOWLOCATION]=$value;
                    break;
                case 'client_type'://设定客户端类型
                    $this->setClientType($value);
                    break;
                case 'client_ip'://设定客户端IP地址，只是在header头，不是真实伪造
                    $this->opt[CURLOPT_HTTPHEADER][]='X-FORWARDED-FOR: '.$value;
                    $this->opt[CURLOPT_HTTPHEADER][]='CLIENT-IP: '.$value;
                    break;
            }
        }
        // 设定当前链接选项,选项设置失败返回false;
        return curl_setopt_array($this->ch, $this->opt);
    }

    // 回调函数，返回指定正则表达式的HTML段
    public function callback ($ch, $str)
    {
        $this->str .= $str;
        preg_match($this->match, $this->str, $match);
        if (! empty($match)) 
        {
            // 存在一个子模式，则返回这个子模式
            if (isset($match[1]))
                $this->str = $match[1];
            else
                $this->str = $match[0];
            return false; // 中断请求
        }
        return strlen($str);
    }

    /**
     * 得到当前请求的错误信息
     * @param int $type
     *  0表示得到错误编码，1表示得到错误信息，2表示得到所有信息
     * @return mixed
     */
    public function getError ($type = 1)
    {
        switch ($type) {
            case 0:
                $info = curl_errno($this->ch);
                break;
            case 1:
                $info = curl_error($this->ch);
                break;
            default:
                $info = curl_getinfo($this->ch);
        }
        return $info;
    }

    /**
     * 清除缓存。
     */
    public function __destruct ()
    {
        curl_close($this->ch);
        $this->cache = array();
    }

    /**
     * 设置缓存，以减少http请求数量。
     * 大量采集中，你应改使用重写此方法。
     * 默认将使用内存缓存
     * 如果缓存数组超过512个，清空数组缓存。
     * @param string $key            
     * @param string $str            
     * @return string boolean
     */
    public function setCache ($key, $str)
    {
        if (count($this->cache)>512)
            $this->cache=array();
        $this->cache[$key] = $str;
        
        //如果引入的phpquery对象
        if(class_exists('phpQuery') && count(phpQuery::$documents) > 512)
            phpQuery::$documents = array();
    }

    /**
     * 得到缓存数据
     * 
     * @param string $key            
     * @return string boolean
     */
    public function getCache ($key)
    {
        if ($this->cache[$key])
            return $this->cache[$key];
        else
            return false;
    }

    /**
     * 将一个{}格式的javasrcipt对象转变成一个php数组
     * 以避免过多的使用正则表达式。
     * 目前支持一维数组；
     * 如果js对像里面还有一个对象。可能会有问题。
     * 通常情况下，你应该在页面中只匹配一个JS对象。
     * @param string $str
     * @return array|bool
     */
    public function jsArray($str)
    {
        $return=array();
    	$str=str_replace(array(
    	    '"',
    	    "'",
    	    '{',
    	    '}'
    	), '', $str);
    	$arr=explode(',', $str);
    	foreach($arr as $row)
    	{
    		$tmpArr=explode(':', $row);
    		$return[trim($tmpArr[0])]=trim($tmpArr[1]);
    	}
    	return $return;
    }

    /**
     * 根据type类型默认浏览器客户端
     * @param type $type
     * iphone 表示iphone客户端,
     * pc 表示电脑客户客户端，
     * android 表示安卓客户端　
     */
    public function setClientType($type)
    {
    	$userAgent=array(
    	    'pc'=>'Mozilla/5.0 (Windows NT 6.1; rv:23.0) Gecko/20100101 Firefox/23.0',
    	    'iphone'=>'Mozilla/5.0 (iPad; U; CPU OS 3_2_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B500 Safari/531.21.10',
    	    'android'=>'Mozilla/5.0 (Linux; U; Android 2.2; en-us; Nexus One Build/FRF91) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1'
    	);
    	if(array_key_exists($type,$userAgent))
    	    $this->opt[CURLOPT_USERAGENT]=$userAgent[$type];
    }
    
    //关闭当前资源连接
    public function close()
    {
        curl_close($this->ch);
    }
}