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
     * 在同一个排他锁内检查封禁状态并累计请求次数。
     */
    public function consume(string $key): void
    {
        $blockedUntil = $this->updateRecord($key, true);
        if ($blockedUntil <= 0) {
            return;
        }

        $now = time();
        header('Retry-After: ' . (string)max(1, $blockedUntil - $now));
        Responder::error('TOO_MANY_ATTEMPTS', '请求过于频繁，请稍后再试。', 429);
    }

    /**
     * 累计当前 key 的请求次数，超限后设置封禁。
     */
    public function hit(string $key): void
    {
        $this->updateRecord($key, false);
    }

    /**
     * @return int 大于 0 时表示当前请求已处于封禁期，并返回封禁截止时间
     */
    private function updateRecord(string $key, bool $rejectIfBlocked): int
    {
        $this->ensureStorageDir();
        $fp = @fopen($this->pathForKey($key), 'c+');
        if ($fp === false) {
            $this->storageFailure();
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            $this->storageFailure();
        }

        $record = $this->decodeRecord((string)stream_get_contents($fp), true);
        $now = time();
        $blockedUntil = (int)($record['blocked_until'] ?? 0);
        if ($rejectIfBlocked && $blockedUntil > $now) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return $blockedUntil;
        }

        $windowStartedAt = (int)($record['window_started_at'] ?? 0);
        if ($windowStartedAt <= 0 || ($now - $windowStartedAt) > $this->windowSeconds) {
            $record = [
                'attempts' => 0,
                'window_started_at' => $now,
                'blocked_until' => 0,
            ];
        }

        $record['attempts'] = (int)($record['attempts'] ?? 0) + 1;
        if ((int)$record['attempts'] >= $this->maxAttempts) {
            $record['blocked_until'] = $now + $this->blockSeconds;
        }

        $encoded = json_encode($record);
        $writeSucceeded = is_string($encoded)
            && rewind($fp)
            && fwrite($fp, $encoded) === strlen($encoded)
            && ftruncate($fp, strlen($encoded))
            && fflush($fp);

        flock($fp, LOCK_UN);
        fclose($fp);

        if (!$writeSucceeded) {
            $this->storageFailure();
        }

        return 0;
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
        if (is_file($path) && !@unlink($path)) {
            $this->storageFailure();
        }
    }

    /**
     * @return array<string, int>
     */
    private function read(string $key): array
    {
        $path = $this->pathForKey($key);
        if (!is_file($path)) {
            return [];
        }

        $fp = @fopen($path, 'r');
        if ($fp === false) {
            $this->storageFailure();
        }

        if (!flock($fp, LOCK_SH)) {
            fclose($fp);
            $this->storageFailure();
        }

        $raw = (string)stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $this->decodeRecord($raw);
    }

    /**
     * @return array<string, int>
     */
    private function decodeRecord(string $raw, bool $allowEmpty = false): array
    {
        if ($raw === '') {
            if ($allowEmpty) {
                return [];
            }

            $this->storageFailure();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $this->storageFailure();
        }

        return $decoded;
    }

    private function storageFailure(): void
    {
        Responder::error('RATE_LIMIT_STORAGE_FAILED', '无法安全读写限流状态，请稍后再试。', 500);
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