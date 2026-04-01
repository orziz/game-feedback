<?php

declare(strict_types=1);

namespace GameFeedback\Support;

use GameFeedback\Repository\TicketRepository;
use GameFeedback\Repository\UserRepository;
use PDO;
use Throwable;

/**
 * 数据库结构迁移管理器
 *
 * 负责所有增量 DDL 迁移，保持 schema_version 与 CURRENT_SCHEMA_VERSION 同步。
 * 每个版本迁移均设计为幂等操作（多次执行结果一致）。
 *
 * 版本历史：
 *  1: type/severity/status 从 ENUM 迁移到 TINYINT
 *  2: 附件相关字段
 *  3: assigned_to 字段与索引
 *  4: ticket_operations 操作记录表
 *  5: content_hash 字段（用于基于内容哈希的重复工单检测）
 */
final class SchemaMigrationManager
{
    /** 当前最新 schema 版本号 */
    const CURRENT_SCHEMA_VERSION = 5;

    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 安装全量基础 schema（用于全新安装）
     *
     * @return void
     */
    public function installLatestSchema(): void
    {
        $this->createBaseTables();
    }

    /**
     * 从指定版本执行增量迁移，返回迁移后的最新版本号
     *
     * @param int $schemaVersion 当前已有的 schema 版本
     * @return int 迁移完成后的版本号
     */
    public function migrateFromVersion(int $schemaVersion): int
    {
        $version = $schemaVersion;

        // 确保基础表结构存在（幂等）
        $this->createBaseTables();

        if ($version < 1) {
            // 将 ENUM 类型字段迁移为 TINYINT 整数字段
            $this->migrateLegacyEnumColumnsIfNeeded();
            $version = 1;
        }

        if ($version < 2) {
            // 补齐附件相关字段
            $this->ensureAttachmentColumns();
            $version = 2;
        }

        if ($version < 3) {
            // 补齐 assigned_to 字段和索引
            $this->ensureAssignedToColumn();
            $version = 3;
        }

        if ($version < 4) {
            // 创建工单操作记录表
            $this->ensureTicketOperationsTable();
            $version = 4;
        }

        if ($version < 5) {
            // 添加 content_hash 列（用于内容哈希去重）并回填历史数据
            $this->ensureContentHashColumn();
            $this->backfillContentHash();
            $version = 5;
        }

        return $version;
    }

    // ==================== 基础表创建 ====================

    /**
     * 创建基础表（feedback_tickets + admin_users + ticket_operations）
     *
     * @return void
     */
    private function createBaseTables(): void
    {
        (new TicketRepository($this->pdo))->createTableIfNotExists();
        (new UserRepository($this->pdo))->createTableIfNotExists();
    }

    // ==================== 迁移版本 1：ENUM → TINYINT ====================

