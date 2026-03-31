<?php

declare(strict_types=1);

namespace GameFeedback\Support;

/**
 * 基于文件存储的 IP 请求速率限制器
 *
 * 使用文件锁（LOCK_EX / LOCK_SH）保证并发安全。
 * 可通过构造函数参数配置时间窗口、最大请求次数和封禁时长，
 * 以满足登录、提交、搜索等不同场景的速率控制需求。
 */
final class RateLimiter
{
    /** @var string 限流状态文件存储目录 */
    private $storageDir;

    /** @var int 时间窗口（秒），窗口内超过 maxAttempts 次则触发封禁 */
    private $windowSeconds;

    /** @var int 时间窗口内允许的最大请求次数 */
    private $maxAttempts;

    /** @var int 触发封禁后的封禁时长（秒） */
    private $blockSeconds;

    /**
     * @param string|null $storageDir    状态文件目录，null 时使用默认目录
     * @param int         $maxAttempts  时间窗口内允许的最大请求次数（默认 5）
     * @param int         $windowSeconds 时间窗口长度（秒，默认 600）
     * @param int         $blockSeconds  封禁时长（秒，默认 900）
     */
    public function __construct(
        ?string $storageDir = null,
        int $maxAttempts = 5,
        int $windowSeconds = 600,
        int $blockSeconds = 900
    ) {
        $this->storageDir = $storageDir ?: dirname(__DIR__, 2) . '/storage/runtime/ratelimit';
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
        $this->blockSeconds = $blockSeconds;
    }

    /**
     * 检查当前 key 是否处于封禁期，若是则直接返回 429 响应
     *
     * @param string $key 限流标识（建议格式：场景前缀|IP）
     * @return void
     */
    public function ensureAllowed(string $key): void
    {
        $record = $this->read($key);
        if (empty($record)) {
            return;
        }

        $now = time();
        $blockedUntil = (int)($record['blocked_until'] ?? 0);
        if ($blockedUntil <= $now) {
            return;
        }

        header('Retry-After: ' . (string)max(1, $blockedUntil - $now));
        Responder::error('TOO_MANY_ATTEMPTS', '请求过于频繁，请稍后再试。', 429);
    }

    /**
     * 累计当前 key 的请求次数，超限后设置封禁
     *
     * @param string $key 限流标识
     * @return void
     */
    public function hit(string $key): void
    {
        $now = time();
        $record = $this->read($key);
        $windowStartedAt = (int)($record['window_started_at'] ?? 0);

        // 窗口已过期或首次请求，重置计数器
        if ($windowStartedAt <= 0 || ($now - $windowStartedAt) > $this->windowSeconds) {
            $record = [
                'attempts' => 0,
                'window_started_at' => $now,
                'blocked_until' => 0,
            ];
        }

        $record['attempts'] = (int)($record['attempts'] ?? 0) + 1;
        if ((int)$record['attempts'] >= $this->maxAttempts) {
            // 已达上限，设置封禁截止时间
            $record['blocked_until'] = $now + $this->blockSeconds;
        }

        $this->write($key, $record);
    }

    /**
     * 清除指定 key 的限流记录（登录成功后调用）
     *
     * @param string $key 限流标识
     * @return void
     */
    public function clear(string $key): void
    {
        $path = $this->pathForKey($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * 读取指定 key 的限流记录
     *
     * 使用共享锁（LOCK_SH）读取，与 write() 的排他锁配合，防止并发写入导致数据撕裂。
     *
     * @return array<string, int>
     */
    private function read(string $key): array
    {
        $path = $this->pathForKey($key);
        if (!is_file($path)) {
            return [];
        }

        // 使用共享锁读取，与 write() 的排他锁配合，避免并发脏读
        $fp = @fopen($path, 'r');
        if ($fp === false) {
            return [];
        }

        $raw = '';
        if (flock($fp, LOCK_SH)) {
            $raw = (string)stream_get_contents($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 以排他锁写入限流记录
     *
     * @param array<string, int> $record 限流数据
     * @return void
     */
    private function write(string $key, array $record): void
    {
        $this->ensureStorageDir();
        @file_put_contents($this->pathForKey($key), json_encode($record), LOCK_EX);
    }

    /**
     * 确保限流状态目录存在
     *
     * @return void
     */
    private function ensureStorageDir(): void
    {
        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0775, true) && !is_dir($this->storageDir)) {
            Responder::error('RATE_LIMIT_STORAGE_FAILED', '无法初始化限流状态目录。', 500);
        }
    }

    /**
     * 根据 key 生成对应的状态文件路径
     *
     * @param string $key 限流标识
     * @return string 文件绝对路径
     */
    private function pathForKey(string $key): string
    {
        return $this->storageDir . '/' . hash('sha256', $key) . '.json';
    }
}