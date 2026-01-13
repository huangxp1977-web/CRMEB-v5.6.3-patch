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
 * EasyWeChat 6.x 兼容性包装器
 * 提供与 4.x API 兼容的接口，内部使用 6.x 实现
 * 不再依赖 Pimple\Container，使用 ArrayAccess 接口代替
 *
 * Class Application
 * @package crmeb\services\easywechat
 * @property-read \EasyWeChat\OfficialAccount\Server $server
 * @property-read object $user
 * @property-read object $material
 * @property-read object $material_temporary
 * @property-read object $staff
 * @property-read object $menu
 * @property-read object $qrcode
 * @property-read object $url
 * @property-read object $oauth
 * @property-read WechatOauth2Provider $oauth2
 * @property-read object $payment
 * @property-read object $js
 * @property-read object $user_tag
 * @property-read object $user_group
 * @property-read object $merchant_pay
 * @property-read object $new_notice
 * @property-read object $minipay
 * @property-read LiveProgramProvider $wechat_live
 * @property-read PayClient $v3pay
 * @property-read OrderClient $order_ship
 */
class Application implements \ArrayAccess
{
    /**
     * @var OfficialAccountApplication
     */
    protected $officialAccount;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var array 服务容器
     */
    protected $services = [];

    /**
     * @var array 服务工厂
     */
    protected $factories = [];

    /**
     * Application constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        // 创建 EasyWeChat 6.x OfficialAccount 实例
        $this->officialAccount = new OfficialAccountApplication([
            'app_id' => $config['app_id'] ?? '',
            'secret' => $config['secret'] ?? '',
            'token' => $config['token'] ?? '',
            'aes_key' => $config['aes_key'] ?? '',
            'http' => $config['guzzle'] ?? [],
        ]);

        // 注册自定义服务提供者
        $this->registerCustomProviders();
    }

    /**
     * 注册 CRMEB 自定义的服务提供者
     */
    protected function registerCustomProviders(): void
    {
        // OAuth2 Provider
        $this->factories['oauth2'] = function () {
            return new WechatOauth2Provider($this);
        };

        // 微信直播
        $this->factories['wechat_live'] = function () {
            return new LiveProgramProvider($this);
        };

        // 模板消息
        $this->factories['wechat_template'] = function () {
            return new TemplateProvider($this);
        };

        // V3 支付
        $this->factories['v3pay'] = function () {
            return new PayClient($this);
        };

        // 订单发货
        $this->factories['order_ship'] = function () {
            return new OrderClient($this);
        };
    }

    /**
     * 获取 EasyWeChat 6.x OfficialAccount 实例
     * @return OfficialAccountApplication
     */
    public function getOfficialAccount(): OfficialAccountApplication
    {
        return $this->officialAccount;
    }

    /**
     * 获取配置
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    // ArrayAccess 接口实现
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
        if (is_callable($value)) {
            $this->factories[$offset] = $value;
        } else {
            $this->services[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->services[$offset], $this->factories[$offset]);
    }

    /**
     * 魔术方法，提供 4.x 风格的属性访问
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        // 先检查是否是容器中注册的服务
        if ($this->offsetExists($name)) {
            return $this->offsetGet($name);
        }

        // 6.x API 映射
        switch ($name) {
            case 'server':
                return $this->officialAccount->getServer();

            case 'oauth':
                return $this->officialAccount->getOAuth();

            case 'js':
                return new class($this->officialAccount) {
                    private $app;
                    public function __construct($app) { $this->app = $app; }
                    public function setUrl($url) { return $this; }
                    public function config($apis) {
                        return $this->app->getUtils()->buildJsSdkConfig(
                            request()->url(true),
                            $apis
                        );
                    }
                };

            case 'user':
                return new UserServiceWrapper($this->officialAccount);

            case 'user_tag':
                return new UserTagServiceWrapper($this->officialAccount);

            case 'user_group':
                return new UserGroupServiceWrapper($this->officialAccount);

            case 'material':
                return new MaterialServiceWrapper($this->officialAccount);

            case 'material_temporary':
                return new MaterialTemporaryServiceWrapper($this->officialAccount);

            case 'freepublish':
                return new FreePublishServiceWrapper($this->officialAccount);

            case 'menu':
                return new MenuServiceWrapper($this->officialAccount);

            case 'qrcode':
                return new QrcodeServiceWrapper($this->officialAccount);

            case 'url':
                return new UrlServiceWrapper($this->officialAccount);

            case 'staff':
                return new StaffServiceWrapper($this->officialAccount);

            case 'payment':
                return new PaymentServiceWrapper($this->config);

            case 'merchant_pay':
                return new MerchantPayServiceWrapper($this->config);

            case 'new_notice':
                return new TemplateMessageServiceWrapper($this->officialAccount);

            case 'minipay':
                return new MiniPayServiceWrapper($this->config);

            default:
                throw new \InvalidArgumentException("Property [{$name}] does not exist.");
        }
    }
}

/**
 * 用户服务包装器
 */
class UserServiceWrapper
{
    protected $app;
    protected $client;

