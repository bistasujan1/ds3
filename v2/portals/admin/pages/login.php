<?php
declare(strict_types=1);

$page_title = 'DS3 Admin Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        var BASEURL = '/v2/portals/admin';
        var APIURL  = '/api/v2/';
        var PORTAL  = 'admin';
        var PORTAL_API_SCOPE = 'admin';
        var LOGIN_HOURS = '2880';
    </script>
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Toastr -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a2035 0%, #2d3561 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-logo strong {
            color: #1a2035;
            font-size: 1.8rem;
            display: block;
            font-weight: 700;
        }
    </style>
</head>
<body>
<div class="login-card card p-4 mx-3">
    <div class="text-center mb-4 py-2">
        <div class="login-logo">
            <strong>DS3</strong>
        </div>
        <p class="text-muted mt-2 mb-0 small">Sign in to your account</p>
    </div>
    <form id="loginForm" data-parsley-validate>
        <div id="loginAlert" class="alert alert-danger d-none" role="alert"></div>
        <div class="mb-3">
            <label for="email" class="form-label fw-semibold">Email address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                <input type="email" class="form-control" id="email" placeholder="admin@ds3.local"
                       required data-parsley-type="email" autofocus>
            </div>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label fw-semibold">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                <input type="password" class="form-control" id="password" placeholder="••••••••" required>
            </div>
        </div>
        <button type="button" id="loginBtn" class="btn btn-primary w-100 py-2">
            <i class="fas fa-sign-in-alt me-2"></i> Sign In
        </button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/parsley.js/2.9.2/parsley.min.js"></script>
<script src="/v2/shared/assets/js/portal-auth.js"></script>
<script src="/v2/portals/admin/assets/js/admin/admin-auth.js"></script>
</body>
</html>
