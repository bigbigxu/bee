<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/10/15
 * Time: 14:32
 * http处理的函数
 */

namespace bee\object;

class HttpObject
{
    const CLIENT_WINDOWS = 'windows';
    const CLIENT_IPHONE = 'ios';
    const CLIENT_IPAD = 'ipad';
    const CLIENT_ANDROID = 'android';

    /**
     * 生成一个url。用于合并url上的参数和data参数。
     * 如果有相同参数名，data中会覆盖。
     * @param $url
     * @param array $data
     * @return string
     */
    public static function createUrl($url, $data = array())
    {
        $parse = parse_url($url);
        if (isset($parse['query'])) {
            //如果url存在get参数，需要做参数合并
            parse_str($parse['query'], $params);
            $data = array_merge($params, $data);
        }
        //删除?后面的部分
        $pos = strpos($url, '?');
        if ($pos !== false) {
            $url = substr($url, 0, $pos);
        }

        $url .= '?' . http_build_query($data);
        return $url;
    }

    /**
     * 设置为utf8
     */
    public static function utf8()
    {
        header("content-type:text/html;charset=utf8");
    }

    /**
     * 得到客户段类型
     */
    public static function getClientType()
    {
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        if (strpos($agent, 'windows nt')) {
            $type = self::CLIENT_WINDOWS;
        } elseif (strpos($agent, 'iphone')) {
            $type = self::CLIENT_IPHONE;
        } elseif (strpos($agent, 'ipad')) {
            $type = self::CLIENT_IPAD;
        } elseif (strpos($agent, 'android')) {
            $type = self::CLIENT_ANDROID;
        } else {
            $type = self::CLIENT_ANDROID;
        }

        return $type;
    }

    /**
     * 设置重定向header头
     * @param $url
     */
    public static function location($url)
    {
        header("location: {$url}");
    }

    /**
     * 设置文件下载header头
     * @param string $name 文件名
     * @param int $size 文件字节数
     */
    public static function file($name, $size)
    {
        header("Content-type: application/octet-stream");
        header("Accept-Ranges: bytes");
        header("Accept-Length: {$size}");
        header("Content-Disposition: attachment; filename= {$name}");
    }
}