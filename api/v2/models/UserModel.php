<?php

declare(strict_types=1);

namespace Models;

/**
 * Repository for the users table.
 */
class UserModel extends BaseModel
{
    protected string $table = 'users';

    /**
     * Find an active (non-deleted) user by email address.
     *
     * @param string $email
     * @return array|null  null if not found or deleted/inactive
     */
    public function findActiveByEmail(string $email): ?array
    {
        // Use rawQuery to avoid MysqliDb null-handling quirks
        $results = $this->db->rawQuery(
            'SELECT * FROM users WHERE email = ? AND status = ? LIMIT 1',
            [$email, 'active']
        );
        return (!empty($results) && isset($results[0])) ? $results[0] : null;
    }

    /**
     * Find an active user by their bearer token (not expired).
     *
     * @param string $token
     * @return array|null
     */
    public function findByToken(string $token): ?array
    {
        $this->db->where('token', $token);
        $this->db->where('token_expires_at', date('Y-m-d H:i:s'), '>=');
        $this->db->where('status', 'active');
        return $this->db->getOne($this->table) ?: null;
    }
}
