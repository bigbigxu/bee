<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/7/24
 * Time: 11:44
 * 时间戳相关，用于简化时间函数的调用
 *
 * a - "am" 或是 "pm"
 * A - "AM" 或是 "PM"
 *
 * d - 几日，二位数字，若不足二位则前面补零; 如: "01" 至 "31"
 * D - 星期几，三个英文字母; 如: "Fri"
 * F - 月份，英文全名; 如: "January"
 *
 * h - 12 小时制的小时; 如: "01" 至 "12"
 * H - 24 小时制的小时; 如: "00" 至 "23"
 * g - 12 小时制的小时，不足二位不补零; 如: "1" 至 12"
 * G - 24 小时制的小时，不足二位不补零; 如: "0" 至 "23"
 * i - 分钟; 如: "00" 至 "59"
 *
 * j - 几日，二位数字，若不足二位不补零; 如: "1" 至 "31"l - 星期几，英文全名; 如: "Friday"
 * m - 月份，二位数字，若不足二位则在前面补零; 如: "01" 至 "12"
 * n - 月份，二位数字，若不足二位则不补零; 如: "1" 至 "12"
 * M - 月份，三个英文字母; 如: "Jan"
 *
 * s - 秒; 如: "00" 至 "59"
 * S - 字尾加英文序数，二个英文字母; 如: "th"，"nd"
 * t - 指定月份的天数; 如: "28" 至 "31"
 * U - 总秒数
 *
 * w - 数字型的星期几，如: "0" (星期日) 至 "6" (星期六)
 *
 * W ISO-8601 格式年份中的第几周，每周从星期一开始（PHP 4.1.0 新加的） 例如：42（当年的第 42 周）
 * Y - 年，四位数字; 如: "1999"
 * y - 年，二位数字; 如: "99"
 * z - 一年中的第几天; 如: "0" 至 "365"
 */
class Timer
{
    const DAY_SECOND = 86400; //1天的秒数
    const MONTH_SECOND = 2592000; //1个月的秒数
    /**
     * 得到今天0点0分的时间戳
     * @return int
     */
    public static function todayStamp()
    {
        return strtotime('today');
    }

    /**
     * 得到标准日期时间格式
     * @param null $stamp
     * @param string $s
     * @param string $m
     * @return bool|string
     */
    public static function dateTime($stamp = null, $s = '-', $m = ':')
    {
        $stamp = $stamp === null ? time() : $stamp;
        $format = "Y{$s}m{$s}d H{$m}i{$m}s";
        return date($format, $stamp);
    }

    public static function date($stamp = null, $s = '-')
    {
        $stamp = $stamp === null ? time() : $stamp;
        $format = "Y{$s}m{$s}d";
        return date($format, $stamp);
    }

    public static function time($stamp = null, $m = ':')
    {
        $stamp = $stamp === null ? time() : $stamp;
        $format = "H{$m}i{$m}s";
        return date($format, $stamp);
    }

    /**
     * 得到昨天0时0分的时间戳
     * @return int
     */
    public static function yesterdayStamp()
    {
        return strtotime('yesterday');
    }

    /**
     * 得到明天0时0分的时间戳
     * @return int
     */
    public static function tomorrowStamp()
    {
        return strtotime("tomorrow");
    }

    public static function nowStamp()
    {
        return time();
    }

    /**
     * 得到指定天数的时间戳。
     * 注意包含时分秒部分。是以当前时间戳为基准的。
     * @param $num
     * @param string $save 时间戳保存到那一位。就是date函数的格式字符串
     * 如果得到想得得指定的时间戳比如 一个月前第1天的12点 save Ym01 12:00:00,
     * 其它位数要补0，不然转换失败
     * @param int|null $now 计算的相对时间戳
     * @return int
     */
    public static function dayStamp($num, $save = 'YmdHis', $now = null)
    {
        if ($now === null) {
            $now = time();
        }
        $date = date($save, strtotime("{$num} day", $now));
        return strtotime($date);
    }

    /**
     * 得到指定月数的时间戳。
     * 注意包含时分秒部分。是以当前时间戳为基准的。
     * @param $num
     * @param string $save 时间戳保存到那一位。就是date函数的格式字符串
     * @param int|null $now 计算的相对时间戳
     * @return int
     */
    public static function monthStamp($num, $save = 'YmdHis', $now = null)
    {
        if ($now === null) {
            $now = time();
        }
        $date = date($save, strtotime("{$num} month", $now));
        return strtotime($date);
    }

    /**
     * 得到指定周相对于当前时间的时间戳。
     * 注意包含时分秒部分。是以当前时间戳为基准的。
     * @param $num
     * @param string $save 时间戳保存到那一位。就是date函数的格式字符串
     * @param int|null $now 计算的相对时间戳
     * @return int
     */
    public static function weekStamp($num, $save = 'YmdHis', $now = null)
    {
        if ($now === null) {
            $now = time();
        }
        $date = date($save, strtotime("{$num} week", $now));
        return strtotime($date);
    }

    public static function mktime($y, $m, $d, $h, $i, $s)
    {
        return mktime($h, $i, $s, $m, $d, $y);
    }

    /**
     * 得到2个时间戳之间多少月份。
     * 返回每个月第一天的时间
     * @param $start
     * @param $end
     * @return array
     */
    public static function someMonthByStamp($start, $end)
    {
        $r = array();
        if ($start > $end) {
            return $r;
        }
        //$start 实际要以当月第一月开始
        $start = strtotime(date('Ym01', $start));
        for ($i = $start; $i <= $end;) {
            $n = date('Y-m-d', $i);
            $i = strtotime("{$n} +1 month");
            $r[] = $n;
        }
        return $r;
    }

    /**
     * 得到2个时间戳之间有多少周
     * 返回每个周周1的时间
     * @param $start
     * @param $end
     * @return array
     */
    public static function someWeekByStamp($start, $end)
    {
        $r = array();
        if ($start > $end) {
            return $r;
        }
        $startDate = date('Y-m-d', $start);
        if (date('w', $start) == 1) {
            $start = strtotime($startDate); //如果当前是星期1
        } else {
            $start = strtotime("{$startDate} -1 week Monday"); //得到星期1 0时0分0秒的时间戳
        }

        for ($i = $start; $i <= $end;) {
            $n = date('Y-m-d', $i);
            $i = strtotime("{$n} +1 week Monday"); //得到下一周周1的时间戳
            $r[] = $n;
        }
        return $r;
    }

    /**
     * 得到2个时间戳之间有多少天
     * @param $start
     * @param $end
     * @return array
     */
    public static function someDayByStamp($start, $end)
    {
        $r  = array();
        if ($start > $end) {
            return $r;
        }
        for ($i = $start; $i <= $end;) {
            $n = date('Y-m-d', $i);
            $i = strtotime("{$n} +1 day");
            $r[] = $n;
        }
        return $r;
    }
}