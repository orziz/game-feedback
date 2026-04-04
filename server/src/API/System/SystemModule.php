<?php

declare(strict_types=1);

namespace GameFeedback\API\System;

use GameFeedback\API\BaseApiModule;


/**
 * 系统模块入口：负责将 system.* 路由分发到 System 子模块目录。
 */
final class SystemModule extends BaseApiModule
{
    /**
     * 返回系统子模块所在目录名。
     */
    protected function moduleDirName(): string
    {
        return 'System';
    }
}
