<?php

declare(strict_types=1);

namespace Utils;

use Utils\Middleware\AuthMiddleware;
use Utils\Middleware\CronBearerMiddleware;
use Utils\Middleware\CronMiddleware;
use Utils\Middleware\PortalScopeMiddleware;
use Utils\Middleware\RateLimitMiddleware;
use Utils\Middleware\ServiceMiddleware;
use Utils\Middleware\WebhookMiddleware;

/**
 * Portal middleware stacks.
 *
 * All auth surfaces in one file — easy to audit and impossible to typo.
 * IDE autocomplete works on every static call. Each method returns a ready-to-use
 * array that the router accepts as the third argument of group() or route methods.
 *
 * Namespace note: this class is Utils\Middleware. The abstract base class is
 * Utils\Middleware\Middleware (lowercase sub-namespace). They are distinct; Router
 * files import only Utils\Middleware and never touch the abstract base directly.
 */
class Middleware
{
    /**
     * Stack for admin portal routes (auth + scope assertion).
     *
     * @return list<\Utils\Middleware\Middleware>
     */
    public static function admin(): array
    {
        return [new AuthMiddleware(), new PortalScopeMiddleware('admin')];
    }

    /**
     * Stack for pharmacy portal routes.
     *
     * @return list<\Utils\Middleware\Middleware>
     */
    public static function pharmacy(): array
    {
        return [new AuthMiddleware(), new PortalScopeMiddleware('pharmacy')];
    }

    /**
     * Stack for clinic/nurse portal routes.
     *
     * @return list<\Utils\Middleware\Middleware>
     */
    public static function clinic(): array
    {
        return [new AuthMiddleware(), new PortalScopeMiddleware('nurse')];
    }

    /**
     * Stack for patient portal routes.
     *
     * @return list<\Utils\Middleware\Middleware>
     */
    public static function patient(): array
    {
        return [new AuthMiddleware(), new PortalScopeMiddleware('patient')];
    }

    /**
     * Stack for partner portal routes.
     *
     * @return list<\Utils\Middleware\Middleware>
     */
    public static function partner(): array
    {
        return [new AuthMiddleware(), new PortalScopeMiddleware('partner')];
    }

    /**
     * Stack for finance portal routes.
     *
     * @return list<\Utils\Middleware\Middleware>
     */
    public static function finance(): array
    {
        return [new AuthMiddleware(), new PortalScopeMiddleware('finance')];
    }

    /**
     * Stack for scheduled cron job routes.
     *
     * @return list<\Utils\Middleware\Middleware>
     */
    public static function cron(): array
    {
        return [new CronMiddleware()];
    }

    /**
     * Stack for legacy v1-compatible cron routes (Authorization bearer token).
     *
     * @param string $scope DB scope after auth. Existing cron groups omit this and stay on `default`.
     * @return list<\Utils\Middleware\Middleware>
     */
    public static function cronAuth(string $scope = 'default'): array
    {
        return [new CronMiddleware($scope)];
    }

    /**
     * Stack for inbound vendor webhook routes.
     *
     * @param string $vendor Vendor key: 'authorize_net', 'twilio', 'sendgrid', or 'custom'
     * @return list<\Utils\Middleware\Middleware>
     */
    public static function webhook(string $vendor): array
    {
        return [new WebhookMiddleware($vendor)];
    }

    /**
     * Stack for internal service / bot routes authenticated via API key.
     *
     * @param string $scope DB scope the key must be authorized for (default 'default')
     * @return list<\Utils\Middleware\Middleware>
     */
    public static function service(string $scope = 'default'): array
    {
        return [new ServiceMiddleware($scope)];
    }

    /**
     * Appends a rate-limit layer to an existing middleware stack.
     *
     * @param list<\Utils\Middleware\Middleware> $stack       Base stack (e.g. Middleware::finance())
     * @param int                               $max         Maximum requests per window
     * @param int                               $window      Window duration in seconds
     * @return list<\Utils\Middleware\Middleware>
     */
    public static function withRateLimit(array $stack, int $max, int $window): array
    {
        return [...$stack, new RateLimitMiddleware($max, $window)];
    }
}
