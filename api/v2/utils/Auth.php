<?php

namespace Utils;

use Reunite\Framework\Cloud;

/**
 * Bearer-token helpers: generation, verification, and route protection for API v2.
 */
class Auth
{
    /** Default token time-to-live in seconds (reserved for future session use). */
    const TOKEN_TTL = 3600;

    /** @var string|null Shared secret for deterministic token hashing */
    private static ?string $secret = null;

    /**
     * Loads the encryption key from config into the static secret (legacy instance hook).
     */
    public function __construct()
    {
        self::$secret = config('ENCRYPTION_KEY');
    }

    /**
     * Generate a token
     *
     * @param string $data The data to generate a token from
     * @return string The generated token
     */
    public static function generateToken(string $data): string
    {
        if (!isset(self::$secret)) {
            self::$secret = config('ENCRYPTION_KEY');
        }
        $tokenGeneric = self::$secret . $_SERVER['SERVER_NAME'];
        return hash('sha256', $tokenGeneric . $data);
    }

    /**
     * Extracts the Bearer token from the Authorization header.
     *
     * @return string The raw token string
     * @throws \RuntimeException If the header is missing or malformed
     */
    private static function getBearerToken(): string
    {
        $headers = getallheaders();

        if (empty($headers['Authorization'])) {
            throw new \RuntimeException('Authorization header missing');
        }

        if (!preg_match('/^Bearer\s+(\S+)$/', $headers['Authorization'], $matches)) {
            throw new \RuntimeException('Bearer token not found');
        }

        return $matches[1];
    }

    /**
     * Returns the raw User-Data header (canonical string that was hashed into the token).
     *
     * @return string Raw header value
     * @throws \RuntimeException If the header is absent
     */
    private static function getUserDataHeaderRaw(): string
    {
        $headers = getallheaders();
        if (empty($headers['User-Data'])) {
            throw new \RuntimeException('User data header missing');
        }

        return $headers['User-Data'];
    }

    /**
     * Verify a token
     *
     * @param string $token The token to verify
     * @param string $data The payload string the token was generated from (e.g. canonical JSON or server-side string)
     * @return bool True if the token is valid, false otherwise
     */
    public static function verifyToken(string $token, string $data): bool
    {
        $expectedToken = self::generateToken($data);
        return hash_equals($expectedToken, $token);
    }

    /**
     * Validates Bearer token against the secret and checks user + token row on the default DB
     * (same rules as v1 {@see GeneralClass::verifyToken}), then loads all role names.
     *
     * @return array{userId: string, roles: list<string>, login_ip: string}|false
     */
    public static function verifySession(string $receivedToken, string $receivedData): array|false
    {
        if (!self::verifyToken($receivedToken, $receivedData)) {
            return false;
        }

        [$payloadWithoutRole, $roleSuffix] = array_pad(explode('*', $receivedData, 2), 2, '');
        if ($payloadWithoutRole === '') {
            return false;
        }

        [$tokenDate, $userData] = array_pad(explode('_', $payloadWithoutRole, 2), 2, '');
        if ($userData === '') {
            return false;
        }

        [$userId, $userEmail] = array_pad(explode('-', $userData, 2), 2, '');
        if ($userId === '') {
            return false;
        }

        $db = Cloud::db();
        $db->where('id', $userId);
        $db->where('token', $receivedToken);
        $result = $db->getOne('user', 'id,login_ip');

        if (empty($result)) {
            return false;
        }

        $db->join('role r', 'ur.role_id = r.id', 'LEFT');
        $db->where('ur.user_id', $result['id']);
        $user_role_mapping = $db->get('user_role_mapping ur', null, 'r.*, ur.user_id, ur.role_id');
        if (empty($user_role_mapping)) {
            return false;
        }

        $roles = [];
        foreach ($user_role_mapping as $value) {
            $roles[] = $value['name'];
        }

        return [
            'userId' => (string) $userId,
            'roles' => $roles,
            'login_ip' => (string) ($result['login_ip'] ?? ''),
        ];
    }

