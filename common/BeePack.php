<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/4/21
 * Time: 9:36
 * 封包类。用于替代 bee\Pack
 */

namespace bee\common;

class BeePack
{
    protected $buffer;
    protected $size;

    protected $data = [];

    /**
     * 开始写入
     * @return $this
     */
    public function writeBegin()
    {
        $this->buffer = '';
        $this->size = 0;
        $this->data = [];
        return $this;
    }

    /**
     * 读取结束
     * @param $buffer
     * @param $size
     */
    public function writeEnd(&$buffer, &$size)
    {
        $buffer = $this->buffer;
        $size = $this->size;
        $this->buffer = '';
        $this->size = 0;
    }

    /**
     * 读取开始
     * @param $buffer
     * @return $this
     */
    public function readBegin($buffer)
    {
        $this->buffer = $buffer;
        $this->size = strlen($buffer);
        return $this;
    }

    /**
     * 读取结束
     * @param $data
     */
    public function readEnd(&$data)
    {
        $data = $this->data;
        $this->data = [];
    }

    /**
     * 写入一个整数。N，网络字节序
     * @param $value
     * @return $this
     */
    public function writeInt($value)
    {
        return $this->pack($value, 'N', 4);
    }

    /**
     * 写入一个64为整数。
     * 注意，64位php才支持64位整数
     * @param $value
     * @return $this
     */
    public function writeInt64($value)
    {
        $this->buffer .= pack('NN', $value >> 32, $value & 0xFFFFFFFF);
        $this->size += 8;
        return $this;
    }

    /**
     * 写入一个字符
     * @param $value
     * @return BeePack
     */
    public function writeByte($value)
    {
        return $this->pack($value, 'C', 1);
    }

    /**
     * 写入一个字符
     * @param $value
     * @return BeePack
     */
    public function writeChar($value)
    {
        return $this->pack($value, 'C', 1);
    }

    /**
     * 短整数
     * @param $value
     * @return $this
     */
    public function writeShort($value)
    {
        return $this->pack($value, 'n', 2);
    }

    /**
     * 打包
     * @param string $value 数值
     * @param string $type 格式化字符
     * @param int $size $type 的字节数
     * @return BeePack
     */
    protected function pack($value, $type, $size)
    {
        $this->buffer .= pack($type, $value);
        $this->size += $size;
        return $this;
    }
    /**
     * 写入一个字符串
     * @param $value
     * @return BeePack
     */
    public function writeString($value)
    {
        $len = strlen($value);
        $this->buffer .= pack('N', $len + 1);
        $this->buffer .= $value;
        $this->buffer .= pack('C', 0);
        $this->size += $len + 5;
        return $this;
    }

    /**
     * 直接连接字符串，不计算长度
     * @param $value
     * @return $this
     */
    public function writeNoPack($value)
    {
        $this->buffer .= $value;
        return $this;
    }

    /**
     * 读取一个整数
     * @param $name
     * @return $this
     */
    public function readInt($name)
    {
        return $this->unpack($name, 'N', 4);
    }

    /**
     * 读取一个短整数
     * @param $name
     * @return BeePack
     */
    public function readShort($name)
    {
        return $this->unpack($name, 'n', 2);
    }

    /**
     * 读取字符串
     * @param $name
     * @return $this
     */
    public function readString($name)
    {
        $this->readInt($name);
        $len = $this->data[$name];
        $this->data[$name] = substr($this->buffer, 0, $len);
        $this->size -= $len;
        $this->buffer = substr($this->buffer, $len);
        return $this;
    }

    /**
     * 读取一个字符
     * @param $name
     * @return $this
     */
    public function readByte($name)
    {
        return $this->unpack($name, 'C', 1);
    }

    /**
     * 读取一个字符
     * @param $name
     * @return $this
     */
    public function readChar($name)
    {
        return $this->unpack($name, 'C', 1);
    }

    /**
     * 读取64位整数
     * @param $name
     * @return $this
     */
    public function readInt64($name)
    {
        $tmp = substr($this->buffer, 0, 8);
        list($high, $low) = array_values(unpack('N2', $tmp));
        $value = ($high << 32) + $low;
        $this->buffer = substr($this->buffer, 8);
        $this->data[$name] = $value;
        $this->size -= 8;
        return $this;
    }

    /**
     * 解包
     * @param string $name 数值下标
     * @param string $type 格式化字符
     * @param int $size $type 的字节数
     * @return $this
     */
    public function unpack($name, $type, $size)
    {
        $tmp = substr($this->buffer, 0, $size);
        $value = unpack($type, $tmp);
        $this->data[$name] = $value[1];
        $this->buffer = substr($this->buffer, $size);
        $this->size -= $size;
        return $this;
    }

    /**
     * 获取当前size
     * @return mixed
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * 获取当前buffer
     * @return mixed
     */
    public function getBuffer()
    {
        return $this->buffer;
    }

    /**
     * 获取数据
     * @param null $name
     * @return array
     */
    public function getData($name =  null)
    {
        if ($name === null) {
            return $this->data;
        } else {
            return $this->data[$name];
        }
    }
}