<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/8/12
 * Time: 16:15
 */
class CoreJson
{
    /**
     * json编码字符串
     * 默认返回编码的字符串
     * @param $arr
     * @param bool $encodeChinese
     * @return mixed|string
     */
    public static function encode($arr, $encodeChinese = true)
    {
        if ($encodeChinese == false) {
            //需要将中文转成utf8编码
            $json = json_encode($arr);
        } else {
            //如果想原样显示中文
            if(version_compare(PHP_VERSION, '5.4', '>=')) {
                $json = json_encode($arr, JSON_UNESCAPED_UNICODE);
            } else {
                 //5.4以下版本不支持 JSON_UNESCAPED_UNICODE参数
                $json = json_encode($arr);
                $json = self::unicodeDecode($json);
            }
        }
        return $json;
    }

    /*
     * json编码，json_decode不存在unicode问题
     * 默认返回对象
     */
    public static function decode($str, $returnArray = false)
    {
        return json_decode($str, $returnArray);
    }

    /**
     * 将unicode编码变成字符
     * @param $str
     * @return mixed
     */
    protected static function unicodeDecode($str)
    {
        $str = preg_replace_callback(
            '/\\\\u([0-9a-f]{4})/i',
            array('self', 'replaceUnicode'),
            $str
        );
        return $str;
    }

    protected static function replaceUnicode($match)
    {
        $str = mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
        return $str;
    }

    /**
     * 输出格式化的JSON串。使之有换行，缩进，以便于阅读
     * 此方法来源于网络，修改了部分参数。
     * @param array|string $json 为数组，将先转变为json串。
     * @param string $newLine 换行符
     * @param string $indentStr 缩进符，
     * @param int $repeatNum 缩进符每次重复次数
     * @return string 格式化后的字符串
     * @example
     * 	    html页面
     *     echo Json::formatJson(array('xx'=>$res),"<br>",'&nbsp',4);
     *     文本返回
     *     echo Json::formatJson(array('xx'=>$res),"\n",' ',4);
     */
    public static function formatJson($json, $newLine = "\n", $indentStr = " ", $repeatNum = 4)
    {
        //如果是一个数组，先变成字符串。
        if(is_array($json)) {
            $json = self::encode($json);
        }
        $result = '';
        $pos = 0;
        $strLen = strlen($json);
        $prevChar = '';
        $oneIndentStr = $indentStr;
        $indentStr = str_repeat($indentStr, $repeatNum);
        $outOfQuotes = true;
        for ($i = 0; $i <= $strLen; $i++) {
            $char = substr($json, $i, 1);
            if ($char == '"' && $prevChar != '\\') {
                $outOfQuotes = !$outOfQuotes;
            } elseif (($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos--;
                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }
            $result .= $char;
            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char == '{' || $char == '[') {
                    $pos++;
                }
                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            } elseif ($char == ':' && $prevChar == '"') {
                $result = $result . $oneIndentStr;
            }
            $prevChar = $char;
        }
        $result = self::unicodeDecode($result);
        $result = str_replace('\/', '/', $result);
        return $result . $newLine;
    }

    public static function showHtmlJson($p)
    {
        return self::formatJson($p, "<br>", "&nbsp", 4);
    }

    public static function showTextJson($p)
    {
        return self::formatJson($p, "\n", " ", 4);
    }
}