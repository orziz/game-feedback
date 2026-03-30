<?php

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$action = (string)($_GET['action'] ?? '');
if ($action !== 'admin_attachment_download') {
    header('Content-Type: application/json; charset=utf-8');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../src/App.php';

$appConfig = require __DIR__ . '/../config/app.php';
$databaseConfigPath = __DIR__ . '/../config/database.php';

$app = new App($appConfig, $databaseConfigPath);
$app->run();
