-- =============================================================
-- game-feedback 数据库升级脚本
-- 兼容 MySQL 5.6+（不使用 IF NOT EXISTS 子句）
-- 可安全重复执行，用存储过程做条件判断后自动清理
-- =============================================================

DELIMITER $$

-- ------------------------------------------------------------
-- 步骤 1：创建 admin_users 表（如不存在）
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _gf_step1$$
CREATE PROCEDURE _gf_step1()
BEGIN
  CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(16) NOT NULL DEFAULT 'admin',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_role (role)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
END$$
CALL _gf_step1()$$
DROP PROCEDURE IF EXISTS _gf_step1$$

-- ------------------------------------------------------------
-- 步骤 2：将 feedback_tickets 的 ENUM 列迁移为 TINYINT
--   映射关系：
--     type:     BUG→0  优化→1  建议→2  其他→3
--     severity: 低→0   中→1    高→2    致命→3
--     status:   待处理→0  处理中→1  已解决→2  已关闭→3
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _gf_step2$$
CREATE PROCEDURE _gf_step2()
BEGIN
  DECLARE v_type_type VARCHAR(64) DEFAULT '';

  SELECT DATA_TYPE INTO v_type_type
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'feedback_tickets'
    AND COLUMN_NAME  = 'type'
  LIMIT 1;

  IF v_type_type = 'enum' THEN
    -- 添加临时整数列
    ALTER TABLE feedback_tickets
      ADD COLUMN type_num     TINYINT UNSIGNED NOT NULL DEFAULT 3,
      ADD COLUMN severity_num TINYINT UNSIGNED NULL,
      ADD COLUMN status_num   TINYINT UNSIGNED NOT NULL DEFAULT 0;

    -- 回填数据
    UPDATE feedback_tickets SET type_num = CASE type
      WHEN 'BUG' THEN 0 WHEN '优化' THEN 1 WHEN '建议' THEN 2 ELSE 3
    END;

    UPDATE feedback_tickets SET severity_num = CASE severity
      WHEN '低' THEN 0 WHEN '中' THEN 1 WHEN '高' THEN 2 WHEN '致命' THEN 3 ELSE NULL
    END;

    UPDATE feedback_tickets SET status_num = CASE status
      WHEN '待处理' THEN 0 WHEN '处理中' THEN 1 WHEN '已解决' THEN 2 WHEN '已关闭' THEN 3 ELSE 0
    END;

    -- 删除旧 ENUM 列，临时列重命名
    ALTER TABLE feedback_tickets
      DROP COLUMN type,
      DROP COLUMN severity,
      DROP COLUMN status;

    ALTER TABLE feedback_tickets
      CHANGE COLUMN type_num     type     TINYINT UNSIGNED NOT NULL,
      CHANGE COLUMN severity_num severity TINYINT UNSIGNED NULL,
      CHANGE COLUMN status_num   status   TINYINT UNSIGNED NOT NULL DEFAULT 0;
  END IF;
END$$
CALL _gf_step2()$$
DROP PROCEDURE IF EXISTS _gf_step2$$

-- ------------------------------------------------------------
-- 步骤 3：补齐附件相关字段（逐列检查）
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _gf_add_col$$
CREATE PROCEDURE _gf_add_col(IN p_col VARCHAR(64), IN p_def TEXT)
BEGIN
  DECLARE v_cnt INT DEFAULT 0;
  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'feedback_tickets'
    AND COLUMN_NAME  = p_col;
  IF v_cnt = 0 THEN
    SET @sql = CONCAT('ALTER TABLE feedback_tickets ADD COLUMN ', p_col, ' ', p_def);
    PREPARE _stmt FROM @sql;
    EXECUTE _stmt;
    DEALLOCATE PREPARE _stmt;
  END IF;
END$$

CALL _gf_add_col('attachment_name',    'VARCHAR(255) NULL')$$
CALL _gf_add_col('attachment_storage', 'VARCHAR(16)  NULL')$$
CALL _gf_add_col('attachment_key',     'VARCHAR(255) NULL')$$
CALL _gf_add_col('attachment_mime',    'VARCHAR(80)  NULL')$$
CALL _gf_add_col('attachment_size',    'INT UNSIGNED NULL')$$
DROP PROCEDURE IF EXISTS _gf_add_col$$

-- ------------------------------------------------------------
-- 步骤 4：补齐索引（逐个检查）
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _gf_add_idx$$
CREATE PROCEDURE _gf_add_idx(IN p_idx VARCHAR(64), IN p_cols TEXT)
BEGIN
  DECLARE v_cnt INT DEFAULT 0;
  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'feedback_tickets'
    AND INDEX_NAME   = p_idx;
  IF v_cnt = 0 THEN
    SET @sql = CONCAT('ALTER TABLE feedback_tickets ADD INDEX ', p_idx, ' (', p_cols, ')');
    PREPARE _stmt FROM @sql;
    EXECUTE _stmt;
    DEALLOCATE PREPARE _stmt;
  END IF;
END$$

CALL _gf_add_idx('idx_type_title',     'type, title')$$
CALL _gf_add_idx('idx_status_created', 'status, created_at')$$
DROP PROCEDURE IF EXISTS _gf_add_idx$$

DELIMITER ;

-- ------------------------------------------------------------
-- 完成
-- ------------------------------------------------------------
SELECT 'migrate.sql 执行完成' AS result;
