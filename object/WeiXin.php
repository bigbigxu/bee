<?php
/**
 * Created by PhpStorm.
 * User: VigoXu
 * Date: 2015/8/10
 * Time: 9:31
 * 微信基础封装类
 */

namespace bee\object;

use bee\App;
use bee\client\Curl;
use bee\common\Functions;
use bee\common\Json;
use bee\common\StructXml;
use bee\core\Log;
use bee\core\TComponent;
use Exception;

class WeiXin
{
    use TComponent;
    protected $appID;
    protected $appKey;
    protected $checkToken; //验证服务器权限的token
    public $msg; //消息模板
    public $menu;
    public $redis;

    /**
     * 用户用户发送消息时的自动回复
     * @param $from
     * @param $to
     * @param $msg
     * @return string
     */
    public function sendAuotText($from, $to, $msg)
    {
        $xml = new StructXml();
        $root = $xml->addRoot('xml');
        $root->addChild('ToUserName', $to);
        $root->addChild('FromUserName', $from);
        $root->addChild('CreateTime', time());
        $root->addChild('MsgType', 'text');
        $root->addChild('Content', $msg);
        return $root->getXml($root, 1);
    }

    /**
     * 微信服务器权限验证
     * @return bool
     */
    public function checkSignature()
    {
        if (App::getInstance()->isDebug()) {
            return true;
        }
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $tmpArr = array($this->checkToken, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 给用户发送客户文本消息 48小时之内。
     * @param $openId
     * @param $msg
     * @throws Exception
     */
    public function sendCustomTextMsg($openId, $msg)
    {
        $token = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=' . $token;
        $str = '{"touser":"%s","msgtype":"text","text":{"content":"%s"}}';
        $str = sprintf($str, $openId, $msg);
        $res = Curl::simplePost($url, $str);
        $arr = json_decode($res, true);
        if ($arr['errmsg'] != 'ok') {
            Log::error($res);
        }
    }

    /**
     * 获取微信access_token
     * @return mixed
     * @throws Exception
     */
    public function getAccessToken()
    {
        $key = "weixin_{$this->appID}_token";
        if (($token = $this->redis()->get($key)) != false) {
            return $token;
        }
        $baseUrl = 'https://api.weixin.qq.com/cgi-bin/token';
        $params = array(
            'grant_type' => 'client_credential',
            'appid' => $this->appID,
            'secret' => $this->appKey
        );
        $url = $baseUrl . '?' . http_build_query($params);
        $str = Curl::simpleGet($url);
        $arr = json_decode($str, true);
        if ($arr['access_token'] == false) {
            throw new Exception('获取微信access_token失败');
        } else {
            $token = $arr['access_token'];
            $expire = $arr['expires_in'] - 10;
            $this->redis()->setex($key, $expire, $token);
            return $token;
        }
    }

    /**
     * jsapi_ticket是公众号用于调用微信JS接口的临时票据
     * @return bool|string
     * @throws Exception
     */
    public function getJsapiTicket()
    {
        $key = "weixin_{$this->appID}_jsapi_ticket";
        if (($ticket = $this->redis()->get($key)) != false) {
            return $ticket;
        }
        $baseUrl = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket';
        $params = array(
            'access_token' => $this->getAccessToken(),
            'type' => 'jsapi'
        );
        $url = $baseUrl . '?' . http_build_query($params);
        $str = Curl::simpleGet($url);
        $arr = json_decode($str, true);
        if ($arr['ticket'] == false) {
            throw new Exception('获取微信ticket失败');
        } else {
            $ticket = $arr['ticket'];
            $expire = $arr['expires_in'] - 10;
            $this->redis()->setex($key, $expire, $ticket);
            return $ticket;
        }
    }

    /**
     * 获取微信jssdk调用配置文件
     * @return array
     * @throws Exception
     */
    public function getJsSdkConfig()
    {
        $data = array(
            'url' => $_GET['url'],
            'jsapi_ticket' => $this->getJsapiTicket(),
            'timestamp' => time(),
            'noncestr' => Functions::randString(16)
        );
        ksort($data, SORT_STRING);
        $str = '';
        foreach ($data as $key => $row) {
            $str .= "{$key}={$row}&";
        }
        $str = rtrim($str, '&');;
        $data['signature'] = sha1($str);
        return $data;
    }

    /**
     * 通过网页获取的code 得到用户信息
     * @param $code
     * @return mixed
     * @throws Exception
     */
    public function getUserInfoByCode($code)
    {
        $info = $this->getOpenId($code);
        $baseUrl = 'https://api.weixin.qq.com/sns/userinfo';
        $data = array(
            'access_token' => $info['access_token'],
            'openid' => $info['openid'],
            'lang' => 'zh_CN'
        );
        $url = $baseUrl . '?' . http_build_query($data);
        $str = Curl::simpleGet($url);
        $arr = json_decode($str, true);
        return $arr;
    }

    /**
     * 根据网页获取的code,获取access_toke和用户id并缓存起来。
     * 引此接口设备无限制。
     * @param $code
     * @return array|bool
     * @throws Exception
     */
    public function getOpenId($code)
    {
        $baseUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token';
        $data = array(
            'appid' => $this->appID,
            'secret' => $this->appKey,
            'code' => $code,
            'grant_type' => 'authorization_code'
        );
        $url = $baseUrl . '?' . http_build_query($data);
        $str = Curl::simpleGet($url);
        $arr = json_decode($str, true);
        if ($arr['access_token'] == false) {
            throw new Exception('获取access_token失败: ' . $arr['errmsg']);
        } else {
            $info['access_token'] = $arr['access_token'];
            $info['openid'] = $arr['openid'];
            return $info;
        }
    }

    /**
     * 得到微信短链接
     * @param $url
     * @return mixed
     * @throws Exception
     */
    public function getShortUrl($url)
    {
        $key = 'weixin_short_url_' . md5($url);
        if (($shortUrl = $this->redis()->get($key)) != false) {
            return $shortUrl;
        }
        $url = 'https://api.weixin.qq.com/cgi-bin/shorturl?access_token=' . $this->getAccessToken();
        $data = array(
            'action' => 'long2short',
            'long_url' => urldecode($url)
        );
        $str = Curl::simplePost($url, json_encode($data));
        $arr = json_decode($str, true);
        if ($arr['short_url'] == false) {
            throw new Exception('获取短链接失败：' . $arr['errmsg']);
        } else {
            $this->redis()->setex($key, 30 * 24 * 3600, $arr['short_url']);
            return $arr['short_url'];
        }
    }

    /**
     * 创建自定义菜单
     * @param null $menu
     * @throws Exception
     */
    public function createMenu($menu = null)
    {
        $menu = $menu === null ? $this->menu : $menu;
        $baseUrl = 'https://api.weixin.qq.com/cgi-bin/menu/create';
        $params = array(
            'access_token' => $this->getAccessToken()
        );
        $url = $baseUrl . '?' . http_build_query($params);
        $post = Json::encode($menu, true);
        $res = Curl::simplePost($url, $post);
        echo $res;
    }

    /**
     * 查询当前配置
     * @throws Exception
     */
    public function queryMenu()
    {
        $baseUrl = 'https://api.weixin.qq.com/cgi-bin/menu/get';
        $params = array(
            'access_token' => $this->getAccessToken()
        );
        $url = $baseUrl . '?' . http_build_query($params);
        $res = Curl::simpleGet($url);
        $res = json_decode($res, true);
        Functions::showArr($res);
    }

    /**
     * 或其当前关注列表。
     * @return mixed|string
     * @throws Exception
     */
    public function getUserList()
    {
        $baseUrl = 'https://api.weixin.qq.com/cgi-bin/user/get';
        $params = array(
            'access_token' => $this->getAccessToken()
        );
        $url = $baseUrl . '?' . http_build_query($params);
        $res = Curl::simpleGet($url);
        $res = json_decode($res, true);
        return $res['data']['openid'];
    }

    public function createAuthUrl($url)
    {
        $params = array(
            'appid' => $this->appID,
            'redirect_uri' => $url,
            'response_type' => 'code',
            'scope' => 'snsapi_base',
            'state' => '123',
        );
        $baseUrl = 'https://open.weixin.qq.com/connect/oauth2/authorize';
        $url = $baseUrl . '?' . http_build_query($params) . '#wechat_redirect';
        return $url;
    }

    public function redis()
    {
        return App::s()->sure($this->redis);
    }
}