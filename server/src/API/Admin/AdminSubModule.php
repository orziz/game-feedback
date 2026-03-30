<?php

declare(strict_types=1);

namespace GameFeedback\API\Admin;

use GameFeedback\API\BaseApiSubModule;
use GameFeedback\Enums\UserRole;
use GameFeedback\Support\AdminToken;
use GameFeedback\Support\Request;
use GameFeedback\Support\Responder;

abstract class AdminSubModule extends BaseApiSubModule
{
    /**
     * @return array<string, mixed>
     */
    protected function ensureAdmin(): array
    {
        $authHeader = Request::authorizationHeader();
        if (strpos($authHeader, 'Bearer ') !== 0) {
            Responder::error('UNAUTHORIZED', '缺少管理员身份信息。', 401);
        }

        $token = trim(substr($authHeader, 7));
        $tokenPayload = AdminToken::verify($token, $this->getAppSecret());
        if ($tokenPayload === false) {
            Responder::error('UNAUTHORIZED', '管理员身份验证失败。', 401);
        }

        $user = $this->createUserRepository()->findById((int)$tokenPayload['userId']);
        if (!$user) {
            Responder::error('UNAUTHORIZED', '用户不存在。', 401);
        }

        $expectedPasswordMarker = AdminToken::buildPasswordMarker((string)($user['password_hash'] ?? ''), $this->getAppSecret());
        if (!hash_equals($expectedPasswordMarker, (string)$tokenPayload['passwordMarker'])) {
            Responder::error('UNAUTHORIZED', '管理员会话已失效，请重新登录。', 401);
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function ensureSuperAdmin(): array
    {
        $user = $this->ensureAdmin();
        if (($user['role'] ?? '') !== UserRole::SuperAdmin) {
            Responder::error('FORBIDDEN', '仅超级管理员可执行此操作。', 403);
        }

        return $user;
    }

    protected function getAppSecret(): string
    {
        return (string)($this->dbConfig['app_secret'] ?? '');
    }

    protected function buildQiniuDownloadUrl(string $key, int $ttl = 600, bool $trimPadding = false): string
    {
        $domain = trim((string)($this->dbConfig['qiniu_domain'] ?? ''));
        if ($domain === '') {
            return '';
        }

        if (strpos($domain, 'http://') !== 0 && strpos($domain, 'https://') !== 0) {
            $domain = 'https://' . $domain;
        }

        $baseUrl = rtrim($domain, '/') . '/' . ltrim($key, '/');
        $accessKey = trim((string)($this->dbConfig['qiniu_access_key'] ?? ''));
        $secretKey = trim((string)($this->dbConfig['qiniu_secret_key'] ?? ''));

        if ($accessKey === '' || $secretKey === '') {
            return $baseUrl;
        }

        $deadline = time() + max(60, $ttl);
        $separator = strpos($baseUrl, '?') === false ? '?' : '&';
        $unsignedUrl = $baseUrl . $separator . 'e=' . $deadline;
        $sign = hash_hmac('sha1', $unsignedUrl, $secretKey, true);
        $encodedSign = $this->base64UrlEncode($sign, $trimPadding);

        return $unsignedUrl . '&token=' . $accessKey . ':' . $encodedSign;
    }

    /**
     * @return array<int, string>
     */
    protected function buildQiniuDownloadUrlVariants(string $key, int $ttl = 600): array
    {
        $urls = [];

        $signedUrl = $this->buildQiniuDownloadUrl($key, $ttl, false);
        if ($signedUrl !== '') {
            $urls[] = $signedUrl;
        }

        $trimmedSignedUrl = $this->buildQiniuDownloadUrl($key, $ttl, true);
        if ($trimmedSignedUrl !== '' && !in_array($trimmedSignedUrl, $urls, true)) {
            $urls[] = $trimmedSignedUrl;
        }

        $publicUrl = $this->buildQiniuPublicUrl($key);
        if ($publicUrl !== '' && !in_array($publicUrl, $urls, true)) {
            $urls[] = $publicUrl;
        }

        return $urls;
    }

    protected function buildQiniuPublicUrl(string $key): string
    {
        $domain = trim((string)($this->dbConfig['qiniu_domain'] ?? ''));
        if ($domain === '') {
            return '';
        }

        if (strpos($domain, 'http://') !== 0 && strpos($domain, 'https://') !== 0) {
            $domain = 'https://' . $domain;
        }

        return rtrim($domain, '/') . '/' . ltrim($key, '/');
    }

    /**
     * @return array<int, bool|int|string>
     */
    protected function buildCurlSslOptions(): array
    {
        $verifySsl = $this->configFlag('curl_verify_ssl', true);
        if (!$verifySsl) {
            return [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ];
        }

        $options = [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        $caFile = $this->resolveConfigPath('curl_ca_file');
        if ($caFile !== '' && is_file($caFile) && is_readable($caFile) && defined('CURLOPT_CAINFO')) {
            $options[CURLOPT_CAINFO] = $caFile;
        }

        $caPath = $this->resolveConfigPath('curl_ca_path');
        if ($caPath !== '' && is_dir($caPath) && is_readable($caPath) && defined('CURLOPT_CAPATH')) {
            $options[CURLOPT_CAPATH] = $caPath;
        }

        if ($this->configFlag('curl_use_native_ca', true)
            && defined('CURLOPT_SSL_OPTIONS')
            && defined('CURLSSLOPT_NATIVE_CA')
        ) {
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }

        return $options;
    }

    protected function configFlag(string $key, bool $default): bool
    {
        if (!array_key_exists($key, $this->dbConfig)) {
            return $default;
        }

        $value = $this->dbConfig[$key];
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    protected function resolveConfigPath(string $key): string
    {
        $value = trim((string)($this->dbConfig[$key] ?? ''));
        if ($value === '') {
            return '';
        }

        if ($this->isAbsolutePath($value)) {
            return $value;
        }

        return dirname(__DIR__, 3) . '/' . ltrim(str_replace('\\', '/', $value), '/');
    }

    protected function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    protected function base64UrlEncode(string $data, bool $trimPadding = false): string
    {
        $encoded = str_replace(['+', '/'], ['-', '_'], base64_encode($data));
        return $trimPadding ? rtrim($encoded, '=') : $encoded;
    }
}
