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

-- ------------------------------------------------------------
-- 步骤 5：添加工单指派字段 assigned_to（如不存在）
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _gf_add_assigned_to$$
CREATE PROCEDURE _gf_add_assigned_to()
BEGIN
  DECLARE v_cnt INT DEFAULT 0;
  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'feedback_tickets'
    AND COLUMN_NAME  = 'assigned_to';
  IF v_cnt = 0 THEN
    ALTER TABLE feedback_tickets ADD COLUMN assigned_to BIGINT UNSIGNED NULL;
    ALTER TABLE feedback_tickets ADD INDEX idx_assigned_to (assigned_to);
  END IF;
END$$
CALL _gf_add_assigned_to()$$
DROP PROCEDURE IF EXISTS _gf_add_assigned_to$$

-- ------------------------------------------------------------
-- 步骤 6：创建工单操作记录表 ticket_operations（如不存在）
-- 记录工单的状态变更、指派操作及操作人
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _gf_create_operations$$
CREATE PROCEDURE _gf_create_operations()
BEGIN
  DECLARE v_exists INT DEFAULT 0;
  SELECT COUNT(*) INTO v_exists
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ticket_operations';
  
  IF v_exists = 0 THEN
    CREATE TABLE ticket_operations (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      ticket_no VARCHAR(32) NOT NULL,
      operator_id BIGINT UNSIGNED NOT NULL,
      operator_username VARCHAR(64) NOT NULL,
      operation_type VARCHAR(32) NOT NULL COMMENT 'status_change, assign',
      old_value VARCHAR(255) NULL,
      new_value VARCHAR(255) NOT NULL,
      created_at DATETIME NOT NULL,
      INDEX idx_ticket_no (ticket_no),
      INDEX idx_operator_id (operator_id),
      INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  END IF;
END$$
CALL _gf_create_operations()$$
DROP PROCEDURE IF EXISTS _gf_create_operations$$

-- ------------------------------------------------------------
-- 步骤 7：补齐 content_hash 字段与索引
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _gf_add_content_hash$$
CREATE PROCEDURE _gf_add_content_hash()
BEGIN
  DECLARE v_cnt INT DEFAULT 0;

  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'feedback_tickets'
    AND COLUMN_NAME  = 'content_hash';

  IF v_cnt = 0 THEN
    ALTER TABLE feedback_tickets
      ADD COLUMN content_hash CHAR(32) NULL;
  END IF;

  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'feedback_tickets'
    AND INDEX_NAME   = 'idx_content_hash';

  IF v_cnt = 0 THEN
    ALTER TABLE feedback_tickets
      ADD INDEX idx_content_hash (content_hash);
  END IF;
END$$
CALL _gf_add_content_hash()$$
DROP PROCEDURE IF EXISTS _gf_add_content_hash$$

-- ------------------------------------------------------------
-- 步骤 8：补齐附件延迟清理字段与索引
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _gf_add_attachment_cleanup$$
CREATE PROCEDURE _gf_add_attachment_cleanup()
BEGIN
  DECLARE v_cnt INT DEFAULT 0;

  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'feedback_tickets'
    AND COLUMN_NAME  = 'attachment_cleanup_due_at';

  IF v_cnt = 0 THEN
    ALTER TABLE feedback_tickets
      ADD COLUMN attachment_cleanup_due_at DATETIME NULL;
  END IF;

  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'feedback_tickets'
    AND COLUMN_NAME  = 'attachment_deleted_at';

  IF v_cnt = 0 THEN
    ALTER TABLE feedback_tickets
      ADD COLUMN attachment_deleted_at DATETIME NULL;
  END IF;

  SELECT COUNT(*) INTO v_cnt
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'feedback_tickets'
    AND INDEX_NAME   = 'idx_attachment_cleanup_due_at';

  IF v_cnt = 0 THEN
    ALTER TABLE feedback_tickets
      ADD INDEX idx_attachment_cleanup_due_at (attachment_cleanup_due_at, attachment_deleted_at);
  END IF;
END$$
CALL _gf_add_attachment_cleanup()$$
DROP PROCEDURE IF EXISTS _gf_add_attachment_cleanup$$

-- ------------------------------------------------------------
-- 步骤 9：创建权限、角色、角色权限、用户角色、功能开关相关表
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _gf_access_control_foundation$$
CREATE PROCEDURE _gf_access_control_foundation()
BEGIN
  CREATE TABLE IF NOT EXISTS permission_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(255) NULL,
    module_key VARCHAR(32) NOT NULL,
    scope_type VARCHAR(16) NOT NULL DEFAULT 'instance',
    is_builtin TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_module_key (module_key),
    INDEX idx_scope_type (scope_type)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

  CREATE TABLE IF NOT EXISTS role_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    role_type VARCHAR(16) NOT NULL DEFAULT 'builtin',
    scope_type VARCHAR(16) NOT NULL DEFAULT 'instance',
    is_builtin TINYINT(1) NOT NULL DEFAULT 1,
    is_editable TINYINT(1) NOT NULL DEFAULT 0,
    is_assignable TINYINT(1) NOT NULL DEFAULT 1,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_role_type (role_type),
    INDEX idx_scope_type (scope_type)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

  CREATE TABLE IF NOT EXISTS role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_role_permission (role_id, permission_id),
    INDEX idx_permission_id (permission_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

  CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uk_user_role (user_id, role_id),
    INDEX idx_role_id (role_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

  CREATE TABLE IF NOT EXISTS feature_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feature_key VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(255) NULL,
    category VARCHAR(32) NOT NULL,
    value_type VARCHAR(16) NOT NULL DEFAULT 'boolean',
    default_value VARCHAR(32) NOT NULL DEFAULT '0',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_category (category)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

  CREATE TABLE IF NOT EXISTS feature_instance_overrides (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feature_key VARCHAR(64) NOT NULL UNIQUE,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    config_json TEXT NULL,
    updated_at DATETIME NOT NULL,
    updated_by BIGINT UNSIGNED NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
END$$
CALL _gf_access_control_foundation()$$
DROP PROCEDURE IF EXISTS _gf_access_control_foundation$$

-- ------------------------------------------------------------
-- 步骤 10：补齐权限、角色、功能开关内置定义
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _gf_seed_access_control_defaults$$
CREATE PROCEDURE _gf_seed_access_control_defaults()
BEGIN
  INSERT INTO permission_definitions
    (permission_key, name, description, module_key, scope_type, is_builtin, created_at, updated_at)
  VALUES
    ('ticket.view', '查看工单', '查看工单列表、详情与操作记录。', 'ticket', 'instance', 1, NOW(), NOW()),
    ('ticket.process', '处理工单', '更新工单状态、备注与严重程度。', 'ticket', 'instance', 1, NOW(), NOW()),
    ('ticket.assign', '分配工单', '分配或批量分配工单负责人。', 'ticket', 'instance', 1, NOW(), NOW()),
    ('ticket.export', '导出工单', '导出工单数据。', 'ticket', 'instance', 1, NOW(), NOW()),
    ('ticket.attachment.download', '下载附件', '下载后台工单附件。', 'ticket', 'instance', 1, NOW(), NOW()),
    ('user.view', '查看用户', '查看后台用户列表。', 'user', 'instance', 1, NOW(), NOW()),
    ('user.create', '创建用户', '创建后台用户。', 'user', 'instance', 1, NOW(), NOW()),
    ('user.delete', '删除用户', '删除后台用户。', 'user', 'instance', 1, NOW(), NOW()),
    ('user.reset_password', '重置用户密码', '重置后台用户密码。', 'user', 'instance', 1, NOW(), NOW()),
    ('role.view', '查看角色', '查看角色与权限信息。', 'role', 'instance', 1, NOW(), NOW()),
    ('role.manage', '管理角色', '管理角色及权限分配。', 'role', 'instance', 1, NOW(), NOW()),
    ('system.settings', '系统设置', '修改系统级配置。', 'system', 'instance', 1, NOW(), NOW()),
    ('feature_flag.view', '查看功能开关', '查看功能开关状态。', 'feature_flag', 'instance', 1, NOW(), NOW()),
    ('feature_flag.manage', '管理功能开关', '修改实例级功能开关。', 'feature_flag', 'instance', 1, NOW(), NOW()),
    ('audit.view', '查看审计日志', '查看审计日志。', 'audit', 'instance', 1, NOW(), NOW()),
    ('report.view', '查看报表', '查看业务报表。', 'report', 'instance', 1, NOW(), NOW()),
    ('notification.manage', '管理通知', '管理通知配置。', 'notification', 'instance', 1, NOW(), NOW())
  ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    module_key = VALUES(module_key),
    scope_type = VALUES(scope_type),
    is_builtin = VALUES(is_builtin),
    updated_at = VALUES(updated_at);

  INSERT INTO role_definitions
    (role_key, name, role_type, scope_type, is_builtin, is_editable, is_assignable, description, created_at, updated_at)
  VALUES
    ('super_admin', '超级管理员', 'builtin', 'instance', 1, 0, 1, '安装时创建的系统最高管理角色。', NOW(), NOW()),
    ('admin', '管理员', 'builtin', 'instance', 1, 0, 1, '开源版兼容保留的基础管理员角色。', NOW(), NOW())
  ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    role_type = VALUES(role_type),
    scope_type = VALUES(scope_type),
    is_builtin = VALUES(is_builtin),
    is_editable = VALUES(is_editable),
    is_assignable = VALUES(is_assignable),
    description = VALUES(description),
    updated_at = VALUES(updated_at);

  INSERT INTO feature_definitions
    (feature_key, name, description, category, value_type, default_value, created_at, updated_at)
  VALUES
    ('feature.audit_log', '审计日志', '审计日志能力。', 'business', 'boolean', '0', NOW(), NOW()),
    ('feature.ticket_export', '工单导出', '后台工单导出能力。', 'business', 'boolean', '1', NOW(), NOW()),
    ('feature.notification', '通知', '通知能力。', 'business', 'boolean', '0', NOW(), NOW()),
    ('feature.report', '报表', '报表能力。', 'business', 'boolean', '0', NOW(), NOW()),
    ('feature.open_api', '开放 API', '开放 API 能力。', 'business', 'boolean', '0', NOW(), NOW()),
    ('feature.sla', 'SLA', 'SLA 能力。', 'business', 'boolean', '0', NOW(), NOW()),
    ('feature.attachment_cleanup_manage', '附件清理管理', '管理附件清理配置与手动执行。', 'system', 'boolean', '1', NOW(), NOW()),
    ('feature.role_permission_center', '角色权限中心', '角色与权限管理基础设施。', 'system', 'boolean', '1', NOW(), NOW())
  ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    category = VALUES(category),
    value_type = VALUES(value_type),
    default_value = VALUES(default_value),
    updated_at = VALUES(updated_at);
END$$
CALL _gf_seed_access_control_defaults()$$
DROP PROCEDURE IF EXISTS _gf_seed_access_control_defaults$$

-- ------------------------------------------------------------
-- 步骤 11：同步内置角色权限与旧 admin_users.role 绑定
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _gf_sync_builtin_access_control$$
CREATE PROCEDURE _gf_sync_builtin_access_control()
BEGIN
  DELETE rp
  FROM role_permissions rp
  INNER JOIN role_definitions rd ON rd.id = rp.role_id
  WHERE rd.role_key IN ('super_admin', 'admin');

  INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
  SELECT rd.id, pd.id, NOW()
  FROM role_definitions rd
  INNER JOIN permission_definitions pd
    ON (
      rd.role_key = 'super_admin'
      OR (rd.role_key = 'admin' AND pd.permission_key IN (
        'ticket.view',
        'ticket.process',
        'ticket.assign',
        'ticket.attachment.download'
      ))
    )
  WHERE rd.role_key IN ('super_admin', 'admin');

  INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
  SELECT rd.id, pd.id, NOW()
  FROM role_definitions rd
  INNER JOIN permission_definitions pd ON pd.permission_key = 'ticket.view'
  WHERE rd.role_key = 'admin';

  DELETE ur
  FROM user_roles ur
  INNER JOIN role_definitions rd ON rd.id = ur.role_id
  WHERE rd.role_key IN ('super_admin', 'admin');

  INSERT IGNORE INTO user_roles (user_id, role_id, created_at, created_by)
  SELECT au.id, rd.id, NOW(), NULL
  FROM admin_users au
  INNER JOIN role_definitions rd ON rd.role_key = au.role
  WHERE au.role IN ('super_admin', 'admin');
END$$
CALL _gf_sync_builtin_access_control()$$
DROP PROCEDURE IF EXISTS _gf_sync_builtin_access_control$$

-- ------------------------------------------------------------
-- 步骤 12：修复历史自定义角色缺失的页面 view 权限依赖
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS _gf_repair_custom_role_permissions$$
CREATE PROCEDURE _gf_repair_custom_role_permissions()
BEGIN
  INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
  SELECT rd.id, p_target.id, NOW()
  FROM role_definitions rd
  INNER JOIN role_permissions rp ON rp.role_id = rd.id
  INNER JOIN permission_definitions p_source ON p_source.id = rp.permission_id AND p_source.permission_key = 'ticket.process'
  INNER JOIN permission_definitions p_target ON p_target.permission_key = 'ticket.view'
  WHERE rd.role_type = 'custom';

  INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
  SELECT rd.id, p_target.id, NOW()
  FROM role_definitions rd
  INNER JOIN role_permissions rp ON rp.role_id = rd.id
  INNER JOIN permission_definitions p_source ON p_source.id = rp.permission_id AND p_source.permission_key = 'ticket.assign'
  INNER JOIN permission_definitions p_target ON p_target.permission_key = 'ticket.view'
  WHERE rd.role_type = 'custom';

  INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
  SELECT rd.id, p_target.id, NOW()
  FROM role_definitions rd
  INNER JOIN role_permissions rp ON rp.role_id = rd.id
  INNER JOIN permission_definitions p_source ON p_source.id = rp.permission_id AND p_source.permission_key = 'ticket.export'
  INNER JOIN permission_definitions p_target ON p_target.permission_key = 'ticket.view'
  WHERE rd.role_type = 'custom';

  INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
  SELECT rd.id, p_target.id, NOW()
  FROM role_definitions rd
  INNER JOIN role_permissions rp ON rp.role_id = rd.id
  INNER JOIN permission_definitions p_source ON p_source.id = rp.permission_id AND p_source.permission_key = 'ticket.attachment.download'
  INNER JOIN permission_definitions p_target ON p_target.permission_key = 'ticket.view'
  WHERE rd.role_type = 'custom';

  INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
  SELECT rd.id, p_target.id, NOW()
  FROM role_definitions rd
  INNER JOIN role_permissions rp ON rp.role_id = rd.id
  INNER JOIN permission_definitions p_source ON p_source.id = rp.permission_id AND p_source.permission_key = 'user.create'
  INNER JOIN permission_definitions p_target ON p_target.permission_key = 'user.view'
  WHERE rd.role_type = 'custom';

  INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
  SELECT rd.id, p_target.id, NOW()
  FROM role_definitions rd
  INNER JOIN role_permissions rp ON rp.role_id = rd.id
  INNER JOIN permission_definitions p_source ON p_source.id = rp.permission_id AND p_source.permission_key = 'user.delete'
  INNER JOIN permission_definitions p_target ON p_target.permission_key = 'user.view'
  WHERE rd.role_type = 'custom';

  INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
  SELECT rd.id, p_target.id, NOW()
  FROM role_definitions rd
  INNER JOIN role_permissions rp ON rp.role_id = rd.id
  INNER JOIN permission_definitions p_source ON p_source.id = rp.permission_id AND p_source.permission_key = 'user.reset_password'
  INNER JOIN permission_definitions p_target ON p_target.permission_key = 'user.view'
  WHERE rd.role_type = 'custom';

  INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
  SELECT rd.id, p_target.id, NOW()
  FROM role_definitions rd
  INNER JOIN role_permissions rp ON rp.role_id = rd.id
  INNER JOIN permission_definitions p_source ON p_source.id = rp.permission_id AND p_source.permission_key = 'role.manage'
  INNER JOIN permission_definitions p_target ON p_target.permission_key = 'role.view'
  WHERE rd.role_type = 'custom';

  INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
  SELECT rd.id, p_target.id, NOW()
  FROM role_definitions rd
  INNER JOIN role_permissions rp ON rp.role_id = rd.id
  INNER JOIN permission_definitions p_source ON p_source.id = rp.permission_id AND p_source.permission_key = 'feature_flag.manage'
  INNER JOIN permission_definitions p_target ON p_target.permission_key = 'feature_flag.view'
  WHERE rd.role_type = 'custom';
END$$
CALL _gf_repair_custom_role_permissions()$$
DROP PROCEDURE IF EXISTS _gf_repair_custom_role_permissions$$

DELIMITER ;

-- ------------------------------------------------------------
-- 完成
-- ------------------------------------------------------------
SELECT 'migrate.sql 执行完成' AS result;
