<?php
/**
 * Master authenticated layout (Bootstrap 5).
 *
 * Page file must:
 *   1. Set $page_title (string) and $active_tab (string)
 *   2. Optionally set $extra_styles / $extra_scripts (arrays)
 *   3. Optionally set $inline_js (string — raw JS without <script> tags)
 *   4. Buffer content: ob_start() … $content = ob_get_clean()
 *   5. Call: include v2_view('shared/layouts/main')
 */
if (!empty($extra_styles)) {
    $portal_config['extra_styles'] = array_merge($portal_config['extra_styles'] ?? [], (array) $extra_styles);
}
if (!empty($extra_scripts)) {
    $portal_config['extra_scripts'] = array_merge($portal_config['extra_scripts'] ?? [], (array) $extra_scripts);
}
?>
<?php include v2_view('shared/partials/head'); ?>
<body class="ds3-portal">
    <?php include v2_view('shared/partials/navbar'); ?>
    <div class="shell">
        <?php include v2_view('shared/partials/sidebar'); ?>
        <main class="content">
            <?php echo $content ?? ''; ?>
        </main>
    </div>
    <?php include v2_view('shared/components/modals'); ?>
<?php include v2_view('shared/partials/footer_scripts'); ?>
<?php if (!empty($inline_js)): ?>
<script>
<?php echo $inline_js; ?>
</script>
<?php endif; ?>
<?php include v2_view('shared/partials/footer'); ?>
