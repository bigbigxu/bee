<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/4/6
 * Time: 15:22
 */
class CoreMsg extends Object
{
    protected $separator = '#'; //模板变量分隔符

    /**
     * @param array $config
     * @param string $name
     * @return static
     */
    public static function getInstance($config = array(), $name = __CLASS__)
    {
        return parent::getInstance($config, $name);
    }

    /**
     * 执行一个模板翻译
     * @param string $msgId 消息ID a.b.c形式
     * @param array $data 模板变量替换数组
     * @return mixed
     * @throws Exception
     */
    public static function t($msgId, $data)
    {
        $o = self::getInstance();
        $msg = App::c($msgId);
        foreach ($data as $key => $value) {
            $msg = str_replace("{$o->separator}{$key}{$o->separator}", $value, $msg);
        }
        return $msg;
    }
}