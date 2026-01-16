<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2023 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services\sms\storage;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\QuerySmsTemplateListRequest;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use crmeb\exceptions\ApiException;
use crmeb\services\sms\BaseSms;
use Darabonba\OpenApi\Models\Config as AliConfig;
use think\exception\ValidateException;
use think\facade\Config;


/**
 * Class Aliyun
 * @package crmeb\services\sms\storage
 */
class Aliyun extends BaseSms
{
    protected $AccessKeyId = '';
    protected $AccessKeySecret = '';
    protected $SignName = '';

    /**
     * @param array $config
     * @return mixed|void
     */
    protected function initialize(array $config = [])
    {
        parent::initialize($config);
        $this->SignName = $config['aliyun_SignName'] ?? '';
        $this->AccessKeyId = $config['aliyun_AccessKeyId'] ?? '';
        $this->AccessKeySecret = $config['aliyun_AccessKeySecret'] ?? '';
    }

    /**
     * 发送短信
     * @param string $phone
     * @param string $templateId
     * @param array $data
     * @return array|bool|mixed
     */
    public function send(string $phone, string $templateId, array $data = [])
    {
        if (empty($phone)) {
            return $this->setError('电话号码不能为空');
        }

        $config = new AliConfig([
            "accessKeyId" => $this->AccessKeyId,
            "accessKeySecret" => $this->AccessKeySecret
        ]);
        // 访问的域名
        $config->endpoint = "dysmsapi.aliyuncs.com";
        $client = new Dysmsapi($config);

        if (!$templateId) throw new ApiException('模板不存在：' . $templateId);

        $sendSmsRequest = new SendSmsRequest([
            "phoneNumbers" => $phone,
            "signName" => $this->SignName,
            "templateCode" => $templateId,
            "templateParam" => json_encode($data),
        ]);
        $runtime = new RuntimeOptions([]);
        try {
            // 复制代码运行请自行打印 API 的返回值
            $resp = $client->sendSmsWithOptions($sendSmsRequest, $runtime);
            if (isset($resp) && $resp->body->code !== 'OK') {
                throw new ApiException('【阿里云平台错误提示】：' . $resp->body->message);
            }
            return [
                'id' => $resp->body->requestId,
                'content' => json_encode($data),
                'template' => $templateId,
            ];
        } catch (\Exception $e) {
            throw new ApiException('【阿里云平台错误提示】：' . $e->getMessage());
        }
    }

    public function open()
    {
    }

    public function modify(string $sign = null, string $phone = '', string $code = '')
    {
    }

    public function info()
    {
    }

    public function temps(int $page = 0, int $limit = 10, int $type = 1)
    {
    }

    public function apply(string $title, string $content, int $type)
    {
    }

    public function applys(int $tempType, int $page, int $limit)
    {
    }

    public function record($record_id)
    {
    }

    /**
     * 获取短信模板列表
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getTemplateList(int $page = 1, int $pageSize = 50): array
    {
        $config = new AliConfig([
            "accessKeyId" => $this->AccessKeyId,
            "accessKeySecret" => $this->AccessKeySecret
        ]);
        $config->endpoint = "dysmsapi.aliyuncs.com";
        $client = new Dysmsapi($config);

        $request = new QuerySmsTemplateListRequest([
            "pageIndex" => $page,
            "pageSize" => $pageSize,
        ]);

        try {
            $runtime = new RuntimeOptions([]);
            $resp = $client->querySmsTemplateListWithOptions($request, $runtime);

            // 返回审核通过的模板列表
            $templates = [];
            if (isset($resp->body->smsTemplateList)) {
                foreach ($resp->body->smsTemplateList as $tpl) {
                    if ($tpl->auditStatus == 'AUDIT_STATE_PASS') {
                        $templates[] = [
                            'code' => $tpl->templateCode,
                            'name' => $tpl->templateName,
                            'content' => preg_replace('/\${(.*?)}/', '{\$$1}', $tpl->templateContent),
                        ];
                    }
                }
            }
            return $templates;
        } catch (\Exception $e) {
            throw new ApiException('【阿里云平台错误提示】：' . $e->getMessage());
        }
    }

    /**
     * 获取短信签名列表
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function getSignList(int $page = 1, int $pageSize = 50): array
    {
        $config = new AliConfig([
            "accessKeyId" => $this->AccessKeyId,
            "accessKeySecret" => $this->AccessKeySecret
        ]);
        $config->endpoint = "dysmsapi.aliyuncs.com";
        $client = new Dysmsapi($config);

        $request = new \AlibabaCloud\SDK\Dysmsapi\V20170525\Models\QuerySmsSignListRequest([
            "pageIndex" => $page,
            "pageSize" => $pageSize,
        ]);

        try {
            $runtime = new RuntimeOptions([]);
            $resp = $client->querySmsSignListWithOptions($request, $runtime);

            // 返回审核通过的签名列表
            $signs = [];
            if (isset($resp->body->smsSignList)) {
                foreach ($resp->body->smsSignList as $sign) {
                    if ($sign->auditStatus == 'AUDIT_STATE_PASS') {
                        $signs[] = [
                            'name' => $sign->signName,
                        ];
                    }
                }
            }
            return $signs;
        } catch (\Exception $e) {
            throw new ApiException('【阿里云平台错误提示】：' . $e->getMessage());
        }
    }
}
