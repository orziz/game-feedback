<?php

declare(strict_types=1);

namespace GameFeedback\API\Admin;

use GameFeedback\Enums\TicketSeverity;
use GameFeedback\Enums\TicketStatus;
use GameFeedback\Enums\TicketType;
use GameFeedback\Support\AttachmentCleanupService;
use GameFeedback\Support\Database;
use GameFeedback\Support\Request;
use GameFeedback\Support\Responder;

final class Ticket extends AdminSubModule
{
    /**
     * @return array<string, array{
     *   methods: array<int, string>,
     *   allow_before_install?: bool,
     *   auth?: string
     * }>
     */
    protected function actionMeta(): array
    {
        return [
            'list' => [
                self::META_METHODS => ['GET'],
                self::META_AUTH => self::AUTH_ADMIN,
            ],
            'assignees' => [
                self::META_METHODS => ['GET'],
                self::META_AUTH => self::AUTH_ADMIN,
            ],
            'detail' => [
                self::META_METHODS => ['GET'],
                self::META_AUTH => self::AUTH_ADMIN,
            ],
            'attachmentDownload' => [
                self::META_METHODS => ['GET'],
                self::META_AUTH => self::AUTH_ADMIN,
            ],
            'publicAttachmentDownload' => [
                self::META_METHODS => ['GET'],
                self::META_AUTH => self::AUTH_NONE,
            ],
            'update' => [
                self::META_METHODS => ['POST'],
                self::META_AUTH => self::AUTH_ADMIN,
            ],
            'assign' => [
                self::META_METHODS => ['POST'],
                self::META_AUTH => self::AUTH_ADMIN,
            ],
            'batchAssign' => [
                self::META_METHODS => ['POST'],
                self::META_AUTH => self::AUTH_ADMIN,
            ],
            'getOperations' => [
                self::META_METHODS => ['GET'],
                self::META_AUTH => self::AUTH_ADMIN,
            ],
            'attachmentUrl' => [
                self::META_METHODS => ['GET'],
                self::META_AUTH => self::AUTH_ADMIN,
            ],
            'cleanupConfig' => [
                self::META_METHODS => ['GET'],
                self::META_AUTH => self::AUTH_SUPER_ADMIN,
            ],
            'updateCleanupConfig' => [
                self::META_METHODS => ['POST'],
                self::META_AUTH => self::AUTH_SUPER_ADMIN,
            ],
            'cleanupAttachments' => [
                self::META_METHODS => ['POST'],
                self::META_AUTH => self::AUTH_SUPER_ADMIN,
            ],
        ];
    }

    /**
     * 按筛选条件分页返回后台工单列表。
     */
    protected function list(): void
    {
        $statusRaw = Request::query('status');
        $statusEnum = $statusRaw !== '' ? TicketStatus::tryFrom((int)$statusRaw) : null;
        $status = $statusEnum !== null ? $statusEnum : null;

        $typeRaw = Request::query('type');
        $typeEnum = $typeRaw !== '' ? TicketType::tryFrom((int)$typeRaw) : null;
        $type = $typeEnum !== null ? $typeEnum : null;

        $severityRaw = Request::query('severity');
        $severityEnum = $severityRaw !== '' ? TicketSeverity::tryFrom((int)$severityRaw) : null;
        $severity = $severityEnum !== null ? $severityEnum : null;

        $assignedToRaw = Request::query('assignedTo');
        $assignedTo = $assignedToRaw !== '' ? $this->sanitizer->parseInt($assignedToRaw, 1, 9999999999) : null;

        $keyword = $this->sanitizer->sanitizeSingleLine(Request::query('keyword'), 120);
        if ($keyword !== '' && preg_match('/^FB\d{8}[A-F0-9]{6}$/i', $keyword) === 1) {
            $keyword = strtoupper($keyword);
        }
        $page = $this->sanitizer->parseInt(Request::query('page', '1'), 1, 100000);
        $pageSize = $this->sanitizer->parseInt(Request::query('pageSize', '20'), 5, 500);

        $result = $this->createTicketRepository()->listTickets($status, $type, $severity, $keyword, $assignedTo, $page, $pageSize);

        Responder::send([
            'ok' => true,
            'tickets' => $result['items'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => max(1, (int)ceil($result['total'] / $pageSize)),
            ],
        ]);
    }

