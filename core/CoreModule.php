<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/3/28
 * Time: 14:46
 * 模块只需要配置文件
 * 模块配置文件如下
 * @example
 * '模块名称' => array(
 *    'controllerDir' => 'controller' //配置模块加载控制器的目录
 *    'modelDir' => 'model' //模块独有模型目录
 *    'viewDir' => 'view' //视图目录
 *    'name' => 'name', //模块名称
 *    ''
 * )
 */
class CoreModule extends Object
{
    /**
     * 所有模块的配置文件
     * @var array
     */
    public $modules;
    public static function getInstance($config = array(), $name = __CLASS__)
    {
        return parent::getInstance($config, $name);
    }

    public function init()
    {
        $this->modules = App::c('modules');
        foreach ($this->modules as $row) {
            if ($row['models'])
        }
    }

}