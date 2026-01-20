<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2023 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// | Modified: EasyWeChat 6.x Compatibility Wrapper
// +----------------------------------------------------------------------

namespace crmeb\services\easywechat;

use EasyWeChat\OfficialAccount\Application as OfficialAccountApplication;
use crmeb\services\easywechat\oauth2\wechat\WechatOauth2Provider;
use crmeb\services\easywechat\v3pay\PayClient;
use crmeb\services\easywechat\orderShipping\OrderClient;
use crmeb\services\easywechat\wechatlive\ProgramProvider as LiveProgramProvider;
use crmeb\services\easywechat\wechatTemplate\ProgramProvider as TemplateProvider;

/**
 * EasyWeChat 6.x Compatibility Wrapper
 * Class Application
 * @package crmeb\services\easywechat
 */
class Application implements \ArrayAccess
{
    protected $officialAccount;
    protected $config;
    protected $services = [];
    protected $factories = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->officialAccount = new OfficialAccountApplication([
            'app_id' => $config['app_id'] ?? '',
            'secret' => $config['secret'] ?? '',
            'token' => $config['token'] ?? '',
            'aes_key' => $config['aes_key'] ?? '',
            'http' => $config['guzzle'] ?? [],
        ]);
        $this->registerCustomProviders();
    }

    protected function registerCustomProviders(): void
    {
        $this->factories['oauth2'] = function () {
            return new WechatOauth2Provider($this);
        };
        $this->factories['wechat_live'] = function () {
            return new LiveProgramProvider($this);
        };
        $this->factories['wechat_template'] = function () {
            return new TemplateProvider($this);
        };
        $this->factories['new_notice'] = function () {
            return (new TemplateProvider($this))->getTemplate();
        };
        $this->factories['v3pay'] = function () {
            return new PayClient(null, $this);
        };
        $this->factories['order_ship'] = function () {
            return new OrderClient($this);
        };
        $this->factories['freepublish'] = function () {
            return new FreePublishServiceWrapper($this->officialAccount);
        };
    }

    public function getOfficialAccount(): OfficialAccountApplication
    {
        return $this->officialAccount;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->services[$offset]) || isset($this->factories[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        if (!isset($this->services[$offset]) && isset($this->factories[$offset])) {
            $this->services[$offset] = $this->factories[$offset]();
        }
        return $this->services[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->services[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->services[$offset]);
    }

    public function __get($name)
    {
        if (isset($this->factories[$name])) {
            if (!isset($this->services[$name])) {
                $this->services[$name] = $this->factories[$name]();
            }
            return $this->services[$name];
        }
        $map = [
            'server' => 'getServer',
            'user' => 'getUserService',
            'material' => 'getMaterialService',
            'material_temporary' => 'getMaterialTemporaryService',
            'staff' => 'getStaffService',
            'menu' => 'getMenuService',
            'qrcode' => 'getQrcodeService',
            'url' => 'getUrlService',
            'oauth' => 'getOauthService',
            'oauth2' => 'getOauth2Service',
            'payment' => 'getPaymentService',
            'js' => 'getJsService',
            'user_tag' => 'getUserTagService',
            'user_group' => 'getUserGroupService',
            'merchant_pay' => 'getMerchantPayService',
            'minipay' => 'getMiniPayService',
            'mini_program' => 'getMiniProgram',
        ];
        if (isset($map[$name])) {
            return $this->{$map[$name]}();
        }
        return null;
    }

    protected function getServer() { return $this->officialAccount->getServer(); }
    protected function getUserService() { return new UserServiceWrapper($this->officialAccount); }
    protected function getMaterialService() { return new MaterialServiceWrapper($this->officialAccount); }
    protected function getMaterialTemporaryService() { return new MaterialTemporaryServiceWrapper($this->officialAccount); }
    protected function getStaffService() { return new StaffServiceWrapper($this->officialAccount); }
    protected function getMenuService() { return new MenuServiceWrapper($this->officialAccount); }
    protected function getQrcodeService() { return new QrcodeServiceWrapper($this->officialAccount); }
    protected function getUrlService() { return new UrlServiceWrapper($this->officialAccount); }
    protected function getOauthService() { return $this->officialAccount->getOAuth(); }
    protected function getOauth2Service() { return $this->factories['oauth2'](); }
    protected function getPaymentService() { return new PaymentServiceWrapper($this); }
    protected function getJsService() { return new JsServiceWrapper($this->officialAccount); }
    protected function getUserTagService() { return new UserTagServiceWrapper($this->officialAccount); }
    protected function getUserGroupService() { return new UserGroupServiceWrapper($this->officialAccount); }
    protected function getMerchantPayService() { return new MerchantPayServiceWrapper($this); }
    protected function getMiniPayService() { return new MiniPayServiceWrapper($this); }
    protected function getMiniProgram() 
    { 
        if (!isset($this->config['mini_program'])) {
            return null;
        }
        return new \EasyWeChat\MiniApp\Application($this->config['mini_program']); 
    }
}

class UserServiceWrapper
{
    protected $client;
    public function __construct(\EasyWeChat\OfficialAccount\Application $app) { $this->client = $app->getClient(); }
    public function get($openid) { return $this->client->get('/cgi-bin/user/info', ['openid' => $openid, 'lang' => 'zh_CN'])->toArray(); }
    public function list($nextOpenId = null) { return $this->client->get('/cgi-bin/user/get', $nextOpenId ? ['next_openid' => $nextOpenId] : [])->toArray(); }
    public function remark($openid, $remark) { return $this->client->postJson('/cgi-bin/user/info/updateremark', ['openid' => $openid, 'remark' => $remark])->toArray(); }
    public function blacklist($nextOpenId = null) { return $this->client->postJson('/cgi-bin/tags/members/getblacklist', $nextOpenId ? ['begin_openid' => $nextOpenId] : [])->toArray(); }
    public function block($openidList) { return $this->client->postJson('/cgi-bin/tags/members/batchblacklist', ['openid_list' => (array)$openidList])->toArray(); }
    public function unblock($openidList) { return $this->client->postJson('/cgi-bin/tags/members/batchunblacklist', ['openid_list' => (array)$openidList])->toArray(); }
}

class MaterialServiceWrapper
{
    protected $client;
    protected $app;
    public function __construct(\EasyWeChat\OfficialAccount\Application $app) { 
        $this->app = $app;
        $this->client = $app->getClient(); 
    }
    public function list($type, $offset = 0, $count = 20) { return $this->client->postJson('/cgi-bin/material/batchget_material', ['type' => $type, 'offset' => $offset, 'count' => $count])->toArray(); }
    public function get($mediaId) { return $this->client->postJson('/cgi-bin/material/get_material', ['media_id' => $mediaId])->toArray(); }
    public function delete($mediaId) { return $this->client->postJson('/cgi-bin/material/del_material', ['media_id' => $mediaId])->toArray(); }
    public function stats() { return $this->client->get('/cgi-bin/material/get_materialcount')->toArray(); }
    
    /**
     * 上传永久图片素材
     * @param string $path 图片路径
     * @return object 返回包含 media_id 和 url 的对象
     */
    public function uploadImage(string $path)
    {
        $response = $this->client->withFile($path, 'media')->post('/cgi-bin/material/add_material', ['type' => 'image']);
        $result = $response->toArray();
        // 返回对象以兼容旧版代码 $material->media_id
        return (object) $result;
    }
    
    /**
     * 上传永久语音素材
     * @param string $path 语音路径
     * @return object 返回包含 media_id 的对象
     */
    public function uploadVoice(string $path)
    {
        $response = $this->client->withFile($path, 'media')->post('/cgi-bin/material/add_material', ['type' => 'voice']);
        $result = $response->toArray();
        return (object) $result;
    }
    
    /**
     * 上传永久视频素材
     * @param string $path 视频路径
     * @param string $title 标题
     * @param string $description 描述
     * @return object 返回包含 media_id 的对象
     */
    public function uploadVideo(string $path, string $title = '', string $description = '')
    {
        $response = $this->client->withFile($path, 'media')->post('/cgi-bin/material/add_material', [
            'type' => 'video',
            'description' => json_encode(['title' => $title, 'introduction' => $description])
        ]);
        $result = $response->toArray();
        return (object) $result;
    }
    
    /**
     * 上传永久缩略图素材
     * @param string $path 图片路径
     * @return object 返回包含 media_id 的对象
     */
    public function uploadThumb(string $path)
    {
        $response = $this->client->withFile($path, 'media')->post('/cgi-bin/material/add_material', ['type' => 'thumb']);
        $result = $response->toArray();
        return (object) $result;
    }
}

class MaterialTemporaryServiceWrapper
{
    protected $client;
    public function __construct(\EasyWeChat\OfficialAccount\Application $app) { $this->client = $app->getClient(); }
    public function get($mediaId) { return $this->client->get('/cgi-bin/media/get', ['media_id' => $mediaId]); }
}

class StaffServiceWrapper
{
    protected $client;
    protected $message;
    protected $to;
    public function __construct(\EasyWeChat\OfficialAccount\Application $app) { $this->client = $app->getClient(); }
    public function message($message) { $this->message = $message; return $this; }
    public function to($openid) { $this->to = $openid; return $this; }
    public function send() {
        $message = $this->message;
        if (is_object($message) && method_exists($message, 'toArray')) { $data = $message->toArray(); }
        elseif (is_array($message)) { $data = $message; }
        else { $data = ['msgtype' => 'text', 'text' => ['content' => (string)$message]]; }
        $data['touser'] = $this->to;
        return $this->client->postJson('/cgi-bin/message/custom/send', $data)->toArray();
    }
}

class MenuServiceWrapper
{
    protected $client;
    public function __construct(\EasyWeChat\OfficialAccount\Application $app) { $this->client = $app->getClient(); }
    public function all() { return $this->client->get('/cgi-bin/get_current_selfmenu_info')->toArray(); }
    public function current() { return $this->all(); }
    public function create(array $buttons) { return $this->client->postJson('/cgi-bin/menu/create', ['button' => $buttons])->toArray(); }
    public function delete() { return $this->client->get('/cgi-bin/menu/delete')->toArray(); }
}

class QrcodeServiceWrapper
{
    protected $client;
    public function __construct(\EasyWeChat\OfficialAccount\Application $app) { $this->client = $app->getClient(); }
    public function temporary($sceneId, $expireSeconds = 2592000) {
        $data = ['expire_seconds' => $expireSeconds, 'action_name' => is_int($sceneId) ? 'QR_SCENE' : 'QR_STR_SCENE', 'action_info' => ['scene' => is_int($sceneId) ? ['scene_id' => $sceneId] : ['scene_str' => $sceneId]]];
        return $this->client->postJson('/cgi-bin/qrcode/create', $data)->toArray();
    }
    public function forever($sceneId) {
        $data = ['action_name' => is_int($sceneId) ? 'QR_LIMIT_SCENE' : 'QR_LIMIT_STR_SCENE', 'action_info' => ['scene' => is_int($sceneId) ? ['scene_id' => $sceneId] : ['scene_str' => $sceneId]]];
        return $this->client->postJson('/cgi-bin/qrcode/create', $data)->toArray();
    }
    public function url($ticket) { return 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($ticket); }
}

class UrlServiceWrapper
{
    protected $client;
    public function __construct(\EasyWeChat\OfficialAccount\Application $app) { $this->client = $app->getClient(); }
    public function shorten($longUrl) { return $this->client->postJson('/cgi-bin/shorturl', ['action' => 'long2short', 'long_url' => $longUrl])->toArray(); }
}

class JsServiceWrapper
{
    protected $app;
    protected $url;
    public function __construct(\EasyWeChat\OfficialAccount\Application $app) { $this->app = $app; }
    public function setUrl($url) { $this->url = $url; return $this; }
    public function config(array $apis = [], bool $debug = false, bool $beta = false, array $openTagList = []) {
        $utils = $this->app->getUtils();
        $url = $this->url ?: ($_SERVER['HTTP_REFERER'] ?? $_SERVER['REQUEST_URI'] ?? '');
        return $utils->buildJsSdkConfig($url, $apis, $openTagList, $debug, $beta);
    }
}

class UserTagServiceWrapper
{
    protected $client;
    public function __construct(\EasyWeChat\OfficialAccount\Application $app) { $this->client = $app->getClient(); }
    public function list() { return $this->client->get('/cgi-bin/tags/get')->toArray(); }
    public function create($name) { return $this->client->postJson('/cgi-bin/tags/create', ['tag' => ['name' => $name]])->toArray(); }
    public function update($tagId, $name) { return $this->client->postJson('/cgi-bin/tags/update', ['tag' => ['id' => $tagId, 'name' => $name]])->toArray(); }
    public function delete($tagId) { return $this->client->postJson('/cgi-bin/tags/delete', ['tag' => ['id' => $tagId]])->toArray(); }
    public function userTags($openid) { return $this->client->postJson('/cgi-bin/tags/getidlist', ['openid' => $openid])->toArray(); }
    public function usersOfTag($tagId, $nextOpenid = '') { return $this->client->postJson('/cgi-bin/user/tag/get', ['tagid' => $tagId, 'next_openid' => $nextOpenid])->toArray(); }
    public function tagUsers(array $openids, $tagId) { return $this->client->postJson('/cgi-bin/tags/members/batchtagging', ['openid_list' => $openids, 'tagid' => $tagId])->toArray(); }
    public function untagUsers(array $openids, $tagId) { return $this->client->postJson('/cgi-bin/tags/members/batchuntagging', ['openid_list' => $openids, 'tagid' => $tagId])->toArray(); }
}

class UserGroupServiceWrapper
{
    protected $client;
    public function __construct(\EasyWeChat\OfficialAccount\Application $app) { $this->client = $app->getClient(); }
    public function list() { return $this->client->get('/cgi-bin/groups/get')->toArray(); }
}

class TemplateServiceWrapper
{
    protected $client;
    public function __construct(\EasyWeChat\OfficialAccount\Application $app) { $this->client = $app->getClient(); }
    public function addTemplate($shortId, $keywords = []) { $data = ['template_id_short' => $shortId]; if ($keywords) { $data['keyword_name_list'] = $keywords; } return $this->client->postJson('/cgi-bin/template/api_add_template', $data)->toArray(); }
    public function getPrivateTemplates() { return $this->client->get('/cgi-bin/template/get_all_private_template')->toArray(); }
    public function deletePrivateTemplate($templateId) { return $this->client->postJson('/cgi-bin/template/del_private_template', ['template_id' => $templateId])->toArray(); }
}

class PaymentServiceWrapper
{
    protected $app;
    public function __construct(Application $app) { $this->app = $app; }
    
    public function prepare($order) 
    { 
        $type = strtolower($order['trade_type'] ?? 'jsapi');
        if ($type === 'mweb') $type = 'h5';
        
        // V3Pay Client expects: $type, $appid, $outTradeNo, $total, $description, $attach, $payer
        // Order array: out_trade_no, total_fee (cents), attach, body, detail, openid
        
        $appid = $this->app->getConfig()['wechat']['appid'];
        $outTradeNo = $order['out_trade_no'];
        // V3 Client expects total in YUAN (string), it converts to cents internally via bcmul(total, 100).
        // BUT WechatService::paymentOrder ALREADY converted total_fee to cents (bcmul(..., 100, 0)).
        // So we must convert back to YUAN for PayClient or PayClient logic is specific?
        // Let's check PayClient: $totalFee = (int)bcmul($total, '100');
        // If we pass cents here, it will be multiplied by 100 again!
        // We have to pass YUAN.
        $totalYuan = bcdiv($order['total_fee'], '100', 2);
        
        $description = $order['body'];
        $attach = $order['attach'] ?? '';
        $payer = isset($order['openid']) ? ['openid' => $order['openid']] : [];
        
        return $this->app->v3pay->pay($type, $appid, $outTradeNo, $totalYuan, $description, $attach, $payer);
    }
    
    public function configForJSSDKPayment($prepayId) 
    { 
        $appid = $this->app->getConfig()['wechat']['appid'];
        return $this->app->v3pay->configForJSSDKPayment($appid, $prepayId); 
    }
    
    public function configForAppPayment($prepayId) 
    { 
        return $this->app->v3pay->configForAppPayment($prepayId); 
    }
    
    public function refund($orderNo, $refundNo, $totalFee, $refundFee, $opUserId = null, $type = 'out_trade_no', $refundAccount = '', $reason = '') 
    { 
        // V3 PayClient refund signature: refund(string $outTradeNo, array $options = [])
        // converting params to options
        // totalFee and refundFee are in CENTS in WechatService. PayClient refund expects options['pay_price'] in YUAN (it multiplies by 100).
        // Wait, PayClient::refund:
        // $totalFee = floatval(bcmul($options['pay_price'], 100, 0));
        // So passed options must be in YUAN.
        
        $options = [
            'pay_price' => bcdiv($totalFee, '100', 2),
            'refund_price' => bcdiv($refundFee, '100', 2),
            'desc' => $reason,
            'refund_id' => $refundNo,
            'refund_account' => $refundAccount // V3 client uses this
        ];
        return $this->app->v3pay->refund($orderNo, $options); 
    }
    
    public function refundByTransactionId($transactionId, $refundNo, $totalFee, $refundFee, $opUserId = null, $refundAccount = '', $reason = '') 
    { 
         return $this->refund($transactionId, $refundNo, $totalFee, $refundFee, $opUserId, 'transaction_id', $refundAccount, $reason);
    }
    
    public function handleNotify(callable $callback) 
    { 
        return $this->app->v3pay->handleNotify($callback); 
    }
}

class MerchantPayServiceWrapper
{
    protected $app;
    public function __construct(Application $app) { $this->app = $app; }
    
    /**
     * 企业付款到零钱 (V3 商家转账)
     * @param array $params 包含 partner_trade_no, openid, amount, desc 等
     * @return array
     */
    public function send(array $params) 
    { 
        // V3 API 商家转账需要更多参数
        $orderId = $params['partner_trade_no'] ?? '';
        $openid = $params['openid'] ?? '';
        $amount = $params['amount'] ?? 0; // 已经是分
        $desc = $params['desc'] ?? '企业付款';
        
        // V3 商家转账必需参数
        $transferSceneId = '1000'; // 默认场景ID: 现金营销-现金红包
        $userName = ''; // 金额小于2000时不需要
        $notifyUrl = sys_config('site_url') . '/api/pay/notify/routine'; // 回调通知地址
        $userRecvPerception = $desc; // 收款用户感知信息
        $transferSceneReportInfos = []; // 场景报备信息
        
        return $this->app->v3pay->transferBills(
            $orderId,
            $transferSceneId,
            $openid,
            $userName,
            (int)$amount, // 已经是分
            $desc,
            $notifyUrl,
            $userRecvPerception,
            $transferSceneReportInfos
        );
    }
}

class MiniPayServiceWrapper
{
    protected $app;
    public function __construct(Application $app) { $this->app = $app; }
    
    /**
     * 新小程序支付下单
     * @param array $order 订单数组，包含 openid, out_trade_no, total_fee(分), attach, body, detail, trade_type
     * @return array 包含 prepay_id 的响应
     */
    public function createorder($order) 
    { 
        $type = strtolower($order['trade_type'] ?? 'jsapi');
        
        // 获取小程序 appid
        $appid = $this->app->getConfig()['mini_program']['app_id'] 
              ?? $this->app->getConfig()['miniprog']['appid'] 
              ?? $this->app->getConfig()['app_id'] 
              ?? '';
        
        if (empty($appid)) {
            throw new \RuntimeException('Mini program appid not configured');
        }
        
        $outTradeNo = $order['out_trade_no'];
        // total_fee 已经是分，需要转换回元给 V3 PayClient
        $totalYuan = bcdiv($order['total_fee'], '100', 2);
        $description = $order['body'] ?? '';
        $attach = $order['attach'] ?? '';
        $payer = isset($order['openid']) ? ['openid' => $order['openid']] : [];
        
        return $this->app->v3pay->pay($type, $appid, $outTradeNo, $totalYuan, $description, $attach, $payer);
    }
    
    /**
     * 新小程序支付退款
     * @param array $order 退款参数
     * @return array 退款响应
     */
    public function refundorder($order)
    {
        return $this->app->v3pay->refund($order['out_trade_no'] ?? '', $order);
    }
}

class FreePublishServiceWrapper
{
    protected $client;
    public function __construct(\EasyWeChat\OfficialAccount\Application $app) { $this->client = $app->getClient(); }
    public function list(int $offset = 0, int $count = 20) { return $this->client->postJson('/cgi-bin/freepublish/batchget', ['offset' => $offset, 'count' => $count, 'no_content' => 0])->toArray(); }
    public function get(string $articleId) { return $this->client->postJson('/cgi-bin/freepublish/getarticle', ['article_id' => $articleId])->toArray(); }
}
