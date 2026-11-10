<?php

declare(strict_types=1);

namespace system;

/**
 * Thrown by response() (functions/global.php) instead of exit() when APP_ENV=testing,
 * so integration tests can capture the JSON payload and control flow halts correctly
 * without killing the PHPUnit process.
 */
class TestResponseException extends \RuntimeException
{
    /** @param array<string, mixed> $payload */
    public function __construct(public readonly array $payload, public readonly int $responseCode)
    {
        parent::__construct('response() halted (APP_ENV=testing)', $responseCode);
    }
}
