<?php

declare(strict_types=1);

namespace GameFeedback\API\System;

use GameFeedback\API\BaseApiSubModule;
use GameFeedback\Support\EnumOptionsProvider;
use GameFeedback\Support\Request;
use GameFeedback\Support\Responder;
use GameFeedback\Support\SystemInstaller;

final class Setup extends BaseApiSubModule
{
    /**
     * @return array<string, array{
     *   methods: array<int, string>,
     *   allow_before_install?: bool,
     *   rate_limit?: array<string, int|string>
     * }>
     */
    protected function actionMeta(): array
    {
        return [
            'enumOptions' => [
                self::META_METHODS => ['GET'],
                self::META_ALLOW_BEFORE_INSTALL => true,
            ],
            'install' => [
                self::META_METHODS => ['POST'],
                self::META_ALLOW_BEFORE_INSTALL => true,
                self::META_RATE_LIMIT => $this->rateLimitMeta('system-install', 10, 600, 600),
            ],
        ];
    }

    /**
     * 返回前端安装页面需要的枚举选项。
     */
    protected function enumOptions(): void
    {
        $lang = Request::query('lang', 'zh-CN');
        $options = EnumOptionsProvider::build($lang);

        Responder::send([
            'ok' => true,
            'types' => $options['types'],
            'severities' => $options['severities'],
            'statuses' => $options['statuses'],
        ]);
    }

    /**
     * 执行系统首次安装并写入数据库配置。
     */
    protected function install(): void
    {
        if ($this->installed || is_file($this->databaseConfigPath)) {
            Responder::error('ALREADY_INSTALLED', '系统已安装，禁止重复初始化。', 409);
        }

        $payload = Request::jsonBody();
        $this->ensureInstallAuthorized($payload);
        (new SystemInstaller($this->databaseConfigPath, $this->sanitizer))->install($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function ensureInstallAuthorized(array $payload): void
    {
        $configuredToken = trim((string)(getenv('APP_INSTALL_TOKEN') ?: ''));
        if ($configuredToken === '') {
            if (in_array(Request::clientIp(), ['127.0.0.1', '::1'], true)) {
                return;
            }

            Responder::error(
                'INSTALL_TOKEN_NOT_CONFIGURED',
                '远程安装前必须先在服务端配置 APP_INSTALL_TOKEN。',
                503
            );
        }

        if (strlen($configuredToken) < 16) {
            Responder::error(
                'INSTALL_TOKEN_TOO_SHORT',
                '服务端 APP_INSTALL_TOKEN 长度不能少于 16 位。',
                503
            );
        }

        $providedToken = $this->sanitizer->sanitizeSingleLine(
            (string)($payload['installToken'] ?? ''),
            255
        );
        if ($providedToken === '' || !hash_equals($configuredToken, $providedToken)) {
            Responder::error('INVALID_INSTALL_TOKEN', '首次安装令牌无效。', 403);
        }
    }
}
