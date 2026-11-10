<?php

declare(strict_types=1);

namespace Utils;

use Reunite\Logger;

/**
 * Queue work to run after the HTTP response is flushed to the client.
 *
 * Register tasks with {@see defer()} or the global {@see defer_after_response()}
 * helper. {@see response()} executes queued tasks after send() (and
 * fastcgi_finish_request() when available). A shutdown fallback runs any
 * tasks left if the request exits without calling response().
 */
final class PostResponse
{
    /** @var list<callable(): void> */
    private static array $tasks = [];

    private static bool $ran = false;

    private static bool $shutdownRegistered = false;

    public static function defer(callable $task): void
    {
        self::$tasks[] = $task;
        self::ensureShutdownFallback();
    }

    public static function run(): void
    {
        if (self::$ran || self::$tasks === []) {
            return;
        }

        self::$ran = true;
        $tasks = self::$tasks;
        self::$tasks = [];

        foreach ($tasks as $task) {
            try {
                $task();
            } catch (\Throwable $e) {
                Logger::error($e->getMessage(), [
                    'location' => 'PostResponse::run',
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Clears queued tasks — for PHPUnit only.
     */
    public static function reset(): void
    {
        self::$tasks = [];
        self::$ran = false;
    }

    private static function ensureShutdownFallback(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }

        self::$shutdownRegistered = true;
        register_shutdown_function([self::class, 'run']);
    }
}
