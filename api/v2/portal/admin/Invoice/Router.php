<?php

declare(strict_types=1);

use Reunite\Framework\Cloud;
use utils\Middleware;

$router = Cloud::router();

$router->group('/admin/invoices', static function () use ($router) {
    $router->get('',         [Portal\Admin\Invoice\InvoiceController::class, 'index']);
    $router->get('/{id}',    [Portal\Admin\Invoice\InvoiceController::class, 'show']);
    $router->put('/{id}',    [Portal\Admin\Invoice\InvoiceController::class, 'update']);
    $router->delete('/{id}', [Portal\Admin\Invoice\InvoiceController::class, 'destroy']);
}, Middleware::admin());
