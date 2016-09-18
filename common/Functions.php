<?php

/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/5/12
 * Time: 15:50
 */
class Functions
{
    public static function numToChinese($num)
    {
        $num = intval($num);
        if ($num > 9) {
            return false;
        }
        $arr = array(
            1 => '一',
            2 => '二',
            3 => '三',
            4 => '四',
            5 => '五',
            6 => '六',
            7 => '七',
            8 => '八',
            9 => '九',
        );
        return $arr[$num];
    }

    public static function showArr($arr)
    {
        //header("content-type:text/html;charset=utf8");
        echo '<pre>';
        print_r($arr);
        echo '</pre>';
    }

    /**
     * 数组合并。用于解决array_merge在参数不是数组，返回空数组的问题。
     * @return array|mixed
     */
    public static function arrayMerge()
    {
        if (func_num_args() == 0) {
            return array();
        }
        $params = func_get_args();
        foreach ($params as &$row) {
            if (is_array($row) == false) {
                $row = array();
            }
        }
        $return = call_user_func_array('array_merge', $params);
        return $return;
    }

    /**
     * 得到当前请求的完整url
     * @return string
     */
    public static function fullUrl()
    {
        $url = '';
        if ($_SERVER['HTTPS'] == 'on') {
            $url .= 'https://';
        } else {
            $url .= 'http://';
        }
        $url .= $_SERVER['HTTP_HOST'];
        $url .= $_SERVER['REQUEST_URI'];
        //$url .= '?' . $_SERVER['QUERY_STRING'];
        return $url;
    }

    /**
     * 得到客户端类型
     * @return string
     */
    public static function getClientType()
    {
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        if (strpos($agent, 'iphone') || strpos($agent, 'ipad')) {
            $type = 'ios';
        } else {
            $type = 'android';
        }
        return $type;
    }

    /**
     * 将一个一维组的key根据map做一个转换
     * @param array $res 要转换的数组
     * @param array $map key映射数组
     * @param bool $save 是否保存不在map中的key
     * @return array
     */
    public static function changeKey($res, $map, $save = true)
    {
        $data = array();
        if (!is_array($res)) {
            return array();
        }
        foreach ($res as $key => $value) {
            if (isset($map[$key])) {
                $data[$map[$key]] = $value;
            } else {
                if ($save == true) {
                    $data[$key] = $value;
                }
            }
        }
        return $data;
    }

    /**
     * 转换一个二维数组的key
     * @param $arr
     * @param $map
     * @param bool $save
     * @return array
     */
    public static function changeArrayKey($arr, $map, $save = true)
    {
        $data = array();
        if (!is_array($arr)) {
            return array();
        }
        foreach ($arr as $key => $value) {
            $data[$key] = self::changeKey($value, $map, $save);
        }
        return $data;
    }

    //串 转 十六进制
    public static function asc2hex($str)
    {
        return '\x' . substr(chunk_split(bin2hex($str), 2, '\x'), 0, -2);
    }

    //十六进制 转串
    public static function hex2asc($str)
    {
        $data = '';
        $str = join('', explode('\x', $str));
        $len = strlen($str);
        for ($i = 0; $i < $len; $i += 2) {
            $data .= chr(hexdec(substr($str, $i, 2)));
        }
        return $data;
    }

    public static function showXml($xml)
    {
        header('content-type:text/xml;charset=utf8');
        echo $xml;
    }

