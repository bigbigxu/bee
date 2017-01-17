<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/4/8
 * Time: 14:24
 */
class CoreController
{
    /**
     * 输出一个json字符串
     * @param array $data
     * @param null $callback
     */
    public function json($data = array(), $callback = null)
    {
        $str = json_encode($data);
        if ($callback) {
            $str = "{$callback}(" . $str . ")";
        }
        if (substr(php_sapi_name(), 0, 3) != 'cli') {
            header('Content-Type: text/html;charset=utf-8');
            die($str);
        } else {
            echo $str;
        }
    }
}