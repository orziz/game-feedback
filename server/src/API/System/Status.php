<?php

declare(strict_types=1);

namespace GameFeedback\API\System;

use GameFeedback\API\BaseApiSubModule;
use GameFeedback\Support\Responder;


/**
 * 系统状态接口：提供健康检查与安装状态查询。
 */
final class Status extends BaseApiSubModule
{
    /**
     * @return array<string, array{methods: array<int, string>, allow_before_install?: bool}>
     */
    protected function actionMeta(): array
    {
        return [
            'health' => [
                self::META_METHODS => ['GET'],
                self::META_ALLOW_BEFORE_INSTALL => true,
            ],
            'installStatus' => [
                self::META_METHODS => ['GET'],
                self::META_ALLOW_BEFORE_INSTALL => true,
            ],
        ];
    }

    protected function health(): void
    {
        // 健康检查：用于探活与基础连通性验证
        Responder::send([
            'ok' => true,
            'installed' => $this->installed,
            'time' => date('c'),
        ]);
    }

    protected function installStatus(): void
    {
        // 安装状态：返回前端初始化面板需要的核心配置摘要
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
