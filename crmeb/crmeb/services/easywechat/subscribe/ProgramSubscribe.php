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

namespace crmeb\services\easywechat\subscribe;

use crmeb\services\easywechat\Application;

/**
 * 小程序订阅消息
 * 重构后不再依赖 EasyWeChat 4.x 的 AbstractAPI
 *
 * Class ProgramSubscribe
 * @package crmeb\utils
 */
class ProgramSubscribe
{
    /**
     * 添加模板接口
     */
    const API_SET_TEMPLATE_ADD = 'https://api.weixin.qq.com/wxaapi/newtmpl/addtemplate';

    /**
     * 删除模板消息接口
     */
    const API_SET_TEMPLATE_DEL = 'https://api.weixin.qq.com/wxaapi/newtmpl/deltemplate';

    /**
     * 获取模板消息列表
     */
    const API_GET_TEMPLATE_LIST = 'https://api.weixin.qq.com/wxaapi/newtmpl/gettemplate';

    /**
     * 获取模板消息分类
     */
    const API_GET_TEMPLATE_CATE = 'https://api.weixin.qq.com/wxaapi/newtmpl/getcategory';

    /**
     * 获取模板消息关键字
     */
    const API_GET_TEMPLATE_KEYWORKS = 'https://api.weixin.qq.com/wxaapi/newtmpl/getpubtemplatekeywords';

    /**
     * 获取公共模板
     */
    const API_GET_PUBLIC_TEMPLATE = 'https://api.weixin.qq.com/wxaapi/newtmpl/getpubtemplatetitles';

    /**
     * 发送模板消息
     */
    const API_SUBSCRIBE_SEND = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send';

    /**
     * @var Application
     */
    protected $app;

    /**
     * Attributes
     * @var array
     */
    protected $message = [
        'touser' => '',
        'template_id' => '',
        'page' => '',
        'data' => [],
    ];

    /**
     * Message backup.
     * @var array
     */
    protected $messageBackup;

    protected $required = ['template_id', 'touser'];

    /**
     * ProgramSubscribe constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->messageBackup = $this->message;
    }

    /**
     * 获取当前拥有的模板列表
     * @return array
     */
    public function getTemplateList()
    {
        return $this->request('GET', self::API_GET_TEMPLATE_LIST);
    }

    /**
     * 获取公众模板列表
     * @param string $ids
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function getPublicTemplateList(string $ids, int $start = 0, int $limit = 10)
    {
        $params = [
            'ids' => $ids,
            'start' => $start,
            'limit' => $limit
        ];
        return $this->request('GET', self::API_GET_PUBLIC_TEMPLATE, $params);
    }

    /**
     * 获取模板分类
     * @return array
     */
    public function getTemplateCate()
    {
        return $this->request('GET', self::API_GET_TEMPLATE_CATE);
    }

    /**
     * 获取模板标题下的关键词列表
     * @param string $tid
     * @return array
     */
    public function getPublicTemplateKeywords(string $tid)
    {
        $params = [
            'tid' => $tid
        ];
        return $this->request('GET', self::API_GET_TEMPLATE_KEYWORKS, $params);
    }

    /**
     * 添加订阅模板消息
     * @param string $tid
     * @param array $kidList
     * @param string $sceneDesc
     * @return array
     */
    public function addTemplate(string $tid, array $kidList, string $sceneDesc = '')
    {
        $params = [
            'tid' => $tid,
            'kidList' => $kidList,
            'sceneDesc' => $sceneDesc,
        ];
        return $this->request('POST', self::API_SET_TEMPLATE_ADD, $params);
    }

    /**
     * 删除模板消息
     * @param string $priTmplId
     * @return array
     */
    public function delTemplate(string $priTmplId)
    {
        $params = [
            'priTmplId' => $priTmplId
        ];
        return $this->request('POST', self::API_SET_TEMPLATE_DEL, $params);
    }

    /**
     * 发送订阅消息
     * @param array $data
     * @return array
     */
    public function send(array $data = [])
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

        return $this->request('POST', self::API_SUBSCRIBE_SEND, $params);
    }

    /**
     * 设置订阅消息发送data
     * @param array $data
     * @return array
     */
    protected function formatData(array $data)
    {
        $return = [];
        foreach ($data as $key => $item) {
            if (is_scalar($item)) {
                $value = $item;
            } elseif (is_array($item) && !empty($item)) {
                if (isset($item['value'])) {
                    $value = strval($item['value']);
                } elseif (count($item) < 2) {
                    $value = array_shift($item);
                } else {
                    [$value] = $item;
                }
            } else {
                $value = 'error data item.';
            }
            $return[$key] = ['value' => $value];
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
        
        if (strtoupper($method) === 'GET') {
            $params['access_token'] = $accessToken;
            $url .= '?' . http_build_query($params);
            $params = [];
        } else {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'access_token=' . $accessToken;
        }
        
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
            'url' => 'page',
            'link' => 'page',
            'data' => 'data',
            'with' => 'data',
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
}
