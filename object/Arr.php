<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/9/18
 * Time: 15:52
 */
namespace bee\object;
class Arr
{
    protected $arr;

    public function __construct($arr)
    {
        $this->arr = $arr;
    }

    public static function g($arr)
    {
        return new static($arr);
    }

    public function avg()
    {
        if (!is_array($this->arr)) {
            return 0;
        }
        $n = count($this->arr);
        if ($n == 0) {
            return 0;
        }

        return array_sum($this->arr) / $n;
    }

    /**
     * 过滤数组中的null值
     * @param $arr
     * @return array
     */
    public static function filterNull($arr)
    {
        $arr = array_filter($arr, function($v) {
            return $v !== null;
        });
        return $arr;
    }
}