    public function __construct(OfficialAccountApplication $app)
    {
        $this->app = $app;
        $this->client = $app->getClient();
    }

    public function get($openid)
    {
        $response = $this->client->get('/cgi-bin/user/info', ['openid' => $openid, 'lang' => 'zh_CN']);
        return $response->toArray();
    }

    public function batchGet(array $openids)
    {
        $list = array_map(fn($openid) => ['openid' => $openid, 'lang' => 'zh_CN'], $openids);
        $response = $this->client->postJson('/cgi-bin/user/info/batchget', ['user_list' => $list]);
        return $response->toArray();
    }

    public function lists($nextOpenid = null)
    {
        $params = $nextOpenid ? ['next_openid' => $nextOpenid] : [];
        $response = $this->client->get('/cgi-bin/user/get', $params);
        return $response->toArray();
    }
}

/**
 * 用户标签服务包装器
 */
class UserTagServiceWrapper
{
    protected $client;

    public function __construct(OfficialAccountApplication $app)
    {
        $this->client = $app->getClient();
    }

    public function create($name)
    {
        $response = $this->client->postJson('/cgi-bin/tags/create', ['tag' => ['name' => $name]]);
        return $response->toArray();
    }

    public function list()
    {
        $response = $this->client->get('/cgi-bin/tags/get');
        return $response->toArray();
    }

    public function update($tagId, $name)
    {
        $response = $this->client->postJson('/cgi-bin/tags/update', ['tag' => ['id' => $tagId, 'name' => $name]]);
        return $response->toArray();
    }

    public function delete($tagId)
    {
        $response = $this->client->postJson('/cgi-bin/tags/delete', ['tag' => ['id' => $tagId]]);
        return $response->toArray();
    }

    public function userTags($openid)
    {
        $response = $this->client->postJson('/cgi-bin/tags/getidlist', ['openid' => $openid]);
        return $response->toArray();
    }

    public function usersOfTag($tagId, $nextOpenid = '')
    {
        $response = $this->client->postJson('/cgi-bin/user/tag/get', ['tagid' => $tagId, 'next_openid' => $nextOpenid]);
        return $response->toArray();
    }

    public function tagUsers(array $openids, $tagId)
    {
        $response = $this->client->postJson('/cgi-bin/tags/members/batchtagging', ['openid_list' => $openids, 'tagid' => $tagId]);
        return $response->toArray();
    }

    public function untagUsers(array $openids, $tagId)
    {
        $response = $this->client->postJson('/cgi-bin/tags/members/batchuntagging', ['openid_list' => $openids, 'tagid' => $tagId]);
        return $response->toArray();
    }
}

/**
 * 用户分组服务包装器（已弃用，使用标签代替）
 */
class UserGroupServiceWrapper
{
    protected $client;

    public function __construct(OfficialAccountApplication $app)
    {
        $this->client = $app->getClient();
    }

    public function list()
    {
        $response = $this->client->get('/cgi-bin/groups/get');
        return $response->toArray();
    }
}

/**
 * 素材服务包装器
 */
class MaterialServiceWrapper
{
    protected $client;

    public function __construct(OfficialAccountApplication $app)
    {
        $this->client = $app->getClient();
    }

    public function uploadImage($path)
    {
        return $this->upload('image', $path);
    }

