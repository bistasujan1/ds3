<?php

declare(strict_types=1);

namespace Utils;

use Reunite\Framework\Cloud;
use Reunite\Cache;

/**
 * Loads runtime application configuration from the shared `configuration`
 * DB table (option_name/option_value rows) into the APCu-backed Reunite\Cache
 * layer, readable anywhere via the config()/set_config() helpers (see
 * api/v2/functions/global.php). v2-native — reuses the framework's own DB
 * instance, with zero dependency on v1's defined-variable.php.
 */
class AppConfig
{
    /**
     * Loads all `configuration` table rows into Cache unless already cached
     * (or when $reset forces a refresh), then defines SITE_URL from the
     * cached `root_path` value. Cached rows expire after $ttl seconds so
     * config changes propagate without an app restart; 0 (default) never
     * expires.
     *
     * v1 defines SITE_URL in configuration.php; v2 loads config.php only —
     * this mirrors that alias for email templates.
     *
     * @param bool $reset When true, reload config from DB even if cache flag is set
     * @param int  $ttl   Cache TTL in seconds (default 0 = never expires)
     */
    public static function load(bool $reset = false, int $ttl = 0): void
    {
        if (Cache::get('config_set_status') == false || $reset == true) {
            $configs = Cloud::db()->get('configuration', null, 'option_name,option_value,note');
            foreach ($configs as $config) {
                Cache::set($config['option_name'], $config['option_value'], $ttl);
            }
            Cache::set('config_set_status', true, $ttl);
        }

        if (!defined('SITE_URL')) {
            $rootPath = Cache::get('root_path');
            if ($rootPath) {
                define('SITE_URL', $rootPath);
            }
        }
    }
}
