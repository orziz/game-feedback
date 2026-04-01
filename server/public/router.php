<?php

/**
 * PHP 内置服务器路由脚本（仅限开发环境）
 *
 * 静态文件直接返回，其他请求转发到 index.php。
 * 生产环境请使用 Nginx 等反向代理，不要依赖本脚本。
 * 用法：php -S localhost:8080 router.php
 */

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    // 安全校验：规范化路径并确认文件在当前目录范围内，防止路径遍历
    $realFile = realpath($file);
    $realBase = realpath(__DIR__);
    if (
        $realFile !== false
        && $realBase !== false
        && strncmp($realFile, $realBase . DIRECTORY_SEPARATOR, strlen($realBase) + 1) === 0
    ) {
        return false;
    }
}

require __DIR__ . '/index.php';