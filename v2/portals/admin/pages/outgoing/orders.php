<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

$page_title    = 'Sales Orders';
$active_tab    = 'orders';
$extra_scripts = ['/v2/portals/admin/assets/js/outgoing/orders.js'];

ob_start();
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div><h1 class="h4 mb-0">Sales Orders</h1><div class="muted small">Outgoing ingredient orders</div></div>
    <div class="ms-auto">
        <button class="btn btn-assay btn-sm" id="btnAddOrder">
            <i class="fas fa-plus me-1"></i> New Order
        </button>
    </div>
</div>

<!-- Pipeline widget -->
<div class="panel mb-3">
    <div class="pipeline" id="orderPipeline">
        <div class="stage" data-stage="review">
            <div class="count" id="pipeReview">—</div>
            <div class="label">Needs Review</div>
        </div>
        <div class="stage" data-stage="allocated">
            <div class="count" id="pipeAllocated">—</div>
            <div class="label">Allocated</div>
        </div>
        <div class="stage" data-stage="picking">
            <div class="count" id="pipePicking">—</div>
            <div class="label">In Preparation</div>
        </div>
        <div class="stage" data-stage="transit">
            <div class="count" id="pipeTransit">—</div>
            <div class="label">In Transit</div>
        </div>
        <div class="stage" data-stage="delivered">
            <div class="count" id="pipeDelivered">—</div>
            <div class="label">Delivered</div>
        </div>
        <div class="stage" data-stage="attention">
            <div class="count" id="pipeAttention">—</div>
            <div class="label">Needs Attention</div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="panel">
    <table id="ordersTable" class="table table-hover mb-0 w-100">
            <thead class="table-light">
                <tr>
                    <th>Order #</th>
                    <th>Client</th>
                    <th>Product</th>
                    <th class="text-end">Qty (g)</th>
                    <th class="text-end">Value ($)</th>
                    <th>Placed</th>
                    <th>Ship By</th>
                    <th>Status</th>
                    <th>Tracking</th>
                    <th>Actions</th>
                </tr>
            </thead>
        </table>
</div>

<!-- Create / Edit Modal -->
<div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderModalLabel">New Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="orderForm" novalidate>
                    <input type="hidden" id="orderId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Client <span class="text-danger">*</span></label>
                            <select class="form-select" id="oClientId" required
                                    data-parsley-errors-container="#oClientIdErrors"></select>
                            <span id="oClientIdErrors"></span>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Product <span class="text-danger">*</span></label>
                            <select class="form-select" id="oProductId" required
                                    data-parsley-errors-container="#oProductIdErrors"></select>
                            <span id="oProductIdErrors"></span>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quantity (grams) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="oQtyG" required
                                   min="0.001" step="0.001">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Price per gram ($) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="oPriceG" required
                                   min="0" step="0.000001">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ship By <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="oShipBy" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="oStatus">
                                <option value="New">New</option>
                                <option value="Received">Received</option>
                                <option value="Review">Review</option>
                                <option value="Allocated">Allocated</option>
                                <option value="Pick List Ready">Pick List Ready</option>
                                <option value="Pick Approved">Pick Approved</option>
                                <option value="Picked">Picked</option>
                                <option value="Packed">Packed</option>
                                <option value="Shipped">Shipped</option>
                                <option value="In Transit">In Transit</option>
                                <option value="Delivered">Delivered</option>
                                <option value="Closed">Closed</option>
                                <option value="Backorder">Backorder</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Carrier</label>
                            <select class="form-select" id="oCarrier">
                                <option value="">— none —</option>
                                <option value="UPS">UPS</option>
                                <option value="FedEx">FedEx</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tracking #</label>
                            <input type="text" class="form-control" id="oTrackingNumber" placeholder="e.g. 1Z999...">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="oNotes" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-assay" id="orderSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Order Detail Modal -->
<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-labelledby="orderDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderDetailLabel">Order Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderDetailBody">
                <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="orderDetailEditBtn">
                    <i class="fas fa-edit me-1"></i> Edit
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include v2_view('shared/layouts/main');
