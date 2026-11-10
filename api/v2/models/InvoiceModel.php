<?php

declare(strict_types=1);

namespace Models;

/**
 * Repository for the invoices and invoice_lines tables.
 */
class InvoiceModel extends BaseModel
{
    protected string $table = 'invoices';

    /**
     * All invoices with client name and line count, newest first.
     *
     * @return array
     */
    public function findAll(): array
    {
        return $this->db->rawQuery(
            'SELECT inv.id, inv.invoice_number, inv.status, inv.due_at,
                    inv.subtotal, inv.tax, inv.total, inv.amount_paid, inv.created_at,
                    c.name AS client_name,
                    COUNT(il.id) AS line_count
             FROM invoices inv
             JOIN clients c ON c.id = inv.client_id
             LEFT JOIN invoice_lines il ON il.invoice_id = inv.id
             WHERE inv.status != ?
             GROUP BY inv.id
             ORDER BY inv.id DESC',
            ['Void']
        ) ?: [];
    }

    /**
     * Single invoice with client info.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id, string $columns = '*'): ?array
    {
        $rows = $this->db->rawQuery(
            'SELECT inv.*, c.name AS client_name, c.email AS client_email,
                    c.billing_address
             FROM invoices inv
             JOIN clients c ON c.id = inv.client_id
             WHERE inv.id = ? LIMIT 1',
            [$id]
        );
        return !empty($rows) ? $rows[0] : null;
    }

    /**
     * Lines for an invoice with product info.
     *
     * @param int $invoiceId
     * @return array
     */
    public function findLines(int $invoiceId): array
    {
        return $this->db->rawQuery(
            'SELECT il.id, il.qty_g, il.price_g, il.qty_g * il.price_g AS line_total,
                    p.id AS product_id, p.code AS product_code, p.name AS product_name
             FROM invoice_lines il
             JOIN products p ON p.id = il.product_id
             WHERE il.invoice_id = ?
             ORDER BY il.id ASC',
            [$invoiceId]
        ) ?: [];
    }

    /**
     * Generate the next sequential invoice number for today.
     *
     * @return string  e.g. INV-20261109-0001
     */
    public function generateInvoiceNumber(): string
    {
        $date   = date('Ymd');
        $result = $this->db->rawQuery(
            "SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1",
            ["INV-{$date}-%"]
        );
        $seq = empty($result) ? 1 : ((int) substr($result[0]['invoice_number'], -4)) + 1;
        return sprintf('INV-%s-%04d', $date, $seq);
    }

    /**
     * Void an invoice (soft-delete via status transition).
     *
     * @param int $id
     * @return bool
     */
    public function softDelete(int $id): bool
    {
        return (bool) $this->db->rawQuery(
            'UPDATE invoices SET status = ? WHERE id = ?',
            ['Void', $id]
        );
    }
}
