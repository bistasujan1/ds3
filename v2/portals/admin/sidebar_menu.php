<?php

declare(strict_types=1);

/**
 * Admin portal sidebar navigation.
 * Maps to the DS3 module structure.
 * 'tab' values must match $active_tab set on each page.
 */
return [
    [
        'id'    => 'dashboardMenu',
        'icon'  => 'fas fa-tachometer-alt',
        'title' => 'Dashboard',
        'roles' => '',
        'items' => [
            ['label' => 'Overview', 'page' => 'dashboard', 'tab' => 'dashboard'],
        ],
    ],
    [
        'id'    => 'outgoingMenu',
        'icon'  => 'fas fa-paper-plane',
        'title' => 'Outgoing',
        'note'  => 'Selling and shipping',
        'roles' => '',
        'items' => [
            ['label' => 'Orders',             'page' => 'outgoing/orders',    'tab' => 'orders'],
            ['label' => 'Quotes',             'page' => 'outgoing/quotes',    'tab' => 'quotes'],
            ['label' => 'Invoices',           'page' => 'outgoing/invoices',  'tab' => 'invoices'],
            ['label' => 'Clients',            'page' => 'outgoing/clients',   'tab' => 'clients'],
            ['label' => 'Shipping Locations', 'page' => 'outgoing/shiplocs',  'tab' => 'shiplocs'],
            ['label' => 'Pricing Tiers',      'page' => 'outgoing/pricing',   'tab' => 'pricing'],
        ],
    ],
    [
        'id'    => 'incomingMenu',
        'icon'  => 'fas fa-truck-loading',
        'title' => 'Incoming',
        'note'  => 'Buying and receiving',
        'roles' => '',
        'items' => [
            ['label' => 'Purchase Orders', 'page' => 'incoming/purchase-orders', 'tab' => 'purchase_orders'],
            ['label' => 'Vendors',         'page' => 'incoming/vendors',         'tab' => 'vendors'],
        ],
    ],
    [
        'id'    => 'inventoryMenu',
        'icon'  => 'fas fa-cubes',
        'title' => 'Inventory',
        'note'  => 'Stock on hand and movement',
        'roles' => '',
        'items' => [
            ['label' => 'Stock by Lot',   'page' => 'inventory/lots',      'tab' => 'inventory'],
            ['label' => 'Replenishment',  'page' => 'inventory/par',       'tab' => 'par'],
            ['label' => 'Transfers',      'page' => 'inventory/transfers', 'tab' => 'transfers'],
            ['label' => 'Stock Ledger',   'page' => 'inventory/ledger',    'tab' => 'ledger'],
        ],
    ],
    [
        'id'    => 'catalogueMenu',
        'icon'  => 'fas fa-flask',
        'title' => 'Catalogue',
        'note'  => 'What you sell',
        'roles' => '',
        'items' => [
            ['label' => 'Products',   'page' => 'catalogue/products',   'tab' => 'products'],
            ['label' => 'Categories', 'page' => 'catalogue/categories', 'tab' => 'categories'],
        ],
    ],
    [
        'id'    => 'qualityMenu',
        'icon'  => 'fas fa-shield-alt',
        'title' => 'Quality & Compliance',
        'note'  => 'Safety and records',
        'roles' => '',
        'items' => [
            ['label' => 'Recalls',     'page' => 'quality/recalls',   'tab' => 'recalls'],
            ['label' => 'Documents',   'page' => 'quality/library',   'tab' => 'library'],
            ['label' => 'Audit Trail', 'page' => 'quality/audit',     'tab' => 'audit'],
        ],
    ],
    [
        'id'    => 'sitesMenu',
        'icon'  => 'fas fa-warehouse',
        'title' => 'Sites',
        'note'  => 'Where stock lives',
        'roles' => '',
        'items' => [
            ['label' => 'Warehouses',         'page' => 'sites/warehouses', 'tab' => 'warehouses'],
            ['label' => 'Storage Locations',  'page' => 'sites/locations',  'tab' => 'locations'],
        ],
    ],
    [
        'id'    => 'insightsMenu',
        'icon'  => 'fas fa-chart-bar',
        'title' => 'Insights',
        'note'  => 'Reporting',
        'roles' => '',
        'items' => [
            ['label' => 'Reports', 'page' => 'insights/reports', 'tab' => 'reports'],
        ],
    ],
    [
        'id'    => 'adminMenu',
        'icon'  => 'fas fa-users-cog',
        'title' => 'Administration',
        'note'  => 'People and alerts',
        'roles' => 'Admin',
        'items' => [
            ['label' => 'Users',         'page' => 'administration/users',         'tab' => 'users'],
            ['label' => 'Notifications', 'page' => 'administration/notifications', 'tab' => 'notifications'],
        ],
    ],
];
