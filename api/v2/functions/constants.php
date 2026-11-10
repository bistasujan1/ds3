<?php

declare(strict_types=1);

define('REUNITE_CONFIG_PATH', __DIR__ . '/../config/lib_config.php');

// Token lifetime in minutes (2 days default)
if (!defined('LOGIN_HOURS')) {
    define('LOGIN_HOURS', (int) ($_SERVER['LOGIN_HOURS'] ?? 2880));
}

// DS3 admin portal role names — match role.name values in the `role` table
define('ADMIN_ROLES', ['admin', 'manager', 'sales', 'compliance', 'warehouse', 'purchasing', 'finance']);

// DS3 client portal role names
define('CLIENT_ROLES', ['client_admin', 'client_user']);