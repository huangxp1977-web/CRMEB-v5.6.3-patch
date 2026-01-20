<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2023 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// | Modified: Refactored for EasyWeChat 6.x - Removed Pimple dependency
// +----------------------------------------------------------------------
namespace crmeb\services\easywechat\Open3rd;

use crmeb\services\easywechat\Application;

/**
 * 注册第三方平台 Provider
 * 重构后不再依赖 Pimple
 * 
 * Class ProgramProvider
 * @package crmeb\services\easywechat\Open3rd
 */
class ProgramProvider
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var ProgramOpen3rd
     */
    protected $open3rd;

    /**
     * ProgramProvider constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 获取第三方平台实例
     * @return ProgramOpen3rd
     */
    public function getOpen3rd(): ProgramOpen3rd
    {
        if (!$this->open3rd) {
            $config = $this->app->getConfig();
            $accessToken = new AccessToken(
                $config['open3rd']['component_appid'] ?? '',
                $config['open3rd']['component_appsecret'] ?? '',
                $config['open3rd']['component_verify_ticket'] ?? '',
                $config['open3rd']['authorizer_appid'] ?? ''
            );
            $this->open3rd = new ProgramOpen3rd($accessToken);
        }
        return $this->open3rd;
    }

    /**
     * 魔术方法，代理到 ProgramOpen3rd
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->getOpen3rd()->{$name}(...$arguments);
    }
}
