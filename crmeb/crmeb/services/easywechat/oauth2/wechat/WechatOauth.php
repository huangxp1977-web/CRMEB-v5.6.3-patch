<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2023 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// | Modified: Refactored for EasyWeChat 6.x compatibility
// +----------------------------------------------------------------------

namespace crmeb\services\easywechat\oauth2\wechat;

use Symfony\Component\HttpFoundation\Request;

/**
 * 网页授权 OAuth
 * 重构后不再依赖 EasyWeChat 4.x 的 AbstractAPI
 *
 * Class WechatOauth
 * @package crmeb\services\easywechat\oauth\wechat
 */
class WechatOauth
{
    /**
     * 通过code获取网页授权access_token
     */
    const API_OAUTH_ACCESS_TOKEN = 'https://api.weixin.qq.com/sns/oauth2/access_token';

    /**
     * 检验授权凭证（access_token）是否有效
     */
    const API_OAUTH_CHECK_TOKEN = 'https://api.weixin.qq.com/sns/auth';

    /**
     * 刷新access_token
     */
    const API_OAUTH_REFRESH_TOKEN = 'https://api.weixin.qq.com/sns/oauth2/refresh_token';

    /**
     * 获取用户信息
     */
    const API_OAUTH_GET_USER_INFO = 'https://api.weixin.qq.com/sns/userinfo';

    /**
     * App ID.
     * @var string
     */
    protected $appId;

    /**
     * App secret.
     * @var string
     */
    protected $secret;

    /**
     * @var string
     */
    protected $openid;

    /**
     * @var Request
     */
    protected $request;

    /**
     * Response Json key name.
     * @var string
     */
    protected $tokenJsonKey = 'access_token';

    /**
     * Response Json key name.
     * @var string
     */
    protected $refreshTokenJsonKey = 'refresh_token';

    /**
     * Cache key prefix.
     * @var string
     */
    protected $prefix = 'easywechat.common.oauth.access_token.';

    /**
     * @var array 缓存的 token 数据
     */
    protected $cachedTokens = [];

    /**
     * WechatOauth constructor.
     * @param string $appId
     * @param string $appSecret
     */
    public function __construct(string $appId, string $appSecret)
    {
        $this->appId = $appId;
        $this->secret = $appSecret;
    }

    /**
     * @param Request $request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * 获取code
     * @return mixed
     */
    public function getCode()
    {
        return $this->request->get('code');
    }

    /**
     * 授权获取token
     * @param string $code
     * @return array
     */
    public function oauth(string $code = '')
    {
        $params = [
            'appid' => $this->appId,
            'secret' => $this->secret,
            'code' => $code ?: $this->getCode(),
            'grant_type' => 'authorization_code',
        ];

        $token = $this->httpGet(self::API_OAUTH_ACCESS_TOKEN, $params);

        if (empty($token[$this->tokenJsonKey])) {
            throw new \RuntimeException('Request AccessToken fail. response: ' . json_encode($token, JSON_UNESCAPED_UNICODE));
        }
        $this->setCache($token);

        return $token;
    }

    /**
     * 刷新token
     * @param string $refresh_token
     * @return array
     */
    public function refreshToken(string $refresh_token)
    {
        $params = [
            'appid' => $this->appId,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token',
        ];

        $token = $this->httpGet(self::API_OAUTH_REFRESH_TOKEN, $params);

        if (empty($token[$this->tokenJsonKey])) {
            throw new \RuntimeException('Request AccessToken fail. response: ' . json_encode($token, JSON_UNESCAPED_UNICODE));
        }
        $this->setCache($token);

        return $token;
    }

    /**
     * 获取用户信息
     * @param string $openId
     * @param string $accessToken
     * @param string $lang
     * @return array
     */
    public function getUserInfo(string $openId, string $accessToken = '', string $lang = 'zh_CN')
    {
        $this->openid = $openId;
        $params = [
            'openid' => $openId,
            'access_token' => $accessToken ?: $this->getToken(),
            'lang' => $lang,
        ];
        return $this->httpGet(self::API_OAUTH_GET_USER_INFO, $params);
    }

    /**
     * 获取token
     * @param bool $forceRefresh
     * @return string
     */
    public function getToken($forceRefresh = false)
    {
        $cacheKey = $this->prefix . $this->tokenJsonKey . $this->openid;
        
        if (!$forceRefresh && isset($this->cachedTokens[$cacheKey])) {
            return $this->cachedTokens[$cacheKey];
        }

        $refreshCacheKey = $this->prefix . $this->refreshTokenJsonKey . $this->openid;
        if (isset($this->cachedTokens[$refreshCacheKey])) {
            $token = $this->refreshToken($this->cachedTokens[$refreshCacheKey]);
            return $token[$this->tokenJsonKey] ?? '';
        }
        
        return '';
    }

    /**
     * 保存token信息
     * @param array $token
     * @return bool
     */
    public function setCache($token)
    {
        $cacheKey = $this->prefix;
        $this->cachedTokens[$cacheKey . $this->tokenJsonKey . $token['openid']] = $token[$this->tokenJsonKey];
        $this->cachedTokens[$cacheKey . $this->refreshTokenJsonKey . $token['openid']] = $token[$this->refreshTokenJsonKey];
        return true;
    }

    /**
     * 发送 HTTP GET 请求
     * @param string $url
     * @param array $params
     * @return array
     */
    protected function httpGet(string $url, array $params = []): array
    {
        $url .= '?' . http_build_query($params);
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true) ?: [];
    }
}