    /**
     * 返回指定工单的详情和操作记录。
     */
    protected function detail(): void
    {
        $ticketNo = $this->sanitizer->sanitizeSingleLine(Request::query('ticketNo'), 32);
        if ($ticketNo === '' || !$this->sanitizer->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        $repo = $this->createTicketRepository();
        $ticket = $repo->findTicketByNo($ticketNo);
        if (!$ticket) {
            Responder::error('TICKET_NOT_FOUND', '工单不存在。', 404);
        }

        $operations = $repo->getTicketOperations($ticketNo);

        Responder::send([
            'ok' => true,
            'ticket' => $ticket,
            'operations' => $operations,
        ]);
    }

    /**
     * 获取可用于工单指派的管理员列表。
     */
    protected function assignees(): void
    {
        Responder::send([
            'ok' => true,
            'users' => $this->createUserRepository()->listAssignableUsers(),
        ]);
    }

    /**
     * 更新工单状态、严重程度和后台备注。
     */
    protected function update(): void
    {
        $currentUser = $this->currentAdminUser();

        $payload = Request::jsonBody();
        $ticketNo = $this->sanitizer->sanitizeSingleLine((string)($payload['ticketNo'] ?? ''), 32);
        $status = TicketStatus::tryFrom((int)($payload['status'] ?? -1));
        $severity = array_key_exists('severity', $payload)
            ? TicketSeverity::tryFrom((int)$payload['severity'])
            : null;
        $adminNote = $this->sanitizer->sanitizeText((string)($payload['adminNote'] ?? ''), 2000);

        if ($ticketNo === '' || $status === null) {
            Responder::error('MISSING_REQUIRED_FIELDS', '工单号和状态不能为空。', 422);
        }

        if (!$this->sanitizer->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        $repo = $this->createTicketRepository();
        $ticket = $repo->findTicketByNo($ticketNo);
        if (!$ticket) {
            Responder::error('TICKET_NOT_FOUND', '工单不存在。', 404);
        }

        $safeSeverity = null;
        if ((int)($ticket['type'] ?? -1) === TicketType::Bug) {
            if ($severity === null) {
                Responder::error('INVALID_SEVERITY', 'BUG 工单的严重程度不合法。', 422);
            }
            $safeSeverity = $severity;
        }

        $updatedAt = date('Y-m-d H:i:s');
        $repo->updateTicket(
            $ticketNo,
            (int)$status,
            $safeSeverity,
            $adminNote !== '' ? $adminNote : null,
            $updatedAt
        );

        $cleanupService = $this->createAttachmentCleanupService();
        $cleanupService->syncTicketSchedule($ticket, (int)$status, $updatedAt);

        // 记录状态变更
        $oldStatus = (int)($ticket['status'] ?? 0);
        $newStatusValue = (int)$status;
        if ($oldStatus !== $newStatusValue) {
            $repo->recordOperation(
                $ticketNo,
                (int)$currentUser['id'],
                (string)$currentUser['username'],
                'status_change',
                (string)$oldStatus,
                (string)$newStatusValue
            );
        }

        Responder::send([
            'ok' => true,
            'message' => '更新成功。',
        ]);
    }

    /**
     * 代理下载指定工单的附件内容。
     */
    protected function attachmentDownload(): void
    {
        $ticketNo = $this->sanitizer->sanitizeSingleLine(Request::query('ticketNo'), 32);
        if ($ticketNo === '' || !$this->sanitizer->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        $ticket = $this->createTicketRepository()->findTicketByNo($ticketNo);
        if (!$ticket) {
            Responder::error('TICKET_NOT_FOUND', '工单不存在。', 404);
        }

        $this->streamTicketAttachment($ticket);
    }

    /**
     * 通过签名链接直接下载指定工单附件。
     */
    protected function publicAttachmentDownload(): void
    {
        $ticketNo = $this->sanitizer->sanitizeSingleLine(Request::query('ticketNo'), 32);
        $expires = $this->sanitizer->parseInt(Request::query('expires', '0'), 0, 2147483647);
        $signature = $this->sanitizer->sanitizeSingleLine(Request::query('signature'), 128);

        if ($ticketNo === '' || !$this->sanitizer->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        if ($expires <= time()) {
            Responder::error('LINK_EXPIRED', '附件链接已过期。', 410);
        }

        if (!$this->isValidPublicAttachmentSignature($ticketNo, $expires, $signature)) {
            Responder::error('INVALID_SIGNATURE', '附件链接签名无效。', 403);
        }

        $ticket = $this->createTicketRepository()->findTicketByNo($ticketNo);
        if (!$ticket) {
            Responder::error('TICKET_NOT_FOUND', '工单不存在。', 404);
        }

        $this->streamTicketAttachment($ticket);
    }

    /**
     * 按工单记录输出附件内容。
     *
     * @param array<string, mixed> $ticket
     */
    private function streamTicketAttachment(array $ticket): void
    {
        $attachmentKey = (string)($ticket['attachment_key'] ?? '');
        $attachmentStorage = (string)($ticket['attachment_storage'] ?? '');
        $attachmentName = (string)($ticket['attachment_name'] ?? '');
        $attachmentMime = (string)($ticket['attachment_mime'] ?? 'application/octet-stream');

        if ($attachmentKey === '' || $attachmentStorage === '' || $attachmentName === '') {
            Responder::error('ATTACHMENT_NOT_FOUND', '该工单没有附件。', 404);
        }

        if ($attachmentStorage === 'local') {
            // 使用 realpath 校验最终路径必须在 uploads 目录内，防止路径穿越
            $uploadsBasePath = realpath(dirname(__DIR__, 3) . '/storage/uploads');
            $absolutePath = realpath(dirname(__DIR__, 3) . '/storage/uploads/' . ltrim($attachmentKey, '/'));
            if (
                $uploadsBasePath === false
                || $absolutePath === false
                || strncmp($absolutePath, $uploadsBasePath . DIRECTORY_SEPARATOR, strlen($uploadsBasePath) + 1) !== 0
                || !is_file($absolutePath)
            ) {
                Responder::error('ATTACHMENT_NOT_FOUND', '附件文件不存在。', 404);
            }

            header('Content-Type: ' . $attachmentMime);
            header('Content-Disposition: attachment; filename="' . rawurlencode($attachmentName) . '"; filename*=UTF-8\'\'' . rawurlencode($attachmentName));
            header('Content-Length: ' . (string)filesize($absolutePath));
            readfile($absolutePath);
            exit;
        }

        if ($attachmentStorage === 'qiniu') {
            $qiniuUrls = $this->buildQiniuDownloadUrlVariants($attachmentKey);
            if ($qiniuUrls === []) {
                Responder::error('QINIU_CONFIG_MISSING', '七牛云下载地址未配置。', 500);
            }

            if (!function_exists('curl_init')) {
                Responder::error('QINIU_DOWNLOAD_FAILED', '当前环境未启用 cURL，无法下载七牛云附件。', 500);
            }

            $downloadedStream = null;
            $lastHttpCode = 0;
            $lastCurlErrno = 0;
            $lastCurlError = '';
            $lastBody = '';

            foreach ($qiniuUrls as $qiniuUrl) {
                $ch = curl_init($qiniuUrl);
                if ($ch === false) {
                    continue;
                }

                $bodyBuffer = fopen('php://temp', 'w+b');
                if ($bodyBuffer === false) {
                    curl_close($ch);
                    Responder::error('QINIU_DOWNLOAD_FAILED', '无法初始化附件下载缓冲区。', 500);
                }

                $options = [
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HEADER => false,
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_FILE => $bodyBuffer,
                ];
                foreach ($this->buildCurlSslOptions() as $option => $value) {
                    $options[$option] = $value;
                }
                curl_setopt_array($ch, $options);

                $ok = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErrno = curl_errno($ch);
                $curlError = (string)curl_error($ch);
                curl_close($ch);

                rewind($bodyBuffer);
                $responseSnippet = fread($bodyBuffer, 180);

                if ($ok === true && $curlErrno === 0 && $httpCode >= 200 && $httpCode < 300) {
                    rewind($bodyBuffer);
                    $downloadedStream = $bodyBuffer;
                    break;
                }

                $lastHttpCode = $httpCode;
                $lastCurlErrno = $curlErrno;
                $lastCurlError = $curlError;
                $lastBody = is_string($responseSnippet) ? trim($responseSnippet) : '';
                fclose($bodyBuffer);
            }

            if (!is_resource($downloadedStream)) {
                $message = '七牛云附件下载失败。';
                if ($lastCurlErrno !== 0 && $lastCurlError !== '') {
                    $message .= ' cURL(' . $lastCurlErrno . '): ' . $lastCurlError;
                } elseif ($lastHttpCode > 0) {
                    $message .= ' HTTP ' . $lastHttpCode . '。';
                }
                if ($lastBody !== '') {
                    $snippet = function_exists('mb_substr') ? mb_substr($lastBody, 0, 180, 'UTF-8') : substr($lastBody, 0, 180);
                    $message .= ' ' . $snippet;
                }
                $message .= $this->getQiniuDownloadHint();
                Responder::error('QINIU_DOWNLOAD_FAILED', $message, 500);
            }

            header('Content-Type: ' . $attachmentMime);
            header('Content-Disposition: attachment; filename="' . rawurlencode($attachmentName) . '"; filename*=UTF-8\'\'' . rawurlencode($attachmentName));
            $stat = fstat($downloadedStream);
            if (is_array($stat) && isset($stat['size']) && (int)$stat['size'] > 0) {
                header('Content-Length: ' . (string)((int)$stat['size']));
            }
            rewind($downloadedStream);
            fpassthru($downloadedStream);
            fclose($downloadedStream);

            exit;
        }

        Responder::error('UNSUPPORTED_STORAGE', '不支持的附件存储方式。', 500);
    }

    /**
     * 代理下载指定工单的附件内容。
     */
    protected function assign(): void
    {
        $currentUser = $this->currentAdminUser();

        $payload = Request::jsonBody();
        $ticketNo = $this->sanitizer->sanitizeSingleLine((string)($payload['ticketNo'] ?? ''), 32);
        $assignToId = array_key_exists('assignedTo', $payload) && $payload['assignedTo'] !== null
            ? $this->sanitizer->parseInt((string)$payload['assignedTo'], 1, 9999999999)
            : null;

        if ($ticketNo === '') {
            Responder::error('MISSING_TICKET_NO', '工单号不能为空。', 422);
        }

        if (!$this->sanitizer->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        $repo = $this->createTicketRepository();
        $ticket = $repo->findTicketByNo($ticketNo);
        if (!$ticket) {
            Responder::error('TICKET_NOT_FOUND', '工单不存在。', 404);
        }

        $userRepo = $this->createUserRepository();

        // 验证指派的用户存在（如果有指定用户）
        if ($assignToId !== null) {
            $user = $userRepo->findById($assignToId);
            if (!$user) {
                Responder::error('USER_NOT_FOUND', '指派的用户不存在。', 404);
            }
            $assignedUsername = (string)($user['username'] ?? '');
        } else {
            $assignedUsername = '';
        }

        $oldAssignedTo = (int)($ticket['assigned_to'] ?? 0);
        $oldAssignedLabel = null;
        if ($oldAssignedTo > 0) {
            $oldAssignee = $userRepo->findById($oldAssignedTo);
            $oldAssigneeUsername = $oldAssignee ? (string)($oldAssignee['username'] ?? '') : '';
            $oldAssignedLabel = $oldAssigneeUsername !== ''
                ? $oldAssigneeUsername . ' (#' . $oldAssignedTo . ')'
                : '用户#' . $oldAssignedTo;
        }

        $newAssignedLabel = $assignToId !== null
            ? ($assignedUsername !== '' ? $assignedUsername . ' (#' . $assignToId . ')' : '用户#' . $assignToId)
            : '未指派';

        $updatedAt = date('Y-m-d H:i:s');
        $repo->assignTicket($ticketNo, $assignToId, $updatedAt);

        // 记录指派操作
        $repo->recordOperation(
            $ticketNo,
            (int)$currentUser['id'],
            (string)$currentUser['username'],
            'assign',
            $oldAssignedLabel,
            $newAssignedLabel
        );

        Responder::send([
            'ok' => true,
            'message' => '指派成功。',
        ]);
    }

    /**
     * 返回附件可直接下载的地址。
     *
     * 优先返回七牛直链；否则返回带签名的公开下载地址。
     */
    protected function attachmentUrl(): void
    {
        $ticketNo = $this->sanitizer->sanitizeSingleLine(Request::query('ticketNo'), 32);
        if ($ticketNo === '' || !$this->sanitizer->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        $ticket = $this->createTicketRepository()->findTicketByNo($ticketNo);
        if (!$ticket) {
            Responder::error('TICKET_NOT_FOUND', '工单不存在。', 404);
        }

        $attachmentKey     = (string)($ticket['attachment_key']     ?? '');
        $attachmentStorage = (string)($ticket['attachment_storage'] ?? '');
        $attachmentName    = (string)($ticket['attachment_name']    ?? '');

        if ($attachmentKey === '' || $attachmentStorage === '' || $attachmentName === '') {
            Responder::error('ATTACHMENT_NOT_FOUND', '该工单没有附件。', 404);
        }

        $directAccess = $this->resolveConfigFlag('qiniu_direct_access', false);
        $linkTtl = $this->attachmentExportLinkTtlSeconds();
        if ($directAccess && $attachmentStorage === 'qiniu') {
            $url = $this->buildQiniuDownloadUrl($attachmentKey, $linkTtl);
            if ($url !== '') {
                Responder::send([
                    'ok'   => true,
                    'mode' => 'direct',
                    'url'  => $url,
                ]);
                return;
            }
        }

        Responder::send([
            'ok' => true,
            'mode' => 'direct',
            'url' => $this->buildPublicAttachmentDownloadUrl($ticketNo, $linkTtl),
        ]);
    }

    /**
     * 批量将多条工单指派给指定管理员。
     */
    protected function batchAssign(): void
    {
        $currentUser = $this->currentAdminUser();
        $payload = Request::jsonBody();

        $ticketNosRaw = isset($payload['ticketNos']) && is_array($payload['ticketNos'])
            ? $payload['ticketNos']
            : [];
        $assignToId = array_key_exists('assignedTo', $payload) && $payload['assignedTo'] !== null
            ? $this->sanitizer->parseInt((string)$payload['assignedTo'], 1, 9999999999)
            : null;

        if ($ticketNosRaw === []) {
            Responder::error('MISSING_TICKETS', '至少需要选择一条工单。', 422);
        }

        $ticketNos = [];
        foreach ($ticketNosRaw as $ticketNoRaw) {
            $ticketNo = $this->sanitizer->sanitizeSingleLine((string)$ticketNoRaw, 32);
            if ($ticketNo === '' || !$this->sanitizer->isValidTicketNo($ticketNo)) {
                Responder::error('INVALID_TICKET_NO', '存在格式不正确的工单号。', 422);
            }
            $ticketNos[] = $ticketNo;
        }
        $ticketNos = array_values(array_unique($ticketNos));

        if (count($ticketNos) > 100) {
            Responder::error('TOO_MANY_TICKETS', '单次最多只能批量指派 100 条工单。', 422);
        }

        if ($assignToId === null) {
            Responder::error('MISSING_ASSIGNEE', '请选择指派对象。', 422);
        }

        $userRepo = $this->createUserRepository();
        $assignee = $userRepo->findById($assignToId);
        if (!$assignee) {
            Responder::error('USER_NOT_FOUND', '指派的用户不存在。', 404);
        }

        $repo = $this->createTicketRepository();
        $tickets = $repo->findTicketsByNos($ticketNos);
        if (count($tickets) !== count($ticketNos)) {
            Responder::error('TICKET_NOT_FOUND', '部分工单不存在或已被删除。', 404);
        }

        $assignedUsername = (string)($assignee['username'] ?? '');
        $newAssignedLabel = $assignedUsername !== ''
            ? $assignedUsername . ' (#' . $assignToId . ')'
            : '用户#' . $assignToId;
        $updatedAt = date('Y-m-d H:i:s');

        $repo->assignTickets($ticketNos, $assignToId, $updatedAt);

        foreach ($ticketNos as $ticketNo) {
            $ticket = $tickets[$ticketNo];
            $oldAssignedTo = (int)($ticket['assigned_to'] ?? 0);
            $oldAssignedLabel = null;
            if ($oldAssignedTo > 0) {
                $oldAssignee = $userRepo->findById($oldAssignedTo);
                $oldAssigneeUsername = $oldAssignee ? (string)($oldAssignee['username'] ?? '') : '';
                $oldAssignedLabel = $oldAssigneeUsername !== ''
                    ? $oldAssigneeUsername . ' (#' . $oldAssignedTo . ')'
                    : '用户#' . $oldAssignedTo;
            }

            $repo->recordOperation(
                $ticketNo,
                (int)$currentUser['id'],
                (string)$currentUser['username'],
                'assign',
                $oldAssignedLabel,
                $newAssignedLabel
            );
        }

        Responder::send([
            'ok' => true,
            'affected' => count($ticketNos),
            'message' => '批量指派成功。',
        ]);
    }

    /**
     * 读取当前附件清理配置。
     */
    protected function cleanupConfig(): void
    {
        $cleanupService = $this->createAttachmentCleanupService();
        Responder::send([
            'ok' => true,
            'enabled' => $cleanupService->isEnabled(),
            'retentionDays' => $cleanupService->retentionDays(),
            'intervalSeconds' => $this->cleanupIntervalSeconds(),
            'batchLimit' => $this->cleanupBatchLimit(),
        ]);
    }

    /**
     * 更新附件清理开关、保留时长和执行参数。
     */
    protected function updateCleanupConfig(): void
    {
        $payload = Request::jsonBody();
        $enabled = $this->normalizeCleanupEnabled($payload['enabled'] ?? true);
        $retentionDays = $this->sanitizer->parseInt((string)($payload['retentionDays'] ?? 15), 1, 3650);
        $intervalSeconds = $this->sanitizer->parseInt((string)($payload['intervalSeconds'] ?? 600), 1, 86400);
        $batchLimit = $this->sanitizer->parseInt((string)($payload['batchLimit'] ?? 100), 1, 10000);

        $nextConfig = $this->dbConfig;
        $nextConfig['attachment_cleanup_enabled'] = $enabled;
        $nextConfig['attachment_cleanup_retention_days'] = $retentionDays;
        $nextConfig['attachment_cleanup_interval_seconds'] = $intervalSeconds;
        $nextConfig['attachment_cleanup_batch_limit'] = $batchLimit;
        $nextConfig['schema_version'] = max((int)($nextConfig['schema_version'] ?? 0), 6);
        Database::writeConfig($this->databaseConfigPath, $nextConfig);
        $this->dbConfig = $nextConfig;

        Responder::send([
            'ok' => true,
            'enabled' => $enabled,
            'retentionDays' => $retentionDays,
            'intervalSeconds' => $intervalSeconds,
            'batchLimit' => $batchLimit,
            'message' => '附件清理配置已更新。',
        ]);
    }

    /**
     * 立即执行一次附件清理任务。
     */
    protected function cleanupAttachments(): void
    {
        $cleanupService = $this->createAttachmentCleanupService();
        $result = $cleanupService->run(100, false);
        if (!$result['enabled']) {
            Responder::error('ATTACHMENT_CLEANUP_DISABLED', '附件清理当前已禁用。', 409);
        }

        Responder::send([
            'ok' => true,
            'message' => '附件清理执行完成。',
            'result' => $result,
        ]);
    }

    /**
     * 生成导出用的公开附件下载地址。
     */
    private function buildPublicAttachmentDownloadUrl(string $ticketNo, int $ttl): string
    {
        $expires = time() + max(60, $ttl);
        $signature = hash_hmac('sha256', $ticketNo . '|' . $expires, $this->getAppSecret());
        $query = http_build_query([
            's' => 'admin/Ticket/publicAttachmentDownload',
            'ticketNo' => $ticketNo,
            'expires' => $expires,
            'signature' => $signature,
        ]);

        $requestScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $requestHost = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        $requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $basePath = is_string($requestPath) && trim($requestPath) !== ''
            ? $requestPath
            : ((string)($_SERVER['SCRIPT_NAME'] ?? '/api'));

        if ($requestHost !== '') {
            return $requestScheme . '://' . $requestHost . $basePath . '?' . $query;
        }

        return $basePath . '?' . $query;
    }

    /**
     * 校验公开附件下载链接签名。
     */
    private function isValidPublicAttachmentSignature(string $ticketNo, int $expires, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $ticketNo . '|' . $expires, $this->getAppSecret());
        return hash_equals($expected, $signature);
    }

    /**
     * 读取布尔型配置项，并兼容字符串形式的真值。
     */
    private function resolveConfigFlag(string $key, bool $default): bool
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
     * 将附件清理开关输入规范化为布尔值。
     */
    private function normalizeCleanupEnabled($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * 获取附件清理任务的最小执行间隔。
     */
    private function cleanupIntervalSeconds(): int
    {
        $value = (int)($this->dbConfig['attachment_cleanup_interval_seconds'] ?? 600);
        return $value > 0 ? $value : 600;
    }

    /**
     * 获取单次附件清理任务的批处理上限。
     */
    private function cleanupBatchLimit(): int
    {
        $value = (int)($this->dbConfig['attachment_cleanup_batch_limit'] ?? 100);
        return $value > 0 ? $value : 100;
    }

    /**
     * 获取导出附件直链的有效期（秒）。
     */
    private function attachmentExportLinkTtlSeconds(): int
    {
        $value = (int)($this->dbConfig['attachment_export_link_ttl_seconds'] ?? 604800);
        return $value > 0 ? $value : 604800;
    }

    /**
     * 创建附件清理服务实例。
     */
    private function createAttachmentCleanupService(): AttachmentCleanupService
    {
        return new AttachmentCleanupService($this->dbConfig, $this->getPdo());
    }

    /**
     * 返回指定工单的完整操作流水。
     */
    protected function getOperations(): void
    {
        $ticketNo = $this->sanitizer->sanitizeSingleLine(Request::query('ticketNo'), 32);
        if ($ticketNo === '' || !$this->sanitizer->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        $repo = $this->createTicketRepository();
        $ticket = $repo->findTicketByNo($ticketNo);
        if (!$ticket) {
            Responder::error('TICKET_NOT_FOUND', '工单不存在。', 404);
        }

        $operations = $repo->getTicketOperations($ticketNo);

        Responder::send([
            'ok' => true,
            'operations' => $operations,
        ]);
    }
}
