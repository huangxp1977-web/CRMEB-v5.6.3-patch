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

namespace crmeb\services\easywechat\miniPayment;

use crmeb\services\easywechat\Application;

/**
 * 小程序支付客户端
 * 重构后不再依赖 EasyWeChat 4.x 的 AbstractAPI
 *
 * Class WeChatClient
 * @package crmeb\services\easywechat\miniPayment
 */
class WeChatClient
{
    private $expire_time = 7000;

    /**
     * 创建订单 支付
     */
    const API_SET_CREATE_ORDER = 'https://api.weixin.qq.com/shop/pay/createorder';
    
    /**
     * 退款
     */
    const API_SET_REFUND_ORDER = 'https://api.weixin.qq.com/shop/pay/refundorder';

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var array
     */
    protected $merchant;

    /**
     * WeChatClient constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $config = $app->getConfig();
        $this->merchant = [
            'merchant_id' => $config['payment']['merchant_id'] ?? $config['v3_payment']['mchid'] ?? '',
        ];
    }

    /**
     * 支付
     * @param array $order
     * @return mixed
     */
    public function createorder($order)
    {
        $params = [
            'openid' => $order['openid'],    // 支付者的openid
            'combine_trade_no' => $order['out_trade_no'],  // 商家合单支付总交易单号
            'expire_time' => time() + $this->expire_time,
            'sub_orders' => [
                [
                    'mchid' => $this->merchant['merchant_id'],
                    'amount' => (int)$order['total_fee'],
                    'trade_no' => $order['out_trade_no'],
                    'description' => $order['body']
                ]
            ]
        ];
        return $this->request('POST', self::API_SET_CREATE_ORDER, $params);
    }

    /**
     * 退款
     * @param array $order
     * @return mixed
     */
    public function refundorder(array $order)
    {
        $params = [
            'openid' => $order['openid'],
            'mchid' => $this->merchant['merchant_id'],
            'trade_no' => $order['trade_no'],
            'transaction_id' => $order['transaction_id'],
            'refund_no' => $order['refund_no'],
            'total_amount' => $order['total_amount'],
            'refund_amount' => $order['refund_amount'],
        ];
        return $this->request('POST', self::API_SET_REFUND_ORDER, $params);
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
        // 获取 access_token
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