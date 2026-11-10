<?php
/**
 * Top-navbar variant of shared/layouts/main.php — for portals whose v1
 * counterpart has no sidebar (currently: clinic). Identical to main.php
 * except it renders shared/partials/navbar-topnav instead of the
 * navbar+sidebar pair, and uses the 'layout-topnav' body class (main.css
 * already defines this — it's the same class v1's clinic pages use).
 *
 * Usage is identical to main.php: set $page_title/$active_tab, buffer
 * $content, then `include v2_view('shared/layouts/main-topnav')`.
 */
if (!empty($extra_styles)) {
    $portal_config['extra_styles'] = array_merge($portal_config['extra_styles'] ?? [], (array) $extra_styles);
}
if (!empty($extra_scripts)) {
    $portal_config['extra_scripts'] = array_merge($portal_config['extra_scripts'] ?? [], (array) $extra_scripts);
}
?>
<?php include v2_view('shared/partials/head'); ?>
<body class="layout-topnav">
    <div id="first_load"></div>
    <div id="modal_load"></div>
    <!-- WRAPPER -->
    <div id="wrapper">
        <!-- TOP NAVBAR (no sidebar — matches v1 clinic's navbar_top.php) -->
        <?php include v2_view('shared/partials/navbar-topnav'); ?>
        <!-- END TOP NAVBAR -->
        <!-- MAIN -->
        <div class="main">
            <!-- MAIN CONTENT -->
            <div class="main-content">
                <?php echo $content ?? ''; ?>
            </div>
            <!-- END MAIN CONTENT -->
        </div>
        <!-- END MAIN -->
        <?php include v2_view('shared/partials/footer_tag'); ?>
    </div>
    <!-- END WRAPPER -->
    <?php include v2_view('shared/components/modals'); ?>
<?php include v2_view('shared/partials/footer_scripts'); ?>
<?php if (!empty($inline_js)): ?>
<script>
<?php echo $inline_js; ?>
</script>
<?php endif; ?>
<?php include v2_view('shared/partials/footer'); ?>
