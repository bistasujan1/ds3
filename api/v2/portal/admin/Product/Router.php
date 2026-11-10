<?php

declare(strict_types=1);

use Reunite\Framework\Cloud;
use utils\Middleware;

$router = Cloud::router();

$router->group('/admin/products', static function () use ($router) {
    $router->get('/dropdown', [Portal\Admin\Product\ProductController::class, 'dropdown']);
}, Middleware::admin());
