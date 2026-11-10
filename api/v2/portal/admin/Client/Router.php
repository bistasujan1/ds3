<?php

declare(strict_types=1);

use Reunite\Framework\Cloud;
use utils\Middleware;

$router = Cloud::router();

$router->group('/admin/clients', static function () use ($router) {
    $router->get('',              [Portal\Admin\Client\ClientController::class, 'index']);
    $router->get('/dropdown',     [Portal\Admin\Client\ClientController::class, 'dropdown']);
    $router->get('/{id}',         [Portal\Admin\Client\ClientController::class, 'show']);
    $router->post('',             [Portal\Admin\Client\ClientController::class, 'store']);
    $router->put('/{id}',         [Portal\Admin\Client\ClientController::class, 'update']);
    $router->delete('/{id}',      [Portal\Admin\Client\ClientController::class, 'destroy']);
}, Middleware::admin());
