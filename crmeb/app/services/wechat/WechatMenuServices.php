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

namespace app\services\wechat;


use app\dao\wechat\WechatMenuDao;
use app\services\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\app\WechatService;

/**
 * 微信菜单
 * Class WechatMenuServices
 * @package app\services\wechat
 */
class WechatMenuServices extends BaseServices
{
    /**
     * 构造方法
     * WechatMenuServices constructor.
     * @param WechatMenuDao $dao
     */
    public function __construct(WechatMenuDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取微信菜单 - 直接从公众号获取，不存本地
     * @return array|mixed
     */
    public function getWechatMenu()
    {
        try {
            // 直接从微信公众号获取当前菜单
            $result = WechatService::menuService()->current();
            
            if (!isset($result['selfmenu_info']['button'])) {
                return [];
            }
            
            $wechatButtons = $result['selfmenu_info']['button'];
            return $this->convertWechatMenuToLocal($wechatButtons);
        } catch (\Exception $e) {
            // 如果获取失败，返回空数组或本地缓存
            $menus = $this->dao->value(['key' => 'wechat_menus'], 'result');
            return $menus ? json_decode($menus, true) : [];
        }
    }

    /**
     * 转换微信菜单格式为商城格式
     * @param array $wechatButtons
     * @return array
     */
    protected function convertWechatMenuToLocal(array $wechatButtons): array
    {
        $menus = [];
        foreach ($wechatButtons as $button) {
            $menu = [
                'name' => $button['name'] ?? '',
                'type' => $button['type'] ?? 'click',
                'key' => $button['key'] ?? '',
                'url' => $button['url'] ?? '',
                'appid' => $button['appid'] ?? '',
                'pagepath' => $button['pagepath'] ?? '',
            ];
            
            // 处理子菜单
            if (isset($button['sub_button']['list']) && !empty($button['sub_button']['list'])) {
                $menu['sub_button'] = [];
                foreach ($button['sub_button']['list'] as $subButton) {
                    $menu['sub_button'][] = [
                        'name' => $subButton['name'] ?? '',
                        'type' => $subButton['type'] ?? 'click',
                        'key' => $subButton['key'] ?? '',
                        'url' => $subButton['url'] ?? '',
                        'appid' => $subButton['appid'] ?? '',
                        'pagepath' => $subButton['pagepath'] ?? '',
                    ];
                }
            }
            
            $menus[] = $menu;
        }
        return $menus;
    }

    /**
     * 保存微信菜单
     * @param array $buttons
     * @return bool
     */
    public function saveMenu(array $buttons)
    {
        try {
            // 直接推送到微信服务器，不保存本地副本
            WechatService::menuService()->create($buttons);
            return true;
        } catch (\Exception $e) {
            if (strstr($e->getMessage(), 'Request AccessToken fail. response')) {
                $msgData = str_replace('Request AccessToken fail. response: ', '', $e->getMessage());
                $msgData = json_decode($msgData, true);
                $errcode = $msgData['errcode'] ?? 0;
                if ($errcode == 40164) {
                    throw new AdminException(400704);
                }
            }
            if (strstr($e->getMessage(), 'invalid weapp appid')) {
                throw new AdminException(400705);
            }
            throw new AdminException(WechatService::getMessage($e->getMessage()));
        }
    }
}

