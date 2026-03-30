<?php

declare(strict_types=1);

namespace GameFeedback\API\System;

use GameFeedback\API\BaseApiSubModule;
use GameFeedback\Support\Responder;


final class Status extends BaseApiSubModule
{
    /**
     * @return array<string, array{methods: array<int, string>, allow_before_install?: bool}>
     */
    protected function actionMeta(): array
    {
        return [
            'health' => [
                'methods' => ['GET'],
                'allow_before_install' => true,
            ],
            'installStatus' => [
                'methods' => ['GET'],
                'allow_before_install' => true,
            ],
        ];
    }

    protected function health(): void
    {
        Responder::send([
            'ok' => true,
            'installed' => $this->installed,
            'time' => date('c'),
        ]);
    }

    protected function installStatus(): void
    {
        $uploadMaxBytes = (int)($this->dbConfig['upload_max_bytes'] ?? 5 * 1024 * 1024);
        if ($uploadMaxBytes <= 0) {
            $uploadMaxBytes = 5 * 1024 * 1024;
        }

        Responder::send([
            'ok' => true,
            'installed' => $this->installed,
            'uploadMode' => (string)($this->dbConfig['upload_mode'] ?? 'off'),
            'uploadMaxBytes' => $uploadMaxBytes,
        ]);
    }
}
