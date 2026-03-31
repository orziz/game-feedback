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
     * @return array<string, array{methods: array<int, string>, allow_before_install?: bool}>
     */
    protected function actionMeta(): array
    {
        return [
            'list' => [
                'methods' => ['GET'],
            ],
            'assignees' => [
                'methods' => ['GET'],
            ],
            'detail' => [
                'methods' => ['GET'],
            ],
            'attachmentDownload' => [
                'methods' => ['GET'],
            ],
            'update' => [
                'methods' => ['POST'],
            ],
            'assign' => [
                'methods' => ['POST'],
            ],
            'getOperations' => [
                'methods' => ['GET'],
            ],
        ];
    }

    protected function list(): void
    {
        $this->ensureAdmin();

        $statusRaw = Request::query('status');
        $statusEnum = $statusRaw !== '' ? TicketStatus::tryFrom((int)$statusRaw) : null;
        $status = $statusEnum !== null ? $statusEnum : null;

        $typeRaw = Request::query('type');
        $typeEnum = $typeRaw !== '' ? TicketType::tryFrom((int)$typeRaw) : null;
        $type = $typeEnum !== null ? $typeEnum : null;

        $assignedToRaw = Request::query('assignedTo');
        $assignedTo = $assignedToRaw !== '' ? $this->sanitizer->parseInt($assignedToRaw, 1, 9999999999) : null;

        $keyword = $this->sanitizer->sanitizeSingleLine(Request::query('keyword'), 120);
        $page = $this->sanitizer->parseInt(Request::query('page', '1'), 1, 100000);
        $pageSize = $this->sanitizer->parseInt(Request::query('pageSize', '20'), 5, 100);

        $result = $this->createTicketRepository()->listTickets($status, $type, $keyword, $assignedTo, $page, $pageSize);

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
        $this->ensureAdmin();

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
        $this->ensureAdmin();

        Responder::send([
            'ok' => true,
            'users' => $this->createUserRepository()->listAssignableUsers(),
        ]);
    }

    protected function update(): void
    {
        $currentUser = $this->ensureAdmin();

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

        $statusValue = $status;
        $updatedAt = date('Y-m-d H:i:s');
        $repo->updateTicket(
            $ticketNo,
            $statusValue,
            $safeSeverity,
            $adminNote !== '' ? $adminNote : null,
            $updatedAt
        );

        // 记录状态变更
        $oldStatus = (int)($ticket['status'] ?? 0);
        $newStatusValue = $statusValue instanceof TicketStatus ? $statusValue->value : (int)$statusValue;
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
        $this->ensureAdmin();

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
            $absolutePath = dirname(__DIR__, 3) . '/storage/uploads/' . ltrim($attachmentKey, '/');
            if (!is_file($absolutePath)) {
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

            $content = null;
            $lastHttpCode = 0;
            $lastCurlErrno = 0;
            $lastCurlError = '';
            $lastBody = '';

            foreach ($qiniuUrls as $qiniuUrl) {
                $ch = curl_init($qiniuUrl);
                if ($ch === false) {
                    continue;
                }

                $options = [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 30,
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

                if (is_string($response) && $curlErrno === 0 && $httpCode >= 200 && $httpCode < 300) {
                    $content = $response;
                    break;
                }

                $lastHttpCode = $httpCode;
                $lastCurlErrno = $curlErrno;
                $lastCurlError = $curlError;
                $lastBody = is_string($response) ? trim($response) : '';
            }

            if (!is_string($content)) {
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
            header('Content-Length: ' . (string)strlen($content));
            echo $content;
            exit;
        }

        Responder::error('UNSUPPORTED_STORAGE', '不支持的附件存储方式。', 500);
    }

    protected function assign(): void
    {
        $currentUser = $this->ensureAdmin();

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

    protected function getOperations(): void
    {
        $this->ensureAdmin();

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
