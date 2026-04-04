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

    /** @var array<int, string> actionMeta 允许出现的键 */
    private const ALLOWED_META_KEYS = [
        self::META_METHODS,
        self::META_ALLOW_BEFORE_INSTALL,
        self::META_RATE_LIMIT,
        self::META_AUTH,
    ];

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
     * 规范化后的动作元数据缓存，避免单次请求重复解析/校验 actionMeta
     *
     * @var array<string, array<string, mixed>>|null
     */
    private $resolvedActionMeta = null;

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

    /**
     * 获取并缓存动作说明，避免同一次请求里反复解析。
     *
     * @return array<string, array<string, mixed>>
     */
    protected function resolvedActionMeta(): array
    {
        if (is_array($this->resolvedActionMeta)) {
            return $this->resolvedActionMeta;
        }

        $this->resolvedActionMeta = $this->validateAndNormalizeActionMeta($this->actionMeta());
        return $this->resolvedActionMeta;
    }

    /**
     * 判断当前子模块里有没有这个动作可执行。
     */
    public function hasActionFunction(string $functionName): bool
    {
        return isset($this->resolvedActionMeta()[$functionName]) && method_exists($this, $functionName);
    }

    /**
     * 看看这个动作是否允许当前请求方法调用。
     */
    public function allowsMethod(string $functionName, string $method): bool
    {
        $meta = $this->resolvedActionMeta()[$functionName] ?? null;
        if ($meta === null) {
            return false;
        }

        return in_array(strtoupper($method), $meta[self::META_METHODS], true);
    }

    /**
     * 看看这个动作在安装完成前能不能先访问。
     */
    public function allowsBeforeInstall(string $functionName): bool
    {
        $meta = $this->resolvedActionMeta()[$functionName] ?? null;
        if ($meta === null) {
            return false;
        }

        return (bool)($meta[self::META_ALLOW_BEFORE_INSTALL] ?? false);
    }

    /**
     * 真正执行目标动作。
     */
    public function dispatch(string $functionName): void
    {
        if (!$this->hasActionFunction($functionName)) {
            throw new RuntimeException('Unknown function: ' . $functionName);
        }

        $meta = $this->resolvedActionMeta()[$functionName] ?? [];
        $this->beforeDispatch($functionName, is_array($meta) ? $meta : []);

        $this->{$functionName}();
    }

    /**
     * 执行动作前先做统一处理。
     *
     * 默认会先处理限流；子类也可以在这里继续补鉴权等逻辑。
     *
     * @param array<string, mixed> $meta
     * @return void
     */
    protected function beforeDispatch(string $functionName, array $meta): void
    {
        $this->applyRateLimit($functionName, $meta);
    }

    /**
     * 生成一份统一格式的限流配置，给 actionMeta 直接复用。
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
     * 按 actionMeta 里的限流规则判断这次请求能不能继续。
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
     * 校验并标准化 actionMeta 结构
     *
     * @param array<string, mixed> $metaMap
     * @return array<string, array<string, mixed>>
     */
    private function validateAndNormalizeActionMeta(array $metaMap): array
    {
        $normalizedMap = [];
        foreach ($metaMap as $action => $meta) {
            $actionName = trim((string)$action);
            if ($actionName === '') {
                throw new RuntimeException('actionMeta contains empty action name');
            }

            if (!method_exists($this, $actionName)) {
                throw new RuntimeException('actionMeta [' . $actionName . '] points to undefined method');
            }

            if (!is_array($meta)) {
                throw new RuntimeException('actionMeta for [' . $actionName . '] must be an array');
            }

            $this->assertNoUnknownMetaKeys($actionName, $meta);

            $methods = $this->normalizeMethodsMeta($actionName, $meta);
            $normalized = [
                self::META_METHODS => $methods,
            ];

            if (array_key_exists(self::META_ALLOW_BEFORE_INSTALL, $meta)) {
                $normalized[self::META_ALLOW_BEFORE_INSTALL] = (bool)$meta[self::META_ALLOW_BEFORE_INSTALL];
            }

            if (array_key_exists(self::META_AUTH, $meta)) {
                $normalized[self::META_AUTH] = $this->normalizeAuthMeta($actionName, $meta[self::META_AUTH]);
            }

            if (array_key_exists(self::META_RATE_LIMIT, $meta)) {
                $normalized[self::META_RATE_LIMIT] = $this->normalizeRateLimitMeta($actionName, $meta[self::META_RATE_LIMIT]);
            }

            $normalizedMap[$actionName] = $normalized;
        }

        return $normalizedMap;
    }

    /**
     * 校验 actionMeta 是否包含未知键，避免拼写错误被静默忽略
     *
     * @param array<string, mixed> $meta
     * @return void
     */
    private function assertNoUnknownMetaKeys(string $actionName, array $meta): void
    {
        foreach (array_keys($meta) as $key) {
            $metaKey = (string)$key;
            if (!in_array($metaKey, self::ALLOWED_META_KEYS, true)) {
                throw new RuntimeException('actionMeta [' . $actionName . '] has unknown key: ' . $metaKey);
            }
        }
    }

    /**
     * 标准化 methods 元数据
     *
     * @param array<string, mixed> $meta
     * @return array<int, string>
     */
    private function normalizeMethodsMeta(string $actionName, array $meta): array
    {
        $methods = $meta[self::META_METHODS] ?? null;
        if (!is_array($methods) || $methods === []) {
            throw new RuntimeException('actionMeta [' . $actionName . '] requires non-empty methods');
        }

        $normalized = [];
        foreach ($methods as $method) {
            $methodName = strtoupper(trim((string)$method));
            if ($methodName === '') {
                continue;
            }
            $normalized[$methodName] = $methodName;
        }

        if ($normalized === []) {
            throw new RuntimeException('actionMeta [' . $actionName . '] has no valid methods');
        }

        return array_values($normalized);
    }

    /**
     * 标准化 auth 元数据
     */
    private function normalizeAuthMeta(string $actionName, $auth): string
    {
        $authValue = strtolower(trim((string)$auth));
        if ($authValue === '') {
            return self::AUTH_NONE;
        }

        if (!in_array($authValue, [self::AUTH_NONE, self::AUTH_ADMIN, self::AUTH_SUPER_ADMIN], true)) {
            throw new RuntimeException('actionMeta [' . $actionName . '] has invalid auth: ' . $authValue);
        }

        return $authValue;
    }

    /**
     * 标准化 rate_limit 元数据
     *
     * @param mixed $rateLimit
     * @return array<string, int|string>
     */
    private function normalizeRateLimitMeta(string $actionName, $rateLimit): array
    {
        if (!is_array($rateLimit)) {
            throw new RuntimeException('actionMeta [' . $actionName . '] rate_limit must be an array');
        }

        $maxAttempts = (int)($rateLimit[self::RATE_LIMIT_MAX_ATTEMPTS] ?? 0);
        if ($maxAttempts <= 0) {
            throw new RuntimeException('actionMeta [' . $actionName . '] rate_limit.max_attempts must be > 0');
        }

        $windowSeconds = (int)($rateLimit[self::RATE_LIMIT_WINDOW_SECONDS] ?? self::DEFAULT_RATE_LIMIT_WINDOW_SECONDS);
        $blockSeconds = (int)($rateLimit[self::RATE_LIMIT_BLOCK_SECONDS] ?? $windowSeconds);
        $scope = trim((string)($rateLimit[self::RATE_LIMIT_SCOPE] ?? ''));

        return [
            self::RATE_LIMIT_SCOPE => $scope,
            self::RATE_LIMIT_MAX_ATTEMPTS => $maxAttempts,
            self::RATE_LIMIT_WINDOW_SECONDS => max(1, $windowSeconds),
            self::RATE_LIMIT_BLOCK_SECONDS => max(1, $blockSeconds),
        ];
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