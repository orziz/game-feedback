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
     * @return array<string, array{methods: array<int, string>, allow_before_install?: bool}>
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
        (new SystemInstaller($this->databaseConfigPath, $this->sanitizer))->install($payload);
    }
}
