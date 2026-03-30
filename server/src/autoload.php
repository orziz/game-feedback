<?php

declare(strict_types=1);

spl_autoload_register(static function ($className) {
    $prefix = 'GameFeedback\\';
    $prefixLength = strlen($prefix);

    if (strncmp($className, $prefix, $prefixLength) !== 0) {
        return;
    }

    $relativeClass = substr($className, $prefixLength);
    if ($relativeClass === false || $relativeClass === '') {
        return;
    }

    $filePath = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($filePath)) {
        require_once $filePath;
    }
});
