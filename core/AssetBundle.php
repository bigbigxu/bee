<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/11/27
 * Time: 18:03
 * css,js,图片等资源管理器
 */
class AssetBundle extends Object
{
    /**
     * 资源根目录。这个本地文件目录
     * @var string
     */
    public $basePath;

    /**
     * 资源根目录。这个是网站根目录。
     * @var string
     */
    public $baseUrl;
    /**
     * 定义资源依赖。
     * @var array
     */
    public $depends = array();
    /**
     * 所有的css文件
     * @var array
     */
    public $css = array();
    /**
     * 所有的js文件
     * @var array
     */
    public $js = array();
    /**
     * css选项
     * @var array
     */
    public $cssOption = array();
    /**
     * js选项，注意js加载的先后顺序
     * @var array
     */
    public $jsOption = array();
    /**
     * 所有图片
     * @var array
     */
    public $img = array();

    public static function getInstance($config = array(), $name = __CLASS__)
    {
        return parent::getInstance($config, $name);
    }

    public function init()
    {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public static function register($view)
    {

    }

    public function registerCss()
    {
        if ($this->css == false) {
            return $this;
        }
        $str = '';
        foreach ($this->css as $file) {
            $href = "{$this->baseUrl}/{$file}";
            $str .= "<link type=\"text/css\" rel=\"stylesheet\" href=\"{$href}\">\n";
        }
        echo $str;
    }

    /**
     * 添加一个css文件
     * @param $path
     * @param bool|false $append
     * @return $this
     */
    public function addCss($path, $append = false)
    {
        if ($append) {
            array_unshift($this->css, $path);
        } else {
            array_push($this->css, $path);
        }
        return $this;
    }

    public function addJs($path, $append = false)
    {
        if ($append) {
            array_unshift($this->js, $path);
        } else {
            array_push($this->js, $path);
        }
        return $this;
    }

    /**
     * 添加一个图片
     * @param $key
     * @param $path
     * @return $this
     */
    public function addImg($key, $path)
    {
        $this->img[$key] = $path;
        return $this;
    }
}