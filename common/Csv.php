<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/9/9
 * Time: 16:44
 * csv 一种简单的文件格式，excel支持，可用于输出excel
 */
namespace bee\common;
class Csv
{
    protected static $rowSp = "\n"; //一行数据分隔符
    protected static $colSp = ','; //列分隔符
    protected static $dataSp = ':'; //数据分隔符

    public static function g()
    {
        return new static;
    }

    /**
     * 设置3种分隔符
     * @param string $rowSp
     * @param string $colSp
     * @param string $dataSp
     */
    public static function setSp($rowSp = "\n", $colSp = ",", $dataSp = ':')
    {
        self::$rowSp = $rowSp;
        self::$colSp = $colSp;
        self::$dataSp = $dataSp;
    }

    public static function toArray($str, $lineKeys = null)
    {
        $lines = (array)explode(self::$rowSp, $str);
        $res = array();
        foreach ($lines as $item) {
            $item = trim((string)$item);
            if ($item == '') { //空字符串
                continue;
            }
           if (is_array($lineKeys)) {
               $data = self::parseLine($item);
               $tmp = array();
               foreach ($lineKeys as $index => $key) {
                   $tmp[$key] = $data[$index];
               }
               $res[] = $tmp;
           } else {
               $res = self::parseLine($item);
           }
        }
        return $res;
    }

    public static function parseLine($line)
    {
        $line = trim($line);
        $res = array();
        $data = (array)explode(self::$colSp, $line);
        if (self::$dataSp == false) { //不存在数据分隔符
            return $data;
        }

        foreach ($data as $row) { // :分隔表示一个键值对
            $row = trim($row);
            $item = (array)explode(self::$dataSp, $row);
            if ($item) {
                $res[trim($item[0])] = $res[trim($item[1])];
            }
        }
        return $res;
    }

    /**
     * 将一个数组转为一个字符串
     * @param $arr
     * @return string
     */
    public static function toStr($arr)
    {
        $str = '';
        foreach ($arr as $row) {
            $str .= self::buildLine($row) . self::$rowSp;
        }
        return $str;
    }

    /**
     * 创建一行
     * @param $arr
     * @return string
     */
    public static function buildLine($arr)
    {
        if (self::$dataSp) {
            $line = '';
            foreach ($arr as $key => $v) {
                $line = sprintf("%s%s%s%s", $key, self::$dataSp, $v, self::$colSp);
            }
            return trim($line, self::$colSp);
        } else {
            return implode(self::$colSp, $arr);
        }
    }

    /**
     * @param resource $handle 文件指针
     * @param string $length 必须大于 CVS 文件内最长的一行
     * @param string $delimiter 字段分界符
     * @param string $enclosure 字段环绕符
     * @param string $escape 设置转义字符
     * @return mixed
     */
    public static function fileGetCsv($handle, $length = null, $delimiter = null, $enclosure = null, $escape = null)
    {
        return fgetcsv($handle, $length, $delimiter, $enclosure, $escape);
    }

    /**
     * @param resource $handle 文件指针
     * @param string $arr 要写入的数组，必须是一个一维数组
     * @param string $delimiter  字段分界符
     * @param string $enclosure 字段环绕符
     * @return bool
     */
    public static function filePutCsv($handle, $arr, $delimiter = ',',  $enclosure = '"')
    {
        return fputcsv($handle, $arr, $delimiter, $enclosure);
    }

    /**
     * 解析 CSV 字符串为一个数组
     * @param $str
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     * @return array
     */
    public static function strGetCsv($str, $delimiter = ",", $enclosure = '"', $escape = "\\")
    {
        return str_getcsv($str, $delimiter, $enclosure, $escape);
    }
}