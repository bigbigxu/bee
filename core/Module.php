<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2017/4/13
 * Time: 9:10
 */
namespace bee\core;
abstract class Module extends Component
{
    const EVENT_BEFORE_REQUEST = 'before_request';
    const EVENT_AFTER_REQUEST = 'after_request';

    /**
     * 控制器
     * @var mixed
     */
    public $ctrl;
    /**
     * 当前类名和方法名
     * @var []
     */
    public $route;

    /*
     * 获取请求参数
     * @return mixed 数据
     */
    abstract public function getParam();

    /**
     * 权限校验
     * @param $data
     * @return mixed
     */
    abstract public function checkAuth($data);

    /**
     * 运行，实例化控制器，调用方法
     * @param mixed $data
     * @return mixed
     */
    abstract public function exec($data);

    public function run()
    {
        $this->trigger(self::EVENT_BEFORE_REQUEST);
        $data = $this->getParam();
        $this->checkAuth($data);
        $this->exec($data);
        $this->trigger(self::EVENT_AFTER_REQUEST);
    }
}