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
namespace crmeb\services\easywechat\wechatlive;

use crmeb\services\easywechat\Application;

/**
 * 注册直播 Provider
 * 重构后不再依赖 Pimple
 * 
 * Class ProgramProvider
 * @package crmeb\services\easywechat\wechatlive
 */
class ProgramProvider
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var ProgramWechatLive
     */
    protected $live;

    /**
     * ProgramProvider constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 获取直播实例
     * @return ProgramWechatLive
     */
    public function getLive(): ProgramWechatLive
    {
        if (!$this->live) {
            $this->live = new ProgramWechatLive($this->app);
        }
        return $this->live;
    }

    /**
     * 魔术方法，代理到 ProgramWechatLive
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->getLive()->{$name}(...$arguments);
    }
}
