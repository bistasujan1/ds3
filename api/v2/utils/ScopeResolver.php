<?php

declare(strict_types=1);

namespace Utils;

use Reunite\Framework\Cloud;

/**
 * Resolves {@see Cloud::get('api_scope')} from login keys or role sets; applies DB rebinding.
 */
class ScopeResolver
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $map = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function scopeMap(): array
    {
        if (self::$map === null) {
            self::$map = require __DIR__ . '/../config/scopes.php';
        }

        return self::$map;
    }

    /**
     * Apply DB scope from a client login key (see {@see config/scopes.php} keys: admin, clinic, …).
     *
     * @throws void Uses {@see response()} on invalid input
     */
    public static function applyFromLoginKey(?string $loginKey): void
    {
        if ($loginKey === null || $loginKey === '') {
            response(['code' => 400, 'message' => 'portal is required (login scope key, e.g. admin, pharmacy, clinic).']);
        }

        $map = self::scopeMap();
        if (!isset($map[$loginKey])) {
            response(['code' => 400, 'message' => 'Invalid portal value.']);
        }

        $entry = $map[$loginKey];
        if (!isset($entry['role_portal']) || $entry['role_portal'] === '') {
            response(['code' => 400, 'message' => 'Login is not supported for this portal via this endpoint.']);
        }

        Cloud::set('api_scope', $entry['db_scope']);
        Cloud::set('url_scope', $loginKey);
        Cloud::set('portal_folder', $entry['portal_folder']);
        Cloud::set('role_portal', $entry['role_portal']);
        Database::setDbInstance();
    }

    /**
     * Map role names (from {@see user_role_mapping}) to primary DB scope keys used by {@see Database::$dbConfigs}.
     */
    public static function apiScopeFromRoles(array $roles): ?string
    {
        if (count(array_intersect(ADMIN_ROLES, $roles))) {
            return 'admin';
        }
        if (count(array_intersect(PHARMACY_ROLES, $roles))) {
            return 'pharmacy';
        }
        if (count(array_intersect(PARTNER_ROLES, $roles))) {
            return 'partner';
        }
        if (count(array_intersect(MANUFACTURER_ROLES, $roles))) {
            return 'm';
        }
        if (count(array_intersect(CLINIC_ROLES, $roles))) {
            return 'nurse';
        }
        if (count(array_intersect(FINANCE_ROLES, $roles))) {
            return 'finance';
        }

        return null;
    }

    /**
     * Attach portal_folder / url_scope for logging; rebinding is already done by the caller.
     */
    public static function attachContextForDbScope(string $dbScope): void
    {
        foreach (self::scopeMap() as $key => $entry) {
            if (($entry['db_scope'] ?? '') === $dbScope) {
                Cloud::set('url_scope', $key);
                Cloud::set('portal_folder', $entry['portal_folder']);
                if (!empty($entry['role_portal'])) {
                    Cloud::set('role_portal', $entry['role_portal']);
                }
                return;
            }
        }

        Cloud::set('url_scope', $dbScope);
        Cloud::set('portal_folder', '');
    }

    /**
     * Apply DB scope from a scopes.php key (admin, patient, clinic, …).
     * Does not require role_portal — use for public routes via set_portal_scope().
     *
     * @throws void Uses {@see response()} on invalid input
     */
    public static function applyPortalScope(string $scope): void
    {
        if ($scope === '') {
            response(['code' => 400, 'message' => 'Invalid portal value.']);
        }

        $map = self::scopeMap();
        if (!isset($map[$scope])) {
            response(['code' => 400, 'message' => 'Invalid portal value.']);
        }

        $entry = $map[$scope];
        Cloud::set('api_scope', $entry['db_scope']);
        Cloud::set('url_scope', $scope);
        Cloud::set('portal_folder', $entry['portal_folder']);
        if (!empty($entry['role_portal'])) {
            Cloud::set('role_portal', $entry['role_portal']);
        }
        Database::setDbInstance();
    }
}