    /**
     * Validates the Bearer token and loads the authenticated user into Cloud context.
     *
     * Reads the Authorization header, looks up the token in the users table (checking
     * expiry and active status), resolves the API scope from the user's role, and
     * populates Cloud('user') and Cloud('api_scope').
     * Returns false on any failure so middleware can halt the request without calling exit().
     *
     * @return bool True on success; false if header is missing, token is invalid/expired,
     *              or the user's role has no recognized portal scope
     */
    public static function protect(): bool
    {
        try {
            $token = self::getBearerToken();
        } catch (\RuntimeException) {
            return false;
        }

        $db = Cloud::db();
        $db->where('token', $token);
        $db->where('token_expires_at', date('Y-m-d H:i:s'), '>=');
        $db->where('status', 'active');
        $user = $db->getOne('users', 'id, email, first_name, last_name');

        if (!$user) {
            return false;
        }

        // Load all roles via user_role_mapping → role
        $db->join('role r', 'urm.role_id = r.id', 'INNER');
        $db->where('urm.user_id', $user['id']);
        $roleRows = $db->get('user_role_mapping urm', null, 'r.name') ?: [];
        $roles = array_column($roleRows, 'name');

        if (empty($roles)) {
            return false;
        }

        Cloud::set('user', [
            'id'    => (string) $user['id'],
            'email' => $user['email'],
            'name'  => $user['first_name'] . ' ' . $user['last_name'],
            'role'  => $roles[0],
            'roles' => $roles,
        ]);

        Cloud::set('api_scope', 'admin');
        ScopeResolver::attachContextForDbScope('admin');
        Database::setDbInstance();

        return true;
    }

    /**
     * Check if the user has the given role
     *
     * @param string $role The role to check
     * @return void
     */
    public static function role(string $role): void
    {
        $user = Cloud::get('user') ?? [];
        if (($user['role'] ?? '') !== $role) {
            response(['code' => 403, 'message' => 'Forbidden']);
        }
    }

    /**
     * Grants access if the authenticated user is `super_admin`, holds any role in
     * $anyOfRoles, or — when $module is given — has an active grant for $module via
     * {@see Auth::hasModuleAccess()}. Mirrors the tri-condition role check v1 endpoints
     * hardcoded individually (super_admin OR a specific role set OR hasModuleAccess()).
     * Responds 403 and halts the request on failure, matching {@see Auth::role()}.
     *
     * @param list<string> $anyOfRoles Role names that grant access on their own
     * @param string|null  $module     Optional role_module_access module key checked as a fallback
     * @return void
     */
    public static function authorizeAnyOf(array $anyOfRoles, ?string $module = null): void
    {
        $user  = Cloud::get('user') ?? [];
        $roles = $user['roles'] ?? [];

        if (in_array('super_admin', $roles, true)) {
            return;
        }

        if (array_intersect($anyOfRoles, $roles)) {
            return;
        }

        if ($module !== null && self::hasModuleAccess($roles, $module)) {
            return;
        }

        response(['code' => 403, 'message' => 'Forbidden']);
    }

    /**
     * Checks role_module_access for an active grant on any of the given roles for
     * $module. Ports v1 {@see \GeneralClass::hasModuleAccess()} — same table, same
     * super_admin-bypass semantics (callers typically check super_admin separately
     * before reaching here, as {@see Auth::authorizeAnyOf()} does).
     *
     * @param list<string> $roles  User's role names
     * @param string       $module role_module_access.module key
     * @return bool True if any role has an active grant for $module
     */
    public static function hasModuleAccess(array $roles, string $module): bool
    {
        if (empty($roles) || $module === '') {
            return false;
        }

        if (in_array('super_admin', $roles, true)) {
            return true;
        }

        $db = Cloud::db();
        $db->join('role r', 'rma.role_id = r.id', 'INNER');
        $db->where('rma.module', $module);
        $db->where('rma.active', 'yes');
        $db->where('r.name', $roles, 'IN');
        $result = $db->getOne('role_module_access rma', 'rma.id');

        return $result !== null;
    }
}
