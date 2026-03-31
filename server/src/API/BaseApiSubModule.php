<?php

declare(strict_types=1);

namespace GameFeedback\API;

use GameFeedback\Repository\TicketRepository;
use GameFeedback\Repository\UserRepository;
use GameFeedback\Support\AppInputSanitizer;
use GameFeedback\Support\Database;
use RuntimeException;


/**
 * API 子模块基类
 */
abstract class BaseApiSubModule
{
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
     * @return array<string, array{methods: array<int, string>, allow_before_install?: bool}>
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

        return in_array(strtoupper($method), $meta['methods'], true);
    }

    public function allowsBeforeInstall(string $functionName): bool
    {
        $meta = $this->actionMeta()[$functionName] ?? null;
        if ($meta === null) {
            return false;
        }

        return (bool)($meta['allow_before_install'] ?? false);
    }

    public function dispatch(string $functionName): void
    {
        if (!$this->hasActionFunction($functionName)) {
            throw new RuntimeException('Unknown function: ' . $functionName);
        }

        $this->{$functionName}();
    }

    protected function createTicketRepository(): TicketRepository
    {
        $repo = new TicketRepository(Database::createConfiguredPdo($this->dbConfig));
        // 确保表结构存在并完整，包括 ticket_operations 操作记录表
        $repo->createTableIfNotExists();
        return $repo;
    }

    protected function createUserRepository(): UserRepository
    {
        return new UserRepository(Database::createConfiguredPdo($this->dbConfig));
    }
}
