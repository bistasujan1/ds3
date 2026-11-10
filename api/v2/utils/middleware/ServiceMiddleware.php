<?php

declare(strict_types=1);

namespace Utils\Middleware;

use Reunite\Framework\Cloud;
use Utils\Database;

/**
 * Authenticates internal bots and services via a scoped API key.
 *
 * Reads X-Service-Key header, hashes it with SHA-256, looks up the hash in
 * service_api_keys, and checks scope + expiry. On success, sets Cloud context:
 * request_type=service, caller_id=<key id>, api_scope=<scope>.
 *
 * Required table: service_api_keys (id, name, hashed_key, scope, active, expires_at, ...).
 */
class ServiceMiddleware extends Middleware
{
    /**
     * @param string $scope DB scope the key must be authorized for (default 'default')
     */
    public function __construct(private string $scope = 'default') {}

    /**
     * Validates the X-Service-Key header against the service_api_keys table.
     *
     * @param array<string, mixed> $params URL route parameters (unused)
     * @return bool False if the key is missing, inactive, expired, or scoped differently
     */
    public function before(array $params): bool
    {
        $rawKey = $_SERVER['HTTP_X_SERVICE_KEY'] ?? '';

        if ($rawKey === '') {
            return false;
        }

        $hashed = hash('sha256', $rawKey);
        $db     = Database::getInstance($this->scope);

        $key = $db
            ->where('hashed_key', $hashed)
            ->where('active', 1)
            ->getOne('service_api_keys', 'id, name, scope, expires_at, pharmacy_id');

        if (!$key) {
            return false;
        }

        if ($key['expires_at'] && strtotime($key['expires_at']) < time()) {
            return false;
        }

        $db->where('id', $key['id'])->update('service_api_keys', [
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);

        Cloud::set('request_type', 'service');
        Cloud::set('caller_id', $key['id']);
        Cloud::set('user', ['pharmacy_id' => (int) ($key['pharmacy_id'] ?? 0)]);
        Cloud::set('api_scope', $key['scope']);
        Database::setDbInstance($key['scope']);

        return true;
    }
}
