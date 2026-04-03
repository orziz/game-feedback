<?php

declare(strict_types=1);

namespace GameFeedback\Support;

final class RuntimeConfig
{
    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function overlayAppConfig(array $config): array
    {
        $result = $config;

        $csv = self::envCsv('APP_CORS_ALLOWED_ORIGINS');
        if ($csv !== null) {
            $result['cors_allowed_origins'] = $csv;
        }

        $allowLocalhostCors = self::envBool('APP_ALLOW_LOCALHOST_CORS');
        if ($allowLocalhostCors !== null) {
            $result['allow_localhost_cors'] = $allowLocalhostCors;
        }

        $timezone = self::envString('APP_TIMEZONE');
        if ($timezone !== null) {
            $result['timezone'] = $timezone;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function overlayDatabaseConfig(array $config): array
    {
        $result = $config;

        self::applyString($result, 'host', 'APP_DB_HOST');
        self::applyPositiveInt($result, 'port', 'APP_DB_PORT');
        self::applyString($result, 'database', 'APP_DB_DATABASE');
        self::applyString($result, 'username', 'APP_DB_USERNAME');
        self::applyString($result, 'password', 'APP_DB_PASSWORD');

        $uploadMode = self::envEnum('APP_UPLOAD_MODE', ['off', 'local', 'qiniu']);
        if ($uploadMode !== null) {
            $result['upload_mode'] = $uploadMode;
        }

        $uploadMaxBytes = self::envPositiveInt('APP_UPLOAD_MAX_BYTES');
        if ($uploadMaxBytes !== null) {
            $result['upload_max_bytes'] = $uploadMaxBytes;
        }

        self::applyString($result, 'qiniu_access_key', 'APP_QINIU_ACCESS_KEY');
        self::applyString($result, 'qiniu_secret_key', 'APP_QINIU_SECRET_KEY');
        self::applyString($result, 'qiniu_bucket', 'APP_QINIU_BUCKET');
        self::applyString($result, 'qiniu_domain', 'APP_QINIU_DOMAIN');
        self::applyString($result, 'qiniu_download_domain', 'APP_QINIU_DOWNLOAD_DOMAIN');
        self::applyString($result, 'qiniu_upload_host', 'APP_QINIU_UPLOAD_HOST');
        self::applyString($result, 'curl_ca_file', 'APP_CURL_CA_FILE');
        self::applyString($result, 'curl_ca_path', 'APP_CURL_CA_PATH');

        self::applyBool($result, 'qiniu_direct_access', 'APP_QINIU_DIRECT_ACCESS');
        self::applyBool($result, 'curl_verify_ssl', 'APP_CURL_VERIFY_SSL');
        self::applyBool($result, 'curl_use_native_ca', 'APP_CURL_USE_NATIVE_CA');

        self::applyNonNegativeInt($result, 'qiniu_connect_timeout', 'APP_QINIU_CONNECT_TIMEOUT');
        self::applyNonNegativeInt($result, 'qiniu_upload_timeout', 'APP_QINIU_UPLOAD_TIMEOUT');

        return $result;
    }

    /**
     * @param array<string, mixed> $config
     * @return void
     */
    private static function applyString(array &$config, string $key, string $envName): void
    {
        $value = self::envString($envName);
        if ($value !== null) {
            $config[$key] = $value;
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return void
     */
    private static function applyBool(array &$config, string $key, string $envName): void
    {
        $value = self::envBool($envName);
        if ($value !== null) {
            $config[$key] = $value;
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return void
     */
    private static function applyPositiveInt(array &$config, string $key, string $envName): void
    {
        $value = self::envPositiveInt($envName);
        if ($value !== null) {
            $config[$key] = $value;
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return void
     */
    private static function applyNonNegativeInt(array &$config, string $key, string $envName): void
    {
        $value = self::envNonNegativeInt($envName);
        if ($value !== null) {
            $config[$key] = $value;
        }
    }

    /**
     * @return string|null
     */
    private static function envRaw(string $name)
    {
        $value = getenv($name);
        if ($value !== false) {
            return is_string($value) ? $value : null;
        }

        if (isset($_SERVER[$name]) && is_string($_SERVER[$name])) {
            return $_SERVER[$name];
        }

        return null;
    }

    /**
     * @return string|null
     */
    private static function envString(string $name)
    {
        $value = self::envRaw($name);
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return bool|null
     */
    private static function envBool(string $name)
    {
        $value = self::envString($name);
        if ($value === null) {
            return null;
        }

        $normalized = strtolower($value);
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return null;
    }

    /**
     * @return int|null
     */
    private static function envPositiveInt(string $name)
    {
        $value = self::envString($name);
        if ($value === null || !preg_match('/^\d+$/', $value)) {
            return null;
        }

        $parsed = (int)$value;
        return $parsed > 0 ? $parsed : null;
    }

    /**
     * @return int|null
     */
    private static function envNonNegativeInt(string $name)
    {
        $value = self::envString($name);
        if ($value === null || !preg_match('/^\d+$/', $value)) {
            return null;
        }

        return (int)$value;
    }

    /**
     * @param array<int, string> $allowed
     * @return string|null
     */
    private static function envEnum(string $name, array $allowed)
    {
        $value = self::envString($name);
        if ($value === null) {
            return null;
        }

        $normalized = strtolower($value);
        return in_array($normalized, $allowed, true) ? $normalized : null;
    }

    /**
     * @return array<int, string>|null
     */
    private static function envCsv(string $name)
    {
        $value = self::envRaw($name);
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $parts = preg_split('/\s*,\s*/', $trimmed);
        if (!is_array($parts)) {
            return [];
        }

        $items = [];
        foreach ($parts as $part) {
            $item = trim($part);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }
}
