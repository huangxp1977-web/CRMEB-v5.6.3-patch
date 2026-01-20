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

namespace crmeb\services\easywechat\orderShipping;

use crmeb\services\easywechat\Application;

/**
 * 订单发货 ServiceProvider
 * 重构后不再依赖 Pimple
 *
 * Class ServiceProvider
 * @package crmeb\services\easywechat\orderShipping
 */
class ServiceProvider
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var OrderClient
     */
    protected $orderClient;

    /**
     * ServiceProvider constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 获取订单客户端
     * @return OrderClient
     */
    public function getOrderClient(): OrderClient
    {
        if (!$this->orderClient) {
            $this->orderClient = new OrderClient($this->app);
        }
        return $this->orderClient;
    }

    /**
     * 魔术方法，代理到 OrderClient
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->getOrderClient()->{$name}(...$arguments);
    }
}