    public function uploadVoice($path)
    {
        return $this->upload('voice', $path);
    }

    public function uploadVideo($path, $title, $description)
    {
        return $this->upload('video', $path, ['title' => $title, 'introduction' => $description]);
    }

    public function uploadThumb($path)
    {
        return $this->upload('thumb', $path);
    }

    protected function upload($type, $path, $form = [])
    {
        $response = $this->client->withFile($path, 'media')->post('/cgi-bin/material/add_material', array_merge(['type' => $type], $form));
        return $response->toArray();
    }

    public function get($mediaId)
    {
        $response = $this->client->postJson('/cgi-bin/material/get_material', ['media_id' => $mediaId]);
        return $response->toArray();
    }

    public function delete($mediaId)
    {
        $response = $this->client->postJson('/cgi-bin/material/del_material', ['media_id' => $mediaId]);
        return $response->toArray();
    }

    public function list($type, $offset = 0, $count = 20)
    {
        $response = $this->client->postJson('/cgi-bin/material/batchget_material', [
            'type' => $type,
            'offset' => $offset,
            'count' => $count,
        ]);
        return $response->toArray();
    }

    public function stats()
    {
        $response = $this->client->get('/cgi-bin/material/get_materialcount');
        return $response->toArray();
    }
}

/**
 * 临时素材服务包装器
 */
class MaterialTemporaryServiceWrapper
{
    protected $client;

    public function __construct(OfficialAccountApplication $app)
    {
        $this->client = $app->getClient();
    }

    public function uploadImage($path)
    {
        return $this->upload('image', $path);
    }

    public function uploadVoice($path)
    {
        return $this->upload('voice', $path);
    }

    public function uploadVideo($path)
    {
        return $this->upload('video', $path);
    }

    public function uploadThumb($path)
    {
        return $this->upload('thumb', $path);
    }

    protected function upload($type, $path)
    {
        $response = $this->client->withFile($path, 'media')->post('/cgi-bin/media/upload', ['type' => $type]);
        return $response->toArray();
    }

    public function get($mediaId)
    {
        return $this->client->get('/cgi-bin/media/get', ['media_id' => $mediaId]);
    }
}

/**
 * 菜单服务包装器
 */
class MenuServiceWrapper
{
    protected $client;

    public function __construct(OfficialAccountApplication $app)
    {
        $this->client = $app->getClient();
    }

    public function create(array $buttons)
    {
        $response = $this->client->postJson('/cgi-bin/menu/create', ['button' => $buttons]);
        return $response->toArray();
    }

    public function current()
    {
        $response = $this->client->get('/cgi-bin/get_current_selfmenu_info');
        return $response->toArray();
    }

    public function all()
    {
        $response = $this->client->get('/cgi-bin/menu/get');
        return $response->toArray();
    }

    public function delete()
    {
        $response = $this->client->get('/cgi-bin/menu/delete');
        return $response->toArray();
    }
}

/**
 * 二维码服务包装器
 */
class QrcodeServiceWrapper
{
    protected $client;

    public function __construct(OfficialAccountApplication $app)
    {
        $this->client = $app->getClient();
    }

    public function temporary($sceneId, $expireSeconds = 2592000)
    {
        $data = [
            'expire_seconds' => $expireSeconds,
            'action_name' => is_int($sceneId) ? 'QR_SCENE' : 'QR_STR_SCENE',
            'action_info' => [
                'scene' => is_int($sceneId) ? ['scene_id' => $sceneId] : ['scene_str' => $sceneId],
            ],
        ];
        $response = $this->client->postJson('/cgi-bin/qrcode/create', $data);
        return $response->toArray();
    }

    public function forever($sceneId)
    {
        $data = [
            'action_name' => is_int($sceneId) ? 'QR_LIMIT_SCENE' : 'QR_LIMIT_STR_SCENE',
            'action_info' => [
                'scene' => is_int($sceneId) ? ['scene_id' => $sceneId] : ['scene_str' => $sceneId],
            ],
        ];
        $response = $this->client->postJson('/cgi-bin/qrcode/create', $data);
        return $response->toArray();
    }

