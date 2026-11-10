<?php

declare(strict_types=1);

/**
 * Resolve an absolute path to a v2 view file.
 * Usage: include v2_view('shared/layouts/main');
 */
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

/**
 * Return a versioned URL to a root-level asset.
 * Usage: asset('/assets/css/main.css') → '/assets/css/main.css?v=1234567890'
 */
function asset(string $path): string
{
    $path = trim($path);
    if ($path === '' || $path[0] !== '/') {
        return $path;
    }

    $url_path = parse_url($path, PHP_URL_PATH);
    if (!is_string($url_path) || $url_path === '') {
        return $path;
    }

    $normalized_path = str_replace('\\', '/', $url_path);
    if (preg_match('#(^|/)\.\.(?:/|$)#', $normalized_path) === 1) {
        return $path;
    }

    $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $root_realpath = realpath($document_root);
    if ($root_realpath === false || !is_dir($root_realpath)) {
        return $path;
    }

    $candidate = $root_realpath . DIRECTORY_SEPARATOR . ltrim(
        str_replace('/', DIRECTORY_SEPARATOR, $normalized_path),
        DIRECTORY_SEPARATOR
    );

    $resolved = realpath($candidate);
    $version  = 0;
    if ($resolved !== false && is_file($resolved)) {
        $root_prefix = rtrim($root_realpath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strncmp($resolved, $root_prefix, strlen($root_prefix)) === 0) {
            $version = filemtime($resolved) ?: 0;
        }
    }

    if ($version === 0) {
        return $path;
    }

    return $path . (str_contains($path, '?') ? "&v={$version}" : "?v={$version}");
}

/**
 * Build a URL within the current portal.
 * Usage: portal_url('voucher/allocate') → '/v2/admin/voucher/allocate'
 */
function portal_url(string $page = ''): string
{
    global $portal_config;
    $base = rtrim($portal_config['base_url'] ?? '/v2/', '/');
    return $page ? $base . '/' . ltrim($page, '/') : $base . '/';
}

/**
 * Read a key from the active portal config with an optional default.
 */
function portal_config(string $key, mixed $default = null): mixed
{
    global $portal_config;
    return $portal_config[$key] ?? $default;
}

/**
 * Whether a DataTable instance should use server-side pagination.
 *
 * Reads api/v2/config/datatables.php, keyed by '{portal}.{module}.{table_id}'.
 * Unknown keys default to false (client-side, DataTables paginates in the browser).
 * Mirrors api/v2/functions/global.php's datatable_server_side() for use in
 * server-rendered v2 pages.
 *
 * @param string $key DataTable config key, e.g. 'admin.users.active'
 * @return bool
 */
function datatable_server_side(string $key): bool
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(V2_PATH) . '/api/v2/config/datatables.php';
    }
    return $config[$key] ?? false;
}
