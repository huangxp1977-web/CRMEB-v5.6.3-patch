<?php
/**
 * +----------------------------------------------------------------------
 * | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
 * +----------------------------------------------------------------------
 * | Copyright (c) 2016~2023 https://www.crmeb.com All rights reserved.
 * +----------------------------------------------------------------------
 * | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
 * +----------------------------------------------------------------------
 * | Author: CRMEB Team <admin@crmeb.com>
 * | Modified: Refactored for EasyWeChat 6.x compatibility
 * +----------------------------------------------------------------------
 */

namespace crmeb\services\easywechat\miniScheme;

use crmeb\services\easywechat\Application;

/**
 * 小程序 URL Scheme 服务
 * 重构后不再依赖 EasyWeChat 4.x 的 AbstractAPI
 *
 * Class ProgramScheme
 * @package crmeb\services\easywechat\miniScheme
 */
class ProgramScheme
{
    const URL_SCHEME_API = 'https://api.weixin.qq.com/wxa/generatescheme';
    const URL_LINK_API = 'https://api.weixin.qq.com/wxa/generate_urllink';

    /**
     * @var Application
     */
    protected $app;

    /**
     * ProgramScheme constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 获取 URL Scheme
     * @param array $jumpWxa
     * @param int $expireType
     * @param int $expireNum
     * @return array
     */
    public function getUrlScheme($jumpWxa = [], $expireType = -1, $expireNum = 0)
    {
        $params = [];
        if (!empty($jumpWxa)) $params['jump_wxa'] = $jumpWxa;
        if ($expireType != -1) {
            $params['expire_type'] = (int)$expireType;
            $params['is_expire'] = true;
        } else {
            $params['is_expire'] = false;
        }
        if ($expireType == 0) $params['expire_time'] = (int)$expireNum;
        if ($expireType == 1) $params['expire_interval'] = (int)$expireNum;
        return $this->request('POST', self::URL_SCHEME_API, $params);
    }

    /**
     * 获取 URL Link
     * @param array $jumpWxa
     * @return array
     */
    public function getUrlLink($jumpWxa = [])
    {
        $params = [
            'path' => $jumpWxa['path'],
            'query' => $jumpWxa['query'],
            'expire_type' => 1,
            'expire_interval' => 30,
        ];
        return $this->request('POST', self::URL_LINK_API, $params);
    }

    /**
     * 发送 HTTP 请求
     * @param string $method
     * @param string $url
     * @param array $params
     * @return array
     */
    protected function request(string $method, string $url, array $params = []): array
    {
        $accessToken = $this->app->getOfficialAccount()->getAccessToken()->getToken();
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'access_token=' . $accessToken;
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        
        if (strtoupper($method) === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true) ?: [];
    }
}