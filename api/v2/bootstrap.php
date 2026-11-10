<?php

declare(strict_types=1);

/**
 * API v2 bootstrap: autoload, request/response, portal scope, config, DB, routes, and dispatch.
 */

// ----------------------------
// 1. Load the framework
// ----------------------------
$_composerAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($_composerAutoload)) {
    require $_composerAutoload;
}
unset($_composerAutoload);

use Reunite\Framework\Core\Loader;

// App-defined namespaces (Utils\*, Models\*, Portal\*) aren't PSR-4 registered by
// Composer — only the vendored Reunite\* packages are. Loader::autoload resolves them
// by mapping namespace separators to directories under api/v2.
Loader::autoload(true, [__DIR__]);

require __DIR__ . '/functions/global.php';
require __DIR__ . '/functions/constants.php';
require __DIR__ . '/functions/utility.php';
require __DIR__ . '/utils/Database.php';
require __DIR__ . '/utils/Auth.php';
require __DIR__ . '/utils/AppConfig.php';

use Reunite\Framework\Net\Request;
use Reunite\Framework\Net\Response;
use Reunite\Framework\Cloud;
use Reunite\Logger;
use Utils\Database;
use Utils\AppConfig;

// ----------------------------
// 2. Initialize request/response
// ----------------------------
$request  = new Request();
$response = new Response();

Cloud::set('request', $request);
Cloud::set('response', $response);

// ----------------------------
// 2b. Request correlation ID
// ----------------------------
$incomingRid = Request::getHeader('X-Request-Id', '');
if ($incomingRid === '') {
    $incomingRid = Request::getHeader('X-Request-ID', '');
}
$requestId = $incomingRid !== ''
    ? $incomingRid
    : bin2hex(random_bytes(8));
Cloud::set('request_id', $requestId);
if (!\headers_sent()) {
    header('X-Request-Id: ' . $requestId);
}

// ----------------------------
// 3. Initial DB scope: default connection for token verification and cron/internal (matches v1)
// ----------------------------
Cloud::set('api_scope', 'default');
Cloud::set('url_scope', '');
Cloud::set('portal_folder', '');
Cloud::set('lang_path', __DIR__ . '/language');

// ----------------------------
// 4. Load portal config
// ----------------------------
$config = (function () {
    $skipDefinedVariable = true; // Do not remove this line
    return require __DIR__ . '/../../config.php';
})();
Cloud::loadConfig($config);

// ----------------------------
// 5. Initialize portal DB (default)
// ----------------------------
Database::setDbInstance();
Database::setAuditDbInstance();

// ----------------------------
// 6. Set all database configs to cache (also defines SITE_URL from cached root_path)
// ----------------------------
AppConfig::load(false, 300);

// ----------------------------
// 6b. Wire the is_unique validation rule to the active portal-scoped DB
// ----------------------------
Database::wireUniqueValidator();

// ----------------------------
// 7. Wire structured logging (reunite/logger) + global error/exception/shutdown handlers
// ----------------------------
Logger::init([
    'service' => 'ds3-api-v2',
    'env'               => ENVIRONMENT,
    'log_dir'           => LOG_DIR,
    // LOG_LEVEL is an RFC 5424 severity (0=emergency .. 7=debug), the same scale
    // Reunite\Logger uses natively — passed through as-is, no PSR-3 string mapping needed.
    'log_level'         => (int) LOG_LEVEL,
    'redact_keys'       => ['password', 'ssn', 'token', 'mrn', 'patient_id', 'card_number'],
    'debug'             => ENVIRONMENT !== 'prod',
    'response_renderer' => static function (array $info): void {
        $body = [
            'status'  => 'Error',
            'code'    => 500,
            'message' => 'Internal Server Error',
        ];
        if (Cloud::has('request_id')) {
            $body['request_id'] = Cloud::get('request_id');
        }
        if (!empty($info['debug'])) {
            $body['error'] = [
                'error_path'    => $info['error_path'] ?? '',
                'error_message' => $info['error_message'] ?? '',
            ];
        }
        response($body, 500);
    },
]);

// ----------------------------
// 8. Load portal routes (manifest-first; filesystem discovery as fallback)
// ----------------------------
$manifestPath = __DIR__ . '/config/routes.php';

if (\file_exists($manifestPath)) {
    $routeJobs = require $manifestPath;
} else {
    // Fallback: recursive filesystem discovery (used when manifest is absent)
    $portalRoot = __DIR__ . '/portal';

    /** @return list<string> */
    $discoverRouters = static function (string $dir): array {
        if (!\is_dir($dir)) {
            return [];
        }
        $routerFiles = [];
        $dirIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($dirIterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'Router.php') {
                $routerFiles[] = $file->getPathname();
            }
        }
        sort($routerFiles);
        return $routerFiles;
    };

    $nmsPrefixDirs = ['cron', 'internal'];
    $routeJobs     = [];
    foreach (\scandir($portalRoot) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $abs = $portalRoot . '/' . $entry;
        if (!\is_dir($abs)) {
            continue;
        }
        foreach ($discoverRouters($abs) as $routerPath) {
            $prefix      = \in_array($entry, $nmsPrefixDirs, true) ? '/' . $entry : '';
            $routeJobs[] = ['prefix' => $prefix, 'path' => $routerPath];
        }
    }
    usort($routeJobs, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
}

foreach ($routeJobs as $job) {
    if ($job['prefix'] !== '') {
        Cloud::group($job['prefix'], static function () use ($job) {
            require_once $job['path'];
        });
    } else {
        require_once $job['path'];
    }
}

// ----------------------------
// 9. Do not let Engine replace Logger-based JSON exception handling
// ----------------------------
Cloud::app()->set('system.handle_errors', false);

// ----------------------------
// 10. Cache raw request body before framework parses php://input (needed by WebhookAuth)
// ----------------------------
Cloud::set('raw_request_body', file_get_contents('php://input') ?: '');

// ----------------------------
// 11. Run the API
// ----------------------------
Cloud::run();
