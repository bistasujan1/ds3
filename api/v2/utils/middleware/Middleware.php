<?php

declare(strict_types=1);

namespace Utils\Middleware;

/**
 * Abstract base for all v2 middleware.
 *
 * The routing engine calls before() in registration order, then after() in
 * reverse order (LIFO). Return false from either method to halt the request;
 * the framework will respond with 403. Never call exit() inside middleware —
 * return false instead so the class stays testable.
 */
abstract class Middleware
{
    /**
     * Runs before the route handler.
     *
     * @param array<string, mixed> $params URL route parameters
     * @return bool False halts execution and triggers a 403 response
     */
    abstract public function before(array $params): bool;

    /**
     * Runs after the route handler, in reverse middleware order.
     *
     * Override to add cleanup, response decoration, or post-handler logging.
     *
     * @param array<string, mixed> $params URL route parameters
     * @return bool False halts further after-middleware execution with 403
     */
    public function after(array $params): bool
    {
        return true;
    }
}
