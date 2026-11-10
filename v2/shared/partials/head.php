<!doctype html>
<html lang="en">
<head>
    <title><?php echo htmlspecialchars($page_title ?? portal_config('portal_title', 'DS3'), ENT_QUOTES); ?> | DS3</title>
    <script>
        var BASEURL     = '<?php echo portal_config('base_url', '/v2/'); ?>';
        var ASSETS_PATH = '/';
        var APIURL      = '<?php echo portal_config('api_url', '/api/v2/'); ?>';
        var PORTAL      = '<?php echo portal_config('portal_name', ''); ?>';
        var PORTAL_API_SCOPE = '<?php echo portal_config('api_scope', portal_config('portal_name', '')); ?>';
        var LOGIN_HOURS = '<?php echo defined('LOGIN_HOURS') ? LOGIN_HOURS : 2880; ?>';
    </script>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- DataTables + Bootstrap 5 skin -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <!-- Parsley -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/parsley.js/2.9.2/parsley.css">
    <!-- Date Range Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Portal shared CSS -->
    <link rel="stylesheet" href="<?php echo asset('/v2/shared/assets/css/portal.css'); ?>">
    <!-- Portal skin CSS -->
    <?php foreach (portal_config('skins', []) as $skin): ?>
    <link rel="stylesheet" href="<?php echo asset($skin); ?>">
    <?php endforeach; ?>
    <!-- Per-page extra CSS -->
    <?php foreach (portal_config('extra_styles', []) as $style): ?>
    <link rel="stylesheet" href="<?php echo asset($style); ?>">
    <?php endforeach; ?>
    <!-- jQuery (before portal-auth) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="<?php echo asset('/v2/shared/assets/js/portal-auth.js'); ?>"></script>
    <script src="<?php echo asset('/v2/shared/assets/js/portal-utils.js'); ?>"></script>
    <script src="<?php echo asset('/v2/shared/assets/js/portal-api.js'); ?>"></script>
</head>
