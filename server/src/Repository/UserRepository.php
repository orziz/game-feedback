<?php

declare(strict_types=1);

namespace GameFeedback\Repository;

use GameFeedback\Enums\UserRole;
use PDO;


/**
 * 管理员用户数据库仓储类
 *
 * 封装 admin_users 表的 CRUD 操作
 */
final class UserRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 创建 admin_users 表（如不存在）
     */
    public function createTableIfNotExists(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(16) NOT NULL DEFAULT 'admin',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
        $this->pdo->exec($sql);
    }

    /**
     * 根据用户名查找用户
     *
     * @return array<string, mixed>|false
     */
    public function findByUsername(string $username)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM admin_users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 根据 ID 查找用户
     *
     * @return array<string, mixed>|false
     */
    public function findById(int $id)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 插入新用户
     */
    public function insertUser(string $username, string $passwordHash, string $role): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_users (username, password_hash, role, created_at, updated_at) VALUES (:username, :password_hash, :role, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => $passwordHash,
            ':role' => $role,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    /**
     * 获取用户列表
     *
     * @return array<int, array<string, mixed>>
     */
    public function listUsers(): array
    {
        $stmt = $this->pdo->query('SELECT id, username, role, created_at, updated_at FROM admin_users ORDER BY id ASC');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * 删除用户（不允许删除超级管理员）
     *
     * @return bool 是否成功删除
     */
    public function deleteUser(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM admin_users WHERE id = :id AND role != :super_role');
        $stmt->execute([
            ':id' => $id,
            ':super_role' => UserRole::SuperAdmin,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * 更新用户密码
     */
    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare('UPDATE admin_users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':password_hash' => $passwordHash,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    /**
     * 检查是否存在至少一个超级管理员
     */
    public function hasSuperAdmin(): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM admin_users WHERE role = :role LIMIT 1');
        $stmt->execute([':role' => UserRole::SuperAdmin]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * 统计用户数
     */
    public function countUsers(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM admin_users');
        return $stmt ? (int)$stmt->fetchColumn() : 0;
    }
}
