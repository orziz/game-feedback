<?php

declare(strict_types=1);

namespace GameFeedback\API\Feedback;

use GameFeedback\API\BaseApiSubModule;
use GameFeedback\Enums\TicketSeverity;
use GameFeedback\Enums\TicketStatus;
use GameFeedback\Enums\TicketType;
use GameFeedback\Support\AttachmentUploader;
use GameFeedback\Support\Request;
use GameFeedback\Support\Responder;

final class Ticket extends BaseApiSubModule
{
    /**
     * @return array<string, array{methods: array<int, string>, allow_before_install?: bool}>
     */
    protected function actionMeta(): array
    {
        return [
            'submit' => [
                'methods' => ['POST'],
            ],
            'search' => [
                'methods' => ['GET'],
            ],
        ];
    }

    protected function submit(): void
    {
        $payload = Request::isMultipartFormData() ? Request::formBody() : Request::jsonBody();

        $type = TicketType::tryFrom((int)($payload['type'] ?? -1));
        $severity = TicketSeverity::tryFrom((int)($payload['severity'] ?? -1));
        $title = $this->sanitizer->sanitizeSingleLine((string)($payload['title'] ?? ''), 120);
        $description = $this->sanitizer->sanitizeText((string)($payload['description'] ?? ''), 3000);
        $contact = $this->sanitizer->sanitizeSingleLine((string)($payload['contact'] ?? ''), 120);

        if ($type === null || $severity === null || $title === '' || $description === '') {
            Responder::error('MISSING_REQUIRED_FIELDS', '反馈类型、严重程度、标题、详细介绍均为必填。', 422);
        }

        $attachmentMeta = (new AttachmentUploader($this->dbConfig))->handleUpload(Request::uploadedFile('attachment'));

        $repo = $this->createTicketRepository();
        $existingTicketNo = $repo->findDuplicateTicketNo($type, $title, $description);

        if ($existingTicketNo !== false) {
            Responder::send([
                'ok' => false,
                'code' => 'DUPLICATE_TICKET',
                'message' => '已有相同反馈，请勿重复提交。',
                'ticketNo' => $existingTicketNo,
            ], 409);
        }

        $ticketNo = $repo->generateTicketNo();
        $now = date('Y-m-d H:i:s');

        $repo->insertTicket([
            ':ticket_no' => $ticketNo,
            ':type' => $type,
            ':severity' => $severity,
            ':title' => $title,
            ':details' => $description,
            ':contact' => $contact,
            ':attachment_name' => $attachmentMeta['name'],
            ':attachment_storage' => $attachmentMeta['storage'],
            ':attachment_key' => $attachmentMeta['key'],
            ':attachment_mime' => $attachmentMeta['mime'],
            ':attachment_size' => $attachmentMeta['size'],
            ':status' => TicketStatus::Pending,
            ':admin_note' => null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        Responder::send([
            'ok' => true,
            'message' => '提交成功。',
            'ticketNo' => $ticketNo,
        ], 201);
    }

    protected function search(): void
    {
        $keyword = $this->sanitizer->sanitizeSingleLine(Request::query('keyword'), 120);
        if ($keyword === '') {
            Responder::error('MISSING_KEYWORD', '请输入工单号或标题/内容关键词。', 422);
        }

        $repo = $this->createTicketRepository();
        if ($this->sanitizer->isValidTicketNo($keyword)) {
            $ticket = $repo->findTicketByNo($keyword);
            if ($ticket) {
                $ticket = $this->toPublicTicket($ticket);
            }

            Responder::send([
                'ok' => true,
                'tickets' => $ticket ? [$ticket] : [],
                'pagination' => [
                    'total' => $ticket ? 1 : 0,
                    'page' => 1,
                    'pageSize' => 1,
                    'totalPages' => 1,
                ],
            ]);
        }

        $page = $this->sanitizer->parseInt(Request::query('page', '1'), 1, 100000);
        $pageSize = $this->sanitizer->parseInt(Request::query('pageSize', '10'), 5, 50);
        $result = $repo->searchPublicTickets($keyword, $page, $pageSize);

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
     * @param array<string, mixed> $ticket
     * @return array<string, mixed>
     */
    private function toPublicTicket(array $ticket): array
    {
        unset(
            $ticket['attachment_name'],
            $ticket['attachment_storage'],
            $ticket['attachment_key'],
            $ticket['attachment_mime'],
            $ticket['attachment_size'],
            $ticket['contact']
        );

        return $ticket;
    }
}
