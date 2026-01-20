<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2023 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// | Modified: Refactored for EasyWeChat 6.x - Removed Pimple dependency
// +----------------------------------------------------------------------

namespace crmeb\services\easywechat\oauth2\wechat;

use crmeb\services\SystemConfigService;
use crmeb\services\easywechat\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 * 微信网页授权 Provider
 * 重构后不再依赖 Pimple
 * 
 * Class WechatOauth2Provider
 * @package crmeb\services\easywechat\oauth2\wechat
 * @method oauth(string $code = '') code授权获取acces_token openid
 * @method getUserInfo($openId, $lang = 'zh_CN') openid 获取用户信息
 * @method setRequest(Request $request) 设置request对象
 */
class WechatOauth2Provider
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var WechatOauth
     */
    protected $oauth;

    /**
     * WechatOauth2Provider constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 获取 OAuth 实例
     * @return WechatOauth
     */
    protected function getOauth(): WechatOauth
    {
        if (!$this->oauth) {
            $request = app('request');
            $wechat = SystemConfigService::more(['wechat_appid', 'wechat_app_appid', 'wechat_app_appsecret', 'wechat_appsecret']);
            
            if ($request->isApp()) {
                $appId = isset($wechat['wechat_app_appid']) ? trim($wechat['wechat_app_appid']) : '';
                $appsecret = isset($wechat['wechat_app_appsecret']) ? trim($wechat['wechat_app_appsecret']) : '';
            } else {
                $appId = isset($wechat['wechat_appid']) ? trim($wechat['wechat_appid']) : '';
                $appsecret = isset($wechat['wechat_appsecret']) ? trim($wechat['wechat_appsecret']) : '';
            }
            
            $this->oauth = new WechatOauth($appId, $appsecret);
        }
        return $this->oauth;
    }

    /**
     * 魔术方法，代理到 WechatOauth
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->getOauth()->{$name}(...$arguments);
    }
}
