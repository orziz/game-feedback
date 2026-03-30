<?php

declare(strict_types=1);

namespace GameFeedback;

use GameFeedback\API\Admin\AdminModule;
use GameFeedback\API\BaseApiModule;
use GameFeedback\API\Feedback\FeedbackModule;
use GameFeedback\API\System\SystemModule;
use GameFeedback\Support\AppInputSanitizer;
use GameFeedback\Support\Request;
use GameFeedback\Support\Responder;
use GameFeedback\Support\UserSystemMigrator;

/**
 * 应用入口
 */
final class App
{
    /** @var array<string, mixed> */
    private $appConfig;

    /** @var string */
    private $databaseConfigPath;

    /** @var bool */
    private $installed;

    /** @var array<string, mixed> */
    private $dbConfig;

    /** @var AppInputSanitizer */
    private $sanitizer;

    /**
     * @param array<string, mixed> $appConfig
     */
    public function __construct(array $appConfig, string $databaseConfigPath)
    {
        $this->appConfig = $appConfig;
        $this->databaseConfigPath = $databaseConfigPath;

        date_default_timezone_set($this->appConfig['timezone'] ?? 'Asia/Shanghai');

        $this->installed = is_file($this->databaseConfigPath);
        $this->dbConfig = $this->installed ? require $this->databaseConfigPath : [];
        $this->sanitizer = new AppInputSanitizer();
    }

    public function run(): void
    {
        $route = $this->resolveRoute();

        if ($this->installed) {
            $this->dbConfig = UserSystemMigrator::migrate($this->dbConfig, $this->databaseConfigPath);
        }

        $module = $this->createModule($route['mod']);
        if ($module === null || !$module->hasAction($route['action'])) {
            Responder::send([
                'ok' => false,
                'code' => 'NOT_FOUND',
                'message' => '未找到对应接口。',
            ], 404);
        }

        if (!$this->installed && !$module->allowsBeforeInstall($route['action'])) {
            Responder::send([
                'ok' => false,
                'code' => 'NEED_INSTALL',
                'message' => '系统尚未安装，请先完成初始化。',
            ], 400);
        }

        if (!$module->allowsMethod($route['action'], Request::method())) {
            Responder::send([
                'ok' => false,
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => '当前接口不支持该请求方法。',
            ], 405);
        }

        $module->dispatch($route['action']);
    }

    /**
     * @return array{mod: string, action: string}
     */
    private function resolveRoute(): array
    {
        $route = Request::query('s');

        if (preg_match('/^[a-z][a-z0-9_]*\/[A-Za-z][A-Za-z0-9_]*\/[A-Za-z][A-Za-z0-9_]*$/', $route) !== 1) {
            Responder::send([
                'ok' => false,
                'code' => 'NOT_FOUND',
                'message' => '未找到对应接口。',
            ], 404);
        }

        $parts = explode('/', $route, 3);

        return [
            'mod' => $parts[0],
            'action' => $parts[1] . '/' . $parts[2],
        ];
    }

    private function createModule(string $mod): ?BaseApiModule
    {
        if ($mod === 'system') {
            return new SystemModule($this->appConfig, $this->dbConfig, $this->databaseConfigPath, $this->installed, $this->sanitizer);
        }

        if ($mod === 'feedback') {
            return new FeedbackModule($this->appConfig, $this->dbConfig, $this->databaseConfigPath, $this->installed, $this->sanitizer);
        }

        if ($mod === 'admin') {
            return new AdminModule($this->appConfig, $this->dbConfig, $this->databaseConfigPath, $this->installed, $this->sanitizer);
        }

        return null;
    }
}
