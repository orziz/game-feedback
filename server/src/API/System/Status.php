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

    /**
     * 返回系统健康状态与当前安装标记。
     */
    protected function health(): void
    {
        // 健康检查：用于探活与基础连通性验证
        Responder::send([
            'ok' => true,
            'installed' => $this->installed,
            'time' => date('c'),
        ]);
    }

    /**
     * 返回安装页和管理端需要的系统状态摘要。
     */
    protected function installStatus(): void
    {
        // 安装状态：返回前端初始化面板需要的核心配置摘要
        $uploadMaxBytes = (int)($this->dbConfig['upload_max_bytes'] ?? 5 * 1024 * 1024);
        if ($uploadMaxBytes <= 0) {
            $uploadMaxBytes = 5 * 1024 * 1024;
        }

        $systemVersion = $this->normalizeSystemVersion((string)($this->appConfig['app_version'] ?? '1.0.0'));

        $attachmentCleanupRetentionDays = (int)($this->dbConfig['attachment_cleanup_retention_days'] ?? 15);
        if ($attachmentCleanupRetentionDays <= 0) {
            $attachmentCleanupRetentionDays = 15;
        }

        Responder::send([
            'ok' => true,
            'installed' => $this->installed,
            'uploadMode' => (string)($this->dbConfig['upload_mode'] ?? 'off'),
            'uploadMaxBytes' => $uploadMaxBytes,
            'attachmentCleanupEnabled' => $this->resolveConfigFlag('attachment_cleanup_enabled', true),
            'attachmentCleanupRetentionDays' => $attachmentCleanupRetentionDays,
            'systemVersion' => $systemVersion,
        ]);
    }

    /**
     * 读取布尔型配置项，并兼容字符串形式的真值。
     */
    private function resolveConfigFlag(string $key, bool $default): bool
    {
        if (!array_key_exists($key, $this->dbConfig)) {
            return $default;
        }

        $value = $this->dbConfig[$key];
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * 将任意版本号输入规范化为 x.y.z 形式。
     */
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
