<?php

declare(strict_types=1);

namespace GameFeedback\API;

use GameFeedback\Repository\TicketRepository;
use GameFeedback\Repository\GameRepository;
use GameFeedback\Repository\UserRepository;
use GameFeedback\Support\AppInputSanitizer;
use GameFeedback\Support\Database;
use GameFeedback\Support\Request;
use GameFeedback\Support\Responder;
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
        $repo->createTableIfNotExists();

        return $repo;
    }

    protected function createUserRepository(): UserRepository
    {
        $repo = new UserRepository(Database::createConfiguredPdo($this->dbConfig));
        $repo->createTableIfNotExists();

        return $repo;
    }

    protected function createGameRepository(): GameRepository
    {
        $repo = new GameRepository(Database::createConfiguredPdo($this->dbConfig));
        $repo->createTableIfNotExists();
        $repo->ensureDefaultGame();

        return $repo;
    }

    protected function resolveGameKey(bool $required = true): ?string
    {
        $gameKey = $this->sanitizer->sanitizeSingleLine(Request::query('gameKey'), 64);
        if ($gameKey === '' && Request::method() !== 'GET') {
            $payload = Request::isMultipartFormData() ? Request::formBody() : Request::jsonBody();
            $gameKey = $this->sanitizer->sanitizeSingleLine((string)($payload['gameKey'] ?? ''), 64);
        }

        if ($gameKey === '') {
            if ($required) {
                Responder::error('MISSING_GAME_KEY', '缺少 gameKey。', 422);
            }

            return null;
        }

        if (preg_match('/^[a-z][a-z0-9_\-]{1,63}$/', $gameKey) !== 1) {
            Responder::error('INVALID_GAME_KEY', 'gameKey 仅支持小写字母、数字、下划线和短横线。', 422);
        }

        return $gameKey;
    }

    /**
     * @return array<string, mixed>
     */
    protected function ensureEnabledGame(string $gameKey): array
    {
        $game = $this->ensureGameExists($gameKey);
        if ((int)($game['is_enabled'] ?? 0) !== 1) {
            Responder::error('GAME_DISABLED', '该游戏反馈入口已关闭。', 403);
        }

        return $game;
    }

    /**
     * @return array<string, mixed>
     */
    protected function ensureGameExists(string $gameKey): array
    {
        $game = $this->createGameRepository()->findByKey($gameKey);
        if (!$game) {
            Responder::error('GAME_NOT_FOUND', '游戏不存在。', 404);
        }

        return $game;
    }
}
