<?php

declare(strict_types=1);

/**
 * ReUnite API v2 front controller.
 *
 * Sends JSON API CORS headers (from {@see config/app.php}), short-circuits HTTP OPTIONS preflight, and boots the application.
 */

$appConfig = is_file(__DIR__ . '/config/app.php')
    ? require __DIR__ . '/config/app.php'
    : [
        'cors_origins' => ['*'],
        'cors_allow_credentials' => false,
        'cors_expose_headers' => [],
        'cors_allow_headers' => 'Content-Type, Authorization, X-API-Key, X-Request-Id',
    ];

header('Content-Type: application/json; charset=utf-8');

$allowHeaders = $appConfig['cors_allow_headers'] ?? 'Content-Type, Authorization, X-API-Key, X-Request-Id';
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: ' . $allowHeaders);

$origins = $appConfig['cors_origins'] ?? ['*'];
$allowCreds = !empty($appConfig['cors_allow_credentials']);
$clientOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origins === '*' || $origins === ['*']) {
    header('Access-Control-Allow-Origin: *');
} elseif (\is_array($origins)) {
    if ($clientOrigin !== '' && \in_array($clientOrigin, $origins, true)) {
        header('Access-Control-Allow-Origin: ' . $clientOrigin);
        if ($allowCreds) {
            header('Access-Control-Allow-Credentials: true');
        }
    } else {
        header('Access-Control-Allow-Origin: ' . (string) ($origins[0] ?? '*'));
    }
} else {
    header('Access-Control-Allow-Origin: *');
}

$expose = $appConfig['cors_expose_headers'] ?? [];
if ($expose !== [] && \is_array($expose)) {
    header('Access-Control-Expose-Headers: ' . implode(', ', $expose));
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require __DIR__ . '/bootstrap.php';
