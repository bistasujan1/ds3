<?php

declare(strict_types=1);

/**
 * Login keys and routing metadata for DS3.
 *
 * Key: the `portal` field POSTed on login.
 * db_scope: key in Database::$dbConfigs for the primary connection.
 * portal_folder: subdirectory under api/v2/portal/.
 * role_portal: matches the `role.portal` column for staff login.
 */
return [
    'admin' => [
        'db_scope'      => 'admin',
        'portal_folder' => 'admin',
        'role_portal'   => 'Admin',
    ],
    'default' => [
        'db_scope'      => 'default',
        'portal_folder' => 'admin',
        'role_portal'   => '',
    ],
];
