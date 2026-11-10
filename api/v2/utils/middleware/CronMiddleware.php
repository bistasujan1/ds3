<?php

declare(strict_types=1);

namespace Utils\Middleware;

use Utils\CronAuth;

/**
 * Guards scheduled-job routes with either of two auth styles:
 *
 * - New jobs (no `$bearerScope`): IP allowlist (CRON_ALLOWED_IPS) + shared-secret
 *   header (X-Cron-Secret, matched against CRON_SECRET). Binds api_scope=default.
 * - Jobs migrated from v1 (`$bearerScope` given): v1-compatible
 *   `Authorization: Bearer <CRON_AUTH_TOKEN>`, binding whichever DB scope the
 *   migrated job needs (`default` unless its v1 source used `WebServiceFactory('admin')`).
 */
class CronMiddleware extends Middleware
{
    private ?string $bearerScope;

    /**
     * @param string|null $bearerScope DB scope to bind after bearer auth; omit (null) to
     *                                 use IP allowlist + shared-secret auth instead
     */
    public function __construct(?string $bearerScope = null)
    {
        $this->bearerScope = $bearerScope;
    }

    /**
     * @param array<string, mixed> $params URL route parameters (unused)
     * @return bool False if auth fails for whichever style this instance was built for
     */
    public function before(array $params): bool
    {
        return $this->bearerScope === null
            ? CronAuth::verify()
            : CronAuth::verifyBearer($this->bearerScope);
    }
}
