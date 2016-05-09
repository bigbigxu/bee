<?php
namespace bee\lib;
/**
 * Created by PhpStorm.
 * User: 123
 * Date: 14-9-11
 * Time: 下午6:01
 *
 * $hash & 0x7FFFFFFF 表示hash的最大值为　0x7FFFFFFF,这个数也是Php的最大整数
 * 这个数字是可以改的一般认为使用一个大的质数结果的分布会是比较均匀的，
 * 在 701819 附近的几个建议的值是：175447, 350899, 1403641, 2807303, 5614657。
 *
 * 常用的hash算法
 * 若非必要，尽量使用php自带的hash函数
 * 此类主要用于展示常用hash算法
 */
class Hash
{
    /**
     * hash(i) = hash(i-1) * 33 + str[i] //字符取的是字符的ascii码，c语言中，字符和ascii码是等价的
     *
     * (hash << 5) + hash 相当于 hash * 33
     * 建议使用
     * @param $str
     * @return int
     */
    public static function DJBHash($str)
    {
        $hash = 5381;
        $n = strlen($str);
        for ($i = 0; $i < $n; $i++) {
            $hash = ($hash << 5) + $hash + ord($str{$i});
        }
        return $hash & 0x7FFFFFFF;
    }

    /**
     * 先hash再求余数
     * @param $str
     * @param int $mod
     * @return int
     */
    public static function mod($str, $mod = 10)
    {
        $hash = self::DJBHash($str);
        return $hash % $mod;
    }

    /**
     * 它对于长字符串和短字符串都很有效，
     * 字符串中每个字符都有同样的作用，它巧妙地对字符的ASCII编码值进行计算，
     * ELFhash函数对于能够比较均匀地把字符串分布在散列表中。
     * 这些函数使用位运算使得每一个字符都对最后的函数值产生影响
     * @param $str
     * @return int
     */
    public static function ELEHash($str)
    {
        $n = strlen($str);
        $hash = $x = 0;
        for ($i = 0; $i < $n; $i++) {
            $hash = ($hash << 4) + ord($str{$i});
            if (($x = $hash & 0xF0000000) != 0) {
                $hash ^= ($x >> 24);
                $hash &= ~$x;
            }
        }

        return $hash & 0x7FFFFFFF;
    }

    public static function JSHash($str)
    {
        $n = strlen($str);
        $hash = 0;
        for ($i = 0; $i < $n; $i++) {
            $hash ^= ($hash << 5) + ord($str{$i}) + ($hash >> 2);
        }

        return $hash & 0x7FFFFFFF;
    }

    public static function SDBMHash($str)
    {
        $n = strlen($str);
        $hash = 0;
        for ($i = 0; $i < $n; $i++) {
            $hash = ord($str{$i}) + ($hash << 6) + ($hash << 16) - $hash;
        }
        return $hash & 0x7FFFFFFF;
    }

    public static function APHash($str)
    {
        $hash = 0;
        $n = strlen($str);
        for ($i = 0; $i < $n; $i++) {
            if ($i & 1 == 0) {
                $hash ^= ($hash << 7) ^ (ord($str{$i})) ^ ($hash >> 3);
            }
            else{
                $hash ^= (~(($hash << 11) ^ (ord($str{$i})) ^ ($hash >> 5)));
            }
        }
        return $hash & 0x7FFFFFFF;
    }

    public static function DEKHash($str)
    {
        $hash = $n = strlen($str);
        for ($i = 0; $i < $n; $i++) {
            $hash = (($hash << 5) ^ ($hash >> 27)) ^ ord($str{$i});
        }
        return $hash & 0x7FFFFFFF;
    }

    public static function FNVHash($str)
    {
        $hash = 0;
        $n = strlen($str);
        for ($i = 0; $i < $n; $i++) {
            $hash *= 0x811C9DC5;
            $hash ^= ord($str{$i});
        }
        return $hash & 0x7FFFFFFF;
    }

    public static function PJWHash($str)
    {
        $hash = $test = 0;
        $n = strlen($str);
        for ($i = 0; $i < $n; $i++) {
            $hash = ($hash << 4) + ord($str{$i});

            if (($test = $hash & -268435456) != 0) {
                $hash = (($hash ^ ($test >> 24)) & (~-268435456));
            }

        }
        return $hash & 0x7FFFFFFF;
    }

    public static function PHPHash($str)
    {
        $hash = 0;
        $n = strlen($str);
        for ($i = 0; $i < $n; $i++) {
            $hash = ($hash << 4) + ord($str[$i]);
            if (($g = ($hash & 0xF0000000))) {
                $hash = $hash ^ ($g >> 24);
                $hash = $hash ^ $g;
            }
        }
        return $hash & 0x7FFFFFFF;
    }

    public static function openSLLHash($str)
    {
        $hash = 0;
        $n = strlen($str);
        for ($i = 0; $i < $n; $i++) {
            $hash ^= (ord($str[$i]) << ($i & 0x0f));
        }
        return $hash & 0x7FFFFFFF;
    }

    /**
     * crc32会变成一个整数。取模后，可以映射为内存的下标
     * @param $str
     * @return string
     */
    public static function crc32($str)
    {
        return sprintf('%u', crc32($str));
    }
}
