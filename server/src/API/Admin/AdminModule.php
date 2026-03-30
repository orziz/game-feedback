<?php

declare(strict_types=1);

namespace GameFeedback\API\Admin;

use GameFeedback\API\BaseApiModule;


final class AdminModule extends BaseApiModule
{
    protected function moduleDirName(): string
    {
        return 'Admin';
    }
}
