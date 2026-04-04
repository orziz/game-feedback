<?php

declare(strict_types=1);

namespace GameFeedback;

use GameFeedback\API\Admin\AdminModule;
use GameFeedback\API\BaseApiModule;
use GameFeedback\API\Feedback\FeedbackModule;
use GameFeedback\API\System\SystemModule;
use GameFeedback\Support\AppInputSanitizer;
use GameFeedback\Support\AttachmentCleanupService;
use GameFeedback\Support\Database;
use GameFeedback\Support\Request;
use GameFeedback\Support\Responder;
use GameFeedback\Support\RuntimeConfig;
use GameFeedback\Support\UserSystemMigrator;

/**
 * 应用入口
 */
final class App
{
    private const DEFAULT_ATTACHMENT_CLEANUP_INTERVAL_SECONDS = 600;
    private const DEFAULT_ATTACHMENT_CLEANUP_BATCH_LIMIT = 100;
    private const ATTACHMENT_CLEANUP_STATE_FILE = 'storage/runtime/attachment_cleanup_last_run.txt';

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
        $this->dbConfig = $this->installed
            ? RuntimeConfig::overlayDatabaseConfig(require $this->databaseConfigPath)
            : [];
        $this->sanitizer = new AppInputSanitizer();
    }

    /**
     * 启动应用并完成模块分发。
     */
    public function run(): void
    {
        $route = $this->resolveRoute();

        if ($this->installed && UserSystemMigrator::needsMigration($this->dbConfig)) {
            // 仅在确实需要迁移时（schema 版本落后或存在遗留配置）才创建 PDO 并执行迁移
            // 正常运行时 needsMigration() 为 false，此处完全不产生数据库连接
            $this->dbConfig = RuntimeConfig::overlayDatabaseConfig(
                UserSystemMigrator::migrate($this->dbConfig, $this->databaseConfigPath)
            );
        }

        if ($this->installed) {
            $this->triggerAttachmentCleanupIfDue();
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

    /**
     * 根据模块名创建对应的 API 模块实例。
     */
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

    /**
     * 时间到了就顺手跑一次附件清理，避免附件一直堆着不删。
     */
    private function triggerAttachmentCleanupIfDue(): void
    {
        if (!$this->isAttachmentCleanupEnabled()) {
            return;
        }

        $stateFile = $this->attachmentCleanupStateFile();
        $stateDir = dirname($stateFile);
        if (!is_dir($stateDir) && !@mkdir($stateDir, 0775, true) && !is_dir($stateDir)) {
            return;
        }

        $lastRun = @filemtime($stateFile);
        if ($lastRun !== false && (time() - $lastRun) < $this->attachmentCleanupIntervalSeconds()) {
            return;
        }

        if (@file_put_contents($stateFile, (string)time()) === false) {
            return;
        }

        try {
            $cleanupService = new AttachmentCleanupService($this->dbConfig, Database::createConfiguredPdo($this->dbConfig));
            $cleanupService->backfillSchedules();
            $cleanupService->run($this->attachmentCleanupBatchLimit(), false);
        } catch (\Throwable $e) {
            error_log('[AttachmentCleanup] ' . $e->getMessage());
        }
    }

    /**
     * 读取两次自动清理之间最少要间隔多少秒。
     */
    private function attachmentCleanupIntervalSeconds(): int
    {
        $value = (int)($this->dbConfig['attachment_cleanup_interval_seconds'] ?? self::DEFAULT_ATTACHMENT_CLEANUP_INTERVAL_SECONDS);
        return $value > 0 ? $value : self::DEFAULT_ATTACHMENT_CLEANUP_INTERVAL_SECONDS;
    }

    /**
     * 读取一次自动清理最多处理多少个附件。
     */
    private function attachmentCleanupBatchLimit(): int
    {
        $value = (int)($this->dbConfig['attachment_cleanup_batch_limit'] ?? self::DEFAULT_ATTACHMENT_CLEANUP_BATCH_LIMIT);
        return $value > 0 ? $value : self::DEFAULT_ATTACHMENT_CLEANUP_BATCH_LIMIT;
    }

    /**
     * 返回记录上次清理时间的状态文件路径。
     */
    private function attachmentCleanupStateFile(): string
    {
        return dirname(__DIR__) . '/' . self::ATTACHMENT_CLEANUP_STATE_FILE;
    }

    /**
     * 判断附件自动清理现在是不是开启状态。
     */
    private function isAttachmentCleanupEnabled(): bool
    {
        if (!array_key_exists('attachment_cleanup_enabled', $this->dbConfig)) {
            return true;
        }

        $value = $this->dbConfig['attachment_cleanup_enabled'];
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return true;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
