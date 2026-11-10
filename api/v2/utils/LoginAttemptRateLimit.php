<?php

declare(strict_types=1);

namespace Utils;

use Reunite\Logger;

/**
 * Reusable auth rate limiting: APCu IP throttle + audit_db logging + login_attempts lockout.
 *
 * Port of v1 AuthRateLimitClass — usable by any portal (patient, admin, etc.).
 */
class LoginAttemptRateLimit
{
    /** @var object Main DB (MysqliDb) */
    private $db;

    /** @var object|null Audit DB (MysqliDb) */
    private $auditDb;

    private int $maxRequests;

    private int $windowSeconds;

    private int $maxFails;

    private int $lockMinutes;

    /**
     * @param object $db
     * @param object|null $auditDb
     * @param array<string, int> $config
     */
    public function __construct($db, $auditDb, array $config = [])
    {
        $this->db = $db;
        $this->auditDb = $auditDb;
        $this->maxRequests = $config['maxRequests'] ?? 20;
        $this->windowSeconds = $config['windowSeconds'] ?? 60;
        $this->maxFails = $config['maxFails'] ?? 5;
        $this->lockMinutes = $config['lockMinutes'] ?? 5;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{client_ip: string, user_agent: string, platform?: string, auth_action: string}
     */
    public static function authRateReport(array $data, string $authAction): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $report = [
            'client_ip' => real_client_ip(),
            'user_agent' => substr((string) ($headers['User-Agent'] ?? ''), 0, 512),
            'auth_action' => $authAction,
        ];
        if (!empty($data['platform'])) {
            $report['platform'] = substr((string) $data['platform'], 0, 64);
        }

        return $report;
    }

    public function getWindowSeconds(): int
    {
        return $this->windowSeconds;
    }

    /**
     * IP rate limit: APCu when available; audit_db COUNT fallback (v1 AuthRateLimitClass parity).
     *
     * @param array<string, mixed>|null $report
     */
    public function isIpAllowed(string $key, string $scope, ?array $report = null): bool
    {
        $hashKey = hash('sha256', $key);

        if ($this->isApcuAvailable()) {
            $cacheKey = 'login_rate:' . $scope . ':' . $hashKey;
            $stored = apcu_fetch($cacheKey);
            $count = ($stored === false) ? 0 : (int) $stored;
            if ($count >= $this->maxRequests) {
                return false;
            }
            if (apcu_store($cacheKey, $count + 1, $this->windowSeconds)) {
                $this->logRateLimitRequest($hashKey, $scope, $report);

                return true;
            }
        }

        return $this->isIpAllowedViaAuditDb($hashKey, $scope, $report);
    }

    private function isApcuAvailable(): bool
    {
        if (!function_exists('apcu_fetch') || !function_exists('apcu_store')) {
            return false;
        }

        return !function_exists('apcu_enabled') || apcu_enabled();
    }

    /**
     * @param array<string, mixed>|null $report
     */
    private function isIpAllowedViaAuditDb(string $hashKey, string $scope, ?array $report): bool
    {
        if ($this->auditDb === null) {
            return true;
        }

        try {
            $windowSec = (int) $this->windowSeconds;
            $this->auditDb->where('key_hash', $hashKey);
            $this->auditDb->where('scope', $scope);
            // Do not use ? inside the expression: MysqliDb appends "= ?" for the value.
            $this->auditDb->where("created >= DATE_SUB(NOW(), INTERVAL {$windowSec} SECOND)");
            $count = (int) $this->auditDb->getValue('rate_limiter', 'COUNT(*)');

            if ($count >= $this->maxRequests) {
                return false;
            }

            $this->logRateLimitRequest($hashKey, $scope, $report);

            return true;
        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), [
                'location' => 'LoginAttemptRateLimit::isIpAllowedViaAuditDb',
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return true;
        }
    }

