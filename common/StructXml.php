<?php
//namespace iphp\core;
/**
 * 使用多维数组描述XML结构
 * 用于XML输出的基类控制器
 * @author xuen
 * @version 1.0
 */
class StructXml
{
    public $name; //节点名
    public $attr; //节点属性，数组
    public $child; //子节点，数组
    public $value; //节点值
    public $str = ''; //xml值
    public $arr = array(); //解析XML的数组
    public $flag = 1;//执行标识判断位

    /**
     * 添加根结点
     * @param string $nodeName 节点名称
     * @param string|int $value 节点属性
     * @param array|null $attr
     * @return self 根结点
     */
    public function addRoot($nodeName, $value = null, $attr = null)
    {
        $root = new self();
        $root->name = $nodeName;
        if ($attr !== null) {
            $root->addAttr($attr);
        }
        if ($value !== null) {
            $root->value = $value;
        }
        return $root;
    }

    /**
     * 为一个结点添加属性
     * @param array $attr
     * @return $this
     */
    public function addAttr($attr)
    {
        foreach ($attr as $key => $value) {
            $this->attr[$key] = $value;
        }
        return $this;
    }

    /**
     * 添加子节点
     * @param string $nodeName 节点名
     * @param null|string $value 节点值
     * @param null|array $attr 节点属性
     * @return self 返回当前添加的节点对象
     */
    public function addChild($nodeName, $value = null, $attr = null)
    {
        $node = new self();
        $node->name = $nodeName;
        if ($attr !== null) {
            $node->addAttr($attr);
        }
        if ($value !== null) {
            if(is_string($value)) {
                //如果是一个字符串，变成cdata
                $value = "<![CDATA[{$value}]]>";
            }
            $node->value = $value;
        }
        $this->child[] = $node;
        return $node;
    }

    /**
     * 根据StrXml结构返回一个XML串
     * @param self $root 根结点
     * @param int $type 生成XML的类型，1表示有换行，无XML头。0表示标准XML，默认为1
     * @return string ,链接生成的串
     */
    public function getXml(StructXml $root, $type = 0)
    {
        //为当前节点添加前半部
        if ($type == 0) {
            if ($this->flag == 1) {
                $this->str .= "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n";
                $this->flag = 0;//XML头已经写入。
            }
            $space = '';
        } else {
            $space = "\n";
        }
        $this->str .= "<{$root->name}";

        //添加属性
        if (!empty($root->attr)) {
            foreach ($root->attr as $key => $value) {
                $this->str .= " {$key}=\"{$value}\"";
            }
        }
        //根据有无子节点添加节点值
        if (empty($root->child)) {
            if (!isset($root->value)) {
                $this->str .= "/>{$space}";
            } else {
                $this->str .= ">{$root->value}</{$root->name}>{$space}";
            }
            return '';
        } else {
            $this->str .= ">{$space}";
            foreach ($root->child as $node) {
                self::getXml($node, $type);
            }
        }
        $this->str .= "</{$root->name}>{$space}";
        return $this->str;
    }

    /**
     * 格式化一个XML串，如果当前串是由DOM生成。去掉换行，和XML头
     * @param string $str DOM生成的串
     * @param bool $header 要不要xml头，默认保留
     * @param bool $rn 是否生成换行，默认生成换行
     * @return string
     */
    public static function formatXml($str, $header = true, $rn = true)
    {
        //生成换行
        if ($rn == true) {
            $str = preg_replace('/(>)</e', "'$1'.'\n'.'<'", $str);
        }
        //不要XML头
        if ($header == false) {
            $str = preg_replace('/<?xml[\S\s]+?>/e', "", $str);
            $pos = strpos($str, "\n");
            $str = substr($str, $pos + 1);
        }
        return $str;
    }

    /**
     * 解析XML文件，生成一个数组,要考虑换行等问题
     * @param SimpleXMLElement $doc 节点名称
     * @param int $i 节点索引
     * @return array
     * @example $arr[nodeName][$i]，返回指定结点的数组,$i，节点索引
     * $arr['mac'][0][0]:节点值
     * $arr['mac'][0]['ver']:节点属性
     */
    public function getArray($doc, $i = 0)
    {
        $nodeName = (string)$doc->getName();
        foreach ($doc->attributes() as $key => $attr) {
            $this->arr[$nodeName][$i][$key] = (string)$attr;
        }
        //去掉换行（换行也会被当成结点）
        $value = trim((string)$doc[0]);
        if (!empty($value)) {
            $this->arr[$nodeName][$i][0] = $value;
        }
        foreach ($doc->children() as $key1 => $node) {
            self::getArray($node, ++$i);
        }
        //重新生成索引
        foreach ($this->arr as $key => $value) {
            $this->arr[$key] = array_values($value);
        }
        return $this->arr;
    }

    /**
     * 将一个XML转变成一个树形结构的多维数组
     * 将节点属性看作xml的一个子节点
     * @param SimpleXMLElement|string $doc
     * @return array|bool
     */
    public static function getTreeArray($doc)
    {
        if (!($doc instanceof SimpleXMLElement)) {
            $doc = simplexml_load_string($doc);
        }
        $array = array();
        $nodeName = (string)$doc->getName(); ////得到节点名称
        //处理当前节点属性
        foreach ($doc->attributes() as $attrName => $value) {
            $array[$nodeName][$attrName] = (string)$value;
        }
        //处理节点值,如果一个节点有节点值，则认为是叶子节点。
        $value = trim((string)$doc[0]);
        if ($value !== '') {
            if (!empty($array[$nodeName])) {
                $array[$nodeName][0] = $value;
            } else {
                $array[$nodeName] = $value;
            }
        }
        //处理子节点。
        foreach ($doc->children() as $childNode) {
            $tmp = self::getTreeArray($childNode);
            foreach ($tmp as $key => $nodeValue) {
                //判断下面它有几个节点，同名的字节点有几个
                //如果有2个或以上同名子节点，用数组形式表示。
                if (count($doc->$key) == 1) {
                    $array[$nodeName][(string)$childNode->getName()] = $nodeValue;
                } else {
                    $array[$nodeName][(string)$childNode->getName()][] = $nodeValue;
                }
            }
        }
        return $array;
    }
}