<?php

declare(strict_types=1);

namespace Utils\Middleware;

use Utils\PortalAccessLog;

/**
 * Thin middleware wrapper — all logic lives in {@see PortalAccessLog}.
 */
class AccessLogMiddleware extends Middleware
{
    private string $methodName;

    private bool $logResponse;

    private ?string $portal;

    public function __construct(string $methodName, bool $logResponse = false, ?string $portal = null)
    {
        $this->methodName = $methodName;
        $this->logResponse = $logResponse;
        $this->portal = $portal;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function before(array $params): bool
    {
        unset($params);
        PortalAccessLog::begin($this->methodName, $this->logResponse, $this->portal);

        return true;
    }
}
