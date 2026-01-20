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
namespace crmeb\services\easywechat\wechatTemplate;

use crmeb\services\easywechat\Application;

/**
 * 公众号模板消息 Provider
 * 重构后不再依赖 Pimple
 * 
 * Class ProgramProvider
 * @package crmeb\services\easywechat\wechatTemplate
 */
class ProgramProvider
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var ProgramTemplate
     */
    protected $template;

    /**
     * ProgramProvider constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 获取模板消息实例
     * @return ProgramTemplate
     */
    public function getTemplate(): ProgramTemplate
    {
        if (!$this->template) {
            $this->template = new ProgramTemplate($this->app);
        }
        return $this->template;
    }

    /**
     * 魔术方法，代理到 ProgramTemplate
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->getTemplate()->{$name}(...$arguments);
    }
}