    /**
     * 将旧版 ENUM 类型的 type/severity/status 字段迁移为 TINYINT
     *
     * 仅在 status 列仍为 ENUM 类型时执行，否则立即返回（幂等）。
     *
     * @return void
     */
    private function migrateLegacyEnumColumnsIfNeeded(): void
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM feedback_tickets LIKE 'status'");
        $statusCol = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!$statusCol || strpos(strtolower((string)($statusCol['Type'] ?? '')), 'enum') !== 0) {
            // status 列已是整数类型或不存在，无需迁移
            return;
        }

        // 新增临时整数列
        $this->pdo->exec(
            'ALTER TABLE feedback_tickets ' .
            'ADD COLUMN type_num TINYINT UNSIGNED NOT NULL DEFAULT 3, ' .
            'ADD COLUMN severity_num TINYINT UNSIGNED NULL, ' .
            'ADD COLUMN status_num TINYINT UNSIGNED NOT NULL DEFAULT 0'
        );

        // 将 ENUM 字面量映射为整数
        $this->pdo->exec("UPDATE feedback_tickets SET type_num = CASE type WHEN 'BUG' THEN 0 WHEN '优化' THEN 1 WHEN '建议' THEN 2 ELSE 3 END");
        $this->pdo->exec("UPDATE feedback_tickets SET severity_num = CASE severity WHEN '低' THEN 0 WHEN '中' THEN 1 WHEN '高' THEN 2 WHEN '致命' THEN 3 ELSE NULL END");
        $this->pdo->exec("UPDATE feedback_tickets SET status_num = CASE status WHEN '待处理' THEN 0 WHEN '处理中' THEN 1 WHEN '已解决' THEN 2 WHEN '已关闭' THEN 3 ELSE 0 END");

        // 删除旧 ENUM 列并将临时列重命名为正式列
        $this->pdo->exec('ALTER TABLE feedback_tickets DROP COLUMN type, DROP COLUMN severity, DROP COLUMN status');
        $this->pdo->exec(
            'ALTER TABLE feedback_tickets ' .
            'CHANGE COLUMN type_num type TINYINT UNSIGNED NOT NULL, ' .
            'CHANGE COLUMN severity_num severity TINYINT UNSIGNED NULL, ' .
            'CHANGE COLUMN status_num status TINYINT UNSIGNED NOT NULL DEFAULT 0'
        );
    }

    // ==================== 迁移版本 2：附件字段 ====================

    /**
     * 确保附件相关字段存在，不存在则添加（幂等）
     *
     * @return void
     */
    private function ensureAttachmentColumns(): void
    {
        $attachmentColumns = [
            'attachment_name', 'attachment_storage', 'attachment_key',
            'attachment_mime', 'attachment_size',
        ];

        $missingColumns = [];
        foreach ($attachmentColumns as $col) {
            if (!$this->columnExists('feedback_tickets', $col)) {
                $missingColumns[] = $col;
            }
        }

        if (empty($missingColumns)) {
            return;
        }

        try {
            $columnDefs = [];
            foreach ($missingColumns as $col) {
                switch ($col) {
                    case 'attachment_name':
                        $columnDefs[] = 'ADD COLUMN attachment_name VARCHAR(255) NULL';
                        break;
                    case 'attachment_storage':
                        $columnDefs[] = 'ADD COLUMN attachment_storage VARCHAR(16) NULL';
                        break;
                    case 'attachment_key':
                        $columnDefs[] = 'ADD COLUMN attachment_key VARCHAR(255) NULL';
                        break;
                    case 'attachment_mime':
                        $columnDefs[] = 'ADD COLUMN attachment_mime VARCHAR(80) NULL';
                        break;
                    case 'attachment_size':
                        $columnDefs[] = 'ADD COLUMN attachment_size INT UNSIGNED NULL';
                        break;
                }
            }
            if (!empty($columnDefs)) {
                $this->pdo->exec('ALTER TABLE feedback_tickets ' . implode(', ', $columnDefs));
            }
        } catch (Throwable $e) {
            Responder::error('TABLE_MIGRATION_FAILED', '表结构升级失败（附件字段）：' . $e->getMessage(), 500);
        }
    }

    // ==================== 迁移版本 3：assigned_to ====================

    /**
     * 确保 assigned_to 字段和索引存在（幂等）
     *
     * @return void
     */
    private function ensureAssignedToColumn(): void
    {
        if ($this->columnExists('feedback_tickets', 'assigned_to')) {
            // 列已存在，仅检查索引
            if (!$this->indexExists('feedback_tickets', 'idx_assigned_to')) {
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
            Responder::error('TABLE_MIGRATION_FAILED', '表结构升级失败（assigned_to）：' . $e->getMessage(), 500);
        }
    }

    // ==================== 迁移版本 4：ticket_operations 表 ====================

    /**
     * 确保 ticket_operations 表及必要字段和索引存在（幂等）
     *
     * @return void
     */
    private function ensureTicketOperationsTable(): void
    {
        try {
            if (!$this->tableExists('ticket_operations')) {
                // 表不存在，直接创建
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

            // 表已存在，补齐可能缺失的字段
            $missingCols = [];
            if (!$this->columnExists('ticket_operations', 'operator_id')) {
                $missingCols[] = 'ADD COLUMN operator_id BIGINT UNSIGNED NOT NULL DEFAULT 0';
            }
            if (!$this->columnExists('ticket_operations', 'operator_username')) {
                $missingCols[] = "ADD COLUMN operator_username VARCHAR(64) NOT NULL DEFAULT ''";
            }
            if (!$this->columnExists('ticket_operations', 'operation_type')) {
                $missingCols[] = "ADD COLUMN operation_type VARCHAR(32) NOT NULL DEFAULT 'assign'";
            }
            if (!$this->columnExists('ticket_operations', 'old_value')) {
                $missingCols[] = 'ADD COLUMN old_value VARCHAR(255) NULL';
            }
            if (!$this->columnExists('ticket_operations', 'new_value')) {
                $missingCols[] = "ADD COLUMN new_value VARCHAR(255) NOT NULL DEFAULT ''";
            }
            if (!$this->columnExists('ticket_operations', 'created_at')) {
                $missingCols[] = 'ADD COLUMN created_at DATETIME NOT NULL';
            }
            if (!empty($missingCols)) {
                $this->pdo->exec('ALTER TABLE ticket_operations ' . implode(', ', $missingCols));
            }

            // 补齐索引
            if (!$this->indexExists('ticket_operations', 'idx_ticket_no')) {
                $this->pdo->exec('ALTER TABLE ticket_operations ADD INDEX idx_ticket_no (ticket_no)');
            }
            if (!$this->indexExists('ticket_operations', 'idx_operator_id')) {
                $this->pdo->exec('ALTER TABLE ticket_operations ADD INDEX idx_operator_id (operator_id)');
            }
            if (!$this->indexExists('ticket_operations', 'idx_created_at')) {
                $this->pdo->exec('ALTER TABLE ticket_operations ADD INDEX idx_created_at (created_at)');
            }
        } catch (Throwable $e) {
            Responder::error('TABLE_MIGRATION_FAILED', '表结构升级失败（ticket_operations）：' . $e->getMessage(), 500);
        }
    }

    // ==================== 迁移版本 5：content_hash 列 ====================

    /**
     * 确保 content_hash 列和对应索引存在（幂等）
     *
     * @return void
     */
    private function ensureContentHashColumn(): void
    {
        if ($this->columnExists('feedback_tickets', 'content_hash')) {
            // 列已存在，仅检查索引
            if (!$this->indexExists('feedback_tickets', 'idx_content_hash')) {
                $this->pdo->exec('ALTER TABLE feedback_tickets ADD INDEX idx_content_hash (content_hash)');
            }
            return;
        }

        try {
            $this->pdo->exec(
                'ALTER TABLE feedback_tickets ' .
                'ADD COLUMN content_hash CHAR(32) NULL, ' .
                'ADD INDEX idx_content_hash (content_hash)'
            );
        } catch (Throwable $e) {
            Responder::error('TABLE_MIGRATION_FAILED', '表结构升级失败（content_hash）：' . $e->getMessage(), 500);
        }
    }

    /**
     * 回填历史工单的 content_hash
     *
     * 使用与 TicketRepository::computeContentHash 完全一致的 PHP 侧标准化逻辑，
     * 确保新旧数据的哈希口径统一。回填失败不阻断迁移流程，新工单在 insertTicket 时会自动计算。
     *
     * @return void
     */
    private function backfillContentHash(): void
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT id, type, title, details FROM feedback_tickets WHERE content_hash IS NULL'
            );
            if (!$stmt) {
                return;
            }

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                return;
            }

            $update = $this->pdo->prepare(
                'UPDATE feedback_tickets SET content_hash = :hash WHERE id = :id'
            );
            foreach ($rows as $row) {
                $hash = TicketRepository::computeContentHash(
                    (int)$row['type'],
                    (string)$row['title'],
                    (string)$row['details']
                );
                $update->execute([':hash' => $hash, ':id' => $row['id']]);
            }
        } catch (Throwable $e) {
            // 回填失败记录日志但不中断迁移，新工单在 insert 时自动获得哈希
            error_log('[Migration v5] backfillContentHash failed: ' . $e->getMessage());
        }
    }

    // ==================== 通用 DDL 辅助方法 ====================

    /**
     * 检查指定列是否存在于给定表中
     *
     * @param string $table  表名
     * @param string $column 列名
     * @return bool
     */
    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1'
        );
        $stmt->execute([':table' => $table, ':column' => $column]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * 检查指定索引是否存在于给定表中
     *
     * @param string $table 表名
     * @param string $index 索引名
     * @return bool
     */
    private function indexExists(string $table, string $index): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index LIMIT 1'
        );
        $stmt->execute([':table' => $table, ':index' => $index]);
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
            'SELECT 1 FROM INFORMATION_SCHEMA.TABLES ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
        );
        $stmt->execute([':table' => $table]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }
}