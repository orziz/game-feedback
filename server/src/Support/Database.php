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
     * @param string $host     数据库主机
     * @param int    $port     端口
     * @param string $database 库名
     * @param string $username 用户名
     * @param string $password 密码
     * @return PDO
     */
    public static function createPdo(string $host, int $port, string $database, string $username, string $password): PDO
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $e) {
            Responder::send([
                'ok' => false,
                'code' => 'DB_CONNECT_FAILED',
                'message' => '数据库连接失败：' . $e->getMessage(),
            ], 500);
            exit;
        }
    }

    /**
     * 从已加载的配置数组创建 PDO 实例
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
            (string)$dbConfig['password']
        );
    }

    /**
     * 将数据库配置写入 PHP 文件
     *
     * @param string               $path           配置文件绝对路径
     * @param array<string, mixed> $databaseConfig 配置数据
     * @return void
     */
    public static function writeConfig(string $path, array $databaseConfig): void
    {
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($databaseConfig, true) . ";\n";
        $result = @file_put_contents($path, $content);
        if ($result === false) {
            Responder::send([
                'ok' => false,
                'code' => 'CONFIG_WRITE_FAILED',
                'message' => '无法写入配置文件，请检查目录权限：' . $path,
            ], 500);
        }
    }
}
