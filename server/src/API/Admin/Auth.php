<?php

declare(strict_types=1);

namespace GameFeedback\API\Admin;

use GameFeedback\Support\AdminToken;
use GameFeedback\Support\RateLimiter;
use GameFeedback\Support\Request;
use GameFeedback\Support\Responder;

final class Auth extends AdminSubModule
{
    /**
     * @return array<string, array{methods: array<int, string>, allow_before_install?: bool}>
     */
    protected function actionMeta(): array
    {
        return [
            'login' => [
                'methods' => ['POST'],
            ],
            'currentUser' => [
                'methods' => ['GET'],
            ],
        ];
    }

    protected function login(): void
    {
        $payload = Request::jsonBody();
        $username = $this->sanitizer->sanitizeSingleLine((string)($payload['username'] ?? ''), 64);
        $password = $this->sanitizer->sanitizeSingleLine((string)($payload['password'] ?? ''), 128);
        $rateLimiter = new RateLimiter();
        $rateLimitKey = 'admin-login|' . strtolower($username) . '|' . Request::clientIp();

        $rateLimiter->ensureAllowed($rateLimitKey);

        if ($username === '' || $password === '') {
            $rateLimiter->hit($rateLimitKey);
            Responder::error('INVALID_CREDENTIALS', '用户名或密码错误。', 401);
        }

        $user = $this->createUserRepository()->findByUsername($username);
        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            $rateLimiter->hit($rateLimitKey);
            Responder::error('INVALID_CREDENTIALS', '用户名或密码错误。', 401);
        }

        $rateLimiter->clear($rateLimitKey);

        Responder::send([
            'ok' => true,
            'token' => AdminToken::create((int)$user['id'], (string)$user['password_hash'], $this->getAppSecret()),
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ],
        ]);
    }

    protected function currentUser(): void
    {
        $user = $this->ensureAdmin();

        Responder::send([
            'ok' => true,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ],
        ]);
    }
}
