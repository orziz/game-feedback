<?php

declare(strict_types=1);

namespace GameFeedback\Support;

final class AdminToken
{
    private const TTL = 12 * 3600;

    public static function create(int $userId, string $passwordHash, string $appSecret): string
    {
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

    public static function buildPasswordMarker(string $passwordHash, string $appSecret): string
    {
        return hash_hmac('sha256', $passwordHash, $appSecret);
    }
}