    /**
     * 将一个二维数组进行分页(必须为索引数组)
     * @param $arr
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function arrayPage($arr, $page = 0, $pageSize = 20)
    {
        if (!is_array($arr)) {
            return array();
        }
        $pageSize = $pageSize <= 0 ? 20 : $pageSize;
        $page = $page <= 0 ? 1 : $page;
        $offset = ($page - 1) * $pageSize;
        $i = 1;
        $res = array();
        foreach ($arr as $key => $row) {
            if ($key < $offset) {
                continue;
            }
            if ($i > $pageSize) {
                break;
            }
            $res[] = $row;
            $i++;
        }
        return $res;
    }

    /**
     * 得到一个随机字符串
     * @param int $len
     * @return int
     */
    public static function randString($len = 6)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $arr = str_split($chars, 1);
        $str = '';
        $n = count($arr);
        for ($i = 0; $i < $len; $i++) {
            $index = mt_rand(0, $n);
            $str .= $arr[$index];
        }
        return $str;
    }

    /**
     * 得到一个数组的维度。使用引用传值。
     * @param $arr
     * @return int
     */
    public static function arrayLevel(&$arr)
    {
        $level = 0;
        if (!is_array($arr)) {
            return $level;
        }
        $level++;
        foreach ($arr as &$item) {
            if (is_array($item)) {
                $level += self::arrayLevel($item);
            } else {
                continue;
            }
        }
        return $level;
    }

    /**
     * 过滤数组
     * 只有在saveKey中的下标才会被保存。
     * @param $res
     * @param $saveKey
     * @return array
     */
    public static function arrayFilterKey(&$res, $saveKey)
    {
        $r = array();
        if (!is_array($res)) {
            return array();
        }
        foreach ($res as $key => $row) {
            if (in_array($key, $saveKey)) {
                $r[$key] = $row;
            }
        }
        return $r;
    }

    /**
     * @param $proArr
     * @return string
     */
    public static function draw($proArr)
    {
        $result = '';
        //概率数组的总概率精度
        $proSum = array_sum($proArr);
        //概率数组循环
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }
        unset ($proArr);
        return $result;
    }

    public static function stripslashesDeep()
    {
        $_POST = array_map(array('self', '_stripslashes'), $_POST);
        $_GET = array_map(array('self', '_stripslashes'), $_GET);
        $_COOKIE = array_map(array('self', '_stripslashes'), $_COOKIE);
        $_REQUEST = array_map(array('self', '_stripslashes'), $_REQUEST);
    }

    private static function _stripslashes($value)
    {
        if (is_array($value)) {
            $value = array_map(array('self', '_stripslashes'), $value);
        } else {
            $value = stripslashes($value);
        }
        return $value;
    }

    /**
     * 使用array_multisort 对一个二维数组排序
     * array_multisort()先把第一个数组按照键值的大小排序，
     * 然后其它数组都按照第一个数组的调整策略进行调整——第三个元素放到第一位，
     * 第二个元素放到第二位……——其实这个多维数组排序算法的最基本体现！
     *
     * 还有一点值得注意：这个函数改变数字索引，其他索引不变！
     *
     * 在根据指定的排序对值排序
     *
     * 类似于 SQL 的 ORDER BY 子句的功能
     * 第一个数组是要排序的主要数组。
     * 数组中的行（值）比较为相同的话就按照下一个输入数组中相应值的大小来排序，依此类推。
     *
     * @param  array $arr 要排序的数组
     * @param  string $sort 排序串，和sql order子句一样 id desc, name asc
     * @return mixed
     */
    public static function sortByField($arr, $sort)
    {
        if (!is_array($arr)) {
            return array();
        }
        //解析$sort 得到要排序的字段和排序方式
        $sortFieldArr = array();
        $sort = explode(',', $sort);
        foreach ($sort as $row) {
            $tmp = explode(' ', trim($row));
            if (trim($tmp[1]) == 'desc') {
                $sortType = SORT_DESC;
            } else {
                $sortType = SORT_ASC;
            }
            $sortFieldArr[trim($tmp[0])] = $sortType;
        }
        //得到每个排序的一维数组
        $sortData = array();
        foreach ($arr as $key => $item) {
            foreach ($sortFieldArr as $sortField => $sortType) {
                $sortData[$sortField][$key] = $item[$sortField];
            }
        }
        //组织函数参数
        foreach ($sortData as $sortField => $item) {
            $params[] = &$sortData[$sortField];
            $params[] = $sortFieldArr[$sortField];
        }
        $params[] = &$arr;
        //必须使用引用传递。因为call_user_func_array，是值传递。中间多了一层调用。
        call_user_func_array('array_multisort', $params);
        return $arr;
    }

    /**
     * 格式化文件大小
     * @param string $byte 大小
     * @param string $unit 单位 默认为b
     * @param string $returnUnit 返回的数组单位
     * @param int $decimals 保留的小数位数
     * @return array
     */
    public static function sizeFormat($byte, $unit = "B", $returnUnit = 'MB', $decimals = 2)
    {
        $unit = strtoupper($unit);
        $returnUnit = strtoupper($returnUnit);
        if ($unit == $returnUnit) {
            return $byte;
        }
        $units = array("B", "KB", "MB", "GB", "TB", 'PB');
        $start = array_search($unit, $units);
        $end = array_search($returnUnit, $units);
        if ($end < $start || $start === false || $end === false) {
            return false;
        }
        while ($start < $end) {
            $byte = $byte / 1024;
            $start++;
        }
        $newSize = round($byte, $decimals);
        return $newSize;
    }

    /**
     * 得到本机IP地址，windows下，只使用回环地址。
     * 默认将只返回一个IP地址。
     */
    public static function getLocalIp()
    {
        if (strtolower(PHP_OS) != 'linux') {
            return 'localhost';
        }
        $str = shell_exec('ifconfig');
        preg_match_all('/inet addr:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $str, $ma);
        if (!$ma[1]) {
            return 'localhost';
        }

        //只返回第一个IP地址
        foreach ($ma[1] as $row) {
            if (preg_match('/$[10|192|172]/', $row)) {
                continue;
            } else {
                return $row;
            }
        }
        return 'localhost';
    }

    /**
     * 昨到当前时间戳的毫秒数
     */
    public static function milliSecondTime()
    {
        list($t1, $t2) = explode(' ', microtime());
        return (string)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }

    /**
     * 计算2个数值这间的增长率
     * @param float $now 现在的数值
     * @param float $before 之前的数值
     * @param int $float 小数位数
     * @param bool $percent 是否返回百分比
     * @return float|string
     */
    public static function getRiseRate($now, $before, $float = 4, $percent = true)
    {
        $rate = round(($now - $before) / max(1, $before), $float);
        if ($percent == true) {
            return $rate * 100 . "%";
        } else {
            return $rate;
        }
    }

    /**
     *用于分页
     */
    public static function page_limit($page, $size)
    {
        $page = (int)$page;
        $page -= 1;
        if ($page <= 0) {
            $page = 0;
        }
        return $page * $size . "," . $size;
    }

    public static function time2second($seconds)
    {
        $seconds = (int)$seconds;
        if ($seconds > 3600) {
            $daysNum = '';
            if ($seconds > 24 * 3600) {
                $days = (int)($seconds / 86400);
                $daysNum = $days . "天";
                $seconds = $seconds % 86400;//取余
            }
            $hours = intval($seconds / 3600);
            $minutes = $seconds % 3600;//取余下秒数
            $time = $daysNum . $hours . "小时" . gmstrftime('%M分钟%S秒', $minutes);
        } else {
            $time = gmstrftime('%H小时%M分钟%S秒', $seconds);
        }
        return $time;
    }

    public static function arrayFilterRecursive($input, $callback)
    {
        if (!is_array($input)) {
            return array();
        }
        foreach ($input as &$value) {
            if (is_array($value)) {
                $value = self::arrayFilterRecursive($value, $callback);
            }
        }
        return array_filter($input, $callback);
    }
}
