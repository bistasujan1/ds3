<?php

// Route manifest — register Router.php files here as features are built.
// Each entry: ['prefix' => '', 'path' => __DIR__ . '/../portal/{portal}/{Feature}/Router.php']
return [
    ['prefix' => '', 'path' => __DIR__ . '/../portal/admin/App/Router.php'],
    ['prefix' => '', 'path' => __DIR__ . '/../portal/admin/Client/Router.php'],
    ['prefix' => '', 'path' => __DIR__ . '/../portal/admin/Product/Router.php'],
    ['prefix' => '', 'path' => __DIR__ . '/../portal/admin/Quote/Router.php'],
    ['prefix' => '', 'path' => __DIR__ . '/../portal/admin/Order/Router.php'],
    ['prefix' => '', 'path' => __DIR__ . '/../portal/admin/Invoice/Router.php'],
];
