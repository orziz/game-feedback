<?php

declare(strict_types=1);

namespace GameFeedback\API;

use GameFeedback\Repository\TicketRepository;
use GameFeedback\Repository\UserRepository;
use GameFeedback\Support\AppInputSanitizer;
use GameFeedback\Support\Database;
use GameFeedback\Support\RateLimiter;
use GameFeedback\Support\Request;
use PDO;
use RuntimeException;

/**
 * API 子模块基类
 *
 * 管理 PDO 连接缓存（同一请求内复用同一连接）和表结构初始化（单次请求内只执行一次）。
 */
abstract class BaseApiSubModule
{
    /** 动作元数据键：HTTP 方法列表 */
    protected const META_METHODS = 'methods';

    /** 动作元数据键：安装前是否允许访问 */
    protected const META_ALLOW_BEFORE_INSTALL = 'allow_before_install';

    /** 动作元数据键：限流配置 */
    protected const META_RATE_LIMIT = 'rate_limit';

    /** 动作元数据键：鉴权策略 */
    protected const META_AUTH = 'auth';

    /** 鉴权策略：不需要鉴权 */
    protected const AUTH_NONE = 'none';

    /** 鉴权策略：管理员 */
    protected const AUTH_ADMIN = 'admin';

    /** 鉴权策略：超级管理员 */
    protected const AUTH_SUPER_ADMIN = 'super_admin';

    /** 限流配置键：作用域 */
    protected const RATE_LIMIT_SCOPE = 'scope';

    /** 限流配置键：窗口内最大次数 */
    protected const RATE_LIMIT_MAX_ATTEMPTS = 'max_attempts';

    /** 限流配置键：窗口秒数 */
    protected const RATE_LIMIT_WINDOW_SECONDS = 'window_seconds';

    /** 限流配置键：封禁秒数 */
    protected const RATE_LIMIT_BLOCK_SECONDS = 'block_seconds';

    /** 默认限流窗口（秒） */
    protected const DEFAULT_RATE_LIMIT_WINDOW_SECONDS = 600;

    /** 默认限流封禁时长（秒） */
    protected const DEFAULT_RATE_LIMIT_BLOCK_SECONDS = 600;

    /** @var array<string, mixed> */
    protected $appConfig;

    /** @var array<string, mixed> */
    protected $dbConfig;

    /** @var string */
    protected $databaseConfigPath;

    /** @var bool */
    protected $installed;

    /** @var AppInputSanitizer */
    protected $sanitizer;

    /**
     * 当前请求内缓存的 PDO 实例，避免同一请求多次建立数据库连接
     *
     * @var PDO|null
     */
    private $cachedPdo = null;

    /**
     * 同一请求内是否已执行过表结构检查；静态属性在单次 PHP 进程中共享，
     * 确保 createTableIfNotExists 在多次调用 createTicketRepository 时只运行一次。
     *
     * @var bool
     */
    private static $tablesEnsured = false;

    /**
     * @param array<string, mixed> $appConfig
     * @param array<string, mixed> $dbConfig
     */
    public function __construct(
        array $appConfig,
        array $dbConfig,
        string $databaseConfigPath,
        bool $installed,
        AppInputSanitizer $sanitizer
    ) {
        $this->appConfig = $appConfig;
        $this->dbConfig = $dbConfig;
        $this->databaseConfigPath = $databaseConfigPath;
        $this->installed = $installed;
        $this->sanitizer = $sanitizer;
    }

    /**
     * @return array<string, array{
     *   methods: array<int, string>,
     *   allow_before_install?: bool,
     *   rate_limit?: array{
     *     scope?: string,
     *     max_attempts?: int,
     *     window_seconds?: int,
     *     block_seconds?: int
     *   },
     *   auth?: string
     * }>
     */
    abstract protected function actionMeta(): array;

    public function hasActionFunction(string $functionName): bool
    {
        return isset($this->actionMeta()[$functionName]) && method_exists($this, $functionName);
    }

    public function allowsMethod(string $functionName, string $method): bool
    {
        $meta = $this->actionMeta()[$functionName] ?? null;
        if ($meta === null) {
            return false;
        }

        return in_array(strtoupper($method), $meta[self::META_METHODS], true);
    }

    public function allowsBeforeInstall(string $functionName): bool
    {
        $meta = $this->actionMeta()[$functionName] ?? null;
        if ($meta === null) {
            return false;
        }

        return (bool)($meta[self::META_ALLOW_BEFORE_INSTALL] ?? false);
    }

