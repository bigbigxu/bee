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
}