<?php

declare(strict_types=1);

namespace Utils\Middleware;

use Utils\CronBearerAuth;

/**
 * v1-compatible cron bearer auth middleware.
 */
class CronBearerMiddleware extends Middleware
{
    /**
     * @param array<string, mixed> $params
     */
    public function before(array $params): bool
    {
        return CronBearerAuth::verify();
    }
}
