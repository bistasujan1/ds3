<?php

declare(strict_types=1);

namespace Models;

/**
 * Repository for the orders table (sales orders, one product per row).
 */
class OrderModel extends BaseModel
{
    protected string $table = 'orders';

    /**
     * All orders with client and product names, newest first.
     *
     * @return array
     */
    public function findAll(): array
    {
        return $this->db->rawQuery(
            'SELECT o.id, o.order_number, o.status, o.qty_g, o.price_g,
                    o.total, o.qty_g * o.price_g AS line_total,
                    o.ship_by, o.shipment_at, o.delivery_at,
                    o.carrier, o.tracking_number, o.held,
                    o.notes, o.created_at,
                    c.name AS client_name,
                    p.code AS product_code, p.name AS product_name
             FROM orders o
             JOIN clients c ON c.id = o.client_id
             JOIN products p ON p.id = o.product_id
             WHERE o.status != \'Cancelled\'
             ORDER BY o.id DESC'
        ) ?: [];
    }

    /**
     * Single order with full client and product detail.
     * The $columns parameter is accepted for interface compatibility but ignored —
     * this override always selects the joined set needed for order display.
     *
     * @param int    $id
     * @param string $columns Ignored; kept for BaseModel compatibility
     * @return array|null
     */
    public function findById(int $id, string $columns = '*'): ?array
    {
        $rows = $this->db->rawQuery(
            'SELECT o.*, c.name AS client_name, c.email AS client_email,
                    p.code AS product_code, p.name AS product_name, p.schedule
             FROM orders o
             JOIN clients c ON c.id = o.client_id
             JOIN products p ON p.id = o.product_id
             WHERE o.id = ? LIMIT 1',
            [$id]
        );
        return !empty($rows) ? $rows[0] : null;
    }

    /**
     * Generate the next sequential order number for today.
     *
     * @return string  e.g. ORD-20261109-0001
     */
    public function generateOrderNumber(): string
    {
        $date   = date('Ymd');
        $result = $this->db->rawQuery(
            "SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1",
            ["ORD-{$date}-%"]
        );
        $seq = empty($result) ? 1 : ((int) substr($result[0]['order_number'], -4)) + 1;
        return sprintf('ORD-%s-%04d', $date, $seq);
    }

    /**
     * Soft-delete an order.
     *
     * @param int $id
     * @return bool
     */
    public function softDelete(int $id): bool
    {
        return (bool) $this->db->rawQuery(
            'UPDATE orders SET status = ? WHERE id = ?',
            ['Cancelled', $id]
        );
    }
}
