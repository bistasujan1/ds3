<?php

declare(strict_types=1);

use Reunite\Framework\Cloud;
use Portal\Admin\App\AppController;

$router = Cloud::router();

// Health check (unauthenticated)
$router->group('/admin/health', static function () use ($router) {
    $router->get('', [AppController::class, 'health']);
});

// Admin login (rate-limited, portal-scoped)
$router->group('/admin/user', static function () use ($router) {
    $router->post('/login', [AppController::class, 'login']);
}, [login_portal(), rate_limit(10, 60)]);
