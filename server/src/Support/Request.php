<?php

declare(strict_types=1);

namespace GameFeedback\Support;

final class Request
{
    /**
     * 读取当前请求使用的是 GET、POST 还是其他方法。
     */
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * 读取 URL 查询参数，不传时用默认值顶上。
     */
    public static function query(string $key, string $default = ''): string
    {
        return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
    }

    /**
     * 尽量从当前运行环境里拿到 Authorization 请求头。
     */
    public static function authorizationHeader(): string
    {
        $fromServer = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if ($fromServer !== '') {
            return $fromServer;
        }

        $fromRedirect = (string)($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($fromRedirect !== '') {
            return $fromRedirect;
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $key => $value) {
                    if (strtolower((string)$key) === 'authorization' && is_string($value)) {
                        return $value;
                    }
                }
            }
        }

        return '';
    }

    /**
     * 获取客户端 IP；拿不到或者格式不对时返回 unknown。
     */
    public static function clientIp(): string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '') {
            return 'unknown';
        }

        return preg_match('/^[A-Fa-f0-9:\.]{1,45}$/', $ip) === 1 ? $ip : 'unknown';
    }

    /**
     * 判断当前请求是不是文件上传表单。
     */
    public static function isMultipartFormData(): bool
    {
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        return strpos($contentType, 'multipart/form-data') === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public static function formBody(): array
    {
        if (!is_array($_POST)) {
            return [];
        }

        return $_POST;
    }

    /**
     * @return array{name:string,type:string,tmp_name:string,error:int,size:int}|null
     */
    public static function uploadedFile(string $key): ?array
    {
        if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
            return null;
        }

        return $_FILES[$key];
    }

    /**
     * @return array<string, mixed>
     */
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
