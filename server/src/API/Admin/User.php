<?php

declare(strict_types=1);

namespace GameFeedback\API\Admin;

use GameFeedback\Enums\UserRole;
use GameFeedback\Support\Request;
use GameFeedback\Support\Responder;

final class User extends AdminSubModule
{
    /**
     * @return array<string, array{
     *   methods: array<int, string>,
     *   allow_before_install?: bool,
     *   auth?: string
     * }>
     */
    protected function actionMeta(): array
    {
        return [
            'list' => [
                self::META_METHODS => ['GET'],
                self::META_AUTH => self::AUTH_SUPER_ADMIN,
            ],
            'create' => [
                self::META_METHODS => ['POST'],
                self::META_AUTH => self::AUTH_SUPER_ADMIN,
            ],
            'delete' => [
                self::META_METHODS => ['POST'],
                self::META_AUTH => self::AUTH_SUPER_ADMIN,
            ],
            'resetPassword' => [
                self::META_METHODS => ['POST'],
                self::META_AUTH => self::AUTH_SUPER_ADMIN,
            ],
        ];
    }

    /**
     * 获取后台管理员用户列表。
     */
    protected function list(): void
    {
        Responder::send([
            'ok' => true,
            'users' => $this->createUserRepository()->listUsers(),
        ]);
    }

    /**
     * 创建新的后台管理员账号。
     */
    protected function create(): void
    {
        $payload = Request::jsonBody();
        $username = $this->sanitizer->sanitizeSingleLine((string)($payload['username'] ?? ''), 64);
        $password = $this->sanitizer->sanitizeSingleLine((string)($payload['password'] ?? ''), 128);
        $role = $this->sanitizer->sanitizeSingleLine((string)($payload['role'] ?? UserRole::Admin), 16);

        if ($username === '' || $password === '') {
            Responder::error('MISSING_REQUIRED_FIELDS', '用户名和密码不能为空。', 422);
        }

        if (!preg_match('/^[a-zA-Z0-9_]{2,64}$/', $username)) {
            Responder::error('INVALID_USERNAME', '用户名只能包含字母、数字和下划线，长度 2-64 位。', 422);
        }

        if ($this->sanitizer->stringLength($password) < 8) {
            Responder::error('WEAK_PASSWORD', '管理员密码长度不能少于 8 位。', 422);
        }

        if (!in_array($role, UserRole::ALL, true)) {
            Responder::error('INVALID_ROLE', '角色不合法。', 422);
        }

        $userRepo = $this->createUserRepository();
        if ($userRepo->findByUsername($username)) {
            Responder::error('USERNAME_EXISTS', '用户名已存在。', 409);
        }

        $userRepo->insertUser($username, password_hash($password, PASSWORD_DEFAULT), $role);

        Responder::send([
            'ok' => true,
            'message' => '用户创建成功。',
        ]);
    }

    /**
     * 删除指定的后台管理员账号。
     */
    protected function delete(): void
    {
        $payload = Request::jsonBody();
        $id = (int)($payload['id'] ?? 0);

        if ($id <= 0) {
            Responder::error('MISSING_REQUIRED_FIELDS', '请提供用户 ID。', 422);
        }

        $deleted = $this->createUserRepository()->deleteUser($id);
        if (!$deleted) {
            Responder::error('DELETE_FAILED', '删除失败，用户不存在或为超级管理员。', 400);
        }

        Responder::send([
            'ok' => true,
            'message' => '用户已删除。',
        ]);
    }

    /**
     * 重置指定管理员的登录密码。
     */
    protected function resetPassword(): void
    {
        $payload = Request::jsonBody();
        $id = (int)($payload['id'] ?? 0);
        $newPassword = $this->sanitizer->sanitizeSingleLine((string)($payload['password'] ?? ''), 128);

        if ($id <= 0 || $newPassword === '') {
            Responder::error('MISSING_REQUIRED_FIELDS', '请提供用户 ID 和新密码。', 422);
        }

        if ($this->sanitizer->stringLength($newPassword) < 8) {
            Responder::error('WEAK_PASSWORD', '管理员密码长度不能少于 8 位。', 422);
        }

        $userRepo = $this->createUserRepository();
        if (!$userRepo->findById($id)) {
            Responder::error('USER_NOT_FOUND', '用户不存在。', 404);
        }

        $userRepo->updatePassword($id, password_hash($newPassword, PASSWORD_DEFAULT));

        Responder::send([
            'ok' => true,
            'message' => '密码已重置。',
        ]);
    }
}
