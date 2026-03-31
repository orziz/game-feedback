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
            'detail' => [
                'methods' => ['GET'],
            ],
            'attachmentDownload' => [
                'methods' => ['GET'],
            ],
            'update' => [
                'methods' => ['POST'],
            ],
        ];
    }

    protected function list(): void
    {
        $this->ensureAdmin();

        $statusRaw = Request::query('status');
        $statusEnum = $statusRaw !== '' ? TicketStatus::tryFrom((int)$statusRaw) : null;

        $typeRaw = Request::query('type');
        $typeEnum = $typeRaw !== '' ? TicketType::tryFrom((int)$typeRaw) : null;

        $keyword = $this->sanitizer->sanitizeSingleLine(Request::query('keyword'), 120);
        $gameKey = $this->resolveGameKey(false);
        $page = $this->sanitizer->parseInt(Request::query('page', '1'), 1, 100000);
        $pageSize = $this->sanitizer->parseInt(Request::query('pageSize', '20'), 5, 100);

        if ($gameKey !== null) {
            $this->ensureGameExists($gameKey);
        }

        $result = $this->createTicketRepository()->listTickets($statusEnum, $typeEnum, $keyword, $page, $pageSize, $gameKey);

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
        $gameKey = $this->resolveGameKey(false);
        if ($ticketNo === '' || !$this->sanitizer->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        if ($gameKey !== null) {
            $this->ensureGameExists($gameKey);
        }

        $ticket = $this->createTicketRepository()->findTicketByNo($ticketNo, $gameKey);
        if (!$ticket) {
            Responder::error('TICKET_NOT_FOUND', '工单不存在。', 404);
        }

        Responder::send([
            'ok' => true,
            'ticket' => $ticket,
        ]);
    }

    protected function update(): void
    {
        $this->ensureAdmin();

        $payload = Request::jsonBody();
        $ticketNo = $this->sanitizer->sanitizeSingleLine((string)($payload['ticketNo'] ?? ''), 32);
        $gameKey = $this->sanitizer->sanitizeSingleLine((string)($payload['gameKey'] ?? ''), 64);
        $status = TicketStatus::tryFrom((int)($payload['status'] ?? -1));
        $severity = array_key_exists('severity', $payload)
            ? TicketSeverity::tryFrom((int)$payload['severity'])
            : null;
        $adminNote = $this->sanitizer->sanitizeText((string)($payload['adminNote'] ?? ''), 2000);

        if ($ticketNo === '' || $status === null) {
            Responder::error('MISSING_REQUIRED_FIELDS', '工单号和状态不能为空。', 422);
        }

        if ($gameKey !== '' && preg_match('/^[a-z][a-z0-9_\-]{1,63}$/', $gameKey) !== 1) {
            Responder::error('INVALID_GAME_KEY', 'gameKey 仅支持小写字母、数字、下划线和短横线。', 422);
        }

        if (!$this->sanitizer->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        $repo = $this->createTicketRepository();
        $scopeGameKey = $gameKey !== '' ? $gameKey : null;
        if ($scopeGameKey !== null) {
            $this->ensureGameExists($scopeGameKey);
        }

        $ticket = $repo->findTicketByNo($ticketNo, $scopeGameKey);
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

        $repo->updateTicket(
            $ticketNo,
            $status,
            $safeSeverity,
            $adminNote !== '' ? $adminNote : null,
            date('Y-m-d H:i:s'),
            $scopeGameKey
        );

        Responder::send([
            'ok' => true,
            'message' => '更新成功。',
        ]);
    }

    protected function attachmentDownload(): void
    {
        $this->ensureAdmin();

        $ticketNo = $this->sanitizer->sanitizeSingleLine(Request::query('ticketNo'), 32);
        $gameKey = $this->resolveGameKey(false);
        if ($ticketNo === '' || !$this->sanitizer->isValidTicketNo($ticketNo)) {
            Responder::error('INVALID_TICKET_NO', '工单号格式不正确。', 422);
        }

        if ($gameKey !== null) {
            $this->ensureGameExists($gameKey);
        }

        $ticket = $this->createTicketRepository()->findTicketByNo($ticketNo, $gameKey);
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
}
