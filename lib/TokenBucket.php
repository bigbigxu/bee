<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2016/9/22
 * Time: 15:20
 * 令牌桶算法。
 * 在一定的时间内发放指定数据量的令牌
 */
class TokenBucket
{
    const ERR_NO = 0;
    const ERR_TOKEN_FINISH =  1; //没有令牌了
    const ERR_TOKEN_OVER = 2; //超出了可以使用的令牌
    protected $errno = self::ERR_NO;

    /**
     * @var int 可以使用这令牌的时间
     */
    protected $resetTime;
    /**
     * @var int 在useTime内最多可以使用的令牌数量
     */
    protected $maxToken;
    /**
     * @var int 计算的开始时间
     */
    protected $beginTime;

    /**
     * @var int 已经使用的令牌数量
     */
    protected $useToken = 0;

    /**
     * TokenBucket constructor.
     * @param int $resetTime 令牌发放间隔
     * @param int $maxToken  令牌总数
     * @param int $useToken 已经使用了的令牌数
     * @param null|int $beginTime 最近一次的重置时间
     */
    public function __construct($resetTime, $maxToken, $useToken = 0,  $beginTime = null)
    {
        $this->resetTime = intval($resetTime);
        $this->maxToken = intval($maxToken);
        $this->useToken = $useToken;
        $this->beginTime = $beginTime ? $beginTime : time();
    }

    /**
     * 使用token
     * @param int $num
     * @return int
     */
    public function getToken($num)
    {
        $num = intval($num);
        if ($this->beginTime + $this->resetTime < time()) { //检查是否到达了重置时间
            $this->beginTime = time();
            $this->useToken = 0;
        }

        if ($this->useToken >= $this->maxToken) { //没有令牌
            $this->errno = self::ERR_TOKEN_FINISH;
            return 0;
        }

        if ($this->useToken + $num > $this->maxToken) {
            $getToken = $this->maxToken - $this->useToken;
            $this->useToken = $this->maxToken;
            $this->errno = self::ERR_TOKEN_OVER;
            return $getToken;
        }

        $this->useToken += $num;
        $this->errno = self::ERR_NO;
        return $num;
    }

    public function getErrno()
    {
        return $this->errno;
    }

    /**
     * 得到当前使用了的token
     * @return int
     */
    public function getUserToken()
    {
        if ($this->beginTime + $this->resetTime < time()) {
            return 0;
        } else {
            return $this->useToken;
        }
    }

    /**
     * 返回最大令牌数
     * @return int
     */
    public function getMaxToken()
    {
        return $this->maxToken;
    }

    /**
     * 获取剩下的令牌
     * @return int
     */
    public function getLeftToken()
    {
        if ($this->beginTime + $this->resetTime < time()) {
            return $this->maxToken;
        } else {
            return $this->maxToken - $this->useToken;
        }
    }

    /**
     * 获取下一次的重置时间
     * @return int|null
     */
    public function getNextResetTime()
    {
        return $this->beginTime + $this->resetTime;
    }
}