    public function url($ticket)
    {
        return 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($ticket);
    }
}

/**
 * 短链接服务包装器
 */
class UrlServiceWrapper
{
    protected $client;

    public function __construct(OfficialAccountApplication $app)
    {
        $this->client = $app->getClient();
    }

    public function shorten($url)
    {
        $response = $this->client->postJson('/cgi-bin/shorturl', ['action' => 'long2short', 'long_url' => $url]);
        return $response->toArray();
    }
}

/**
 * 客服消息服务包装器
 */
class StaffServiceWrapper
{
    protected $client;
    protected $message;
    protected $to;

    public function __construct(OfficialAccountApplication $app)
    {
        $this->client = $app->getClient();
    }

    public function message($message)
    {
        $this->message = $message;
        return $this;
    }

    public function to($openid)
    {
        $this->to = $openid;
        return $this;
    }

    public function send()
    {
        $message = $this->message;
        $data = ['touser' => $this->to];

        if (is_string($message)) {
            $data['msgtype'] = 'text';
            $data['text'] = ['content' => $message];
        } elseif (is_object($message)) {
            // 根据消息类型处理
            $data = array_merge($data, $message->toArray());
        }

        $response = $this->client->postJson('/cgi-bin/message/custom/send', $data);
        return $response->toArray();
    }
}

/**
 * 模板消息服务包装器
 */
class TemplateMessageServiceWrapper
{
    protected $client;
    protected $to;
    protected $templateId;
    protected $data = [];
    protected $url;
    protected $miniprogram;
    protected $color;

    public function __construct(OfficialAccountApplication $app)
    {
        $this->client = $app->getClient();
    }

    public function to($openid)
    {
        $this->to = $openid;
        return $this;
    }

    public function template($templateId)
    {
        $this->templateId = $templateId;
        return $this;
    }

    public function andData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    public function url($url)
    {
        $this->url = $url;
        return $this;
    }

    public function setMiniprogram(array $miniprogram)
    {
        $this->miniprogram = $miniprogram;
        return $this;
    }

    public function defaultColor($color)
    {
        $this->color = $color;
        return $this;
    }

    public function send()
    {
        $params = [
            'touser' => $this->to,
            'template_id' => $this->templateId,
            'data' => $this->data,
        ];

        if ($this->url) {
            $params['url'] = $this->url;
        }

        if ($this->miniprogram) {
            $params['miniprogram'] = $this->miniprogram;
        }

        $response = $this->client->postJson('/cgi-bin/message/template/send', $params);

        // 重置状态
        $this->to = $this->templateId = $this->url = $this->miniprogram = null;
        $this->data = [];

        return $response->toArray();
    }

    public function setIndustry($industryOne, $industryTwo)
    {
        $response = $this->client->postJson('/cgi-bin/template/api_set_industry', [
            'industry_id1' => $industryOne,
            'industry_id2' => $industryTwo,
        ]);
        return $response->toArray();
    }

    public function addTemplate($shortId, $keywords = [])
    {
        $data = ['template_id_short' => $shortId];
        if ($keywords) {
            $data['keyword_name_list'] = $keywords;
        }
        $response = $this->client->postJson('/cgi-bin/template/api_add_template', $data);
        return $response->toArray();
    }

    public function getPrivateTemplates()
    {
        $response = $this->client->get('/cgi-bin/template/get_all_private_template');
        return $response->toArray();
    }

    public function deletePrivateTemplate($templateId)
    {
        $response = $this->client->postJson('/cgi-bin/template/del_private_template', ['template_id' => $templateId]);
        return $response->toArray();
    }
}

/**
 * 支付服务包装器（使用 v2 API）
 */
class PaymentServiceWrapper
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function prepare($order)
    {
        // 这里需要实现微信支付统一下单
        // TODO: 使用 EasyWeChat 6.x Pay 模块实现
        throw new \RuntimeException('Payment service needs to be implemented with EasyWeChat 6.x Pay module');
    }

    public function configForJSSDKPayment($prepayId)
    {
        // TODO: 实现 JSAPI 支付配置
        throw new \RuntimeException('Payment service needs to be implemented with EasyWeChat 6.x Pay module');
    }

