<?php

declare(strict_types=1);

namespace Models;

/**
 * Repository for the clients table (pharmacy customers).
 */
class ClientModel extends BaseModel
{
    protected string $table = 'clients';

    /**
     * All active clients ordered by name.
     *
     * @return array
     */
    public function findAll(): array
    {
        return $this->db->rawQuery(
            'SELECT id, name, contact, email, phone, billing_address, credit_limit, terms,
                    tier_id, controlled_approved, license_number, license_expiry,
                    credit_hold, status, created_at
             FROM clients
             ORDER BY name ASC'
        ) ?: [];
    }

    /**
     * Single client by ID; null if not found.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id, string $columns = '*'): ?array
    {
        $rows = $this->db->rawQuery(
            'SELECT * FROM clients WHERE id = ? LIMIT 1',
            [$id]
        );
        return !empty($rows) ? $rows[0] : null;
    }

    /**
     * Minimal list for select2 dropdowns.
     *
     * @return array  [{id, name}]
     */
    public function findForDropdown(): array
    {
        return $this->db->rawQuery(
            'SELECT id, name FROM clients WHERE status = ? ORDER BY name ASC',
            ['Active']
        ) ?: [];
    }

    /**
     * Soft-delete a client by transitioning status to Inactive.
     *
     * @param int $id
     * @return bool
     */
    public function softDelete(int $id): bool
    {
        return (bool) $this->db->rawQuery(
            'UPDATE clients SET status = ? WHERE id = ?',
            ['Inactive', $id]
        );
    }
}
