<?php

declare(strict_types=1);

namespace GameFeedback\Support;

use GameFeedback\Enums\UserRole;
use GameFeedback\Repository\UserRepository;

/**
 * 用户系统迁移器
 *
 * 将旧版单一 admin_password_hash 配置迁移到 admin_users + app_secret 架构。
 * 正常运行时通过 needsMigration() 快速路径判断，无需创建 PDO；
 * 仅当确实有迁移任务时才调用 migrate()。
 */
final class UserSystemMigrator
{
    /**
     * 判断当前配置是否需要执行迁移（纯内存操作，不创建 PDO）
     *
     * 在 App::run() 调用前执行快速检查，避免每次请求都进入迁移函数。
     *
     * @param array<string, mixed> $dbConfig database.php 返回的配置数组
     * @return bool
     */
    public static function needsMigration(array $dbConfig): bool
    {
        $legacyHash = (string)($dbConfig['admin_password_hash'] ?? '');
        $hasAppSecret = isset($dbConfig['app_secret']) && (string)$dbConfig['app_secret'] !== '';
        $schemaVersion = isset($dbConfig['schema_version']) ? (int)$dbConfig['schema_version'] : 0;
        return $legacyHash !== '' || !$hasAppSecret || $schemaVersion < SchemaMigrationManager::CURRENT_SCHEMA_VERSION;
    }

    /**
     * 执行迁移到用户表认证架构
     *
     * 建议先用 needsMigration() 检查后再调用本方法，避免不必要的 PDO 创建。
     *
     * @param array<string, mixed> $dbConfig
     * @param string $databaseConfigPath
     * @return array<string, mixed> 迁移后（可能已更新）的配置数组
     */
    public static function migrate(array $dbConfig, string $databaseConfigPath): array
    {
        $legacyHash = (string)($dbConfig['admin_password_hash'] ?? '');
        $hasAppSecret = isset($dbConfig['app_secret']) && (string)$dbConfig['app_secret'] !== '';
        $schemaVersion = isset($dbConfig['schema_version']) ? (int)$dbConfig['schema_version'] : 0;
        $needsSchemaMigration = $schemaVersion < SchemaMigrationManager::CURRENT_SCHEMA_VERSION;

        // 内部快速路径：无需迁移时直接返回（由调用方的 needsMigration() 提前保障）
        if ($hasAppSecret && $legacyHash === '' && !$needsSchemaMigration) {
            return $dbConfig;
        }

        $newConfig = $dbConfig;
        $configChanged = false;
        $pdo = Database::createConfiguredPdo($dbConfig);
        Database::ensureSupportedServer($pdo, false);
        $schemaMigrationManager = new SchemaMigrationManager($pdo);

        if ($needsSchemaMigration) {
            // 执行增量 DDL 迁移并更新版本号
            $newConfig['schema_version'] = $schemaMigrationManager->migrateFromVersion($schemaVersion);
            $configChanged = true;
        }

        if ($legacyHash !== '') {
            // 将旧版密码哈希迁移为 admin_users 表中的超级管理员账号
            $userRepo = new UserRepository($pdo);
            if (!$userRepo->hasSuperAdmin()) {
                $userRepo->insertUser('admin', $legacyHash, UserRole::SuperAdmin);
            }
            unset($newConfig['admin_password_hash']);
            $configChanged = true;
        }

        if (!$hasAppSecret) {
            // 生成 app_secret 用于 JWT 签名
            $newConfig['app_secret'] = bin2hex(random_bytes(32));
            $configChanged = true;
        }

        if ($configChanged) {
            Database::writeConfig($databaseConfigPath, $newConfig);
        }

        return $newConfig;
    }
}