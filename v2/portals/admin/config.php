<?php

declare(strict_types=1);

return [
    'portal_name'        => 'admin',
    'portal_title'       => 'DS3 Admin',
    'portal_title_short' => 'DS3',
    'base_url'           => '/v2/portals/admin',
    'api_url'            => '/api/v2/',
    'login_url'          => '/v2/portals/admin/pages/login.php',
    'dashboard_url'      => '/v2/portals/admin/pages/dashboard/index.php',
    'nav_class'          => 'ds3-navbar',
    'nav_menu'           => [],
    'skins'              => ['/v2/portals/admin/assets/css/admin-skin.css'],
    'extra_styles'       => [],
    'extra_scripts'      => ['/v2/portals/admin/assets/js/admin/admin.js'],
    'auth_scripts'       => ['/v2/portals/admin/assets/js/admin/admin-auth.js'],
];
