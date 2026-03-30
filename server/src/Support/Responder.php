<?php

declare(strict_types=1);

namespace GameFeedback\Support;

/**
 * JSON 响应工具类
 *
 * 统一输出 JSON 格式响应并终止进程
 */
final class Responder
{
    /**
     * 发送 JSON 响应并终止进程
     *
     * @param array<string, mixed> $data   响应数据
     * @param int                  $status HTTP 状态码
     * @return never
     */
    public static function send(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 发送错误响应并终止进程
     *
     * @param string $code    业务错误码
     * @param string $message 可读错误描述
     * @param int    $status  HTTP 状态码
     * @return never
     */
    public static function error(string $code, string $message, int $status): void
    {
        self::send([
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
