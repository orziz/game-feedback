<?php

declare(strict_types=1);

final class Responder
{
    public static function send(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $code, string $message, int $status): void
    {
        self::send([
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
