<?php

declare(strict_types=1);

namespace Utils;

use Reunite\Framework\Cloud;
use Reunite\Logger;

/**
 * Audit_db user_login_history access logging — reusable by any portal (v1 PortalClass parity).
 */
class PortalAccessLog
{
    public static function begin(string $methodName, bool $logResponse = false, ?string $portal = null): void
    {
        try {
            if (!Cloud::has('audit_db')) {
                return;
            }

            $payload = request_post();
            if (!is_array($payload)) {
                $payload = [];
            }

            $audit = Cloud::audit_db();
            $audit->insert('user_login_history', [
                'user_id' => (int) (Cloud::get('user')['id'] ?? 0),
                'ip' => real_client_ip(),
                'token' => self::resolveToken($payload),
                'method_name' => $methodName,
                'referer' => (string) ($_SERVER['HTTP_REFERER'] ?? ''),
                'portal' => self::resolvePortalKey($payload, $portal),
                'json_request' => json_encode(self::redact($payload)),
            ]);

            $accessId = (int) $audit->getInsertId();
            if ($accessId <= 0) {
                return;
            }

            Cloud::set('portal_access_id', $accessId);
            if ($logResponse) {
                Cloud::set('portal_access_log_response', true);
            }
        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), [
                'location' => 'PortalAccessLog::begin',
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function finish(array $payload): void
    {
        if (!Cloud::get('portal_access_log_response') || !Cloud::has('portal_access_id')) {
            return;
        }

        try {
            if (!Cloud::has('audit_db')) {
                return;
            }

            $accessId = (int) Cloud::get('portal_access_id');
            if ($accessId <= 0) {
                return;
            }

            $audit = Cloud::audit_db();
            $audit->where('id', $accessId);
            $audit->update('user_login_history', [
                'json_response' => json_encode(self::redact($payload)),
            ]);
        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), [
                'location' => 'PortalAccessLog::finish',
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function resolvePortalKey(array $payload, ?string $portal): string
    {
        if ($portal !== null && $portal !== '') {
            if (strpos($portal, '{platform}') !== false) {
                return self::keyWithPlatform($portal, self::resolvePlatform($payload));
            }

            return $portal;
        }

        $scope = (string) (Cloud::get('api_scope') ?? '');

        return $scope !== '' ? $scope : '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function resolvePlatform(array $payload): string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $header = trim((string) ($headers['Platform'] ?? ''));
        if ($header !== '') {
            return self::cleanPlatform($header);
        }
        if (!empty($payload['platform'])) {
            return self::cleanPlatform((string) $payload['platform']);
        }

        return '';
    }

    private static function cleanPlatform(string $platform): string
    {
        $platform = str_replace(['_', '-'], '', $platform);

        return strtolower($platform);
    }

    private static function keyWithPlatform(string $key, string $platform): string
    {
        return str_replace('__', '_', str_replace('{platform}', $platform, $key));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function resolveToken(array $payload): string
    {
        foreach (['token', 'auth_token', 'login_token'] as $key) {
            if (!empty($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = (string) ($headers['Authorization'] ?? '');
        if ($authHeader !== '' && preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function redact(array $data): array
    {
        $keys = ['otp', 'login_token', 'token', 'auth_token', 'password', 'password_confirm'];

        $walk = static function ($value) use (&$walk, $keys) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $value[$k] = in_array($k, $keys, true) ? '[REDACTED]' : $walk($v);
                }

                return $value;
            }

            return $value;
        };

        return $walk($data);
    }
}
