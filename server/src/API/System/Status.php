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

        $systemVersion = $this->normalizeSystemVersion((string)($this->appConfig['app_version'] ?? '1.0.0'));

        Responder::send([
            'ok' => true,
            'installed' => $this->installed,
            'uploadMode' => (string)($this->dbConfig['upload_mode'] ?? 'off'),
            'uploadMaxBytes' => $uploadMaxBytes,
            'systemVersion' => $systemVersion,
        ]);
    }

    private function normalizeSystemVersion(string $version): string
    {
        $trimmed = trim($version);
        if ($trimmed === '') {
            return '1.0.0';
        }

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $trimmed) === 1) {
            return $trimmed;
        }

        if (preg_match('/^(\d+)$/', $trimmed, $matches) === 1) {
            return $matches[1] . '.0.0';
        }

        return '1.0.0';
    }
}
