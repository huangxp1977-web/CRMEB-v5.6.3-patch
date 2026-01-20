<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Modified: Refactored for EasyWeChat 6.x - Removed Pimple dependency
// +----------------------------------------------------------------------

namespace crmeb\services\easywechat\miniScheme;

use crmeb\services\easywechat\Application;

/**
 * 小程序 URL Scheme Provider
 * 重构后不再依赖 Pimple
 * 
 * Class ProgramProvider
 * @package crmeb\services\easywechat\miniScheme
 */
class ProgramProvider
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var ProgramScheme
     */
    protected $scheme;

    /**
     * ProgramProvider constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 获取 Scheme 实例
     * @return ProgramScheme
     */
    public function getScheme(): ProgramScheme
    {
        if (!$this->scheme) {
            $this->scheme = new ProgramScheme($this->app);
        }
        return $this->scheme;
    }

    /**
     * 魔术方法，代理到 ProgramScheme
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->getScheme()->{$name}(...$arguments);
    }
}