<?php

declare(strict_types=1);

namespace GameFeedback\Repository;

use PDO;
use Throwable;

/**
 * 工单数据库仓储类
 *
 * 封装 feedback_tickets 与 ticket_operations 表的全部 CRUD 操作。
 * 表结构迁移逻辑已迁移至 SchemaMigrationManager，本类只负责数据访问。
 */
final class TicketRepository
{
    /** @var PDO */
    private $pdo;

    /**
     * @param PDO $pdo 数据库连接实例
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 创建基础表结构（幂等，使用 CREATE TABLE IF NOT EXISTS）
     *
     * 包含 feedback_tickets（含 content_hash 列）和 ticket_operations 两张表。
     *
     * @return void
     */
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
    assigned_to BIGINT UNSIGNED NULL,
    status TINYINT UNSIGNED NOT NULL DEFAULT 0,
    admin_note TEXT NULL,
    content_hash CHAR(32) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_type_title (type, title),
    INDEX idx_status_created (status, created_at),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_content_hash (content_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

        $this->pdo->exec($sql);
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ticket_operations (' .
            'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ' .
            'ticket_no VARCHAR(32) NOT NULL, ' .
            'operator_id BIGINT UNSIGNED NOT NULL, ' .
            'operator_username VARCHAR(64) NOT NULL, ' .
            'operation_type VARCHAR(32) NOT NULL, ' .
            'old_value VARCHAR(255) NULL, ' .
            'new_value VARCHAR(255) NOT NULL, ' .
            'created_at DATETIME NOT NULL, ' .
            'INDEX idx_ticket_no (ticket_no), ' .
            'INDEX idx_operator_id (operator_id), ' .
            'INDEX idx_created_at (created_at)' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * 计算工单内容哈希，用于重复提交检测
     *
     * 对 type、title、details 做标准化（trim + 折叠空白 + 小写）后取 MD5，
     * 防止仅通过大小写或多余空格绕过重复检测。
     *
     * @param int    $type    反馈类型
     * @param string $title   标题
     * @param string $details 详情
     * @return string 32 位十六进制 MD5
     */
    public static function computeContentHash(int $type, string $title, string $details): string
    {
        $normalizedTitle = mb_strtolower(
            trim((string)(preg_replace('/\s+/u', ' ', $title) ?? '')),
            'UTF-8'
        );
        $normalizedDetails = mb_strtolower(
            trim((string)(preg_replace('/\s+/u', ' ', $details) ?? '')),
            'UTF-8'
        );
        return md5((string)$type . '|' . $normalizedTitle . '|' . $normalizedDetails);
    }

    /**
     * 查找相同内容哈希的重复工单
     *
     * 使用 computeContentHash 对输入做标准化后比对，防止大小写或多余空白绕过查重。
     *
     * @param int    $type    反馈类型
     * @param string $title   标题
     * @param string $details 详情
     * @return string|false 已存在则返回工单号，否则 false
     */
    public function findDuplicateTicketNo(int $type, string $title, string $details)
    {
        $hash = self::computeContentHash($type, $title, $details);
        $stmt = $this->pdo->prepare(
            'SELECT ticket_no FROM feedback_tickets WHERE content_hash = :content_hash LIMIT 1'
        );
        $stmt->execute([':content_hash' => $hash]);
        return $stmt->fetchColumn();
    }

    /**
     * 生成唯一工单号，格式为 FB{YYYYMMDD}{6位十六进制大写}
     *
     * 最多重试 20 次，超限则抛出 RuntimeException。
     *
     * @throws \RuntimeException 当无法在 20 次内生成唯一工单号时
     * @return string 新工单号
     */
    public function generateTicketNo(): string
    {
        $maxAttempts = 20;
        $attempts = 0;
        do {
            if (++$attempts > $maxAttempts) {
                throw new \RuntimeException('无法在 ' . $maxAttempts . ' 次内生成唯一工单号，请稍后重试。');
            }
            $candidate = 'FB' . date('Ymd') . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $this->pdo->prepare('SELECT id FROM feedback_tickets WHERE ticket_no = :ticket_no LIMIT 1');
            $stmt->execute([':ticket_no' => $candidate]);
            $exists = $stmt->fetchColumn() !== false;
        } while ($exists);

        return $candidate;
    }

    /**
     * 插入一条工单记录
     *
     * 会自动根据 type/title/details 计算并写入 content_hash，无需调用方手动传入。
     *
     * @param array<string, mixed> $ticket 绑定参数数组（不含 :content_hash，方法内自动计算）
     * @return void
     */
    public function insertTicket(array $ticket): void
    {
        // 自动计算内容哈希，用于重复提交检测（防止大小写/空白绕过）
        $ticket[':content_hash'] = self::computeContentHash(
            (int)($ticket[':type'] ?? 0),
            (string)($ticket[':title'] ?? ''),
            (string)($ticket[':details'] ?? '')
        );

        $stmt = $this->pdo->prepare(
            'INSERT INTO feedback_tickets ' .
            '(ticket_no, type, severity, title, details, contact, ' .
            'attachment_name, attachment_storage, attachment_key, attachment_mime, attachment_size, ' .
            'status, admin_note, content_hash, created_at, updated_at) ' .
            'VALUES (:ticket_no, :type, :severity, :title, :details, :contact, ' .
            ':attachment_name, :attachment_storage, :attachment_key, :attachment_mime, :attachment_size, ' .
            ':status, :admin_note, :content_hash, :created_at, :updated_at)'
        );
        $stmt->execute($ticket);
    }

    /**
     * 根据工单号查询单条工单（含全部字段）
     *
     * @param string $ticketNo 工单号
     * @return array<string, mixed>|false 工单记录或 false
     */
    public function findTicketByNo(string $ticketNo)
    {
        $stmt = $this->pdo->prepare(
            'SELECT ticket_no, type, severity, title, details, contact, ' .
            'attachment_name, attachment_storage, attachment_key, attachment_mime, attachment_size, ' .
            'status, admin_note, assigned_to, created_at, updated_at ' .
            'FROM feedback_tickets WHERE ticket_no = :ticket_no LIMIT 1'
        );
        $stmt->execute([':ticket_no' => $ticketNo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 管理员工单列表查询（支持筛选 + 分页）
     *
     * @param int|null $status      状态筛选（null 表示不筛选）
     * @param int|null $type        类型筛选（null 表示不筛选）
        * @param int|null $severity    严重程度筛选（null 表示不筛选）
     * @param string   $keyword     标题/内容关键词（空字符串表示不筛选）
     * @param int|null $assignedTo  指派用户 ID 筛选（null 表示不筛选）
     * @param int      $page        页码（从 1 开始）
     * @param int      $pageSize    每页条数
     * @return array{total: int, items: array<int, array<string, mixed>>}
     */
    public function listTickets(?int $status = null, ?int $type = null, ?int $severity = null, string $keyword = '', ?int $assignedTo = null, int $page = 1, int $pageSize = 20): array
    {
        $baseSql = ' FROM feedback_tickets t LEFT JOIN admin_users u ON t.assigned_to = u.id WHERE 1=1';
        $params = [];

        if ($status !== null) {
            $baseSql .= ' AND t.status = :status';
            $params[':status'] = $status;
        }

        if ($type !== null) {
            $baseSql .= ' AND t.type = :type';
            $params[':type'] = $type;
        }

        if ($severity !== null) {
            $baseSql .= ' AND t.severity = :severity';
            $params[':severity'] = $severity;
        }

        if ($keyword !== '') {
            $baseSql .= ' AND (t.title LIKE :keyword_title OR t.details LIKE :keyword_details)';
            $params[':keyword_title'] = '%' . $keyword . '%';
            $params[':keyword_details'] = '%' . $keyword . '%';
        }

        if ($assignedTo !== null) {
            $baseSql .= ' AND t.assigned_to = :assigned_to';
            $params[':assigned_to'] = $assignedTo;
        }

        $countStmt = $this->pdo->prepare('SELECT COUNT(1)' . $baseSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $pageSize;
        $sql = 'SELECT t.ticket_no, t.type, t.severity, t.title, t.contact, t.status, t.assigned_to, COALESCE(u.username, \'\') as assigned_username, t.created_at, t.updated_at' . $baseSql . ' ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset';

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

    /**
     * 公共工单模糊搜索（供玩家查询问题进度或解决方案）
     *
     * admin_note 也纳入搜索范围，因为管理员会将解决方案写在备注中供玩家参考。
     *
     * @param string $keyword  搜索关键词
     * @param int    $page     页码（从 1 开始）
     * @param int    $pageSize 每页条数
     * @return array{total: int, items: array<int, array<string, mixed>>}
     */
    public function searchPublicTickets(string $keyword, int $page = 1, int $pageSize = 20): array
    {
        $baseSql = ' FROM feedback_tickets WHERE 1=1';
        $params = [];

        if ($keyword !== '') {
            $baseSql .= ' AND (ticket_no LIKE :keyword_ticket_no OR title LIKE :keyword_title OR details LIKE :keyword_details OR admin_note LIKE :keyword_note)';
            $params[':keyword_ticket_no'] = '%' . $keyword . '%';
            $params[':keyword_title'] = '%' . $keyword . '%';
            $params[':keyword_details'] = '%' . $keyword . '%';
            $params[':keyword_note'] = '%' . $keyword . '%';
        }

        $countStmt = $this->pdo->prepare('SELECT COUNT(1)' . $baseSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $pageSize;
        $sql = 'SELECT ticket_no, type, severity, title, details, status, admin_note, created_at, updated_at' . $baseSql . ' ORDER BY updated_at DESC LIMIT :limit OFFSET :offset';

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

    /**
     * 检查工单号是否存在
     *
     * @param string $ticketNo 工单号
     * @return bool
     */
    public function existsByNo(string $ticketNo): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM feedback_tickets WHERE ticket_no = :ticket_no LIMIT 1');
        $stmt->execute([':ticket_no' => $ticketNo]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * 更新工单状态、严重程度、管理员备注
     *
     * @param string      $ticketNo  工单号
     * @param int         $status    新状态（TicketStatus 常量值）
     * @param int|null    $severity  新严重程度（非 BUG 工单传 null）
     * @param string|null $adminNote 管理员备注（空则传 null）
     * @param string      $updatedAt 更新时间（Y-m-d H:i:s 格式）
     * @return void
     */
    public function updateTicket(string $ticketNo, int $status, ?int $severity, ?string $adminNote, string $updatedAt): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE feedback_tickets SET status = :status, severity = :severity, admin_note = :admin_note, updated_at = :updated_at WHERE ticket_no = :ticket_no'
        );
        $stmt->execute([
            ':status' => $status,
            ':severity' => $severity,
            ':admin_note' => $adminNote,
            ':updated_at' => $updatedAt,
            ':ticket_no' => $ticketNo,
        ]);
    }

    /**
     * 指派工单给管理员用户
     *
     * @param string   $ticketNo   工单号
     * @param int|null $assignedTo 分配给的用户 ID（null 表示取消指派）
     * @param string   $updatedAt  更新时间（Y-m-d H:i:s 格式）
     * @return void
     */
    public function assignTicket(string $ticketNo, ?int $assignedTo, string $updatedAt): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE feedback_tickets SET assigned_to = :assigned_to, updated_at = :updated_at WHERE ticket_no = :ticket_no'
        );
        $stmt->execute([
            ':assigned_to' => $assignedTo,
            ':updated_at' => $updatedAt,
            ':ticket_no' => $ticketNo,
        ]);
    }

    /**
     * 记录工单操作日志到 ticket_operations 表
     *
     * @param string      $ticketNo      工单号
     * @param int         $operatorId    操作人 ID
     * @param string      $operatorName  操作人用户名
     * @param string      $operationType 操作类型（status_change / assign）
     * @param string|null $oldValue      旧值（null 表示无旧值）
     * @param string      $newValue      新值
     * @return void
     */
    public function recordOperation(string $ticketNo, int $operatorId, string $operatorName, string $operationType, ?string $oldValue, string $newValue): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ticket_operations ' .
                '(ticket_no, operator_id, operator_username, operation_type, old_value, new_value, created_at) ' .
                'VALUES (:ticket_no, :operator_id, :operator_username, :operation_type, :old_value, :new_value, :created_at)'
            );
            $result = $stmt->execute([
                ':ticket_no' => $ticketNo,
                ':operator_id' => $operatorId,
                ':operator_username' => $operatorName,
                ':operation_type' => $operationType,
                ':old_value' => $oldValue,
                ':new_value' => $newValue,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
            if (!$result) {
                error_log('Failed to record operation for ticket ' . $ticketNo . ': ' . json_encode($stmt->errorInfo()));
            }
        } catch (Throwable $e) {
            error_log('Exception recording operation for ticket ' . $ticketNo . ': ' . $e->getMessage());
        }
    }

    /**
     * 获取工单的操作记录列表（按时间倒序）
     *
     * @param string $ticketNo 工单号
     * @return array<int, array<string, mixed>> 操作记录列表
     */
    public function getTicketOperations(string $ticketNo): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, operator_id, operator_username, operation_type, old_value, new_value, created_at ' .
            'FROM ticket_operations ' .
            'WHERE ticket_no = :ticket_no ' .
            'ORDER BY created_at DESC'
        );
        $stmt->execute([':ticket_no' => $ticketNo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}