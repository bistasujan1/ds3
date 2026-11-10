<?php
/**
 * Auth layout — login, forgot-password, OTP, reset-password.
 * Matches v1 login.php structure exactly (auth-box left/right split).
 *
 * Page file sets:
 *   $page_title      string
 *   $success_msg     string (optional — shown as alert-success)
 *   $content         string (ob_get_clean) — the form HTML only
 *   $extra_scripts   array  (optional — page-specific JS files loaded after auth_scripts)
 *   $inline_js       string (optional — raw JS)
 *   $verify_action   string (optional — action URL for #verify_form)
 *   $portal_role     string (optional — hidden role field value, defaults to portal_name)
 */
$portal_role   = $portal_role   ?? portal_config('portal_name', 'admin');
$verify_action = $verify_action ?? portal_config('verify_url', '');
?>
<!doctype html>
<html lang="en" class="fullscreen-bg">
<head>
    <title><?php echo htmlspecialchars($page_title ?? 'Welcome', ENT_QUOTES); ?></title>
    <script>
        var BASEURL  = '<?php echo portal_config('base_url', '/v2/'); ?>';
        var APIURL   = '<?php echo portal_config('api_url', '/api/v2/'); ?>';
        var PORTAL   = '<?php echo portal_config('portal_name', ''); ?>';
        var PORTAL_API_SCOPE = '<?php echo portal_config('api_scope', portal_config('portal_name', '')); ?>';
    </script>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge, chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <!-- VENDOR CSS — same stack as authenticated pages -->
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="/assets/vendor/themify-icons/css/themify-icons.css">
    <link rel="stylesheet" href="/assets/vendor/pace/themes/orange/pace-theme-minimal.css">
    <link rel="stylesheet" href="/assets/css/vendor/animate/animate.min.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css">
    <link rel="stylesheet" href="/assets/vendor/x-editable/bootstrap3-editable/css/bootstrap-editable.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-tour/css/bootstrap-tour.min.css">
    <link rel="stylesheet" href="/assets/vendor/jqvmap/jqvmap.min.css">
    <!-- MAIN CSS -->
    <link rel="stylesheet" href="<?php echo asset('/assets/css/main.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('/assets/css/login.css'); ?>">
    <?php foreach (portal_config('skins', []) as $skin): ?>
    <link rel="stylesheet" href="<?php echo asset($skin); ?>" type="text/css">
    <?php endforeach; ?>
    <!-- ICONS -->
    <link rel="apple-touch-icon" sizes="76x76" href="/assets/img/apple-icon.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/assets/img/favicon.png">
    <style>
        .auth-box { height: 500px; }
        @media (max-width: 600px) { .auth-box { height: auto; } }
    </style>
</head>
<body>
    <!-- WRAPPER -->
    <div id="wrapper">
        <div class="vertical-align-wrap">
            <div class="vertical-align-middle">
                <div class="auth-box">
                    <div class="left">
                        <div class="content">
                            <div class="header">
                                <div class="logo text-center"></div>
                            </div>
                            <?php if (!empty($success_msg)): ?>
                            <div class="alert alert-success" role="alert" id="login-alert-success">
                                <?php echo htmlspecialchars($success_msg, ENT_QUOTES); ?>
                            </div>
                            <?php endif; ?>
                            <div class="alert alert-danger hide" role="alert" id="login-alert"></div>

                            <?php echo $content ?? ''; ?>
                        </div>
                    </div>
                    <div class="right">
                        <div class="overlay"></div>
                        <div class="content text"></div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- END WRAPPER -->

    <?php if (!empty($verify_action)): ?>
    <form id="verify_form" method="post" action="<?php echo htmlspecialchars($verify_action, ENT_QUOTES); ?>">
        <input type="hidden" id="email" name="email" />
        <input type="hidden" id="hide_email" name="hide_email" />
        <input type="hidden" id="ph" name="ph" />
    </form>
    <?php endif; ?>

    <script src="/assets/vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo asset('/v2/shared/assets/js/portal-auth.js?rev=20260908-auth-loop-fix'); ?>"></script>
    <script src="<?php echo asset('/v2/shared/assets/js/portal-api.js'); ?>"></script>
    <script src="<?php echo asset('/assets/scripts/jquery.validate.min.js'); ?>"></script>
    <script src="<?php echo asset('/assets/scripts/validation-function.js'); ?>"></script>
    <link rel="stylesheet" href="/assets/vendor/parsleyjs/css/parsley.css">
    <script src="/assets/vendor/parsleyjs/js/parsley.min.js"></script>
    <?php foreach (portal_config('auth_scripts', []) as $script): ?>
    <script src="<?php echo asset($script); ?>"></script>
    <?php endforeach; ?>
    <?php foreach (($extra_scripts ?? []) as $script): ?>
    <script src="<?php echo asset((string) $script); ?>"></script>
    <?php endforeach; ?>
    <?php if (!empty($inline_js)): ?>
    <script>
    <?php echo $inline_js; ?>
    </script>
    <?php endif; ?>
    <style type="text/css">
        body {
            font-family: "Varela Round", sans-serif;
            background: #DA145F;
            background: linear-gradient(90deg, #DA145F, #926EC0);
        }
    </style>
</body>
</html>
