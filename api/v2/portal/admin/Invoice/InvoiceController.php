<?php

declare(strict_types=1);

namespace Portal\Admin\Invoice;

use Models\InvoiceModel;
use Reunite\Framework\Cloud;

/**
 * CRUD endpoints for invoices.
 */
class InvoiceController extends \Portal\Admin\BaseController
{
    private InvoiceModel $invoiceModel;

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->invoiceModel = new InvoiceModel();
    }

    /**
     * GET /admin/invoices — list all invoices.
     *
     * @return void
     */
    public function index(): void
    {
        $invoices = $this->invoiceModel->findAll();
        response(['code' => 200, 'data' => $invoices]);
    }

    /**
     * GET /admin/invoices/{id} — single invoice with line items.
     *
     * @return void
     */
    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            response(['code' => 400, 'message' => 'Invalid invoice ID']);
            return;
        }
        $invoice = $this->invoiceModel->findById($id);
        if (!$invoice) {
            response(['code' => 404, 'message' => 'Invoice not found']);
            return;
        }
        $invoice['lines'] = $this->invoiceModel->findLines($id);
        response(['code' => 200, 'data' => $invoice]);
    }

    /**
     * PUT /admin/invoices/{id} — update invoice status / payment.
     *
     * @return void
     */
    public function update(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            response(['code' => 400, 'message' => 'Invalid invoice ID']);
            return;
        }

        $data   = request_post();
        $allowed = ['status', 'due_at', 'amount_paid', 'notes'];
        $update = array_intersect_key($data, array_flip($allowed));

        $this->invoiceModel->updateById($id, $update);
        response(['code' => 200, 'message' => 'Invoice updated']);
    }

    /**
     * DELETE /admin/invoices/{id} — soft-delete an invoice.
     *
     * @return void
     */
    public function destroy(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            response(['code' => 400, 'message' => 'Invalid invoice ID']);
            return;
        }

        $this->invoiceModel->softDelete($id);
        response(['code' => 200, 'message' => 'Invoice deleted']);
    }
}
