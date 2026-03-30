<?php

/**
 * PHP 内置服务器路由脚本
 *
 * 静态文件直接返回，其他请求转发到 index.php
 * 用法：php -S localhost:8080 router.php
 */

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
