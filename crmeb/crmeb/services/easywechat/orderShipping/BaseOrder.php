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

namespace crmeb\services\easywechat\orderShipping;

use crmeb\exceptions\AdminException;
use crmeb\services\easywechat\Application;

/**
 * 订单发货服务
 * 重构后不再依赖 EasyWeChat 4.x 的 AbstractAPI
 *
 * Class BaseOrder
 * @package crmeb\services\easywechat\orderShipping
 */
class BaseOrder
{
    /**
     * @var array
     */
    public $config;

    /**
     * @var Application
     */
    public $app;

    const BASE_API = 'https://api.weixin.qq.com/';

    const ORDER = 'wxa/sec/order/';
    const EXPRESS = 'cgi-bin/express/delivery/open_msg/';

    const PATH = '/pages/goods/order_details/index';

    /**
     * BaseOrder constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app->getConfig();
    }

    /**
     * 处理结果
     * @param array $result
     * @return array
     */
    private function resultHandle(array $result)
    {
        if (empty($result)) {
            throw new AdminException('微信接口返回异常');
        }
        if ($result['errcode'] == 0) {
            return $result;
        } else {
            throw new AdminException("微信接口异常：code = {$result['errcode']} msg = {$result['errmsg']}");
        }
    }

    /**
     * 发货
     * @param array $params
     * @return array
     */
    public function shipping($params)
    {
        return $this->resultHandle($this->request('POST', self::BASE_API . self::ORDER . 'upload_shipping_info', $params));
    }

    /**
     * 合单
     * @param array $params
     * @return array
     */
    public function combinedShipping($params)
    {
        return $this->resultHandle($this->request('POST', self::BASE_API . self::ORDER . 'upload_combined_shipping_info', $params));
    }

    /**
     * 签收消息提醒
     * @param array $params
     * @return array
     */
    public function notifyConfirm($params)
    {
        return $this->resultHandle($this->request('POST', self::BASE_API . self::ORDER . 'notify_confirm_receive', $params));
    }

    /**
     * 查询小程序是否已开通发货信息管理服务
     * @return array
     */
    public function isManaged()
    {
        $params = [
            'appid' => $this->config['config']['mini_program']['app_id'] ?? $this->config['app_id'] ?? ''
        ];
        return $this->resultHandle($this->request('POST', self::BASE_API . self::ORDER . 'is_trade_managed', $params));
    }

    /**
     * 设置跳转连接
     * @param string $path
     * @return array
     */
    public function setMesJumpPath($path)
    {
        $params = [
            'path' => $path
        ];
        return $this->resultHandle($this->request('POST', self::BASE_API . self::ORDER . 'set_msg_jump_path', $params));
    }

    /**
     * 获取运力id列表
     * @return array
     */
    public function getDeliveryList()
    {
        return $this->resultHandle($this->request('POST', self::BASE_API . self::EXPRESS . 'get_delivery_list', []));
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
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        
        if (strtoupper($method) === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true) ?: [];
    }
}
