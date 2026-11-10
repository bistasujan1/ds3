<?php

declare(strict_types=1);

namespace Models;

/**
 * Repository for the quotes and quote_lines tables.
 */
class QuoteModel extends BaseModel
{
    protected string $table = 'quotes';

    /**
     * All quotes with client name and line count, newest first.
     *
     * @return array
     */
    public function findAll(): array
    {
        return $this->db->rawQuery(
            'SELECT q.id, q.ref, q.status, q.valid_until, q.ship_by, q.terms, q.created_at,
                    c.name AS client_name,
                    COUNT(ql.id) AS line_count
             FROM quotes q
             JOIN clients c ON c.id = q.client_id
             LEFT JOIN quote_lines ql ON ql.quote_id = q.id
             WHERE q.status NOT IN (?, ?)
             GROUP BY q.id
             ORDER BY q.id DESC',
            ['Declined', 'Expired']
        ) ?: [];
    }

    /**
     * Single quote with client info.
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id, string $columns = '*'): ?array
    {
        $rows = $this->db->rawQuery(
            'SELECT q.*, c.name AS client_name, c.email AS client_email
             FROM quotes q
             JOIN clients c ON c.id = q.client_id
             WHERE q.id = ? LIMIT 1',
            [$id]
        );
        return !empty($rows) ? $rows[0] : null;
    }

    /**
     * Lines for a quote with product info.
     *
     * @param int $quoteId
     * @return array
     */
    public function findLines(int $quoteId): array
    {
        return $this->db->rawQuery(
            'SELECT ql.id, ql.qty_g, ql.price_g,
                    ql.qty_g * ql.price_g AS line_total,
                    p.id AS product_id, p.code AS product_code, p.name AS product_name, p.schedule
             FROM quote_lines ql
             JOIN products p ON p.id = ql.product_id
             WHERE ql.quote_id = ?
             ORDER BY ql.id ASC',
            [$quoteId]
        ) ?: [];
    }

    /**
     * Generate the next sequential quote reference number for today.
     *
     * @return string  e.g. QTE-20261109-0001
     */
    public function generateRef(): string
    {
        $date   = date('Ymd');
        $result = $this->db->rawQuery(
            "SELECT ref FROM quotes WHERE ref LIKE ? ORDER BY id DESC LIMIT 1",
            ["QTE-{$date}-%"]
        );
        $seq = empty($result) ? 1 : ((int) substr($result[0]['ref'], -4)) + 1;
        return sprintf('QTE-%s-%04d', $date, $seq);
    }

    /**
     * Insert a quote line.
     *
     * @param int   $quoteId
     * @param int   $productId
     * @param float $qtyG
     * @param float $priceG
     * @return int  Inserted ID
     */
    public function insertLine(int $quoteId, int $productId, float $qtyG, float $priceG): int
    {
        return (int) $this->db->insert('quote_lines', [
            'quote_id'   => $quoteId,
            'product_id' => $productId,
            'qty_g'      => $qtyG,
            'price_g'    => $priceG,
        ]);
    }

    /**
     * Delete all lines for a quote (used on update).
     *
     * @param int $quoteId
     * @return void
     */
    public function deleteLines(int $quoteId): void
    {
        $this->db->rawQuery('DELETE FROM quote_lines WHERE quote_id = ?', [$quoteId]);
    }

    /**
     * Soft-delete a quote by transitioning status to Declined.
     *
     * @param int $id
     * @return bool
     */
    public function softDelete(int $id): bool
    {
        return (bool) $this->db->rawQuery(
            'UPDATE quotes SET status = ? WHERE id = ?',
            ['Declined', $id]
        );
    }
}
