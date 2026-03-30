<?php

declare(strict_types=1);

namespace GameFeedback\API\Feedback;

use GameFeedback\API\BaseApiModule;


final class FeedbackModule extends BaseApiModule
{
    protected function moduleDirName(): string
    {
        return 'Feedback';
    }
}
