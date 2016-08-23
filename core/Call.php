<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/8/15
 * Time: 14:19
 * 代理一个对象调用
 */
Class Call
{
    private $_errno = 0;

    const ERR_CLASS = 1;
    const ERR_METHOD = 2;

    protected $object; //正在代理的对象

    public static function g()
    {
        return new self;
    }

    /**
     * 根据路由实例化对象。不过此处会实例化对象
     * @param $r
     * @param array $params
     * @param string $sp
     * @return bool|mixed
     */
    public function route($r, $params = array(), $sp = '.')
    {
        list($class, $method) = explode($sp, $r);
        if (class_exists($class) == false) {
            return $this->_setErrno(self::ERR_CLASS);
        }

        if (method_exists($class, 'getInstance')) {
            $this->object = $class::getInstance();
        } elseif (method_exists($class, 'g')){
            $this->object  = $class::g();
        } else {
            $this->object  = new $class;
        }

        if (method_exists($this->object , $method) == false) {
            return $this->_setErrno(self::ERR_METHOD);
        }

        //得到方法的参数列表
        $methodParams = CoreReflection::getMethodParam(array($class, $method));
        foreach ($methodParams as $key => $value) {
            if ($params[$key] !== null) {
                $methodParams[$key] = $params[$key];
            }
        }
        return call_user_func_array(array($this->object , $method), $methodParams);
    }

    public function control($r, $params = array(), $sp = '.')
    {
        list($class, $method) = explode($sp, $r);
        $class = $class . 'Controller';
        $r = "{$class}{$sp}{$method}";
        return $this->route($r, $params, $sp);
    }

    private function _setErrno($errno)
    {
        $this->_errno = $errno;
        return false;
    }

    public function getErrno()
    {
        return $this->_errno;
    }

    public function getErrmsg()
    {
        $map = array(
            self::ERR_CLASS => '类不存在',
            self::ERR_METHOD => '方法不存在'
        );
        return $map[$this->_errno];
    }

    public function getObject()
    {
        return $this->object;
    }

    public function getObjectErrno()
    {
        if (method_exists($this->object, 'getErrno')) {
            return $this->object->getErrno();
        } else {
            return null;
        }
    }

    public function getObjectErrmsg()
    {
        if (method_exists($this->object, 'getModelErrmsg')) {
            return $this->object->getModelErrmsg();
        } else {
            return null;
        }
    }
}