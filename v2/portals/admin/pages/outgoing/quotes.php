<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

$page_title    = 'Quotes';
$active_tab    = 'quotes';
$extra_scripts = ['/v2/portals/admin/assets/js/outgoing/quotes.js'];

ob_start();
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div><h1 class="h4 mb-0">Quotes</h1><div class="muted small">Outgoing price quotes</div></div>
    <div class="ms-auto">
        <button class="btn btn-assay btn-sm" id="btnAddQuote">
            <i class="fas fa-plus me-1"></i> New Quote
        </button>
    </div>
</div>

<div class="panel">
    <table id="quotesTable" class="table table-hover mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>Ref</th>
                    <th>Client</th>
                    <th>Lines</th>
                    <th>Status</th>
                    <th>Valid Until</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
        </table>
</div>

<!-- Create / Edit Modal -->
<div class="modal fade" id="quoteModal" tabindex="-1" aria-labelledby="quoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quoteModalLabel">New Quote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quoteForm" novalidate>
                    <input type="hidden" id="quoteId">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Client <span class="text-danger">*</span></label>
                            <select class="form-select" id="qClientId" required
                                    data-parsley-errors-container="#qClientIdErrors"></select>
                            <span id="qClientIdErrors"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valid Until <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="qValidUntil" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ship By</label>
                            <input type="date" class="form-control" id="qShipBy">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Terms</label>
                            <input type="text" class="form-control" id="qTerms" placeholder="e.g. Net 30">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="qStatus">
                                <option value="Draft">Draft</option>
                                <option value="Sent">Sent</option>
                                <option value="Accepted">Accepted</option>
                                <option value="Declined">Declined</option>
                                <option value="Expired">Expired</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="qNotes" rows="2"></textarea>
                        </div>
                    </div>

                    <!-- Line items -->
                    <h6 class="fw-semibold mb-2">Line Items</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="quoteLines">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40%">Product</th>
                                    <th style="width:20%">Qty (g)</th>
                                    <th style="width:20%">Price/g ($)</th>
                                    <th style="width:15%">Total ($)</th>
                                    <th style="width:5%"></th>
                                </tr>
                            </thead>
                            <tbody id="quoteLinesBody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="addLineBtn">
                        <i class="fas fa-plus me-1"></i> Add Line
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-assay" id="quoteSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Line template (hidden) -->
<template id="lineRowTemplate">
    <tr class="line-row">
        <td>
            <select class="form-select form-select-sm line-product" required
                    data-parsley-errors-container=".lp-err"></select>
            <span class="lp-err"></span>
        </td>
        <td><input type="number" class="form-control form-control-sm line-qty" min="0.001" step="0.001" required></td>
        <td><input type="number" class="form-control form-control-sm line-price" min="0" step="0.000001" required></td>
        <td class="line-total text-end fw-semibold">—</td>
        <td class="text-center">
            <button type="button" class="btn btn-link text-danger p-0 btn-remove-line">
                <i class="fas fa-times"></i>
            </button>
        </td>
    </tr>
</template>

<?php
$content = ob_get_clean();
include v2_view('shared/layouts/main');
