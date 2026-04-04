<?php

declare(strict_types=1);

namespace GameFeedback\Support;

/**
 * 管理员登录令牌工具：负责创建与校验后台访问令牌。
 */
final class AdminToken
{
    private const TTL = 12 * 3600;

    /**
     * 生成管理员登录后发给前端的访问令牌。
     */
    public static function create(int $userId, string $passwordHash, string $appSecret): string
    {
        // 令牌载荷包含用户ID、签发时间、随机串、密码标记，再用 appSecret 进行 HMAC 签名
        $timestamp = time();
        $nonce = bin2hex(random_bytes(8));
        $passwordMarker = self::buildPasswordMarker($passwordHash, $appSecret);
        $payload = $userId . '|' . $timestamp . '|' . $nonce . '|' . $passwordMarker;
        $signature = hash_hmac('sha256', $payload, $appSecret);

        return rtrim(strtr(base64_encode($payload . '|' . $signature), '+/', '-_'), '=');
    }

    /**
     * @return array{userId:int, passwordMarker:string}|false
     */
    public static function verify(string $token, string $appSecret)
    {
        // 先做格式与签名校验，再做过期校验，最后返回可用于鉴权的关键信息
        if ($token === '' || $appSecret === '') {
            return false;
        }

        $normalized = strtr($token, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if ($decoded === false) {
            return false;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 5) {
            return false;
        }

        $userIdRaw = $parts[0];
        $timestampRaw = $parts[1];
        $nonce = $parts[2];
        $passwordMarker = $parts[3];
        $signature = $parts[4];

        if (!ctype_digit($userIdRaw) || !ctype_digit($timestampRaw) || $nonce === '' || $passwordMarker === '' || $signature === '') {
            return false;
        }

        $timestamp = (int)$timestampRaw;
        if ($timestamp < time() - self::TTL) {
            return false;
        }

        $payload = $userIdRaw . '|' . $timestampRaw . '|' . $nonce . '|' . $passwordMarker;
        $expected = hash_hmac('sha256', $payload, $appSecret);
        if (!hash_equals($expected, $signature)) {
            return false;
        }

        return [
            'userId' => (int)$userIdRaw,
            'passwordMarker' => $passwordMarker,
        ];
    }

    /**
     * 根据密码哈希生成令牌里的密码标记。
     *
     * 这样一来，只要管理员改了密码，旧令牌就会自动失效。
     */
    public static function buildPasswordMarker(string $passwordHash, string $appSecret): string
    {
        // 密码标记用于密码变更后使旧令牌失效
        return hash_hmac('sha256', $passwordHash, $appSecret);
    }
}
