<?php

declare(strict_types=1);

namespace GameFeedback\Repository;

use GameFeedback\Support\Responder;
use PDO;
use Throwable;


/**
 * 工单数据库仓储类
 *
 * 封装 feedback_tickets 表的全部 CRUD 操作及表结构迁移
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
     * 创建当前版本所需的基础表结构
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
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_type_title (type, title),
    INDEX idx_status_created (status, created_at),
    INDEX idx_assigned_to (assigned_to)
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
     * 迁移旧版 ENUM 类型字段到整数列
     *
     * @return void
     */
    public function migrateLegacyEnumColumns(): void
    {
        $this->migrateLegacyEnumColumnsIfNeeded();
    }

    /**
     * 补齐附件相关字段
     *
     * @return void
     */
    public function migrateAttachmentColumns(): void
    {
        $this->ensureAttachmentColumns();
    }

    /**
     * 补齐指派字段和索引
     *
     * @return void
     */
    public function migrateAssignmentSupport(): void
    {
        $this->ensureAssignedToColumn();
    }

    /**
     * 补齐工单操作记录表
     *
     * @return void
     */
    public function migrateTicketOperationsSupport(): void
    {
        $this->ensureTicketOperationsTable();
    }

    /**
     * 将旧版 ENUM 类型字段迁移为 TINYINT 整数字段
     *
     * @return void
     */
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

    /**
     * 确保附件相关字段存在，不存在则自动添加
     *
     * @return void
     */
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

    /**
     * 确保 assigned_to 字段和索引存在，不存在则自动添加
     *
     * @return void
     */
    private function ensureAssignedToColumn(): void
    {
        if ($this->columnExists('assigned_to')) {
            if (!$this->indexExists('idx_assigned_to')) {
                $this->pdo->exec('ALTER TABLE feedback_tickets ADD INDEX idx_assigned_to (assigned_to)');
            }

            return;
        }

        try {
            $this->pdo->exec(
                'ALTER TABLE feedback_tickets ' .
                'ADD COLUMN assigned_to BIGINT UNSIGNED NULL, ' .
                'ADD INDEX idx_assigned_to (assigned_to)'
            );
        } catch (Throwable $e) {
            Responder::error('TABLE_MIGRATION_FAILED', '表结构升级失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 确保 ticket_operations 表存在，不存在则自动创建
     *
     * @return void
     */
    private function ensureTicketOperationsTable(): void
    {
        try {
            if (!$this->tableExists('ticket_operations')) {
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
                return;
            }

            $alterClauses = [];
            if (!$this->operationColumnExists('operator_id')) {
                $alterClauses[] = 'ADD COLUMN operator_id BIGINT UNSIGNED NOT NULL DEFAULT 0';
            }
            if (!$this->operationColumnExists('operator_username')) {
                $alterClauses[] = 'ADD COLUMN operator_username VARCHAR(64) NOT NULL DEFAULT \'\'';
            }
            if (!$this->operationColumnExists('operation_type')) {
                $alterClauses[] = 'ADD COLUMN operation_type VARCHAR(32) NOT NULL DEFAULT \'assign\'';
            }
            if (!$this->operationColumnExists('old_value')) {
                $alterClauses[] = 'ADD COLUMN old_value VARCHAR(255) NULL';
            }
            if (!$this->operationColumnExists('new_value')) {
                $alterClauses[] = 'ADD COLUMN new_value VARCHAR(255) NOT NULL DEFAULT \'\'';
            }
            if (!$this->operationColumnExists('created_at')) {
                $alterClauses[] = 'ADD COLUMN created_at DATETIME NOT NULL';
            }

            if (!empty($alterClauses)) {
                $this->pdo->exec('ALTER TABLE ticket_operations ' . implode(', ', $alterClauses));
            }

            if (!$this->operationIndexExists('idx_ticket_no')) {
                $this->pdo->exec('ALTER TABLE ticket_operations ADD INDEX idx_ticket_no (ticket_no)');
            }
            if (!$this->operationIndexExists('idx_operator_id')) {
                $this->pdo->exec('ALTER TABLE ticket_operations ADD INDEX idx_operator_id (operator_id)');
            }
            if (!$this->operationIndexExists('idx_created_at')) {
                $this->pdo->exec('ALTER TABLE ticket_operations ADD INDEX idx_created_at (created_at)');
            }
        } catch (Throwable $e) {
            Responder::error('TABLE_MIGRATION_FAILED', '表结构升级失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 检查指定列是否存在于 feedback_tickets 表
     *
     * @param string $column 列名
     * @return bool
     */
    private function columnExists(string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name LIMIT 1'
        );
        $stmt->execute([
            ':table_name' => 'feedback_tickets',
            ':column_name' => $column,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * 检查指定索引是否存在于 feedback_tickets 表
     *
     * @param string $index 索引名
     * @return bool
     */
    private function indexExists(string $index): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name LIMIT 1'
        );
        $stmt->execute([
            ':table_name' => 'feedback_tickets',
            ':index_name' => $index,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * 检查指定表是否存在
     *
     * @param string $table 表名
     * @return bool
     */
    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name LIMIT 1'
        );
        $stmt->execute([
            ':table_name' => $table,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * 检查指定列是否存在于 ticket_operations 表
     *
     * @param string $column 列名
     * @return bool
     */
    private function operationColumnExists(string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name LIMIT 1'
        );
        $stmt->execute([
            ':table_name' => 'ticket_operations',
            ':column_name' => $column,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * 检查指定索引是否存在于 ticket_operations 表
     *
     * @param string $index 索引名
     * @return bool
     */
    private function operationIndexExists(string $index): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name LIMIT 1'
        );
        $stmt->execute([
            ':table_name' => 'ticket_operations',
            ':index_name' => $index,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * 查找相同类型、标题、详情的重复工单
     *
     * @param int    $type    反馈类型
     * @param string $title   标题
     * @param string $details 详情
     * @return string|false 已存在则返回工单号，否则 false
     */
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

    /**
     * 生成唯一工单号，格式为 FB{YYYYMMDD}{6位十六进制}
     *
     * @return string 新工单号
     */
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

    /**
     * 插入一条工单记录
     *
     * @param array<string, mixed> $ticket 绑定参数数组
     * @return void
     */
    public function insertTicket(array $ticket): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO feedback_tickets (ticket_no, type, severity, title, details, contact, attachment_name, attachment_storage, attachment_key, attachment_mime, attachment_size, status, admin_note, created_at, updated_at) VALUES (:ticket_no, :type, :severity, :title, :details, :contact, :attachment_name, :attachment_storage, :attachment_key, :attachment_mime, :attachment_size, :status, :admin_note, :created_at, :updated_at)');
        $stmt->execute($ticket);
    }

    /**
     * 根据工单号查询单条工单
     *
     * @param string $ticketNo 工单号
     * @return array<string, mixed>|false 工单记录或 false
     */
    public function findTicketByNo(string $ticketNo)
    {
        $stmt = $this->pdo->prepare('SELECT ticket_no, type, severity, title, details, contact, attachment_name, attachment_storage, attachment_key, attachment_mime, attachment_size, status, admin_note, assigned_to, created_at, updated_at FROM feedback_tickets WHERE ticket_no = :ticket_no LIMIT 1');
        $stmt->execute([':ticket_no' => $ticketNo]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 管理员工单列表查询（支持筛选 + 分页）
     *
     * @param int|null $status   状态筛选
     * @param int|null $type     类型筛选
     * @param string   $keyword  标题/内容关键词
     * @param int      $page     页码
     * @param int      $pageSize 每页条数
     * @return array{total: int, items: array<int, array<string, mixed>>}
     */
    /**
     * 管理员工单列表查询（支持筛选 + 分页）
     *
     * @param int|null $status      状态筛选
     * @param int|null $type        类型筛选
     * @param string   $keyword     标题/内容关键词
     * @param int|null $assignedTo  指派给用户的ID筛选
     * @param int      $page        页码
     * @param int      $pageSize    每页条数
     * @return array{total: int, items: array<int, array<string, mixed>>}
     */
    public function listTickets(?int $status = null, ?int $type = null, string $keyword = '', ?int $assignedTo = null, int $page = 1, int $pageSize = 20): array
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
     * 公共工单模糊搜索（玩家问题搜索）
     *
     * @param string $keyword  搜索关键词
     * @param int    $page     页码
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
     * @param string   $ticketNo  工单号
     * @param int      $status    新状态
     * @param int|null $severity  新严重程度（非 BUG 工单传 null）
     * @param string|null $adminNote 管理员备注
     * @param string   $updatedAt 更新时间
     * @return void
     */
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

    /**
     * 指派工单给管理员用户
     *
     * @param string     $ticketNo   工单号
     * @param int|null   $assignedTo 分配给的用户 ID（null 表示取消指派）
     * @param string     $updatedAt  更新时间
     * @return void
     */
    public function assignTicket(string $ticketNo, ?int $assignedTo, string $updatedAt): void
    {
        $stmt = $this->pdo->prepare('UPDATE feedback_tickets SET assigned_to = :assigned_to, updated_at = :updated_at WHERE ticket_no = :ticket_no');
        $stmt->execute([
            ':assigned_to' => $assignedTo,
            ':updated_at' => $updatedAt,
            ':ticket_no' => $ticketNo,
        ]);
    }

    /**
     * 记录工单操作
     *
     * @param string $ticketNo     工单号
     * @param int    $operatorId   操作人ID
     * @param string $operatorName 操作人名称
     * @param string $operationType 操作类型（status_change, assign）
     * @param string|null $oldValue 旧值
     * @param string $newValue     新值
     * @return void
     */
    public function recordOperation(string $ticketNo, int $operatorId, string $operatorName, string $operationType, ?string $oldValue, string $newValue): void
    {
        try {
            // 确保表存在
            $this->ensureTicketOperationsTable();
            
            $stmt = $this->pdo->prepare(
                'INSERT INTO ticket_operations (ticket_no, operator_id, operator_username, operation_type, old_value, new_value, created_at) ' .
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
                $error = $stmt->errorInfo();
                error_log('Failed to record operation for ticket ' . $ticketNo . ': ' . json_encode($error));
            }
        } catch (Exception $e) {
            error_log('Exception recording operation for ticket ' . $ticketNo . ': ' . $e->getMessage());
        }
    }

    /**
     * 获取工单的操作记录
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
