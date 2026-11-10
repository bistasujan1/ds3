<?php

declare(strict_types=1);

namespace Utils\Middleware;

use Reunite\Framework\Cloud;
use Reunite\Logger;

/**
 * Asserts the resolved API scope matches the portal this route belongs to.
 *
 * Must run after AuthMiddleware so Cloud('api_scope') is already set.
 * Prevents cross-portal access even when a valid token is present.
 */
class PortalScopeMiddleware extends Middleware
{
    /**
     * @param string $expected Scope string that must match Cloud('api_scope') (e.g. 'admin', 'pharmacy')
     */
    public function __construct(private string $expected) {}

    /**
     * Compares the expected scope against the scope resolved from the user's roles.
     *
     * @param array<string, mixed> $params URL route parameters (unused)
     * @return bool False if the resolved scope does not match the expected portal scope
     */
    public function before(array $params): bool
    {
        $resolved = Cloud::get('api_scope');

        if ($resolved !== $this->expected) {
            Logger::warning('Portal scope mismatch', [
                'expected' => $this->expected,
                'resolved' => $resolved,
                'user_id'  => Cloud::get('user')['id'] ?? null,
            ]);
            return false;
        }

        return true;
    }
}
