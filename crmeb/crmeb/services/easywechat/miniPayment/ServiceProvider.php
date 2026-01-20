<?php
/*
 * This file is part of the overtrue/wechat.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 * 
 * Modified: Refactored for EasyWeChat 6.x - Removed Pimple dependency
 */

namespace crmeb\services\easywechat\miniPayment;

use crmeb\services\easywechat\Application;

/**
 * 小程序支付 ServiceProvider
 * 重构后不再依赖 Pimple
 *
 * Class ServiceProvider
 * @package crmeb\services\easywechat\miniPayment
 */
class ServiceProvider
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var WeChatClient
     */
    protected $client;

    /**
     * ServiceProvider constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 获取微信支付客户端
     * @return WeChatClient
     */
    public function getClient(): WeChatClient
    {
        if (!$this->client) {
            $this->client = new WeChatClient($this->app);
        }
        return $this->client;
    }

    /**
     * 魔术方法，代理到 WeChatClient
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->getClient()->{$name}(...$arguments);
    }
}