    public function dispatch(string $functionName): void
    {
        if (!$this->hasActionFunction($functionName)) {
            throw new RuntimeException('Unknown function: ' . $functionName);
        }

        $meta = $this->actionMeta()[$functionName] ?? [];
        $this->beforeDispatch($functionName, is_array($meta) ? $meta : []);

        $this->{$functionName}();
    }

    /**
     * 动作执行前的统一钩子
     *
     * 默认处理声明式限流；子类可覆盖并调用 parent::beforeDispatch()
     * 追加鉴权、审计等横切能力。
     *
     * @param array<string, mixed> $meta
     * @return void
     */
    protected function beforeDispatch(string $functionName, array $meta): void
    {
        $this->applyRateLimit($functionName, $meta);
    }

    /**
     * 构造标准化的限流元数据，供各子模块在 actionMeta 中复用
     *
     * @return array<string, int|string>
     */
    protected function rateLimitMeta(
        string $scope,
        int $maxAttempts,
        int $windowSeconds = self::DEFAULT_RATE_LIMIT_WINDOW_SECONDS,
        ?int $blockSeconds = null
    ): array {
        $safeWindowSeconds = max(1, $windowSeconds);
        $safeBlockSeconds = $blockSeconds === null
            ? $safeWindowSeconds
            : max(1, $blockSeconds);

        return [
            self::RATE_LIMIT_SCOPE => $scope,
            self::RATE_LIMIT_MAX_ATTEMPTS => max(1, $maxAttempts),
            self::RATE_LIMIT_WINDOW_SECONDS => $safeWindowSeconds,
            self::RATE_LIMIT_BLOCK_SECONDS => $safeBlockSeconds,
        ];
    }

    /**
     * 根据 actionMeta 中的 rate_limit 声明执行限流
     *
     * @param array<string, mixed> $meta
     * @return void
     */
    private function applyRateLimit(string $functionName, array $meta): void
    {
        $rateLimit = isset($meta[self::META_RATE_LIMIT]) && is_array($meta[self::META_RATE_LIMIT])
            ? $meta[self::META_RATE_LIMIT]
            : null;
        if ($rateLimit === null) {
            return;
        }

        $maxAttempts = (int)($rateLimit[self::RATE_LIMIT_MAX_ATTEMPTS] ?? 0);
        if ($maxAttempts <= 0) {
            return;
        }

        $windowSeconds = (int)($rateLimit[self::RATE_LIMIT_WINDOW_SECONDS] ?? self::DEFAULT_RATE_LIMIT_WINDOW_SECONDS);
        $blockSeconds = (int)($rateLimit[self::RATE_LIMIT_BLOCK_SECONDS] ?? $windowSeconds);
        $scope = trim((string)($rateLimit[self::RATE_LIMIT_SCOPE] ?? ''));
        if ($scope === '') {
            $scope = strtolower(str_replace('\\', '.', static::class)) . '.' . $functionName;
        }

        $limiter = new RateLimiter(null, $maxAttempts, max(1, $windowSeconds), max(1, $blockSeconds));
        $key = $scope . '|' . Request::clientIp();
        $limiter->ensureAllowed($key);
        $limiter->hit($key);
    }

    /**
     * 获取（或懒加载创建）当前请求的 PDO 实例
     *
     * 同一请求内多次调用 createTicketRepository / createUserRepository 时复用同一连接，
     * 避免频繁建立 TCP 连接。
     *
     * @return PDO
     */
    protected function getPdo(): PDO
    {
        if ($this->cachedPdo === null) {
            $this->cachedPdo = Database::createConfiguredPdo($this->dbConfig);
        }
        return $this->cachedPdo;
    }

    /**
     * 创建工单仓储实例，并在当前请求首次调用时确保表结构存在
     *
     * @return TicketRepository
     */
    protected function createTicketRepository(): TicketRepository
    {
        $repo = new TicketRepository($this->getPdo());
        if (!self::$tablesEnsured) {
            // 同一请求内只执行一次建表检查，避免每次调用都发送 DDL 到数据库
            $repo->createTableIfNotExists();
            self::$tablesEnsured = true;
        }
        return $repo;
    }

    /**
     * 创建用户仓储实例
     *
     * @return UserRepository
     */
    protected function createUserRepository(): UserRepository
    {
        return new UserRepository($this->getPdo());
    }
}