<?php

declare(strict_types=1);

use Reunite\Framework\Cloud;
use utils\Middleware;

$router = Cloud::router();

$router->group('/admin/orders', static function () use ($router) {
    $router->get('',         [Portal\Admin\Order\OrderController::class, 'index']);
    $router->get('/{id}',    [Portal\Admin\Order\OrderController::class, 'show']);
    $router->post('',        [Portal\Admin\Order\OrderController::class, 'store']);
    $router->put('/{id}',    [Portal\Admin\Order\OrderController::class, 'update']);
    $router->delete('/{id}', [Portal\Admin\Order\OrderController::class, 'destroy']);
}, Middleware::admin());
