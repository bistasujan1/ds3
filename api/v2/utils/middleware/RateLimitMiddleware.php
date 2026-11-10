<?php

declare(strict_types=1);

namespace Utils\Middleware;

/**
 * APCu-based per-IP, per-URI request throttle.
 *
 * Uses both before() and after() to demonstrate the full middleware lifecycle.
 * Fails open when APCu is unavailable so the app keeps running without it.
 *
 * Before: checks + increments hit counter, sets X-RateLimit-* headers.
 * After:  no-op (reserved for response-level quota decoration if needed).
 */
class RateLimitMiddleware extends Middleware
{
    /** APCu key for the current request, set in before() and read in after(). */
    private string $cacheKey = '';

    /**
     * @param int $maxRequests  Maximum requests allowed within the window
     * @param int $windowSeconds Rolling window duration in seconds
     */
    public function __construct(
        private int $maxRequests,
        private int $windowSeconds
    ) {}

    /**
     * Checks the request count for the caller IP + URI pair and blocks if over limit.
     *
     * @param array<string, mixed> $params URL route parameters (unused)
     * @return bool False when the limit is exceeded; always true when APCu is unavailable
     */
    public function before(array $params): bool
    {
        if (!function_exists('apcu_fetch')) {
            return true;
        }

        $this->cacheKey = 'rate:' . request_ip() . ':' . md5($_SERVER['REQUEST_URI'] ?? '');
        $count = apcu_fetch($this->cacheKey) ?: 0;

        if ($count >= $this->maxRequests) {
            header('Retry-After: ' . $this->windowSeconds);
            header('X-RateLimit-Limit: ' . $this->maxRequests);
            header('X-RateLimit-Remaining: 0');
            return false;
        }

        apcu_store($this->cacheKey, $count + 1, $this->windowSeconds);
        header('X-RateLimit-Limit: ' . $this->maxRequests);
        header('X-RateLimit-Remaining: ' . ($this->maxRequests - $count - 1));

        return true;
    }

    /**
     * Post-handler hook — reserved for future response-level quota headers.
     *
     * @param array<string, mixed> $params URL route parameters (unused)
     * @return bool Always true
     */
    public function after(array $params): bool
    {
        return true;
    }
}
