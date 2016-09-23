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

}