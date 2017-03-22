<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/9/18
 * Time: 15:52
 */
namespace bee\object;
class Str
{
    protected $str;

    public function __construct($str)
    {
        $this->str = $str;
    }

    public static function g($str)
    {
        return new static($str);
    }

    public static function dFalse($set, $default)
    {
        if ($set == false) {
            return $default;
        } else {
            return $set;
        }
    }

    public static function pageToLimit($page, $pageSize)
    {
        $pageSize = $pageSize <= 0 ? 20 : $pageSize;
        $page = $page <= 0 ? 1 : $page;
        $offset = ($page - 1) * $pageSize;
        return "{$offset},{$pageSize}";
    }
}