    public function configForAppPayment($prepayId)
    {
        // TODO: 实现 APP 支付配置
        throw new \RuntimeException('Payment service needs to be implemented with EasyWeChat 6.x Pay module');
    }

    public function refund($orderNo, $refundNo, $totalFee, $refundFee, $opUserId = null, $type = 'out_trade_no', $refundAccount = '', $reason = '')
    {
        // TODO: 实现退款
        throw new \RuntimeException('Refund service needs to be implemented with EasyWeChat 6.x Pay module');
    }

    public function refundByTransactionId($transactionId, $refundNo, $totalFee, $refundFee, $opUserId = null, $refundAccount = '', $reason = '')
    {
        // TODO: 实现按交易号退款
        throw new \RuntimeException('Refund service needs to be implemented with EasyWeChat 6.x Pay module');
    }

    public function handleNotify(callable $callback)
    {
        // TODO: 实现支付回调处理
        throw new \RuntimeException('Payment notify handler needs to be implemented with EasyWeChat 6.x Pay module');
    }
}

/**
 * 企业付款服务包装器
 */
class MerchantPayServiceWrapper
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(array $params)
    {
        // TODO: 实现企业付款到零钱
        throw new \RuntimeException('Merchant pay service needs to be implemented with EasyWeChat 6.x Pay module');
    }
}

/**
 * 小程序支付服务包装器
 */
class MiniPayServiceWrapper
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function createorder($order)
    {
        // TODO: 实现小程序下单
        throw new \RuntimeException('Mini program payment needs to be implemented');
    }
}
< ? p h p  
 / /   � � � � � � � � � � �   � "! � `� � ��   A p p l i c a t i o n . p h p   �  ! � � � � a � S� � � � � � R� � S� � � �  � � a   S e r v i c e W r a p p e r   � � �  
  
 / * *  
   *   � �  � � �� a � : � �  ! � � �� � � � S� � `� � R& � � & � "!�  
   * /  
 c l a s s   F r e e P u b l i s h S e r v i c e W r a p p e r  
 {  
         p r o t e c t e d   $ c l i e n t ;  
  
         p u b l i c   f u n c t i o n   _ _ c o n s t r u c t ( \ E a s y W e C h a t \ O f f i c i a l A c c o u n t \ A p p l i c a t i o n   $ a p p )  
         {  
                 $ t h i s - > c l i e n t   =   $ a p p - > g e t C l i e n t ( ) ;  
         }  
  
         / * *  
           *   � }� � �  � �� � `x� �  � � �� � � � �  
           *   @ p a r a m   i n t   $ o f f s e t  
           *   @ p a r a m   i n t   $ c o u n t  
           *   @ r e t u r n   a r r a y  
           * /  
         p u b l i c   f u n c t i o n   l i s t ( i n t   $ o f f s e t   =   0 ,   i n t   $ c o u n t   =   2 0 )  
         {  
                 $ r e s p o n s e   =   $ t h i s - > c l i e n t - > p o s t J s o n ( ' / c g i - b i n / f r e e p u b l i s h / b a t c h g e t ' ,   [  
                         ' o f f s e t '   = >   $ o f f s e t ,  
                         ' c o u n t '   = >   $ c o u n t ,  
                         ' n o _ c o n t e n t '   = >   0  
                 ] ) ;  
                 r e t u r n   $ r e s p o n s e - > t o A r r a y ( ) ;  
         }  
  
         / * *  
           *   � }� � �  � � " � � ! � � � � �  � � �� : � �  !  
           *   @ p a r a m   s t r i n g   $ a r t i c l e I d  
           *   @ r e t u r n   a r r a y  
           * /  
         p u b l i c   f u n c t i o n   g e t ( s t r i n g   $ a r t i c l e I d )  
         {  
                 $ r e s p o n s e   =   $ t h i s - > c l i e n t - > p o s t J s o n ( ' / c g i - b i n / f r e e p u b l i s h / g e t a r t i c l e ' ,   [  
                         ' a r t i c l e _ i d '   = >   $ a r t i c l e I d  
                 ] ) ;  
                 r e t u r n   $ r e s p o n s e - > t o A r r a y ( ) ;  
         }  
 }  
 