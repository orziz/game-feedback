<?php

declare(strict_types=1);

namespace GameFeedback\API\Feedback;

use GameFeedback\API\BaseApiModule;


/**
 * 玩家反馈模块入口：负责将 feedback.* 路由分发到 Feedback 子模块目录。
 */
final class FeedbackModule extends BaseApiModule
{
    /**
     * 返回玩家反馈子模块所在目录名。
     */
    protected function moduleDirName(): string
    {
        return 'Feedback';
    }
}
