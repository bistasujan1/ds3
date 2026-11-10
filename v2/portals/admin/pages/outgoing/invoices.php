<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

$page_title    = 'Invoices';
$active_tab    = 'invoices';
$extra_scripts = ['/v2/portals/admin/assets/js/outgoing/invoices.js'];

ob_start();
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div><h1 class="h4 mb-0">Invoices</h1><div class="muted small">Client billing &amp; payments</div></div>
</div>

<div class="panel">
    <table id="invoicesTable" class="table table-hover mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>Invoice #</th>
                    <th>Client</th>
                    <th>Lines</th>
                    <th>Subtotal ($)</th>
                    <th>Total ($)</th>
                    <th>Status</th>
                    <th>Due Date</th>
                    <th>Amt Paid ($)</th>
                    <th>Actions</th>
                </tr>
            </thead>
        </table>
</div>

<!-- Status / View Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalLabel">Invoice Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="invoiceModalBody">
                <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="invoiceMarkPaidBtn">Mark as Paid</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include v2_view('shared/layouts/main');
