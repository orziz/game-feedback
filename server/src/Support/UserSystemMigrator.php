<?php

declare(strict_types=1);

namespace GameFeedback\Support;

use GameFeedback\Enums\UserRole;
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
        $legacyHash = (string)($dbConfig['admin_password_hash'] ?? '');
        $hasAppSecret = isset($dbConfig['app_secret']) && (string)$dbConfig['app_secret'] !== '';
        $schemaVersion = isset($dbConfig['schema_version']) ? (int)$dbConfig['schema_version'] : 0;
        $needsSchemaMigration = $schemaVersion < SchemaMigrationManager::CURRENT_SCHEMA_VERSION;

        if ($hasAppSecret && $legacyHash === '' && !$needsSchemaMigration) {
            return $dbConfig;
        }

        $newConfig = $dbConfig;
        $configChanged = false;
        $pdo = Database::createConfiguredPdo($dbConfig);
        $schemaMigrationManager = new SchemaMigrationManager($pdo);

        if ($needsSchemaMigration) {
            $newConfig['schema_version'] = $schemaMigrationManager->migrateFromVersion($schemaVersion);
            $configChanged = true;
        }

        if ($legacyHash !== '') {
            $userRepo = new UserRepository($pdo);

            if (!$userRepo->hasSuperAdmin()) {
                $userRepo->insertUser('admin', $legacyHash, UserRole::SuperAdmin);
            }

            unset($newConfig['admin_password_hash']);
            $configChanged = true;
        }

        if (!$hasAppSecret) {
            $newConfig['app_secret'] = bin2hex(random_bytes(32));
            $configChanged = true;
        }

        if ($configChanged) {
            Database::writeConfig($databaseConfigPath, $newConfig);
        }

        return $newConfig;
    }
}
