<?php

declare(strict_types=1);

namespace utils\middleware;

use Reunite\Framework\Cloud;
use utils\Database;

/**
 * Authenticates patient-portal requests via the v1 phone+DOB+OTP JWT scheme.
 *
 * Deliberately NOT AuthMiddleware/utils\Auth — the patient portal login flow
 * (patient/portal/*, api/v1/webservice/patient/portal/patient_auth.php) issues
 * its own HS256 JWT (JwtAuthClass, PATIENT_PORTAL_JWT_SECRET) instead of the
 * bearer+User-Data scheme every other v2 portal uses. Kept as-is per migration
 * decision — ports api/v1/webservice/patient/portal/PortalClass::validate_token()
 * 1:1. See api/v2/tests/patient/dashboard_app/v1_feature_inventory.php.
 *
 * Bridges the v1 JwtAuthClass (plain HS256 encode/decode helper — no DB or
 * framework coupling, no reunite/* library equivalent) via require_once, same
 * as any other dependency-free v1 helper class.
 */
class PatientPortalAuthMiddleware extends Middleware
{
    /**
     * Validates the Bearer JWT + Platform header, then loads patient context onto Cloud.
     *
     * @param array<string, mixed> $params URL route parameters (unused)
     * @return bool False if headers are missing, JWT is invalid/expired, or platform mismatches
     */
    public function before(array $params): bool
    {
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

        $jwtSecret = config('PATIENT_PORTAL_JWT_SECRET');
        if ($jwtSecret === null) {
            return false;
        }
        $jwtConfig = [
            'secret' => $jwtSecret,
            'ttl'    => config('PATIENT_PORTAL_JWT_TTL') ?? 604800,
        ];

        $this->loadJwtClass();
        if (!class_exists('JwtAuthClass')) {
            return false;
        }

        $payload = \JwtAuthClass::decode($token, $jwtConfig);
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

    private function loadJwtClass(): void
    {
        if (class_exists('JwtAuthClass')) {
            return;
        }
        $path = dirname(__DIR__, 4) . '/api/v1/webservice/classes/JwtAuthClass.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
}
