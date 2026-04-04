<?php

declare(strict_types=1);

namespace GameFeedback\Support;

use GameFeedback\Enums\TicketStatus;
use GameFeedback\Repository\TicketRepository;
use PDO;

final class AttachmentCleanupService
{
    /** @var array<string, mixed> */
    private $dbConfig;

    /** @var TicketRepository */
    private $ticketRepository;

    /**
     * @param array<string, mixed> $dbConfig
     * @param PDO                  $pdo      数据库连接实例
     */
    public function __construct(array $dbConfig, PDO $pdo)
    {
        $this->dbConfig = $dbConfig;
        $this->ticketRepository = new TicketRepository($pdo);
    }

    /**
     * 读取附件删除前的保留天数。
     */
    public function retentionDays(): int
    {
        $days = (int)($this->dbConfig['attachment_cleanup_retention_days'] ?? 15);
        return $days > 0 ? $days : 15;
    }

    /**
     * 判断附件自动清理功能当前是否启用。
     */
    public function isEnabled(): bool
    {
        if (!array_key_exists('attachment_cleanup_enabled', $this->dbConfig)) {
            return true;
        }

        $value = $this->dbConfig['attachment_cleanup_enabled'];
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return true;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * 根据工单状态同步附件的清理时间。
     */
    public function syncTicketSchedule(array $ticket, int $status, string $updatedAt): void
    {
        $ticketNo = (string)($ticket['ticket_no'] ?? '');
        if ($ticketNo === '') {
            return;
        }

        if (!$this->hasAttachment($ticket)) {
            $this->ticketRepository->updateAttachmentCleanupSchedule($ticketNo, null);
            return;
        }

        if (!in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            $this->ticketRepository->updateAttachmentCleanupSchedule($ticketNo, null);
            return;
        }

        $dueAt = date('Y-m-d H:i:s', strtotime($updatedAt . ' +' . $this->retentionDays() . ' days'));
        $this->ticketRepository->updateAttachmentCleanupSchedule($ticketNo, $dueAt);
    }

    /**
     * 扫描已经到期的附件，并逐个尝试删除。
     *
     * @return array<string, mixed>
     */
    public function run(int $limit = 20, bool $force = false): array
    {
        if (!$force && !$this->isEnabled()) {
            return [
                'enabled' => false,
                'retentionDays' => $this->retentionDays(),
                'scanned' => 0,
                'deleted' => 0,
                'alreadyMissing' => 0,
                'errors' => [],
            ];
        }

        $now = date('Y-m-d H:i:s');
        $candidates = $this->ticketRepository->findAttachmentCleanupCandidates($now, $limit);
        $result = [
            'enabled' => true,
            'retentionDays' => $this->retentionDays(),
            'scanned' => count($candidates),
            'deleted' => 0,
            'alreadyMissing' => 0,
            'errors' => [],
        ];

        foreach ($candidates as $candidate) {
            $ticketNo = (string)($candidate['ticket_no'] ?? '');
            $storage = (string)($candidate['attachment_storage'] ?? '');
            $key = (string)($candidate['attachment_key'] ?? '');

            if ($ticketNo === '' || $storage === '' || $key === '') {
                continue;
            }

            try {
                $deletedState = $this->deleteAttachment($storage, $key);
                $this->ticketRepository->clearAttachmentAfterCleanup($ticketNo, $now);
                if ($deletedState === 'missing') {
                    $result['alreadyMissing']++;
                } else {
                    $result['deleted']++;
                }
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'ticketNo' => $ticketNo,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * 为历史工单补写附件清理计划。
     */
    public function backfillSchedules(): int
    {
        return $this->ticketRepository->backfillAttachmentCleanupDueAt($this->retentionDays());
    }

    /**
     * 判断这条工单现在有没有附件可清理。
     *
     * @param array<string, mixed> $ticket
     */
    private function hasAttachment(array $ticket): bool
    {
        return trim((string)($ticket['attachment_storage'] ?? '')) !== ''
            && trim((string)($ticket['attachment_key'] ?? '')) !== '';
    }

    /**
     * 按存储类型删除附件，并返回删除结果。
     */
    private function deleteAttachment(string $storage, string $key): string
    {
        if ($storage === 'local') {
            return $this->deleteLocalAttachment($key);
        }

        if ($storage === 'qiniu') {
            return $this->deleteQiniuAttachment($key);
        }

        throw new \RuntimeException('Unsupported attachment storage mode: ' . $storage);
    }

    /**
     * 删除本地存储的附件文件。
     */
    private function deleteLocalAttachment(string $key): string
    {
        $uploadsBasePath = realpath(dirname(__DIR__, 2) . '/storage/uploads');
        if ($uploadsBasePath === false) {
            return 'missing';
        }

        $absolutePath = realpath(dirname(__DIR__, 2) . '/storage/uploads/' . ltrim($key, '/'));
        if ($absolutePath === false) {
            return 'missing';
        }

        if (strncmp($absolutePath, $uploadsBasePath . DIRECTORY_SEPARATOR, strlen($uploadsBasePath) + 1) !== 0) {
            throw new \RuntimeException('Attachment path escaped uploads directory.');
        }

        if (!is_file($absolutePath)) {
            return 'missing';
        }

        if (!@unlink($absolutePath)) {
            throw new \RuntimeException('Failed to delete local attachment file.');
        }

        return 'deleted';
    }

    /**
     * 删除七牛云中的附件对象。
     */
    private function deleteQiniuAttachment(string $key): string
    {
        $bucket = trim((string)($this->dbConfig['qiniu_bucket'] ?? ''));
        $accessKey = trim((string)($this->dbConfig['qiniu_access_key'] ?? ''));
        $secretKey = trim((string)($this->dbConfig['qiniu_secret_key'] ?? ''));

        if ($bucket === '' || $accessKey === '' || $secretKey === '') {
            throw new \RuntimeException('Qiniu configuration is incomplete.');
        }

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL is not enabled in the current PHP environment.');
        }

        $encodedEntry = str_replace(['+', '/'], ['-', '_'], base64_encode($bucket . ':' . $key));
        $pathQuery = '/delete/' . $encodedEntry;
        $host = 'rs.qiniuapi.com';
        $dateHeader = gmdate('Ymd\THis\Z');
        $authorization = $this->buildQiniuManagementAuthorization(
            'POST',
            $host,
            $pathQuery,
            $accessKey,
            $secretKey,
            'application/x-www-form-urlencoded',
            ['X-Qiniu-Date' => $dateHeader]
        );

        $ch = curl_init('https://' . $host . $pathQuery);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize Qiniu delete request.');
        }

        $options = [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
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
        $curlError = (string)curl_error($ch);
        curl_close($ch);

        if ($curlErrno !== 0) {
            throw new \RuntimeException('Qiniu delete failed: ' . $curlError);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return 'deleted';
        }

        $body = is_string($response) ? trim($response) : '';
        if ($httpCode === 612 || stripos($body, 'no such file or directory') !== false) {
            return 'missing';
        }

        throw new \RuntimeException('Qiniu delete failed with HTTP ' . $httpCode . ($body !== '' ? ': ' . $body : ''));
    }

    /**
     * @return array<int, bool|int|string>
     */
    private function buildCurlSslOptions(): array
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
        if ($caFile !== '' && defined('CURLOPT_CAINFO')) {
            $options[CURLOPT_CAINFO] = $caFile;
        }

        $caPath = $this->resolveConfigPath('curl_ca_path');
        if ($caPath !== '' && defined('CURLOPT_CAPATH')) {
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
     * 生成调用七牛管理接口时要带上的签名。
     */
    private function buildQiniuManagementAuthorization(
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
                $signing .= $this->canonicalizeQiniuHeaderName((string)$name) . ': ' . trim((string)$value) . "\n";
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
     * 把签名里用到的请求头名称整理成统一格式。
     */
    private function canonicalizeQiniuHeaderName(string $name): string
    {
        $parts = preg_split('/-+/', trim($name)) ?: [];
        $normalized = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $normalized[] = ucfirst(strtolower($part));
        }
        return implode('-', $normalized);
    }

    /**
     * 把配置里的路径值解析成可以直接访问的绝对路径。
     */
    private function resolveConfigPath(string $key): string
    {
        $value = trim((string)($this->dbConfig[$key] ?? ''));
        if ($value === '') {
            return '';
        }

        if ($this->isAbsolutePath($value)) {
            return $value;
        }

        return dirname(__DIR__, 2) . '/' . ltrim(str_replace('\\', '/', $value), '/');
    }

    /**
     * 判断一个路径是不是已经写成绝对路径。
     */
    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    /**
     * 读取布尔型配置，并兼容 true/false 的字符串写法。
     */
    private function configFlag(string $key, bool $default): bool
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
     * 执行 URL 安全的 Base64 编码。
     */
    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($data));
    }
}
