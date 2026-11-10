<?php
/**
 * Standalone print layout — no navbar, sidebar, or footer chrome, so the browser's
 * print output holds only the document itself. Mirrors the structure of v1's
 * standalone print pages ({portal}/print_prescriptions.php).
 *
 * Only jQuery + PortalAuth + PortalApi are loaded (no DataTables/select2/toastr
 * stack): these pages fetch one server-rendered HTML document and inject it.
 *
 * Page file:
 *   1. Sets $page_title (string)
 *   2. Optionally sets $extra_styles / $extra_scripts (arrays)
 *   3. Buffers content: ob_start() … $content = ob_get_clean()
 *   4. Calls: include v2_view('shared/layouts/print')
 */
?>
<!doctype html>
<html lang="en">
<head>
    <title><?php echo htmlspecialchars($page_title ?? portal_config('portal_title', 'ReUnite Rx'), ENT_QUOTES); ?></title>
    <script>
        var BASEURL = '<?php echo portal_config('base_url', '/v2/'); ?>';
        var APIURL  = '<?php echo portal_config('api_url', '/api/v2/'); ?>';
        var PORTAL  = '<?php echo portal_config('portal_name', ''); ?>';
        var PORTAL_API_SCOPE = '<?php echo portal_config('api_scope', portal_config('portal_name', '')); ?>';
    </script>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge, chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="<?php echo asset('/assets/css/main.css'); ?>">
    <?php foreach ((array) ($extra_styles ?? []) as $style) : ?>
    <link rel="stylesheet" href="<?php echo asset($style); ?>">
    <?php endforeach; ?>
    <link rel="icon" type="image/png" sizes="96x96" href="/assets/img/favicon.png">
    <script src="/assets/vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo asset('/v2/shared/assets/js/portal-auth.js?rev=20260908-auth-loop-fix'); ?>"></script>
    <script src="<?php echo asset('/v2/shared/assets/js/portal-api.js'); ?>"></script>
</head>
<body>
<?php echo $content ?? ''; ?>
<?php foreach ((array) ($extra_scripts ?? []) as $script) : ?>
<script src="<?php echo asset($script); ?>"></script>
<?php endforeach; ?>
</body>
</html>
