<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

$page_title    = 'Clients';
$active_tab    = 'clients';
$extra_scripts = ['/v2/portals/admin/assets/js/outgoing/clients.js'];

ob_start();
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div><h1 class="h4 mb-0">Clients</h1><div class="muted small">Compounding pharmacy accounts</div></div>
    <div class="ms-auto">
        <button class="btn btn-assay btn-sm" id="btnAddClient">
            <i class="fas fa-plus me-1"></i> New Client
        </button>
    </div>
</div>

<div class="panel">
    <table id="clientsTable" class="table table-hover mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>License #</th>
                    <th>License Expiry</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
        </table>
</div>

<!-- Create / Edit Modal -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clientModalLabel">New Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="clientForm" novalidate>
                    <input type="hidden" id="clientId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="cContact">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="cEmail" required
                                   data-parsley-type="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cPhone" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Billing Address</label>
                            <textarea class="form-control" id="cBillingAddress" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">License Number</label>
                            <input type="text" class="form-control" id="cLicenseNumber">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">License Expiry</label>
                            <input type="date" class="form-control" id="cLicenseExpiry">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">DEA Number</label>
                            <input type="text" class="form-control" id="cDeaNumber">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Credit Limit ($)</label>
                            <input type="number" class="form-control" id="cCreditLimit" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Terms (days)</label>
                            <input type="number" class="form-control" id="cTerms" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="cStatus">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Controlled Approved</label>
                            <select class="form-select" id="cControlledApproved">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Credit Hold</label>
                            <select class="form-select" id="cCreditHold">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-assay" id="clientSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include v2_view('shared/layouts/main');
