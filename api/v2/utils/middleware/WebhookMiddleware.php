<?php

declare(strict_types=1);

namespace Utils\Middleware;

use Utils\WebhookAuth;

/**
 * Verifies the HMAC/ECDSA signature on inbound vendor webhook requests.
 *
 * Supported vendors: authorize_net, twilio, sendgrid, custom.
 * Raw request body must be cached on Cloud before routing (done in bootstrap.php).
 */
class WebhookMiddleware extends Middleware
{
    /**
     * @param string $vendor Vendor key (e.g. 'authorize_net', 'twilio', 'sendgrid', 'custom')
     */
    public function __construct(private string $vendor) {}

    /**
     * Verifies the vendor-specific webhook signature.
     *
     * @param array<string, mixed> $params URL route parameters (unused)
     * @return bool False if the signature is missing or does not match
     */
    public function before(array $params): bool
    {
        return WebhookAuth::verify($this->vendor);
    }
}
