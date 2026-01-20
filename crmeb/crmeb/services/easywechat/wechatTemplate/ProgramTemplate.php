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

namespace crmeb\services\easywechat\wechatTemplate;

use crmeb\services\easywechat\Application;

/**
 * 公众号模板消息
 * 重构后不再依赖 EasyWeChat 4.x 的 AbstractAPI
 *
 * Class ProgramTemplate
 * @package crmeb\services\easywechat\wechatTemplate
 */
class ProgramTemplate
{
    /**
     * Default color.
     * @var string
     */
    protected $defaultColor = '#173177';

    /**
     * Attributes.
     * @var array
     */
    protected $message = [
        'touser' => '',
        'template_id' => '',
        'url' => '',
        'data' => [],
        'miniprogram' => '',
    ];

    /**
     * Required attributes.
     * @var array
     */
    protected $required = ['touser', 'template_id'];

    /**
     * Message backup.
     * @var array
     */
    protected $messageBackup;

    /**
     * @var Application
     */
    protected $app;

    const API_SEND_NOTICE = 'https://api.weixin.qq.com/cgi-bin/message/template/send';
    const API_SET_INDUSTRY = 'https://api.weixin.qq.com/cgi-bin/template/api_set_industry';
    const API_ADD_TEMPLATE = 'https://api.weixin.qq.com/cgi-bin/template/api_add_template';
    const API_GET_INDUSTRY = 'https://api.weixin.qq.com/cgi-bin/template/get_industry';
    const API_GET_ALL_PRIVATE_TEMPLATE = 'https://api.weixin.qq.com/cgi-bin/template/get_all_private_template';
    const API_DEL_PRIVATE_TEMPLATE = 'https://api.weixin.qq.com/cgi-bin/template/del_private_template';

    /**
     * ProgramTemplate constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->messageBackup = $this->message;
    }

    /**
     * Set default color.
     * @param string $color
     * @return $this
     */
    public function defaultColor($color)
    {
        $this->defaultColor = $color;
        return $this;
    }

    /**
     * Set miniprogram.
     * @param array $data
     * @return $this
     */
    public function setMiniprogram($data)
    {
        $this->message['miniprogram'] = $data;
        return $this;
    }

    /**
     * Set industry.
     * @param int $industryOne
     * @param int $industryTwo
     * @return array
     */
    public function setIndustry($industryOne, $industryTwo)
    {
        $params = [
            'industry_id1' => $industryOne,
            'industry_id2' => $industryTwo,
        ];
        return $this->request('POST', self::API_SET_INDUSTRY, $params);
    }

    /**
     * Get industry.
     * @return array
     */
    public function getIndustry()
    {
        return $this->request('POST', self::API_GET_INDUSTRY, []);
    }

    /**
     * Add a template and get template ID.
     * @param string $shortId
     * @param array $content
     * @return array
     */
    public function addTemplate($shortId, $content)
    {
        $params = ['template_id_short' => $shortId, 'keyword_name_list' => $content];
        return $this->request('POST', self::API_ADD_TEMPLATE, $params);
    }

    /**
     * Get private templates.
     * @return array
     */
    public function getPrivateTemplates()
    {
        return $this->request('POST', self::API_GET_ALL_PRIVATE_TEMPLATE, []);
    }

    /**
     * Delete private template.
     * @param string $templateId
     * @return array
     */
    public function deletePrivateTemplate($templateId)
    {
        $params = ['template_id' => $templateId];
        return $this->request('POST', self::API_DEL_PRIVATE_TEMPLATE, $params);
    }

    /**
     * Send a notice message.
     * @param array $data
     * @return array
     */
    public function send($data = [])
    {
        $params = array_merge($this->message, $data);

        foreach ($params as $key => $value) {
            if (in_array($key, $this->required, true) && empty($value) && empty($this->message[$key])) {
                throw new \InvalidArgumentException("Attribute '$key' can not be empty!");
            }
            $params[$key] = empty($value) ? $this->message[$key] : $value;
        }

        $params['data'] = $this->formatData($params['data']);
        $this->message = $this->messageBackup;

        return $this->request('POST', static::API_SEND_NOTICE, $params);
    }

    /**
     * Magic access.
     * @param string $method
     * @param array $args
     * @return $this
     */
    public function __call($method, $args)
    {
        $map = [
            'template' => 'template_id',
            'templateId' => 'template_id',
            'uses' => 'template_id',
            'to' => 'touser',
            'receiver' => 'touser',
            'url' => 'url',
            'link' => 'url',
            'data' => 'data',
            'with' => 'data',
            'formId' => 'form_id',
            'prepayId' => 'form_id',
        ];

        if (0 === stripos($method, 'with') && strlen($method) > 4) {
            $method = lcfirst(substr($method, 4));
        }

        if (0 === stripos($method, 'and')) {
            $method = lcfirst(substr($method, 3));
        }

        if (isset($map[$method])) {
            $this->message[$map[$method]] = array_shift($args);
        }

        return $this;
    }

    /**
     * Format template data.
     * @param array $data
     * @return array
     */
    protected function formatData($data)
    {
        $return = [];

        foreach ($data as $key => $item) {
            if (is_scalar($item)) {
                $value = $item;
                $color = $this->defaultColor;
            } elseif (is_array($item) && !empty($item)) {
                if (isset($item['value'])) {
                    $value = strval($item['value']);
                    $color = empty($item['color']) ? $this->defaultColor : strval($item['color']);
                } elseif (count($item) < 2) {
                    $value = array_shift($item);
                    $color = $this->defaultColor;
                } else {
                    list($value, $color) = $item;
                }
            } else {
                $value = 'error data item.';
                $color = $this->defaultColor;
            }

            $return[$key] = [
                'value' => $value,
                'color' => $color,
            ];
        }

        return $return;
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
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true) ?: [];
    }
}