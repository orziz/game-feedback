<?php

declare(strict_types=1);

final class Database
{
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
