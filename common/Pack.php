<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/4/10
 * Time: 11:23
 *
 * 大端序
 * 大端序又叫网络字节序。大端序规定高位字节在存储时放在低地址上，在传输时高位字节放在流的开始；
 * 低位字节在存储时放在高地址上，在传输时低位字节放在流的末尾。
 *
 * 小端序
 * 小端序规定高位字节在存储时放在高地址上，在传输时高位字节放在流的末尾；
 * 低位字节在存储时放在低地址上，在传输时低位字节放在流的开始。
 *
 * 网络字节序
 * 网络字节序是指大端序。TCP/IP都是采用网络字节序的方式，java也是使用大端序方式存储。
 *
 * 主机字节序
 * 主机字节序代表本机的字节序。一般是小端序，但也有一些是大端序。
 * 主机字节序用在协议描述中则是指小端序。
 */
class Pack
{
    protected $sendBuffer = ''; //发送了字符串
    protected $sendSize = 0; //发送的字节数

    protected $recvBuffer = ''; //接收的字符串
    protected $recvSize = 0; //接收的字节数
    protected $recvArr = array(); //unpack后的数组
    private static $_instance;

    protected $f = array(
        //短整数
        'Short' => array('s', 2), //主机字节序,有符号短整数
        'UnShort' => array('n', 2), //大端字节序，无符号短整数

        //整数
        'Int' => array('l', 4), //主机字节序,有符号整数
        'UnInt' => array('N', 4), //大端字节序，无符号整数

        //长整数
        'Long' => array('Q', 8), //主机字节序,有符号长整数
        'UnLong' => array('J', 8), //大端字节序，无符号长整数

        //字符串
        'String' =>array('a'), //将字符串空白以 NULL 字符填满
        'StringSpace' => array('A'), // 将字符串空白以 SPACE 字符 (空格) 填满

        'Hex' => array('h'), //16进制字符串，低位在前以半字节为单位
        'HexHigh'=> array('H'), //16进制字符串，高位在前以半字节为单位

        'Char' => array('c', 1), //有符号字符
        'UnChar' => array('C', 1), // 无符号字符, byte类型也是这个

        'Float' => array('f', 4), //单精度浮点数 (依计算机的范围)
        'Double' => array('d', 8), //双精度浮点数 (依计算机的范围)
    );

