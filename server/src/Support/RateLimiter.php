<?php

declare(strict_types=1);

namespace GameFeedback\Support;

final class RateLimiter
{
    private const WINDOW_SECONDS = 600;
    private const MAX_ATTEMPTS = 5;
    private const BLOCK_SECONDS = 900;

    /** @var string */
    private $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?: dirname(__DIR__, 2) . '/storage/runtime/ratelimit';
    }

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
        Responder::error('TOO_MANY_ATTEMPTS', '登录尝试过于频繁，请稍后再试。', 429);
    }

    public function hit(string $key): void
    {
        $now = time();
        $record = $this->read($key);
        $windowStartedAt = (int)($record['window_started_at'] ?? 0);

        if ($windowStartedAt <= 0 || ($now - $windowStartedAt) > self::WINDOW_SECONDS) {
            $record = [
                'attempts' => 0,
                'window_started_at' => $now,
                'blocked_until' => 0,
            ];
        }

        $record['attempts'] = (int)($record['attempts'] ?? 0) + 1;
        if ((int)$record['attempts'] >= self::MAX_ATTEMPTS) {
            $record['blocked_until'] = $now + self::BLOCK_SECONDS;
        }

        $this->write($key, $record);
    }

    public function clear(string $key): void
    {
        $path = $this->pathForKey($key);
        if (is_file($path)) {
            @unlink($path);
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

        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, int> $record
     */
    private function write(string $key, array $record): void
    {
        $this->ensureStorageDir();
        @file_put_contents($this->pathForKey($key), json_encode($record), LOCK_EX);
    }

    private function ensureStorageDir(): void
    {
        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0775, true) && !is_dir($this->storageDir)) {
            Responder::error('RATE_LIMIT_STORAGE_FAILED', '无法初始化登录限流目录。', 500);
        }
    }

    private function pathForKey(string $key): string
    {
        return $this->storageDir . '/' . hash('sha256', $key) . '.json';
    }
}
