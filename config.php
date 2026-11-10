<?php

require_once __DIR__ . '/constant.php';

define('ENVIRONMENT', $_SERVER['ENVIRONMENT'] ?? 'local');

// Primary database (shared by all DS3 scopes, isolated by DB user grants)
define('DB_HOST_NAME', $_SERVER['DB_HOST_NAME'] ?? 'localhost');
define('DB_NAME',      $_SERVER['DB_NAME']      ?? 'ds3');

// Admin portal DB user (full read/write)
define('ADMIN_DB_USER_NAME', $_SERVER['ADMIN_DB_USER_NAME'] ?? 'root');
define('ADMIN_DB_PASSWORD',  $_SERVER['ADMIN_DB_PASSWORD']  ?? '');

// Default DB user (token verification, public/cron routes)
define('DB_USER_NAME', $_SERVER['DB_USER_NAME'] ?? 'root');
define('DB_PASSWORD',  $_SERVER['DB_PASSWORD']  ?? '');

// Audit database (separate DB for immutable audit log)
define('AUDIT_DB_HOST_NAME', $_SERVER['AUDIT_DB_HOST_NAME'] ?? 'localhost');
define('AUDIT_DB_USER_NAME', $_SERVER['AUDIT_DB_USER_NAME'] ?? 'root');
define('AUDIT_DB_PASSWORD',  $_SERVER['AUDIT_DB_PASSWORD']  ?? '');
define('AUDIT_DB_NAME',      $_SERVER['AUDIT_DB_NAME']      ?? 'ds3_audit');

// RFC 5424 log severity: 0=emergency .. 7=debug. Default 7 (log all) in dev.
define('LOG_LEVEL', (int) ($_SERVER['LOG_LEVEL'] ?? 7));

// Structured log directory. Override with LOG_DIR env var.
define('LOG_DIR', $_SERVER['LOG_DIR'] ?? __DIR__ . '/storage/logs');

define('DB_SSL', filter_var($_SERVER['DB_SSL'] ?? false, FILTER_VALIDATE_BOOLEAN));

date_default_timezone_set('America/Chicago');
define('DEFAULT_TIMEZONE', 'America/Chicago');

ini_set('session.gc_maxlifetime', 3600);
session_set_cookie_params(3600);
