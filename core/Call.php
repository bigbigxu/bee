<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/8/15
 * Time: 14:19
 */
Class Call
{
    private $_errno = 0;

    const ERR_CLASS = 1;
    const ERR_METHOD = 2;

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
            $object = $class::getInstance();
        } elseif (method_exists($class, 'g')){
            $object = $class::g();
        } else {
            $object = new $class;
        }

        if (method_exists($object, $method) == false) {
            return $this->_setErrno(self::ERR_METHOD);
        }

        return call_user_func_array(array($class, $method), $params);
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
}