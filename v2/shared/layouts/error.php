<!doctype html>
<html lang="en">
<head>
    <title><?php echo htmlspecialchars($error_title ?? 'Error', ENT_QUOTES); ?> — ReUnite RX</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo asset('/assets/css/main.css'); ?>">
    <link rel="icon" type="image/png" sizes="96x96" href="/assets/img/favicon.png">
</head>
<body>
    <div class="container" style="margin-top:80px; text-align:center;">
        <h1><?php echo (int) ($error_code ?? 404); ?></h1>
        <h3><?php echo htmlspecialchars($error_title ?? 'Page Not Found', ENT_QUOTES); ?></h3>
        <p><?php echo htmlspecialchars($error_message ?? 'The page you are looking for does not exist.', ENT_QUOTES); ?></p>
        <a href="javascript:history.back()" class="btn btn-default">Go Back</a>
    </div>
</body>
</html>
