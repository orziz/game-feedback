<?php

declare(strict_types=1);

require_once __DIR__ . '/../Support/Responder.php';
require_once __DIR__ . '/../Enums/TicketType.php';
require_once __DIR__ . '/../Enums/TicketSeverity.php';
require_once __DIR__ . '/../Enums/TicketStatus.php';

final class TicketRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function createTableIfNotExists(): void
    {
                $sql = <<<SQL
CREATE TABLE IF NOT EXISTS feedback_tickets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_no VARCHAR(32) NOT NULL UNIQUE,
    type TINYINT UNSIGNED NOT NULL,
    severity TINYINT UNSIGNED NULL,
  title VARCHAR(120) NOT NULL,
  details TEXT NOT NULL,
  contact VARCHAR(120) NOT NULL,
    attachment_name VARCHAR(255) NULL,
    attachment_storage VARCHAR(16) NULL,
    attachment_key VARCHAR(255) NULL,
    attachment_mime VARCHAR(80) NULL,
    attachment_size INT UNSIGNED NULL,
    status TINYINT UNSIGNED NOT NULL DEFAULT 0,
  admin_note TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_type_title (type, title),
  INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

        $this->pdo->exec($sql);
        $this->migrateLegacyEnumColumnsIfNeeded();
        $this->ensureAttachmentColumns();
    }

    private function migrateLegacyEnumColumnsIfNeeded(): void
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM feedback_tickets LIKE 'status'");
        $statusCol = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$statusCol || strpos(strtolower((string)($statusCol['Type'] ?? '')), 'enum') !== 0) {
            return;
        }

        $this->pdo->exec(
            'ALTER TABLE feedback_tickets ' .
            'ADD COLUMN type_num TINYINT UNSIGNED NOT NULL DEFAULT 3, ' .
            'ADD COLUMN severity_num TINYINT UNSIGNED NULL, ' .
            'ADD COLUMN status_num TINYINT UNSIGNED NOT NULL DEFAULT 0'
        );

        $this->pdo->exec("UPDATE feedback_tickets SET type_num = CASE type WHEN 'BUG' THEN 0 WHEN '优化' THEN 1 WHEN '建议' THEN 2 ELSE 3 END");
        $this->pdo->exec("UPDATE feedback_tickets SET severity_num = CASE severity WHEN '低' THEN 0 WHEN '中' THEN 1 WHEN '高' THEN 2 WHEN '致命' THEN 3 ELSE NULL END");
        $this->pdo->exec("UPDATE feedback_tickets SET status_num = CASE status WHEN '待处理' THEN 0 WHEN '处理中' THEN 1 WHEN '已解决' THEN 2 WHEN '已关闭' THEN 3 ELSE 0 END");

        $this->pdo->exec('ALTER TABLE feedback_tickets DROP COLUMN type, DROP COLUMN severity, DROP COLUMN status');
        $this->pdo->exec(
            'ALTER TABLE feedback_tickets ' .
            'CHANGE COLUMN type_num type TINYINT UNSIGNED NOT NULL, ' .
            'CHANGE COLUMN severity_num severity TINYINT UNSIGNED NULL, ' .
            'CHANGE COLUMN status_num status TINYINT UNSIGNED NOT NULL DEFAULT 0'
        );
    }

    private function ensureAttachmentColumns(): void
    {
        $attachmentColumns = [
            'attachment_name',
            'attachment_storage',
            'attachment_key',
            'attachment_mime',
            'attachment_size',
        ];

        $missingColumns = [];
        foreach ($attachmentColumns as $col) {
            if (!$this->columnExists($col)) {
                $missingColumns[] = $col;
            }
        }

        if (empty($missingColumns)) {
            return;
        }

        try {
            $columnDefs = [];
            foreach ($missingColumns as $col) {
                if ($col === 'attachment_name') {
                    $columnDefs[] = 'ADD COLUMN attachment_name VARCHAR(255) NULL';
                } elseif ($col === 'attachment_storage') {
                    $columnDefs[] = 'ADD COLUMN attachment_storage VARCHAR(16) NULL';
                } elseif ($col === 'attachment_key') {
                    $columnDefs[] = 'ADD COLUMN attachment_key VARCHAR(255) NULL';
                } elseif ($col === 'attachment_mime') {
                    $columnDefs[] = 'ADD COLUMN attachment_mime VARCHAR(80) NULL';
                } elseif ($col === 'attachment_size') {
                    $columnDefs[] = 'ADD COLUMN attachment_size INT UNSIGNED NULL';
                }
            }

            if (!empty($columnDefs)) {
                $this->pdo->exec('ALTER TABLE feedback_tickets ' . implode(', ', $columnDefs));
            }
        } catch (Throwable $e) {
            Responder::error('TABLE_MIGRATION_FAILED', '表结构升级失败：' . $e->getMessage(), 500);
        }
    }

    private function columnExists(string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table_name AND COLUMN_NAME = :column_name LIMIT 1'
        );
        $stmt->execute([
            ':table_name' => 'feedback_tickets',
            ':column_name' => $column,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function findDuplicateTicketNo(int $type, string $title, string $details)
    {
        $stmt = $this->pdo->prepare('SELECT ticket_no FROM feedback_tickets WHERE type = :type AND title = :title AND details = :details LIMIT 1');
        $stmt->execute([
            ':type' => $type,
            ':title' => $title,
            ':details' => $details,
        ]);

        return $stmt->fetchColumn();
    }

    public function generateTicketNo(): string
    {
        do {
            $candidate = 'FB' . date('Ymd') . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $this->pdo->prepare('SELECT id FROM feedback_tickets WHERE ticket_no = :ticket_no LIMIT 1');
            $stmt->execute([':ticket_no' => $candidate]);
            $exists = $stmt->fetchColumn() !== false;
        } while ($exists);

        return $candidate;
    }

    public function insertTicket(array $ticket): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO feedback_tickets (ticket_no, type, severity, title, details, contact, attachment_name, attachment_storage, attachment_key, attachment_mime, attachment_size, status, admin_note, created_at, updated_at) VALUES (:ticket_no, :type, :severity, :title, :details, :contact, :attachment_name, :attachment_storage, :attachment_key, :attachment_mime, :attachment_size, :status, :admin_note, :created_at, :updated_at)');
        $stmt->execute($ticket);
    }

    public function findTicketByNo(string $ticketNo)
    {
        $stmt = $this->pdo->prepare('SELECT ticket_no, type, severity, title, details, contact, attachment_name, attachment_storage, attachment_key, attachment_mime, attachment_size, status, admin_note, created_at, updated_at FROM feedback_tickets WHERE ticket_no = :ticket_no LIMIT 1');
        $stmt->execute([':ticket_no' => $ticketNo]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listTickets(?int $status = null, ?int $type = null, string $keyword = '', int $page = 1, int $pageSize = 20): array
    {
        $baseSql = ' FROM feedback_tickets WHERE 1=1';
        $params = [];

        if ($status !== null) {
            $baseSql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        if ($type !== null) {
            $baseSql .= ' AND type = :type';
            $params[':type'] = $type;
        }

        if ($keyword !== '') {
            $baseSql .= ' AND (title LIKE :keyword_title OR details LIKE :keyword_details)';
            $params[':keyword_title'] = '%' . $keyword . '%';
            $params[':keyword_details'] = '%' . $keyword . '%';
        }

        $countStmt = $this->pdo->prepare('SELECT COUNT(1)' . $baseSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $pageSize;
        $sql = 'SELECT ticket_no, type, severity, title, contact, status, created_at, updated_at' . $baseSql . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

        return [
            'total' => $total,
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function searchPublicSolvedTickets(string $keyword, int $page = 1, int $pageSize = 20): array
    {
        $baseSql = ' FROM feedback_tickets WHERE status IN (2, 3)';
        $params = [];

        if ($keyword !== '') {
            $baseSql .= ' AND (title LIKE :keyword_title OR details LIKE :keyword_details OR admin_note LIKE :keyword_note)';
            $params[':keyword_title'] = '%' . $keyword . '%';
            $params[':keyword_details'] = '%' . $keyword . '%';
            $params[':keyword_note'] = '%' . $keyword . '%';
        }

        $countStmt = $this->pdo->prepare('SELECT COUNT(1)' . $baseSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $pageSize;
        $sql = 'SELECT ticket_no, type, severity, title, details, status, admin_note, updated_at' . $baseSql . ' ORDER BY updated_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'total' => $total,
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function existsByNo(string $ticketNo): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM feedback_tickets WHERE ticket_no = :ticket_no LIMIT 1');
        $stmt->execute([':ticket_no' => $ticketNo]);
        return $stmt->fetchColumn() !== false;
    }

    public function updateTicket(string $ticketNo, int $status, ?int $severity, ?string $adminNote, string $updatedAt): void
    {
        $stmt = $this->pdo->prepare('UPDATE feedback_tickets SET status = :status, severity = :severity, admin_note = :admin_note, updated_at = :updated_at WHERE ticket_no = :ticket_no');
        $stmt->execute([
            ':status' => $status,
            ':severity' => $severity,
            ':admin_note' => $adminNote,
            ':updated_at' => $updatedAt,
            ':ticket_no' => $ticketNo,
        ]);
    }
}
