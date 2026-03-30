<?php

declare(strict_types=1);

final class Request
{
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function query(string $key, string $default = ''): string
    {
        return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
    }

    public static function authorizationHeader(): string
    {
        return (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    }

    public static function isMultipartFormData(): bool
    {
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        return strpos($contentType, 'multipart/form-data') === 0;
    }

    public static function formBody(): array
    {
        if (!is_array($_POST)) {
            return [];
        }

        return $_POST;
    }

    public static function uploadedFile(string $key): ?array
    {
        if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
            return null;
        }

        return $_FILES[$key];
    }

    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            Responder::error('INVALID_JSON', '请求体 JSON 格式错误。', 400);
        }

        return $decoded;
    }
}
