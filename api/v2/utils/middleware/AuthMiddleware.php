<?php

declare(strict_types=1);

namespace Utils\Middleware;

use Utils\Auth;

/**
 * Validates the Bearer token and loads the authenticated user into Cloud context.
 *
 * On success, Cloud holds 'user' (id, roles, login_ip, role) and 'api_scope'.
 * On failure, returns false — framework responds with 403.
 */
class AuthMiddleware extends Middleware
{
    /**
     * Verifies the Bearer token from the Authorization header.
     *
     * @param array<string, mixed> $params URL route parameters (unused)
     * @return bool False if the token is missing, invalid, or the user has no recognized scope
     */
    public function before(array $params): bool
    {
        return Auth::protect();
    }
}
