<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

$page_title = 'Dashboard';
$active_tab = 'dashboard';

ob_start();
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div><h1 class="h4 mb-0">Dashboard</h1><div class="muted small"><?php echo date('l, F j Y'); ?></div></div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="panel p-3">
            <div class="muted small mb-1">Products</div>
            <div class="fs-4 fw-semibold num" id="stat-products">—</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="panel p-3">
            <div class="muted small mb-1">Open Orders</div>
            <div class="fs-4 fw-semibold num" id="stat-open-orders">—</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="panel p-3">
            <div class="muted small mb-1">Outstanding Invoices</div>
            <div class="fs-4 fw-semibold num" id="stat-invoices">—</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="panel p-3">
            <div class="muted small mb-1">Low Stock Alerts</div>
            <div class="fs-4 fw-semibold num" id="stat-low-stock">—</div>
        </div>
    </div>
</div>

<!-- Recent activity placeholder -->
<div class="panel">
    <div class="sec">
        <h2>Recent Sales Orders</h2>
        <p class="muted small mb-0">Sales order data will appear here once the module is built.</p>
    </div>
</div>

<?php
$content = ob_get_clean();
include v2_view('shared/layouts/main');
