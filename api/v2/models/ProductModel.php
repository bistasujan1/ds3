<?php

declare(strict_types=1);

namespace Models;

/**
 * Repository for the products table (pharmaceutical compounding ingredients).
 */
class ProductModel extends BaseModel
{
    protected string $table = 'products';

    /**
     * Single product by ID; null if not found.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id, string $columns = '*'): ?array
    {
        $rows = $this->db->rawQuery(
            'SELECT * FROM products WHERE id = ? LIMIT 1',
            [$id]
        );
        return !empty($rows) ? $rows[0] : null;
    }

    /**
     * Active products for select2 dropdowns, includes pricing and schedule info.
     *
     * @return array  [{id, code, name, list_price, schedule, storage}]
     */
    public function findForDropdown(): array
    {
        return $this->db->rawQuery(
            'SELECT id, code, name, list_price, schedule, storage
             FROM products
             WHERE status = ?
             ORDER BY name ASC',
            ['Active']
        ) ?: [];
    }

    /**
     * All products with category name.
     *
     * @return array
     */
    public function findAll(): array
    {
        return $this->db->rawQuery(
            'SELECT p.id, p.code, p.name, p.grade, p.schedule, p.storage,
                    p.list_price, p.status, c.name AS category_name, p.created_at
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             ORDER BY p.name ASC'
        ) ?: [];
    }
}
