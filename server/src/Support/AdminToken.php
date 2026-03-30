<?php

declare(strict_types=1);

final class AdminToken
{
    public static function create(string $hash): string
    {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(8));
        $payload = $timestamp . '|' . $nonce;
        $signature = hash_hmac('sha256', $payload, $hash);

        return rtrim(strtr(base64_encode($payload . '|' . $signature), '+/', '-_'), '=');
    }

    public static function verify(string $token, string $hash): bool
    {
        if ($token === '' || $hash === '') {
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
        if (count($parts) !== 3) {
            return false;
        }

        $timestampRaw = $parts[0];
        $nonce = $parts[1];
        $signature = $parts[2];

        if (!ctype_digit($timestampRaw) || $nonce === '' || $signature === '') {
            return false;
        }

        $timestamp = (int)$timestampRaw;
        if ($timestamp < time() - 86400) {
            return false;
        }

        $payload = $timestampRaw . '|' . $nonce;
        $expected = hash_hmac('sha256', $payload, $hash);

        return hash_equals($expected, $signature);
    }
}
