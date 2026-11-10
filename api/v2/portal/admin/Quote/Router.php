<?php

declare(strict_types=1);

use Reunite\Framework\Cloud;
use utils\Middleware;

$router = Cloud::router();

$router->group('/admin/quotes', static function () use ($router) {
    $router->get('',         [Portal\Admin\Quote\QuoteController::class, 'index']);
    $router->get('/{id}',    [Portal\Admin\Quote\QuoteController::class, 'show']);
    $router->post('',        [Portal\Admin\Quote\QuoteController::class, 'store']);
    $router->put('/{id}',    [Portal\Admin\Quote\QuoteController::class, 'update']);
    $router->delete('/{id}', [Portal\Admin\Quote\QuoteController::class, 'destroy']);
}, Middleware::admin());