    public function __construct()
    {

    }

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (!is_object(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * 设置定接收到的字符串
     * @param $str
     * @return $this
     */
    public function readBegin($str)
    {
        $this->recvBuffer = $str;
        $this->recvSize = strlen($str);
        return $this;
    }

    /**短整形数**/

    /**
     * 'Short' => array('s', 2), //主机字节序,有符号短整数
     * @param $value
     * @return Pack
     */
    public function writeShort($value)
    {
        return $this->_write(__METHOD__, $value);
    }

    public function readShort($name = '')
    {
        return $this->_read(__METHOD__, $name);
    }

    /**
     * 'UnShort' => array('n', 2), //大端字节序，无符号短整数
     * @param $value
     * @return Pack
     */
    public function writeUnShort($value)
    {
        return $this->_write(__METHOD__, $value);
    }

    public function readUnShort($name = '')
    {
        return $this->_read(__METHOD__, $name);
    }


    /**整形数**/

    /**
     * 'Int' => array('l', 4), //主机字节序,有符号整数
     * @param $value
     * @return Pack
     */
    public function writeInt($value)
    {
        return $this->_write(__METHOD__, $value);
    }

    public function readInt($name = '')
    {
        return $this->_read(__METHOD__, $name);
    }

    /**
     * 'UnInt' => array('N', 4), //大端字节序，无符号整数
     * @param $value
     * @return Pack
     */
    public function writeUnInt($value)
    {
        return $this->_write(__METHOD__, $value);
    }

    public function readUnInt($name = '')
    {
        return $this->_read(__METHOD__, $name);
    }


    /**长整数**/

    /**
     *  'Long' => array('Q', 8), //主机字节序,有符号长整数
     * @param $value
     * @return Pack
     */
    public function writeLong($value)
    {
        return $this->_write(__METHOD__, $value);
    }

    public function readLong($name = '')
    {
        return $this->_read(__METHOD__, $name);
    }

    /**
     *   'UnLong' => array('J', 8), //大端字节序，无符号长整数
     * @param $value
     * @return Pack
     */
    public function writeUnLong($value)
    {
        return $this->_write(__METHOD__, $value);
    }

    public function readUnLog($name = '')
    {
        return $this->_read(__METHOD__, $name);
    }

    /**
     * @param $value
     * @return $this
     */
    public function writeString($value)
    {
        return $this->_write(__METHOD__, $value);
    }

    public function readString($name = '')
    {
        return $this->_read(__METHOD__, $name);
    }

    /**
     * 写入一个串，不封包，也不计算长度
     * @param $value
     * @return $this
     */
    public function writeNoPack($value)
    {
        $this->sendBuffer .= $value;
        return $this;
    }

    public function writeChar($value)
    {
        return $this->_write(__METHOD__, $value);
    }

    public function readChar($name)
    {
        return $this->_read(__METHOD__, $name);
    }

    public function readUnChar($name)
    {
        return $this->_read(__METHOD__, $name);
    }

    public function writeUnChar($value)
    {
        return $this->_write(__METHOD__, $value);
    }

    public function readByte($name)
    {
        return $this->readUnChar($name);
    }

    public function writeByte($value)
    {
        return $this->writeUnChar($value);
    }

    /**
     * 写入开始
     * @return $this
     */
    public function writeBegin()
    {
        $this->recvArr = array();
        $this->recvBuffer = '';
        return $this;
    }

    /**
     * 返回unpack后的数组
     * 并且清空相关数据
     * @param $arr
     * @return array
     */
    public function readEnd(&$arr)
    {
        $arr = $this->recvArr;
        $this->recvArr = array();
        $this->recvBuffer = '';
        $this->recvSize = 0;
    }

    /**
     * @param $str
     * @param $size
     */
    public function writeEnd(&$str, &$size)
    {
        $str = $this->sendBuffer;
        $size = $this->sendSize;
        $this->sendBuffer = '';
        $this->sendSize = 0;
    }

    /**
     * 写入
     * @param $method
     * @param $value
     * @return $this
     * @throws Exception
     */
    private function _write($method, $value)
    {
        $typeInfo = $this->_getFormat($method);
        $format = $typeInfo[0]; //格式化类型
        $size = $typeInfo[1]; //字节数。

        //如果字节数为空表示需要计算字节数
        //使用如下方式表法，一个无符号整数+串来表示
        if($size == false) {
            $len = strlen($value) + 1;
            $this->sendBuffer .= pack("N", $len);
            $this->sendBuffer .= $value;
            $this->sendBuffer .= pack('C', 0);
            $size = $len + 4;
        } else {
            $this->sendBuffer .= self::pack($format, $value);
        }

        $this->sendSize += $size;
        return $this;
    }

    /**
     * 读取。
     * @param $method
     * @param $name
     * @return $this
     */
    private function _read($method, $name)
    {
        $typeInfo = $this->_getFormat($method);
        $format = $typeInfo[0]; //格式化类型
        $size = $typeInfo[1]; //字节数

        //如果字节数为空表示需要计算字节数
        //使用如下方式表法，一个无符号整数+串来表示
        if($size == false) {
            $key = 'string_length';
            $this->readUnInt($key);
            $size = $this->recvArr[$key];
            unset($this->recvArr[$key]);
            $temp = substr($this->recvBuffer, 0, $size);
            $value = self::unpack("{$format}{$size}{$name}", $temp);
        } else {
            $temp = substr($this->recvBuffer, 0, $size);
            $value = self::unpack("{$format}{$name}", $temp);
        }
        $this->recvArr[$name] = $value[$name];
        $this->recvBuffer = substr($this->recvBuffer, $size);
        $this->recvSize -= $size;
        if ($value[$name] === null) {
            CoreLog::trace();
        }
        return $this;
    }

    /**
     * 用于支持没有定义的方法。
     * @param $method
     * @param $params
     * @return $this
     * @throws Exception
     */
    public function __call($method, $params)
    {
        //这里这么做是为了__METHOD__传递的参数保持一致。
        $class = get_class($this);
        $method = $class . "::" . $method;
        if(strpos($method, $class . '::read') === 0 ) {
            $this->_read($method, $params[0]);
        } elseif(strpos($method, $class . '::write') === 0) {
            $this->_write($method, $params[0]);
        } else {
            throw new Exception('方法不存在');
        }

        return $this;
    }

    /**
     * 根据函数方法名，取得模式的类型和字节数。
     * @param $method
     * @return mixed
     * @throws Exception
     */
    private  function _getFormat($method)
    {
        //从方法名中取得类型
        $type = str_replace(array(
            get_class($this) . '::read',
            get_class($this) . '::write',
        ), '', $method);
        if(!array_key_exists($type, $this->f)) {
            throw new Exception("不支持的：{$type} 数据类型");
        }

        $typeInfo = $this->f[$type];
        return $typeInfo;
    }

    /**
     * 调用php原生pack方法
     * @return mixed
     */
    public static function pack()
    {
        $params = func_get_args();
        return call_user_func_array('pack', $params);
    }

    /**
     * 调用原生unpack方法
     * @return mixed
     */
    public static function unpack()
    {
        $params = func_get_args();
        return call_user_func_array('unpack', $params);
    }

    /**
     * 返回read读取的一个值
     * @param $key
     * @return mixed
     */
    public function getItem($key = null)
    {
        if($key === null) {
            return $this->recvArr;
        } else {
            return $this->recvArr[$key];
        }
    }
}