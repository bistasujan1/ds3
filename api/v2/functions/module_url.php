<?php

declare(strict_types=1);

/**
 * v2 sidebar link resolver.
 *
 * v2's own implementation of the v1 module_url() family (see the root
 * module_url_helper.php for the v1 version). Reuses the already-initialized
 * framework DB instance (db()) and the shared Reunite\Cache package (via the
 * config()/set_config() helpers) instead of opening a separate connection or
 * duplicating the cache lookup.
 *
 * Global (non-namespaced) functions so v2's plain-PHP sidebar partial
 * (v2/shared/partials/sidebar.php) can call module_url() directly.
 */

if (!function_exists('module_url')) {
    /**
     * Resolves the currently "live" URL for a sidebar module.
     *
     * @param string $moduleKey   Unique module_versions.module_key, e.g. 'admin.voucher'
     * @param string $fallbackUrl URL to use if no row exists for this key (keeps
     *                            today's behavior when a module hasn't been seeded yet)
     * @return string
     */
    function module_url(string $moduleKey, string $fallbackUrl = ''): string
    {
        if ($moduleKey === '') {
            return $fallbackUrl;
        }

        $row = module_versions_all()[$moduleKey] ?? null;
        if (!$row) {
            return $fallbackUrl;
        }

        if ($row['version'] === 'v2') {
            return $row['v2_url'] ?: $fallbackUrl;
        }

        return $row['v1_url'] ?: $fallbackUrl;
    }
}

if (!function_exists('module_versions_all')) {
    /**
     * Returns every module_versions row keyed by module_key, cached for 60s
     * via the shared Reunite\Cache-backed config()/set_config() helpers.
     *
     * @return array<string, array<string, mixed>>
     */
    function module_versions_all(): array
    {
        $cached = config('module_versions.all');
        if ($cached !== null) {
            return $cached;
        }

        $rows = module_versions_fetch_from_db();
        set_config('module_versions.all', $rows, 60);

        return $rows;
    }
}

if (!function_exists('module_versions_fetch_from_db')) {
    /**
     * Reads the module_versions table via the active framework DB instance,
     * keyed by module_key.
     *
     * @return array<string, array<string, mixed>>
     */
    function module_versions_fetch_from_db(): array
    {
        try {
            $result = db()->get('module_versions') ?: [];
        } catch (\Throwable $e) {
            \Reunite\Logger::error('Failed to load module_versions table', ['error' => $e->getMessage()]);
            return [];
        }

        $keyed = [];
        foreach ($result as $row) {
            $keyed[$row['module_key']] = $row;
        }

        return $keyed;
    }
}
