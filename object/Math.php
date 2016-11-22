<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/10/11
 * Time: 16:52
 * 和数学相关的函数
 */
namespace bee\object;
class Math
{
    const TYPE_VALUE = 1; //返回数值
    const TYPE_PERCENT = 2; //返回百分比

    /**
     * 计算增长率
     * @param int $now 当前值
     * @param int $before 之前值
     * @param int $type 返回就百分比还是小数
     * @return float|int
     */
    public static function rise($now, $before, $type = self::TYPE_PERCENT)
    {
        $diff = $now - $before;
        if ($diff == 0) {
            return 0;
        }
        if ($before == 0) {
            $before = 1;
        }
        $rise = $diff / $before;
        if ($type == self::TYPE_PERCENT) {
            $rise *= 100;
        }
        return $rise;
    }
}