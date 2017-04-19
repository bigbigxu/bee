<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/8/5
 * Time: 17:02
 */

namespace bee\common;

class Zlib
{
    const BLOCK_SIZE = 4096; //块大小
    const GZ_LEVEL = 6; //默认压缩级别

    const ERR_FILE_EXISTS = 1;
    const ERR_OPEN_FILE = 2;
    const NO_ERR = 0;

    private $_errno;
    private $_blockSize = self::BLOCK_SIZE;
    private static $_instance;
    private $_fp;

    public function __construct()
    {
    }

    /**
     * 设置每次读取块块大不，此大小将影响压缩
     * @param $size
     * @return $this
     */
    public function setBlockSize($size)
    {
        $this->_blockSize = $size;
        return $this;
    }

    /**
     * 返回一个实例化的对象
     * @param bool $single 是否返回单例对象
     * @return static
     */
    public static function g($single = true)
    {
        if ($single == true && is_object(self::$_instance)) {
            $o = self::$_instance;
        } else {
            $o = new self();
            self::$_instance = $o;
        }
        return $o;
    }


    /**
     * 使用gzip压缩一个文件，功能和linux gzip命令相同
     * @param string $file 要压缩的文件
     * @param int $level 要压缩的等级
     * @param bool $saveFile 是否保存原文件
     * @return bool|string 成功返回以.gz结束的文件名
     */
    public function gzip($file, $level = self::GZ_LEVEL, $saveFile = false)
    {
        $this->_errno = self::NO_ERR;
        $gzFile = "{$file}.gz";
        if (is_file($gzFile) || pathinfo($file, PATHINFO_EXTENSION) == 'gz') {
            return $this->setErrno(self::ERR_FILE_EXISTS);
        }

        $fp = fopen($file, 'r');
        $gzFp = gzopen($gzFile, "wb{$level}");
        if ($fp == false || $gzFp == false) {
            return $this->setErrno(self::ERR_OPEN_FILE);
        }

        while (!feof($fp)) {
            $str = fread($fp, self::BLOCK_SIZE);
            if ($str == '') {
                break;
            }
            gzwrite($gzFp, $str);
        }

        fclose($fp);
        fclose($gzFp);
        if ($saveFile == false) {
            unlink($file);
        }
        return $gzFile;
    }

    /**
     * 使用gunzip压缩一个文件，功能和linux gunzip相同
     * @param string $gzFile 要解压的文件，必须以.gz结束
     * @param bool $saveFile 是否保存原文件
     * @return bool|string 成功返回解压后的文件名。
     */
    public function gunzip($gzFile, $saveFile = false)
    {
        $this->_errno = self::NO_ERR;
        $file = basename($gzFile, '.gz');
        if (is_file($file) || pathinfo($gzFile, PATHINFO_EXTENSION) != 'gz') {
            return $this->setErrno(self::ERR_FILE_EXISTS);
        }

        $fp = fopen($file, 'w');
        $gzFp = gzopen($gzFile, "rb");
        if ($fp == false || $gzFp == false) {
            return $this->setErrno(self::ERR_OPEN_FILE);
        }

        while (!gzeof($gzFp)) {
            $str = gzread($gzFp, self::BLOCK_SIZE);
            if ($str == '') {
                break;
            }
            fwrite($fp, $str);
        }

        fclose($fp);
        fclose($gzFp);
        if ($saveFile == false) {
            unlink($gzFile);
        }
        return $file;
    }

    /**
     * 使用gz压缩一个字符串
     * @param $str
     * @param int $level
     * @return string
     */
    public function gzencode($str, $level = self::GZ_LEVEL)
    {
        return gzencode($str, $level);
    }

    /**
     * 使用gz解压一个字符串
     * @param $str
     * @return string
     */
    public function gzdecode($str)
    {
        return gzdecode($str);
    }

    private function setErrno($no)
    {
        $this->_errno = $no;
        return false;
    }

    public function getErrno()
    {
        return $this->_errno;
    }

    public function getErrmsg()
    {
        $map = array(
            self::ERR_FILE_EXISTS => '文件已经存在',
            self::ERR_OPEN_FILE => '文件OPEN失败'
        );
        return (string)$map[$this->_errno];
    }

    /**
     * 使用gz打开一个文件
     * @param string $file 文件名
     * @param string $mode 打开模式。注意，不能同时以读写模式打开。
     *        读建议以 rb模式。写建议以 wb{level}或ab{$level} 方式打开
     * @return $this
     */
    public function gzopen($file, $mode)
    {
        $this->_fp = gzopen($file, $mode);
        if ($this->_fp == false) {
            return $this->setErrno(self::ERR_OPEN_FILE);
        }
        return $this;
    }

    /**
     * 关闭文件描述符
     * @return $this
     */
    public function gzclose()
    {
        gzclose($this->_fp);
        $this->_fp = null;
        return $this;
    }

    /**
     * 读取文件一行。注意,需要判断返回值是否为空字符串。
     * 某些情况下，gzgets会返回空字符串，但是gzeof返回false,会造成死循环
     * @param int $length
     * @return string
     */
    public function gzgets($length = self::BLOCK_SIZE)
    {
        $str = gzgets($this->_fp, $length);
        return $str;
    }

    /**
     * 定入一个字符串
     * @param $str
     * @return int
     */
    public function gzwrite($str)
    {
        $len = gzwrite($this->_fp, $str);
        return $len;
    }

    /**
     * 判断是否到达文件末尾
     * @return int
     */
    public function gzeof()
    {
        return gzeof($this->_fp);
    }
}