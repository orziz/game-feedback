<?php

declare(strict_types=1);

$appConfig = require __DIR__ . '/../config/app.php';

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = is_array($appConfig['cors_allowed_origins'] ?? null) ? $appConfig['cors_allowed_origins'] : [];
$requestScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$requestHost = (string)($_SERVER['HTTP_HOST'] ?? '');
$currentOrigin = $requestHost !== '' ? $requestScheme . '://' . $requestHost : '';

$isAllowedCorsOrigin = false;
if ($origin !== '') {
    if ($origin === $currentOrigin || in_array($origin, $allowedOrigins, true)) {
        $isAllowedCorsOrigin = true;
    } else {
        $parts = parse_url($origin);
        $devHosts = ['localhost', '127.0.0.1'];
        $isAllowedCorsOrigin = is_array($parts)
            && in_array((string)($parts['host'] ?? ''), $devHosts, true)
            && in_array((string)($parts['scheme'] ?? ''), ['http', 'https'], true);
    }
}

if ($isAllowedCorsOrigin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

if (!isset($_GET['s']) || !is_string($_GET['s']) || trim($_GET['s']) === '') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_string($path)) {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static function ($segment) {
            return $segment !== '';
        }));

        if (count($segments) >= 3) {
            $_GET['s'] = implode('/', array_slice($segments, -3));
        }
    }
}

$route = (string)($_GET['s'] ?? '');
$isAttachmentDownload = $route === 'admin/Ticket/attachmentDownload';

if (!$isAttachmentDownload) {
    header('Content-Type: application/json; charset=utf-8');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    if ($origin !== '' && !$isAllowedCorsOrigin) {
        http_response_code(403);
        exit;
    }

    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../src/autoload.php';

$databaseConfigPath = __DIR__ . '/../config/database.php';

$app = new GameFeedback\App($appConfig, $databaseConfigPath);
$app->run();
