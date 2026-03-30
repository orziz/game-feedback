<?php

declare(strict_types=1);

namespace GameFeedback\API\System;

use GameFeedback\API\BaseApiModule;


final class SystemModule extends BaseApiModule
{
    protected function moduleDirName(): string
    {
        return 'System';
    }
}
