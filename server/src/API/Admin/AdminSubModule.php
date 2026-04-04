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
    /** @var array<int, string>|null */
    private $qiniuDownloadDomains = null;

    /** @var array<string, mixed>|null 当前动作已鉴权管理员缓存 */
    private $authorizedUser = null;

    /**
     * 管理端动作统一前置处理
     *
     * 先执行基类的限流，再根据 actionMeta 的 auth 声明执行鉴权。
     *
     * @param array<string, mixed> $meta
     * @return void
     */
    protected function beforeDispatch(string $functionName, array $meta): void
    {
        parent::beforeDispatch($functionName, $meta);

        $auth = strtolower((string)($meta[self::META_AUTH] ?? self::AUTH_NONE));
        if ($auth === self::AUTH_ADMIN) {
            $this->authorizedUser = $this->ensureAdmin();
            return;
        }

        if ($auth === self::AUTH_SUPER_ADMIN) {
            $this->authorizedUser = $this->ensureSuperAdmin();
            return;
        }

        $this->authorizedUser = null;
    }

    /**
     * 获取当前动作已鉴权管理员信息
     *
     * 若当前动作未声明 auth=admin/super_admin，则会兜底触发 ensureAdmin。
     *
     * @return array<string, mixed>
     */
    protected function currentAdminUser(): array
    {
        if (is_array($this->authorizedUser)) {
            return $this->authorizedUser;
        }

        $this->authorizedUser = $this->ensureAdmin();
        return $this->authorizedUser;
    }

    /**
     * 校验 Bearer Token，并返回当前管理员信息。
     *
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
     * 校验当前管理员是否具备超级管理员权限。
     *
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

    /**
     * 读取用于令牌签名的应用密钥。
     */
    protected function getAppSecret(): string
    {
        return (string)($this->dbConfig['app_secret'] ?? '');
    }

    /**
     * 基于默认下载域名生成七牛附件下载链接。
     */
    protected function buildQiniuDownloadUrl(string $key, int $ttl = 600, bool $trimPadding = false): string
    {
        $domains = $this->resolveQiniuDownloadDomains();
        if ($domains === []) {
            return '';
        }

        return $this->buildQiniuDownloadUrlForDomain($domains[0], $key, $ttl, $trimPadding);
    }

    /**
     * 基于指定域名生成七牛附件下载链接。
     */
    protected function buildQiniuDownloadUrlForDomain(string $domain, string $key, int $ttl = 600, bool $trimPadding = false): string
    {
        $baseUrl = $this->buildQiniuPublicUrlForDomain($domain, $key);
        if ($baseUrl === '') {
            return '';
        }

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
        foreach ($this->resolveQiniuDownloadDomains() as $domain) {
            $signedUrl = $this->buildQiniuDownloadUrlForDomain($domain, $key, $ttl, false);
            if ($signedUrl !== '' && !in_array($signedUrl, $urls, true)) {
                $urls[] = $signedUrl;
            }

            $trimmedSignedUrl = $this->buildQiniuDownloadUrlForDomain($domain, $key, $ttl, true);
            if ($trimmedSignedUrl !== '' && !in_array($trimmedSignedUrl, $urls, true)) {
                $urls[] = $trimmedSignedUrl;
            }

            $publicUrl = $this->buildQiniuPublicUrlForDomain($domain, $key);
            if ($publicUrl !== '' && !in_array($publicUrl, $urls, true)) {
                $urls[] = $publicUrl;
            }
        }

        return $urls;
    }

    /**
     * 基于默认下载域名生成七牛公开访问链接。
     */
    protected function buildQiniuPublicUrl(string $key): string
    {
        $domains = $this->resolveQiniuDownloadDomains();
        if ($domains === []) {
            return '';
        }

        return $this->buildQiniuPublicUrlForDomain($domains[0], $key);
    }

    /**
     * 基于指定域名生成七牛公开访问链接。
     */
    protected function buildQiniuPublicUrlForDomain(string $domain, string $key): string
    {
        $normalizedDomain = trim($domain);
        if ($normalizedDomain === '') {
            return '';
        }

        if (strpos($normalizedDomain, 'http://') !== 0 && strpos($normalizedDomain, 'https://') !== 0) {
            $normalizedDomain = 'https://' . $normalizedDomain;
        }

        return rtrim($normalizedDomain, '/') . '/' . $this->normalizeQiniuObjectKey($key);
    }

    /**
     * @return array<int, string>
     */
    protected function resolveQiniuDownloadDomains(): array
    {
        if (is_array($this->qiniuDownloadDomains)) {
            return $this->qiniuDownloadDomains;
        }

        $domains = [];
        $configuredDomains = [
            trim((string)($this->dbConfig['qiniu_download_domain'] ?? '')),
            trim((string)($this->dbConfig['qiniu_domain'] ?? '')),
        ];

        foreach ($configuredDomains as $domain) {
            foreach ($this->expandQiniuDomainCandidates($domain) as $candidate) {
                $domains[] = $candidate;
            }
        }

        foreach ($this->queryQiniuBucketDomains() as $domain) {
            foreach ($this->expandQiniuDomainCandidates($domain) as $candidate) {
                $domains[] = $candidate;
            }
        }

        $domains = array_values(array_unique($domains));
        usort($domains, function (string $left, string $right): int {
            $leftScore = $this->isQiniuTestDomain($left) ? 1 : 0;
            $rightScore = $this->isQiniuTestDomain($right) ? 1 : 0;

            if ($leftScore !== $rightScore) {
                return $leftScore <=> $rightScore;
            }

            $leftHttps = strpos($left, 'https://') === 0 ? 0 : 1;
            $rightHttps = strpos($right, 'https://') === 0 ? 0 : 1;
            if ($leftHttps !== $rightHttps) {
                return $leftHttps <=> $rightHttps;
            }

            return 0;
        });

        $this->qiniuDownloadDomains = $domains;
        return $this->qiniuDownloadDomains;
    }

    /**
     * @return array<int, string>
     */
    protected function queryQiniuBucketDomains(): array
    {
        $bucket = trim((string)($this->dbConfig['qiniu_bucket'] ?? ''));
        $accessKey = trim((string)($this->dbConfig['qiniu_access_key'] ?? ''));
        $secretKey = trim((string)($this->dbConfig['qiniu_secret_key'] ?? ''));

        if ($bucket === '' || $accessKey === '' || $secretKey === '' || !function_exists('curl_init')) {
            return [];
        }

        $host = 'uc.qiniuapi.com';
        $pathQuery = '/v2/domains?tbl=' . rawurlencode($bucket);
        $dateHeader = gmdate('Ymd\THis\Z');
        $authorization = $this->buildQiniuManagementAuthorization(
            'GET',
            $host,
            $pathQuery,
            $accessKey,
            $secretKey,
            'application/x-www-form-urlencoded',
            ['X-Qiniu-Date' => $dateHeader]
        );

        $ch = curl_init('https://' . $host . $pathQuery);
        if ($ch === false) {
            return [];
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $authorization,
                'Content-Type: application/x-www-form-urlencoded',
                'X-Qiniu-Date: ' . $dateHeader,
            ],
        ];

        foreach ($this->buildCurlSslOptions() as $option => $value) {
            $options[$option] = $value;
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if (!is_string($response) || $curlErrno !== 0 || $httpCode < 200 || $httpCode >= 300) {
            return [];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [];
        }

        $domains = [];
        foreach ($decoded as $item) {
            if (is_string($item) && trim($item) !== '') {
                $domains[] = trim($item);
                continue;
            }

            if (is_array($item) && isset($item['name']) && is_string($item['name']) && trim($item['name']) !== '') {
                $domains[] = trim($item['name']);
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * 生成七牛管理接口请求所需的签名头。
     */
    protected function buildQiniuManagementAuthorization(
        string $method,
        string $host,
        string $pathQuery,
        string $accessKey,
        string $secretKey,
        string $contentType = '',
        array $headers = [],
        string $body = ''
    ): string {
        $signing = strtoupper($method) . ' ' . $pathQuery . "\n";
        $signing .= 'Host: ' . $host . "\n";

        if ($contentType !== '') {
            $signing .= 'Content-Type: ' . $contentType . "\n";
        }

        if ($headers !== []) {
            uksort($headers, 'strcasecmp');
            foreach ($headers as $name => $value) {
                $canonical = $this->canonicalizeQiniuHeaderName($name);
                $signing .= $canonical . ': ' . trim((string)$value) . "\n";
            }
        }

        $signing .= "\n";
        if ($body !== '' && strtolower($contentType) !== 'application/octet-stream') {
            $signing .= $body;
        }

        $signature = hash_hmac('sha1', $signing, $secretKey, true);
        return 'Qiniu ' . $accessKey . ':' . $this->base64UrlEncode($signature);
    }

    /**
     * 规范化七牛签名头中的自定义请求头名称。
     */
    protected function canonicalizeQiniuHeaderName(string $name): string
    {
        $parts = preg_split('/-+/', trim($name)) ?: [];
        $normalized = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $normalized[] = strtoupper(substr($part, 0, 1)) . strtolower(substr($part, 1));
        }

        return implode('-', $normalized);
    }

    /**
     * @return array<int, string>
     */
    protected function expandQiniuDomainCandidates(string $domain): array
    {
        $normalized = trim($domain);
        if ($normalized === '') {
            return [];
        }

        if (strpos($normalized, 'http://') === 0 || strpos($normalized, 'https://') === 0) {
            return [$normalized];
        }

        return [
            'https://' . $normalized,
            'http://' . $normalized,
        ];
    }

    /**
     * 对七牛对象 key 做路径清洗与分段编码。
     */
    protected function normalizeQiniuObjectKey(string $key): string
    {
        $trimmed = trim($key);
        if ($trimmed === '') {
            return '';
        }

        $segments = preg_split('#/+?#', ltrim($trimmed, '/')) ?: [];
        $encodedSegments = [];
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $encodedSegments[] = rawurlencode(rawurldecode($segment));
        }

        return implode('/', $encodedSegments);
    }

    /**
     * 判断域名是否属于七牛测试域名。
     */
    protected function isQiniuTestDomain(string $domain): bool
    {
        $host = (string)(parse_url($domain, PHP_URL_HOST) ?: $domain);
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }

        return preg_match('/\.(hd-)?bkt\.clouddn\.com$/', $host) === 1
            || preg_match('/\.(hd-)?bkt\.clouddn\.net$/', $host) === 1;
    }

    /**
     * 当仅检测到七牛测试域名时，返回给管理员的配置提示。
     */
    protected function getQiniuDownloadHint(): string
    {
        $domains = $this->resolveQiniuDownloadDomains();
        if ($domains === []) {
            return '';
        }

        foreach ($domains as $domain) {
            if (!$this->isQiniuTestDomain($domain)) {
                return '';
            }
        }

        return ' 当前配置解析到的七牛下载域名都是测试域名，七牛私有空间不能使用测试域名下载。请为 Bucket 绑定自定义源站域名，并将 qiniu_domain 或 qiniu_download_domain 改为该域名。';
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

    /**
     * 读取布尔型配置项，并兼容字符串形式的真值。
     */
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

    /**
     * 将配置中的相对路径解析为项目内绝对路径。
     */
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

    /**
     * 判断给定路径是否已经是绝对路径。
     */
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

    /**
     * 执行 URL 安全的 Base64 编码，可选去掉填充字符。
     */
    protected function base64UrlEncode(string $data, bool $trimPadding = false): string
    {
        $encoded = str_replace(['+', '/'], ['-', '_'], base64_encode($data));
        return $trimPadding ? rtrim($encoded, '=') : $encoded;
    }
}
