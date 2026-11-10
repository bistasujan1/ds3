<?php

declare(strict_types=1);

namespace Utils\Middleware;

use Reunite\Framework\Cloud;
use Services\PatientPortal\AuthService;
use Utils\Database;
use Utils\Hs256Jwt;

/**
 * Authenticates patient-portal requests via phone+DOB+OTP JWT (Bearer + Platform header).
 */
class PatientAuthMiddleware extends Middleware
{
    /**
     * Validates the Bearer JWT + Platform header, then loads patient context onto Cloud.
     *
     * @param array<string, mixed> $params URL route parameters (unused)
     * @return bool False if headers are missing, JWT is invalid/expired, or platform mismatches
     */
    public function before(array $params): bool
    {
        unset($params);
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        $platform = trim($headers['Platform'] ?? '');

        if ($authHeader === '' || $platform === '') {
            return false;
        }

        if (!preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $authHeader, $matches)) {
            return false;
        }
        $token = $matches[1];

        try {
            $jwtConfig = AuthService::jwtConfig();
        } catch (\Throwable $e) {
            return false;
        }

        $payload = Hs256Jwt::decode($token, $jwtConfig);
        if (
            !$payload
            || empty($payload['id'])
            || empty($payload['platform'])
            || empty($payload['name'])
            || $payload['platform'] !== $platform
        ) {
            return false;
        }

        Cloud::set('patient', [
            'cld_patient_id' => $payload['id'],
            'patient_name'   => $payload['name'],
            'platform'       => $payload['platform'],
        ]);
        Cloud::set('api_scope', 'patient');
        Database::setDbInstance();

        return true;
    }
}
