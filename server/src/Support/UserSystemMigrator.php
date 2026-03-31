<?php

declare(strict_types=1);

namespace GameFeedback\Support;

use GameFeedback\Enums\UserRole;
use GameFeedback\Repository\GameRepository;
use GameFeedback\Repository\TicketRepository;
use GameFeedback\Repository\UserRepository;


/**
 * 用户系统迁移器
 *
 * 将旧版单一 admin_password_hash 配置迁移到 admin_users + app_secret 架构
 */
final class UserSystemMigrator
{
    /**
     * 迁移到用户表认证架构
     *
     * @param array<string, mixed> $dbConfig
     * @return array<string, mixed>
     */
    public static function migrate(array $dbConfig, string $databaseConfigPath): array
    {
        $hasAppSecret = isset($dbConfig['app_secret']) && (string)$dbConfig['app_secret'] !== '';
        if ($hasAppSecret) {
            // app_secret 已存在，只需补齐可能缺失的表结构（幂等）
            $pdo = Database::createConfiguredPdo($dbConfig);
            (new TicketRepository($pdo))->migrateSchema();
            $gameRepo = new GameRepository($pdo);
            $gameRepo->createTableIfNotExists();
            $gameRepo->ensureDefaultGame();
            return $dbConfig;
        }

        $newConfig = $dbConfig;

        $legacyHash = (string)($dbConfig['admin_password_hash'] ?? '');
        if ($legacyHash !== '') {
            $pdo = Database::createConfiguredPdo($dbConfig);
            $userRepo = new UserRepository($pdo);
            $userRepo->createTableIfNotExists();

            if (!$userRepo->hasSuperAdmin()) {
                $userRepo->insertUser('admin', $legacyHash, UserRole::SuperAdmin);
            }

            unset($newConfig['admin_password_hash']);
        }

        $newConfig['app_secret'] = bin2hex(random_bytes(32));
        Database::writeConfig($databaseConfigPath, $newConfig);

        $pdo = Database::createConfiguredPdo($newConfig);
        (new TicketRepository($pdo))->migrateSchema();
        $gameRepo = new GameRepository($pdo);
        $gameRepo->createTableIfNotExists();
        $gameRepo->ensureDefaultGame();

        return $newConfig;
    }
}
