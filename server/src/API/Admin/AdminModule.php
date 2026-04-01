<?php

declare(strict_types=1);

namespace GameFeedback\API\Admin;

use GameFeedback\API\BaseApiModule;


/**
 * 管理端模块入口：负责将 admin.* 路由分发到 Admin 子模块目录。
 */
final class AdminModule extends BaseApiModule
{
    protected function moduleDirName(): string
    {
        return 'Admin';
    }
}