    /**
     * @param list<string> $scopes
     */
    public function canAttemptLoginScopes(string $username, array $scopes): bool
    {
        if ($scopes === []) {
            return true;
        }
        $this->db->where('username', $username);
        $this->db->where('scope', $scopes, 'IN');
        $this->db->where('(locked_until IS NOT NULL AND locked_until > NOW())');
        $row = $this->db->getOne('login_attempts', ['id']);

        return !$row;
    }

    public function canAttemptLogin(string $username, string $scope): bool
    {
        $this->db->where('username', $username);
        $this->db->where('scope', $scope);
        $this->db->where('(locked_until IS NULL OR locked_until > NOW())');
        $row = $this->db->getOne('login_attempts', ['fail_count', 'locked_until']);

        return !$row || empty($row['locked_until']);
    }

    /**
     * @param array<string, mixed>|null $report
     * @return array{attempts_remaining:int, locked:bool, fail_count:int}
     */
    public function recordFailure(string $username, string $scope, ?array $report = null): array
    {
        $this->db->where('username', $username);
        $this->db->where('scope', $scope);
        $row = $this->db->getOne('login_attempts', ['fail_count']);

        $meta = $this->reportingColumns($report);

        if (!$row) {
            $failCount = 1;
            $this->db->insert('login_attempts', array_merge([
                'username'   => $username,
                'scope'      => $scope,
                'fail_count' => $failCount,
                'created'    => $this->db->now(),
                'updated'    => $this->db->now(),
            ], $meta));

            return $this->failureOutcome($failCount);
        }

        $failCount = (int) $row['fail_count'] + 1;
        $lockedUntil = null;

        if ($failCount >= $this->maxFails) {
            $lockMin = (int) $this->lockMinutes;
            $lockedUntil = $this->db->rawQueryValue(
                "SELECT DATE_ADD(NOW(), INTERVAL {$lockMin} MINUTE) AS locked_until LIMIT 1"
            );
        }

        $this->db->where('username', $username);
        $this->db->where('scope', $scope);
        $this->db->update('login_attempts', array_merge([
            'fail_count'   => $failCount,
            'locked_until' => $lockedUntil,
            'updated'      => $this->db->now(),
        ], $meta));

        return $this->failureOutcome($failCount);
    }

    public function recordSuccess(string $username, string $scope): void
    {
        $this->db->where('username', $username);
        $this->db->where('scope', $scope);
        $this->db->delete('login_attempts');
    }

    /**
     * @param array<string, mixed>|null $report
     */
    private function logRateLimitRequest(string $hashKey, string $scope, ?array $report): void
    {
        if ($this->auditDb === null) {
            return;
        }

        try {
            $insert = array_merge([
                'key_hash' => $hashKey,
                'scope' => $scope,
                'created' => $this->auditDb->now(),
            ], $this->reportingColumns($report));

            $this->auditDb->insert('rate_limiter', $insert);
        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), [
                'location' => 'LoginAttemptRateLimit::logRateLimitRequest',
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * @param array<string, mixed>|null $report
     * @return array<string, string>
     */
    private function reportingColumns(?array $report): array
    {
        if ($report === null || $report === []) {
            return [];
        }
        $out = [];
        if (!empty($report['client_ip'])) {
            $out['client_ip'] = substr((string) $report['client_ip'], 0, 45);
        }
        if (!empty($report['user_agent'])) {
            $out['user_agent'] = substr((string) $report['user_agent'], 0, 512);
        }
        if (!empty($report['platform'])) {
            $out['platform'] = substr((string) $report['platform'], 0, 64);
        }
        if (!empty($report['auth_action'])) {
            $out['auth_action'] = substr((string) $report['auth_action'], 0, 32);
        }

        return $out;
    }

    /**
     * @return array{attempts_remaining:int, locked:bool, fail_count:int}
     */
    private function failureOutcome(int $failCount): array
    {
        $locked = $failCount >= $this->maxFails;

        return [
            'attempts_remaining' => max(0, $this->maxFails - $failCount),
            'locked'             => $locked,
            'fail_count'         => $failCount,
        ];
    }
}
