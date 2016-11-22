<?php

/**
 * Class LinuxCrontab
 * php解析linux crontab格式
 *
 *  基本格式 :
 *　*　*　　*　　*　　*　　command
 *  分　时　日　月　周　命令
 * 第1列表示分钟1～59 每分钟用*或者 *\/1表示
 * 第2列表示小时1～23（0表示0点）
 * 第3列表示日期1～31第4列表示月份1～12
 * 第5列标识号星期0～6（0表示星期天）
 * 每位为数字表示指定时间
 * 以-分开，表示时间范围
 * 以/分开，表示每隔多少时间，取余
 * *表示每隔1
 *  ,分开几个离散的数字
 * @TODO 星期，月份不支持使用英文符号
 */
class LinuxCrontab
{
    const ERR_NO = 0;
    const ERR_FORMAT = 10;
    const ERR_RIGHT = 11;
    const ERR_VALUE = 12;

    protected $errno = 0; //当前错误号

    public function getError()
    {
        $arr = array(
            self::ERR_FORMAT => '定时器格式错误',
            self::ERR_RIGHT => '使用"-"设置范围时，左不能大于右',
            self::ERR_VALUE => '数值越界。应该：分0-59，时0-59，日1-31，月1-12，周0-6'
        );
        return (string)$arr[$this->errno];
    }

    public function setErrno($code)
    {
        $this->errno = $code;
        return false;
    }

    public function getErrno()
    {
        return $this->errno;
    }

    /**
     * 检查某时间($time)是否符合某个corntab时间计划($str_cron)
     * @param int $time 时间戳
     * @param string $strCron corntab的时间计划，如，"30 2 * * 1-5"
     * @return bool/string 出错返回string（错误信息）
     */
    public function check($time, $strCron)
    {
        $this->errno = self::ERR_NO; //先设置为没有错误
        $formatTime = $this->formatTimestamp($time);
        $formatCron = $this->formatCrontab($strCron);
        if (!is_array($formatCron)) {
            $res = $formatCron;
        } else {
            $res = $this->formatCheck($formatTime, $formatCron);
        }
        return $res;
    }

    /**
     * 使用格式化的数据检查某时间($format_time)是否符合某个corntab时间计划($format_cron)
     *
     * @param array $formatTime self::formatTimestamp()格式化时间戳得到
     * @param array $formatCron self::formatCrontab()格式化的时间计划
     *
     * @return bool
     */
    public function formatCheck(array $formatTime, array $formatCron)
    {
        return (!$formatCron[0] || in_array($formatTime[0], $formatCron[0]))
        && (!$formatCron[1] || in_array($formatTime[1], $formatCron[1]))
        && (!$formatCron[2] || in_array($formatTime[2], $formatCron[2]))
        && (!$formatCron[3] || in_array($formatTime[3], $formatCron[3]))
        && (!$formatCron[4] || in_array($formatTime[4], $formatCron[4]));
    }

    /**
     * 格式化时间戳，以便比较
     * @param int $time 时间戳
     * @return array
     */
    public function formatTimestamp($time)
    {
        return explode('-', date('i-G-j-n-w', $time));
    }

    /**
     * 格式化crontab时间设置字符串,用于比较
     * @param string $strCron crontab的时间计划字符串，如"15 3 * * *"
     * @return array/string 正确返回数组，出错返回字符串（错误信息）
     */
    public function formatCrontab($strCron)
    {
        //格式检查
        $strCron = trim($strCron);
        $reg = '#^((\*(/\d+)?|((\d+(-\d+)?)(?3)?)(,(?4))*))( (?2)){4}.*?$#';
        if (!preg_match($reg, $strCron)) {
            return $this->setErrno(self::ERR_FORMAT);
        }

        //分别解析分、时、日、月、周
        $arrCron = array();
        $parts = explode(' ', $strCron);
        $arrCron[0] = self::parseCronPart($parts[0], 0, 59);//分
        $arrCron[1] = self::parseCronPart($parts[1], 0, 59);//时
        $arrCron[2] = self::parseCronPart($parts[2], 1, 31);//日
        $arrCron[3] = self::parseCronPart($parts[3], 1, 12);//月
        $arrCron[4] = self::parseCronPart($parts[4], 0, 6);//周（0周日）

        return $arrCron;
    }

    /**
     * 解析crontab时间计划里一个部分(分、时、日、月、周)的取值列表
     * @param string $part 时间计划里的一个部分，被空格分隔后的一个部分
     * @param int $fMin 此部分的最小取值
     * @param int $fMax 此部分的最大取值
     *
     * @return array 若为空数组则表示可任意取值
     * @throws Exception
     */
    protected function parseCronPart($part, $fMin, $fMax)
    {
        $list = array();

        //处理"," -- 列表
        if (false !== strpos($part, ',')) {
            $arr = explode(',', $part);
            foreach ($arr as $v) {
                $tmp = self::parseCronPart($v, $fMin, $fMax);
                $list = array_merge($list, $tmp);
            }
            return $list;
        }

        //处理"/" -- 间隔
        $tmp = explode('/', $part);
        $part = $tmp[0];
        $step = isset($tmp[1]) ? $tmp[1] : 1;

        //处理"-" -- 范围
        if (false !== strpos($part, '-')) {
            list($min, $max) = explode('-', $part);
            if ($min > $max) {
                return $this->setErrno(self::ERR_RIGHT);
            }
        } elseif ('*' == $part) {
            $min = $fMin;
            $max = $fMax;
        } else { //数字
            $min = $max = $part;
        }

        //空数组表示可以任意值
        if ($min == $fMin && $max == $fMax && $step == 1) {
            return $list;
        }

        //越界判断
        if ($min < $fMin || $max > $fMax) {
            return $this->setErrno(self::ERR_VALUE);
        }

        return $max - $min > $step ? range($min, $max, $step) : array((int)$min);
    }
}