<?php

declare(strict_types=1);

namespace GameFeedback\API\Admin;

use GameFeedback\Enums\TicketSeverity;
use GameFeedback\Enums\TicketStatus;
use GameFeedback\Enums\TicketType;
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
        ];
    }

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
        $page = $this->sanitizer->parseInt(Request::query('page', '1'), 1, 100000);
        $pageSize = $this->sanitizer->parseInt(Request::query('pageSize', '20'), 5, 100);

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

    protected function assignees(): void
    {
        Responder::send([
            'ok' => true,
            'users' => $this->createUserRepository()->listAssignableUsers(),
        ]);
    }

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

            // 下载成功后再输出附件响应头，避免失败时污染错误 JSON 响应
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

        // 仅当 storage=qiniu 且配置启用了 qiniu_direct_access 时，才返回直连 URL
        $directAccess = $this->resolveConfigFlag('qiniu_direct_access', false);
        if ($directAccess && $attachmentStorage === 'qiniu') {
            $url = $this->buildQiniuDownloadUrl($attachmentKey, 600);
            if ($url !== '') {
                Responder::send([
                    'ok'   => true,
                    'mode' => 'direct',
                    'url'  => $url,
                ]);
                return;
            }
        }

        // 本地存储或未开启直连：让前端走代理下载
        Responder::send(['ok' => true, 'mode' => 'proxy']);
    }

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
