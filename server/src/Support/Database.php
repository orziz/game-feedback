<?php

declare(strict_types=1);

namespace GameFeedback\Support;

use PDO;
use Throwable;

/**
 * 数据库连接与配置工具类
 *
 * 提供 PDO 实例创建和 database.php 配置文件写入
 */
final class Database
{
    /**
     * 根据连接参数创建 PDO 实例
     *
     * @param string $host        数据库主机
     * @param int    $port        端口
     * @param string $database    库名
     * @param string $username    用户名
     * @param string $password    密码
     * @param bool   $exposeError 是否在响应中暴露详细错误（安装流程传 true，运行态传 false）
     * @return PDO
     */
    public static function createPdo(string $host, int $port, string $database, string $username, string $password, bool $exposeError = true): PDO
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $e) {
            if ($exposeError) {
                // 安装流程中允许输出详细错误，方便诊断连接参数
                $message = '数据库连接失败：' . $e->getMessage();
            } else {
                // 运行时不对外暴露内部错误信息，防止泄露主机/用户名等拓扑信息
                error_log('[DB] Connection failed: ' . $e->getMessage());
                $message = '数据库连接失败，请稍后重试。';
            }
            Responder::send([
                'ok' => false,
                'code' => 'DB_CONNECT_FAILED',
                'message' => $message,
            ], 500);
            exit;
        }
    }

    /**
     * 从已加载的配置数组创建 PDO 实例（运行态，不对外暴露连接错误详情）
     *
     * @param array<string, mixed> $dbConfig database.php 返回的配置数组
     * @return PDO
     */
    public static function createConfiguredPdo(array $dbConfig): PDO
    {
        return self::createPdo(
            (string)$dbConfig['host'],
            (int)$dbConfig['port'],
            (string)$dbConfig['database'],
            (string)$dbConfig['username'],
            (string)$dbConfig['password'],
            false // 运行时不对外暴露连接错误详情
        );
    }

    /**
     * 将数据库配置写入 PHP 文件（含双语注释）
     *
     * @param string               $path           配置文件绝对路径
     * @param array<string, mixed> $databaseConfig 配置数据
     * @return void
     */
    public static function writeConfig(string $path, array $databaseConfig): void
    {
        $content = self::buildConfigContent($databaseConfig);
        $result = @file_put_contents($path, $content);
        if ($result === false) {
            Responder::send([
                'ok' => false,
                'code' => 'CONFIG_WRITE_FAILED',
                'message' => '无法写入配置文件，请检查目录权限：' . $path,
            ], 500);
        }
    }

    /**
     * 生成带双语注释的配置文件内容（使用 heredoc 模板）
     *
     * 已知键按分组顺序输出并附带说明注释；未知键统一追加在末尾。
     *
     * @param array<string, mixed> $config
     * @return string
     */
    private static function buildConfigContent(array $config): string
    {
        // 先把每个值序列化成 PHP 字面量，再插入模板
        $v = function (string $key, $default = '') use ($config) {
            return self::exportValue(array_key_exists($key, $config) ? $config[$key] : $default);
        };

        $host             = $v('host');
        $port             = $v('port', 3306);
        $database         = $v('database');
        $username         = $v('username');
        $password         = $v('password');
        $appSecret        = $v('app_secret');
        $uploadMode       = $v('upload_mode', 'off');
        $uploadMaxBytes   = $v('upload_max_bytes', 5242880);
        $qiniuAccessKey   = $v('qiniu_access_key');
        $qiniuSecretKey   = $v('qiniu_secret_key');
        $qiniuBucket      = $v('qiniu_bucket');
        $qiniuDomain      = $v('qiniu_domain');
        $qiniuDirectAccess = $v('qiniu_direct_access', false);
        $qiniuUploadHost  = $v('qiniu_upload_host');
        $qiniuConnTimeout = self::exportValue((int)($config['qiniu_connect_timeout'] ?? 0));
        $qiniuUpTimeout   = self::exportValue((int)($config['qiniu_upload_timeout'] ?? 0));
        $curlVerifySsl    = $v('curl_verify_ssl', true);
        $curlUseNativeCa  = $v('curl_use_native_ca', true);
        $curlCaFile       = $v('curl_ca_file');
        $curlCaPath       = $v('curl_ca_path');
        $schemaVersion    = $v('schema_version', 0);

        // 追加 qiniu_download_domain（可选键，仅在存在时输出）
        $extraQiniuDomain = '';
        if (array_key_exists('qiniu_download_domain', $config)) {
            $val = self::exportValue($config['qiniu_download_domain']);
            $extraQiniuDomain = "\n    'qiniu_download_domain' => {$val},";
        }

        // 追加其他未知键
        $knownKeys = [
            'host', 'port', 'database', 'username', 'password', 'app_secret',
            'upload_mode', 'upload_max_bytes',
            'qiniu_access_key', 'qiniu_secret_key', 'qiniu_bucket',
            'qiniu_domain', 'qiniu_download_domain', 'qiniu_direct_access',
            'qiniu_upload_host', 'qiniu_connect_timeout', 'qiniu_upload_timeout',
            'curl_verify_ssl', 'curl_use_native_ca', 'curl_ca_file', 'curl_ca_path',
            'schema_version',
        ];
        $extraLines = '';
        foreach (array_diff_key($config, array_flip($knownKeys)) as $key => $value) {
            $extraLines .= "\n    " . self::pair((string)$key, $value) . ',';
        }
        if ($extraLines !== '') {
            $extraLines = "\n\n    // ── 其他配置 / Additional configuration ──────────────────────────────────────"
                . $extraLines;
        }

        return <<<PHP
<?php

declare(strict_types=1);

// 数据库与存储相关配置（由安装器生成，可在此手动调整后重启生效）
// Database and storage configuration (generated by installer; editable manually)

return [

    // ── 数据库连接 / Database connection ──────────────────────────────────────────
    'host'     => {$host},
    'port'     => {$port},
    'database' => {$database},
    'username' => {$username},
    'password' => {$password},

    // 用于 JWT 签名与会话安全，请勿泄露或手动修改
    // Used for JWT signing and session security — keep confidential, do not modify manually
    'app_secret' => {$appSecret},

    // ── 附件上传 / Attachment upload ──────────────────────────────────────────────
    // 上传模式：off=关闭 | local=本地磁盘 | qiniu=七牛云
    // Upload mode: off=disabled | local=local disk | qiniu=Qiniu Cloud
    'upload_mode' => {$uploadMode},
    // 附件最大大小（字节），默认 5 MB = 5242880
    // Maximum attachment size in bytes; default 5 MB = 5242880
    'upload_max_bytes' => {$uploadMaxBytes},

    // ── 七牛云 / Qiniu Cloud ───────────────────────────────────────────────────────
    // upload_mode=qiniu 时必填 / Required when upload_mode=qiniu
    'qiniu_access_key' => {$qiniuAccessKey},
    'qiniu_secret_key' => {$qiniuSecretKey},
    'qiniu_bucket'     => {$qiniuBucket},
    // 七牛云访问域名，用于生成文件下载 URL（例如 https://cdn.example.com）
    // Qiniu domain for generating download URLs, e.g. https://cdn.example.com
    'qiniu_domain' => {$qiniuDomain},{$extraQiniuDomain}
    // 是否允许管理端附件直接从七牛 CDN 加载（绕过后端代理）
    // true  — 图片预览/下载直连七牛 CDN，速度更快，但 CDN 地址对客户端可见
    // false — 所有附件请求通过后端代理转发，隐藏 CDN 地址（默认）
    // Enable Qiniu direct-access for admin attachments (bypass backend proxy)
    // true  — images/files load directly from the Qiniu CDN (faster, CDN URL visible to client)
    // false — all requests are proxied through the backend; CDN URL stays hidden (default)
    'qiniu_direct_access' => {$qiniuDirectAccess},
    // 七牛云上传节点，逗号分隔；留空使用内置默认节点（up.qiniup.com 等）
    // Qiniu upload endpoint(s), comma-separated; leave empty for built-in defaults (up.qiniup.com, etc.)
    'qiniu_upload_host' => {$qiniuUploadHost},
    // 七牛云 cURL 连接超时（秒），0 = 使用默认值 8 秒
    // Qiniu cURL connection timeout in seconds; 0 = use default (8 s)
    'qiniu_connect_timeout' => {$qiniuConnTimeout},
    // 七牛云 cURL 上传总超时（秒），0 = 使用默认值 45 秒
    // Qiniu cURL total upload timeout in seconds; 0 = use default (45 s)
    'qiniu_upload_timeout' => {$qiniuUpTimeout},

    // ── cURL / SSL ─────────────────────────────────────────────────────────────────
    // 是否验证 SSL 证书；生产环境保持 true，仅在无法配置 CA 证书时临时设为 false
    // Verify SSL certificates — keep true in production; disable only as a temporary workaround
    'curl_verify_ssl' => {$curlVerifySsl},
    // 使用操作系统原生 CA 证书库；Windows 下 PHP 通常需要开启
    // Use the native OS CA store; usually needed for PHP on Windows
    'curl_use_native_ca' => {$curlUseNativeCa},
    // 自定义 CA 证书文件路径；留空使用系统默认（支持绝对路径或相对项目根目录的路径）
    // Path to a custom CA bundle file; leave empty for the system default
    'curl_ca_file' => {$curlCaFile},
    // 自定义 CA 证书目录路径；留空使用系统默认
    // Path to a custom CA certificate directory; leave empty for the system default
    'curl_ca_path' => {$curlCaPath},

    // 数据库 Schema 版本号，由系统自动维护，请勿手动修改
    // Database schema version — managed automatically by the system, do not edit manually
    'schema_version' => {$schemaVersion},{$extraLines}
];

PHP;
    }

    /**
     * 序列化单个配置条目为 'key' => value 字符串
     *
     * @param string $key
     * @param mixed  $value
     * @return string
     */
    private static function pair(string $key, $value): string
    {
        return var_export($key, true) . ' => ' . self::exportValue($value);
    }

    /**
     * 将 PHP 值序列化为源码字符串（bool/null/int/float/string）
     *
     * @param mixed $value
     * @return string
     */
    private static function exportValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        return var_export((string)$value, true);
    }
}