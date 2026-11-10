<?php

declare(strict_types=1);

/**
 * Admin portal frontend bootstrap.
 *
 * Loaded at the top of every admin page.
 * Defines path helpers and loads portal config used by the shared layout.
 */

// V2_PATH: absolute path to the v2/ directory — used by v2_view() and helpers
if (!defined('V2_PATH')) {
    define('V2_PATH', dirname(__DIR__, 2));
}

if (!defined('LOGIN_HOURS')) {
    define('LOGIN_HOURS', 2880);
}

// Path helper: resolve a path relative to v2/
if (!function_exists('v2_view')) {
    function v2_view(string $path): string
    {
        $file = V2_PATH . '/' . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR) . '.php';
        if (!file_exists($file)) {
            throw new RuntimeException("v2 view not found: {$file}");
        }
        return $file;
    }
}

// Load shared helper functions (asset(), portal_url(), portal_config(), datatable_server_side())
require_once V2_PATH . '/shared/utils/helpers.php';

// DS3 has no v1/v2 module versioning — module_url() simply returns the portal URL
if (!function_exists('module_url')) {
    function module_url(string $moduleKey, string $fallbackUrl = ''): string
    {
        return $fallbackUrl;
    }
}

// Portal config consumed by v2/shared/layouts/main.php
$portal_config = require __DIR__ . '/config.php';
$sidebar_menu  = require __DIR__ . '/sidebar_menu.php';

// These vars are used in partials/head.php and layouts/main.php
$portal_name        = $portal_config['portal_name'];
$portal_title       = $portal_config['portal_title'];
$portal_title_short = $portal_config['portal_title_short'];
$api_url            = $portal_config['api_url'];
$login_url          = $portal_config['login_url'];
$dashboard_url      = $portal_config['dashboard_url'];

// Defaults overridden per-page
$page_title    = $page_title   ?? $portal_config['portal_title'];
$active_tab    = $active_tab   ?? '';
$extra_styles  = $extra_styles  ?? $portal_config['extra_styles'];
$extra_scripts = $extra_scripts ?? [];
$inline_js     = $inline_js    ?? '';
