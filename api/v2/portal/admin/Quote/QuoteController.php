<?php

declare(strict_types=1);

namespace Portal\Admin\Quote;

use Models\QuoteModel;
use Reunite\Framework\Cloud;

/**
 * CRUD endpoints for price quotes.
 */
class QuoteController extends \Portal\Admin\BaseController
{
    private QuoteModel $quoteModel;

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->quoteModel = new QuoteModel();
    }

    /**
     * GET /admin/quotes — list all quotes with client name and line count.
     *
     * @return void
     */
    public function index(): void
    {
        $quotes = $this->quoteModel->findAll();
        response(['code' => 200, 'data' => $quotes]);
    }

    /**
     * GET /admin/quotes/{id} — single quote with lines.
     *
     * @return void
     */
    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            response(['code' => 400, 'message' => 'Invalid quote ID']);
            return;
        }
        $quote = $this->quoteModel->findById($id);
        if (!$quote) {
            response(['code' => 404, 'message' => 'Quote not found']);
            return;
        }
        $quote['lines'] = $this->quoteModel->findLines($id);
        response(['code' => 200, 'data' => $quote]);
    }

    /**
     * POST /admin/quotes — create quote with line items.
     *
     * @return void
     */
    public function store(): void
    {
        $data = request_post();
        $this->validate($data, [
            'client_id'  => 'required|numeric',
            'valid_until' => 'required',
            'lines'      => 'required',
        ]);

        $lines = $data['lines'] ?? [];
        if (!is_array($lines) || count($lines) === 0) {
            response(['code' => 400, 'message' => 'At least one line item is required']);
            return;
        }

        try {
            db()->startTransaction();

            $quoteId = $this->quoteModel->insert([
                'ref'        => $this->quoteModel->generateRef(),
                'client_id'  => (int) $data['client_id'],
                'status'     => 'Draft',
                'valid_until' => $data['valid_until'],
                'ship_by'    => $data['ship_by'] ?? null,
                'terms'      => $data['terms'] ?? null,
                'notes'      => $data['notes'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            foreach ($lines as $line) {
                $this->quoteModel->insertLine(
                    $quoteId,
                    (int) $line['product_id'],
                    (float) $line['qty_g'],
                    (float) $line['price_g']
                );
            }

            db()->commit();
        } catch (\Exception $e) {
            db()->rollback();
            response(['code' => 500, 'message' => 'Failed to create quote']);
            return;
        }

        response(['code' => 201, 'data' => ['id' => $quoteId]]);
    }

    /**
     * PUT /admin/quotes/{id} — update quote header + replace lines.
     *
     * @return void
     */
    public function update(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            response(['code' => 400, 'message' => 'Invalid quote ID']);
            return;
        }

        $data = request_post();
        $this->validate($data, [
            'client_id'   => 'required|numeric',
            'valid_until' => 'required',
            'lines'       => 'required',
        ]);

        $lines = $data['lines'] ?? [];
        if (!is_array($lines) || count($lines) === 0) {
            response(['code' => 400, 'message' => 'At least one line item is required']);
            return;
        }

        try {
            db()->startTransaction();

            $this->quoteModel->updateById($id, [
                'client_id'  => (int) $data['client_id'],
                'valid_until' => $data['valid_until'],
                'ship_by'    => $data['ship_by'] ?? null,
                'terms'      => $data['terms'] ?? null,
                'notes'      => $data['notes'] ?? null,
                'status'     => $data['status'] ?? 'Draft',
            ]);

            $this->quoteModel->deleteLines($id);
            foreach ($lines as $line) {
                $this->quoteModel->insertLine(
                    $id,
                    (int) $line['product_id'],
                    (float) $line['qty_g'],
                    (float) $line['price_g']
                );
            }

            db()->commit();
        } catch (\Exception $e) {
            db()->rollback();
            response(['code' => 500, 'message' => 'Failed to update quote']);
            return;
        }

        response(['code' => 200, 'message' => 'Quote updated']);
    }

    /**
     * DELETE /admin/quotes/{id} — soft-delete a quote.
     *
     * @return void
     */
    public function destroy(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            response(['code' => 400, 'message' => 'Invalid quote ID']);
            return;
        }

        $this->quoteModel->softDelete($id);
        response(['code' => 200, 'message' => 'Quote deleted']);
    }
}
