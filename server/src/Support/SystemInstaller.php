<?php

declare(strict_types=1);

namespace GameFeedback\Support;

use GameFeedback\Enums\UserRole;
use GameFeedback\Repository\UserRepository;

/**
 * 安装器：负责首次安装时的数据库连通校验、基础表创建和超级管理员初始化。
 */
final class SystemInstaller
{
    /** @var string */
    private $databaseConfigPath;

    /** @var AppInputSanitizer */
    private $sanitizer;

    public function __construct(string $databaseConfigPath, AppInputSanitizer $sanitizer)
    {
        $this->databaseConfigPath = $databaseConfigPath;
        $this->sanitizer = $sanitizer;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function install(array $payload): void
    {
        // 已安装场景直接拦截，避免重复覆盖配置
        if (is_file($this->databaseConfigPath)) {
            Responder::error('ALREADY_INSTALLED', 'The system is already installed.', 409);
        }

        $host = $this->sanitizer->sanitizeSingleLine((string)($payload['host'] ?? ''), 128);
        $port = (int)($payload['port'] ?? 3306);
        $database = $this->sanitizer->sanitizeSingleLine((string)($payload['database'] ?? ''), 64);
        $username = $this->sanitizer->sanitizeSingleLine((string)($payload['username'] ?? ''), 64);
        $password = $this->sanitizer->sanitizeSingleLine((string)($payload['password'] ?? ''), 128);
        $adminUsername = $this->sanitizer->sanitizeSingleLine((string)($payload['adminUsername'] ?? 'admin'), 64);
        $adminPassword = $this->sanitizer->sanitizeSingleLine((string)($payload['adminPassword'] ?? ''), 128);
        $uploadMode = strtolower($this->sanitizer->sanitizeSingleLine((string)($payload['uploadMode'] ?? 'off'), 16));
        $qiniuAccessKey = $this->sanitizer->sanitizeSingleLine((string)($payload['qiniuAccessKey'] ?? ''), 128);
        $qiniuSecretKey = $this->sanitizer->sanitizeSingleLine((string)($payload['qiniuSecretKey'] ?? ''), 128);
        $qiniuBucket = $this->sanitizer->sanitizeSingleLine((string)($payload['qiniuBucket'] ?? ''), 128);
        $qiniuDomain = $this->sanitizer->sanitizeSingleLine((string)($payload['qiniuDomain'] ?? ''), 255);
        $curlCaFile = $this->sanitizer->sanitizeSingleLine((string)($payload['curlCaFile'] ?? ''), 260);
        $curlCaPath = $this->sanitizer->sanitizeSingleLine((string)($payload['curlCaPath'] ?? ''), 260);
        $curlVerifySsl = $this->normalizeBool($payload['curlVerifySsl'] ?? true);
        $curlUseNativeCa = $this->normalizeBool($payload['curlUseNativeCa'] ?? true);

        if ($host === '' || $database === '' || $username === '') {
            Responder::error('INVALID_INSTALL_PAYLOAD', 'Please provide the database connection settings.', 422);
        }

        if ($adminUsername === '') {
            Responder::error('INVALID_INSTALL_PAYLOAD', 'Admin username cannot be empty.', 422);
        }

        if (!preg_match('/^[a-zA-Z0-9_]{2,64}$/', $adminUsername)) {
            Responder::error('INVALID_INSTALL_PAYLOAD', 'Admin username must be 2-64 characters of letters, numbers, or underscores.', 422);
        }

        if ($adminPassword === '') {
            Responder::error('INVALID_INSTALL_PAYLOAD', 'Admin password cannot be empty.', 422);
        }

        if ($this->sanitizer->stringLength($adminPassword) < 8) {
            Responder::error('WEAK_PASSWORD', 'Admin password must be at least 8 characters long.', 422);
        }

        if (!in_array($uploadMode, ['off', 'local', 'qiniu'], true)) {
            Responder::error('INVALID_UPLOAD_MODE', 'Invalid upload mode.', 422);
        }

        if ($uploadMode === 'qiniu' && ($qiniuAccessKey === '' || $qiniuSecretKey === '' || $qiniuBucket === '' || $qiniuDomain === '')) {
            Responder::error('MISSING_QINIU_CONFIG', 'Qiniu mode requires AccessKey, SecretKey, Bucket, and Domain.', 422);
        }

        // 安装流程允许暴露数据库连接错误详情，便于用户修正安装参数
        $pdo = Database::createPdo($host, $port, $database, $username, $password, true);
        $schemaMigrationManager = new SchemaMigrationManager($pdo);

        $schemaMigrationManager->installLatestSchema();

        $userRepo = new UserRepository($pdo);

        $existing = $userRepo->findByUsername($adminUsername);
        if (!$existing) {
            // 首次安装创建超级管理员
            $userRepo->insertUser(
                $adminUsername,
                password_hash($adminPassword, PASSWORD_DEFAULT),
                UserRole::SuperAdmin
            );
        }

        $appSecret = bin2hex(random_bytes(32));

        $databaseConfig = [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'app_secret' => $appSecret,
            'upload_mode' => $uploadMode,
            'upload_max_bytes' => 5 * 1024 * 1024,
            'qiniu_access_key' => $qiniuAccessKey,
            'qiniu_secret_key' => $qiniuSecretKey,
            'qiniu_bucket' => $qiniuBucket,
            'qiniu_domain' => $qiniuDomain,
            'qiniu_direct_access' => false,
            'qiniu_upload_host' => '',
            'qiniu_connect_timeout' => 0,
            'qiniu_upload_timeout' => 0,
            'curl_verify_ssl' => $curlVerifySsl,
            'curl_use_native_ca' => $curlUseNativeCa,
            'curl_ca_file' => $curlCaFile,
            'curl_ca_path' => $curlCaPath,
            'schema_version' => SchemaMigrationManager::CURRENT_SCHEMA_VERSION,
        ];

        // 将安装后的完整配置持久化到 database.php
        Database::writeConfig($this->databaseConfigPath, $databaseConfig);

        Responder::send([
            'ok' => true,
            'message' => 'Installation completed successfully.',
        ]);
    }

    private function normalizeBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
