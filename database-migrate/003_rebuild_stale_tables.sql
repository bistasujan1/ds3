-- DS3 — Rebuild stale tables
-- The tables below existed from an older schema version before 001_initial_schema.sql was run.
-- CREATE TABLE IF NOT EXISTS skipped them, so they still have old columns.
-- This script drops them all and recreates from the current design.
-- Safe to run on dev — no real data exists.

SET FOREIGN_KEY_CHECKS = 0;

-- Drop everything that may be stale
DROP TABLE IF EXISTS `pick_list_items`;
DROP TABLE IF EXISTS `invoice_reminders`;
DROP TABLE IF EXISTS `invoice_lines`;
DROP TABLE IF EXISTS `order_tracking`;
DROP TABLE IF EXISTS `credit_notes`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `quote_lines`;
DROP TABLE IF EXISTS `quotes`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `purchase_order_lines`;
DROP TABLE IF EXISTS `purchase_orders`;
DROP TABLE IF EXISTS `inventory_transfers`;
DROP TABLE IF EXISTS `inventory_lots`;
DROP TABLE IF EXISTS `stock_ledger`;
DROP TABLE IF EXISTS `storage_locations`;
DROP TABLE IF EXISTS `par_config`;
DROP TABLE IF EXISTS `tier_prices`;
DROP TABLE IF EXISTS `pricing_tiers`;
DROP TABLE IF EXISTS `client_allocations`;
DROP TABLE IF EXISTS `client_ship_locations`;
DROP TABLE IF EXISTS `client_user_role_mapping`;
DROP TABLE IF EXISTS `client_users`;
DROP TABLE IF EXISTS `user_role_mapping`;
DROP TABLE IF EXISTS `role`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `vendors`;
DROP TABLE IF EXISTS `warehouses`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `users`;

-- ─────────────────────────────────────────────────────────────
-- Users
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `users` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name`       VARCHAR(100) NOT NULL,
    `last_name`        VARCHAR(100) NOT NULL,
    `email`            VARCHAR(255) NOT NULL,
    `password`         VARCHAR(255) NOT NULL COMMENT 'bcrypt via PASSWORD_DEFAULT',
    `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `token`            VARCHAR(255) NULL,
    `token_expires_at` DATETIME     NULL,
    `last_login_at`    DATETIME     NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Role Definitions
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `role` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `label`       VARCHAR(255) NOT NULL,
    `portal`      ENUM('admin','client') NOT NULL,
    `description` TEXT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_role_name` (`name`),
    KEY `idx_role_portal` (`portal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- User Role Mapping
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `user_role_mapping` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `role_id`    INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_urm_user_role` (`user_id`, `role_id`),
    KEY `idx_urm_role` (`role_id`),
    CONSTRAINT `fk_urm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_urm_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Categories
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `note`       TEXT NULL,
    `active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Products
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `products` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(50)   NOT NULL COMMENT 'Internal code: letters, numbers, dot, dash',
    `name`        VARCHAR(255)  NOT NULL COMMENT 'API (active pharmaceutical ingredient) name',
    `category_id` INT UNSIGNED  NULL,
    `grade`       VARCHAR(100)  NOT NULL COMMENT 'USP, EP, ACS, etc.',
    `schedule`    ENUM('None','II','III','IV','V') NOT NULL DEFAULT 'None',
    `storage`     ENUM('Ambient','Cold') NOT NULL DEFAULT 'Ambient',
    `list_price`  DECIMAL(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'List price per gram',
    `min_kg`      DECIMAL(10,3) NULL COMMENT 'Minimum order quantity in grams',
    `status`      ENUM('Active','Inactive','Discontinued') NOT NULL DEFAULT 'Active',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_code` (`code`),
    KEY `idx_products_category` (`category_id`),
    KEY `idx_products_status` (`status`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Vendors
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `vendors` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `country`    VARCHAR(100) NOT NULL DEFAULT 'US',
    `contact`    VARCHAR(255) NULL,
    `email`      VARCHAR(255) NULL,
    `phone`      VARCHAR(50)  NULL,
    `lead_days`  SMALLINT UNSIGNED NOT NULL DEFAULT 14,
    `address`    TEXT NULL,
    `apis`       TEXT NULL,
    `status`     ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vendors_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Clients
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `clients` (
    `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`                 VARCHAR(255)  NOT NULL,
    `contact`              VARCHAR(255)  NULL,
    `email`                VARCHAR(255)  NOT NULL,
    `phone`                VARCHAR(50)   NULL,
    `billing_address`      TEXT          NULL,
    `credit_limit`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `terms`                ENUM('Prepaid','Net 15','Net 30','Net 45','Net 60') NOT NULL DEFAULT 'Net 30',
    `tier_id`              INT UNSIGNED  NULL,
    `controlled_approved`  TINYINT(1)   NOT NULL DEFAULT 0,
    `license_number`       VARCHAR(100)  NULL,
    `license_expiry`       DATE          NULL,
    `credit_hold`          TINYINT(1)   NOT NULL DEFAULT 0,
    `status`               ENUM('Active','Inactive','Suspended') NOT NULL DEFAULT 'Active',
    `portal_user_id`       INT UNSIGNED  NULL,
    `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_clients_email` (`email`),
    KEY `idx_clients_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Client Shipping Locations
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `client_ship_locations` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`  INT UNSIGNED NOT NULL,
    `label`      VARCHAR(255) NOT NULL,
    `address`    TEXT NOT NULL,
    `city`       VARCHAR(100) NULL,
    `state`      VARCHAR(50)  NULL,
    `zip`        VARCHAR(20)  NULL,
    `is_default` TINYINT(1)  NOT NULL DEFAULT 0,
    `active`     TINYINT(1)  NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_shiplocs_client` (`client_id`, `is_default`),
    CONSTRAINT `fk_shiplocs_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Client Users (pharmacy contacts — client portal)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `client_users` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`        INT UNSIGNED NOT NULL,
    `first_name`       VARCHAR(100) NOT NULL,
    `last_name`        VARCHAR(100) NOT NULL,
    `email`            VARCHAR(255) NOT NULL,
    `password`         VARCHAR(255) NOT NULL,
    `status`           ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `token`            VARCHAR(255) NULL,
    `token_expires_at` DATETIME NULL,
    `last_login_at`    DATETIME NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_client_users_email` (`email`),
    KEY `idx_client_users_client` (`client_id`, `status`),
    CONSTRAINT `fk_client_users_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Client User Role Mapping
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `client_user_role_mapping` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_user_id` INT UNSIGNED NOT NULL,
    `role_id`        INT UNSIGNED NOT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_curm_user_role` (`client_user_id`, `role_id`),
    KEY `idx_curm_role` (`role_id`),
    CONSTRAINT `fk_curm_user` FOREIGN KEY (`client_user_id`) REFERENCES `client_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_curm_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Warehouses
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `warehouses` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255)  NOT NULL,
    `city`        VARCHAR(100)  NOT NULL,
    `state`       VARCHAR(50)   NOT NULL,
    `address`     TEXT          NULL,
    `manager`     VARCHAR(255)  NULL,
    `phone`       VARCHAR(50)   NULL,
    `capacity_kg` DECIMAL(12,3) NOT NULL DEFAULT 0,
    `storage`     SET('Ambient','Cold','Controlled') NOT NULL DEFAULT 'Ambient',
    `status`      ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_warehouses_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Storage Locations
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `storage_locations` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `warehouse_id` INT UNSIGNED  NOT NULL,
    `code`         VARCHAR(50)   NOT NULL,
    `zone`         ENUM('Ambient','Cold','Controlled','Quarantine') NOT NULL DEFAULT 'Ambient',
    `capacity_kg`  DECIMAL(12,3) NOT NULL DEFAULT 0,
    `active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_location_wh_code` (`warehouse_id`, `code`),
    KEY `idx_locations_zone` (`warehouse_id`, `zone`, `active`),
    CONSTRAINT `fk_locations_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Inventory Lots
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `inventory_lots` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `lot_id`       VARCHAR(50)   NOT NULL,
    `product_id`   INT UNSIGNED  NOT NULL,
    `warehouse_id` INT UNSIGNED  NOT NULL,
    `vendor_id`    INT UNSIGNED  NULL,
    `location_id`  INT UNSIGNED  NULL,
    `qty_g`        DECIMAL(12,3) NOT NULL DEFAULT 0,
    `qty_reserved` DECIMAL(12,3) NOT NULL DEFAULT 0,
    `expiry_date`  DATE          NULL,
    `status`       ENUM('Quarantine','Available','Reserved','Picked','In Transit','Depleted','Rejected','Recalled','Expired','Written_off') NOT NULL DEFAULT 'Quarantine',
    `coa_received` TINYINT(1)   NOT NULL DEFAULT 0,
    `coa_verified` TINYINT(1)   NOT NULL DEFAULT 0,
    `po_id`        INT UNSIGNED  NULL,
    `notes`        TEXT          NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lot_warehouse` (`lot_id`, `warehouse_id`),
    KEY `idx_lots_product` (`product_id`, `status`),
    KEY `idx_lots_warehouse` (`warehouse_id`, `status`),
    KEY `idx_lots_expiry` (`expiry_date`, `status`),
    CONSTRAINT `fk_lots_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
    CONSTRAINT `fk_lots_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
    CONSTRAINT `fk_lots_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_lots_location` FOREIGN KEY (`location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Stock Ledger (append-only)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `stock_ledger` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lot_id`       INT UNSIGNED    NOT NULL,
    `product_id`   INT UNSIGNED    NOT NULL,
    `warehouse_id` INT UNSIGNED    NOT NULL,
    `move_type`    ENUM('receive','release','allocate','deallocate','transfer_out','transfer_in','adjust','write_off','recall') NOT NULL,
    `qty_g`        DECIMAL(12,3)   NOT NULL,
    `qty_before_g` DECIMAL(12,3)   NOT NULL,
    `qty_after_g`  DECIMAL(12,3)   NOT NULL,
    `ref_type`     VARCHAR(50)     NULL,
    `ref_id`       INT UNSIGNED    NULL,
    `notes`        TEXT            NULL,
    `actor`        VARCHAR(255)    NOT NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ledger_lot` (`lot_id`, `created_at`),
    KEY `idx_ledger_product` (`product_id`, `created_at`),
    KEY `idx_ledger_ref` (`ref_type`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Inventory Transfers
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `inventory_transfers` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `lot_id`       INT UNSIGNED  NOT NULL,
    `from_wh_id`   INT UNSIGNED  NOT NULL,
    `to_wh_id`     INT UNSIGNED  NOT NULL,
    `from_loc_id`  INT UNSIGNED  NULL,
    `to_loc_id`    INT UNSIGNED  NULL,
    `qty_g`        DECIMAL(12,3) NOT NULL,
    `status`       ENUM('Pending','In transit','Received','Closed','Cancelled') NOT NULL DEFAULT 'Pending',
    `notes`        TEXT NULL,
    `created_by`   INT UNSIGNED  NOT NULL,
    `completed_at` DATETIME NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_xfer_lot` (`lot_id`),
    CONSTRAINT `fk_xfer_lot` FOREIGN KEY (`lot_id`) REFERENCES `inventory_lots` (`id`),
    CONSTRAINT `fk_xfer_from_wh` FOREIGN KEY (`from_wh_id`) REFERENCES `warehouses` (`id`),
    CONSTRAINT `fk_xfer_to_wh` FOREIGN KEY (`to_wh_id`) REFERENCES `warehouses` (`id`),
    CONSTRAINT `fk_xfer_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Pricing Tiers
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `pricing_tiers` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `note`       TEXT NULL,
    `active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tiers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Tier Prices
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `tier_prices` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `tier_id`    INT UNSIGNED  NOT NULL,
    `product_id` INT UNSIGNED  NOT NULL,
    `min_qty_g`  DECIMAL(12,3) NOT NULL DEFAULT 0,
    `price_g`    DECIMAL(10,4) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tier_price` (`tier_id`, `product_id`, `min_qty_g`),
    KEY `idx_tier_prices_product` (`product_id`),
    CONSTRAINT `fk_tier_prices_tier` FOREIGN KEY (`tier_id`) REFERENCES `pricing_tiers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tier_prices_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Purchase Orders
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `purchase_orders` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `po_number`   VARCHAR(50)   NOT NULL,
    `vendor_id`   INT UNSIGNED  NOT NULL,
    `status`      ENUM('Draft','Submitted','Approved','Acknowledged','Partial','Received','Cancelled') NOT NULL DEFAULT 'Draft',
    `ordered_at`  DATETIME      NULL,
    `expected_at` DATE          NULL,
    `received_at` DATETIME      NULL,
    `subtotal_g`  DECIMAL(12,3) NOT NULL DEFAULT 0,
    `total_cost`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `notes`       TEXT NULL,
    `created_by`  INT UNSIGNED  NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_po_number` (`po_number`),
    KEY `idx_po_vendor` (`vendor_id`, `status`),
    CONSTRAINT `fk_po_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
    CONSTRAINT `fk_po_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Purchase Order Lines
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `purchase_order_lines` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `po_id`          INT UNSIGNED  NOT NULL,
    `product_id`     INT UNSIGNED  NOT NULL,
    `qty_g_ordered`  DECIMAL(12,3) NOT NULL,
    `qty_g_received` DECIMAL(12,3) NOT NULL DEFAULT 0,
    `cost_per_g`     DECIMAL(10,4) NOT NULL,
    `total_cost`     DECIMAL(12,2) NOT NULL,
    `lot_id`         INT UNSIGNED  NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_po_lines_po` (`po_id`),
    KEY `idx_po_lines_product` (`product_id`),
    CONSTRAINT `fk_po_lines_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_po_lines_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
    CONSTRAINT `fk_po_lines_lot` FOREIGN KEY (`lot_id`) REFERENCES `inventory_lots` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Quotes
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `quotes` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ref`         VARCHAR(50)  NOT NULL,
    `client_id`   INT UNSIGNED NOT NULL,
    `status`      ENUM('Draft','Sent','Accepted','Declined','Expired') NOT NULL DEFAULT 'Draft',
    `valid_until` DATE NOT NULL,
    `ship_by`     DATE NULL,
    `terms`       ENUM('Prepaid','Net 15','Net 30','Net 45','Net 60') NULL,
    `notes`       TEXT NULL,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_quotes_ref` (`ref`),
    KEY `idx_quotes_client` (`client_id`, `status`),
    CONSTRAINT `fk_quotes_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
    CONSTRAINT `fk_quotes_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Quote Lines
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `quote_lines` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `quote_id`   INT UNSIGNED  NOT NULL,
    `product_id` INT UNSIGNED  NOT NULL,
    `qty_g`      DECIMAL(12,3) NOT NULL,
    `price_g`    DECIMAL(10,4) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quote_lines_quote` (`quote_id`),
    CONSTRAINT `fk_quote_lines_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_quote_lines_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Orders
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `orders` (
    `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_number`          VARCHAR(50)   NOT NULL,
    `client_id`             INT UNSIGNED  NOT NULL,
    `product_id`            INT UNSIGNED  NOT NULL,
    `qty_g`                 DECIMAL(12,3) NOT NULL,
    `price_g`               DECIMAL(10,4) NOT NULL,
    `total`                 DECIMAL(12,2) NOT NULL,
    `status`                ENUM('New','Received','Review','Allocated','Pick List Ready','Pick Approved','Picked','Packed','Shipped','In Transit','Delivered','Closed','Backorder','Cancelled') NOT NULL DEFAULT 'New',
    `held`                  TINYINT(1)   NOT NULL DEFAULT 0,
    `hold_reason`           TEXT NULL,
    `hold_kind`             ENUM('credit','compliance','other') NULL,
    `backorder`             TINYINT(1)   NOT NULL DEFAULT 0,
    `coa_verified`          TINYINT(1)   NOT NULL DEFAULT 0,
    `license_ok`            TINYINT(1)   NOT NULL DEFAULT 0,
    `ship_by`               DATE NOT NULL,
    `ship_to_id`            INT UNSIGNED  NULL,
    `warehouse_id`          INT UNSIGNED  NULL,
    `lot_id`                INT UNSIGNED  NULL,
    `carrier`               ENUM('UPS','FedEx','Other') NULL,
    `tracking_number`       VARCHAR(100)  NULL,
    `routed_via`            VARCHAR(100)  NULL,
    `pick_list_approved_by` INT UNSIGNED  NULL,
    `pick_list_approved_at` DATETIME NULL,
    `picked_at`             DATETIME NULL,
    `packed_at`             DATETIME NULL,
    `documents_verified_at` DATETIME NULL,
    `shipment_at`           DATETIME NULL,
    `delivery_at`           DATETIME NULL,
    `client_confirmed_at`   DATETIME NULL,
    `client_confirmed_by`   VARCHAR(255)  NULL,
    `invoice_id`            INT UNSIGNED  NULL,
    `quote_id`              INT UNSIGNED  NULL,
    `notes`                 TEXT NULL,
    `created_by`            INT UNSIGNED  NOT NULL,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_order_number` (`order_number`),
    KEY `idx_orders_client` (`client_id`, `status`),
    KEY `idx_orders_status` (`status`, `ship_by`),
    KEY `idx_orders_lot` (`lot_id`),
    CONSTRAINT `fk_orders_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
    CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
    CONSTRAINT `fk_orders_shiploc` FOREIGN KEY (`ship_to_id`) REFERENCES `client_ship_locations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_orders_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_orders_lot` FOREIGN KEY (`lot_id`) REFERENCES `inventory_lots` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_orders_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_orders_pick_approver` FOREIGN KEY (`pick_list_approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Order Tracking (append-only)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `order_tracking` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`   INT UNSIGNED NOT NULL,
    `event`      VARCHAR(255) NOT NULL,
    `detail`     TEXT NULL,
    `actor`      VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tracking_order` (`order_id`),
    CONSTRAINT `fk_tracking_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Pick List Items
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `pick_list_items` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`     INT UNSIGNED  NOT NULL,
    `lot_id`       INT UNSIGNED  NOT NULL,
    `location_id`  INT UNSIGNED  NULL,
    `qty_g`        DECIMAL(12,3) NOT NULL,
    `picked_qty_g` DECIMAL(12,3) NOT NULL DEFAULT 0,
    `picked_by`    INT UNSIGNED  NULL,
    `picked_at`    DATETIME NULL,
    `status`       ENUM('Pending','Picked','Skipped') NOT NULL DEFAULT 'Pending',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_picklist_order` (`order_id`),
    KEY `idx_picklist_lot` (`lot_id`),
    CONSTRAINT `fk_picklist_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_picklist_lot` FOREIGN KEY (`lot_id`) REFERENCES `inventory_lots` (`id`),
    CONSTRAINT `fk_picklist_location` FOREIGN KEY (`location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_picklist_user` FOREIGN KEY (`picked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Invoices
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `invoices` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(50)   NOT NULL,
    `client_id`      INT UNSIGNED  NOT NULL,
    `status`         ENUM('Draft','Sent','Posted','Paid','Partial','Overdue','Closed','Void') NOT NULL DEFAULT 'Draft',
    `issued_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `due_at`         DATE NOT NULL,
    `subtotal`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `amount_paid`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `pdf_path`       VARCHAR(500)  NULL,
    `notes`          TEXT NULL,
    `created_by`     INT UNSIGNED  NOT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invoice_number` (`invoice_number`),
    KEY `idx_invoices_client` (`client_id`, `status`),
    KEY `idx_invoices_due` (`due_at`, `status`),
    CONSTRAINT `fk_invoices_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
    CONSTRAINT `fk_invoices_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Invoice Lines
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `invoice_lines` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `invoice_id` INT UNSIGNED  NOT NULL,
    `order_id`   INT UNSIGNED  NOT NULL,
    `product_id` INT UNSIGNED  NOT NULL,
    `qty_g`      DECIMAL(12,3) NOT NULL,
    `price_g`    DECIMAL(10,4) NOT NULL,
    `total`      DECIMAL(12,2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_invoice_lines_invoice` (`invoice_id`),
    KEY `idx_invoice_lines_order` (`order_id`),
    CONSTRAINT `fk_invoice_lines_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invoice_lines_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
    CONSTRAINT `fk_invoice_lines_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Invoice Reminders
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `invoice_reminders` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id`   INT UNSIGNED NOT NULL,
    `contact_type` ENUM('email','phone','portal_message') NOT NULL DEFAULT 'email',
    `notes`        TEXT NULL,
    `response`     TEXT NULL,
    `actor`        VARCHAR(255) NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_inv_reminders_invoice` (`invoice_id`),
    CONSTRAINT `fk_inv_reminders_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Credit Notes
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `credit_notes` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `credit_number` VARCHAR(50)   NOT NULL,
    `client_id`     INT UNSIGNED  NOT NULL,
    `invoice_id`    INT UNSIGNED  NULL,
    `amount`        DECIMAL(12,2) NOT NULL,
    `remaining`     DECIMAL(12,2) NOT NULL,
    `reason`        TEXT NULL,
    `status`        ENUM('Active','Used','Voided') NOT NULL DEFAULT 'Active',
    `created_by`    INT UNSIGNED  NOT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_credit_number` (`credit_number`),
    KEY `idx_credits_client` (`client_id`, `status`),
    CONSTRAINT `fk_credits_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
    CONSTRAINT `fk_credits_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_credits_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Client Allocations
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `client_allocations` (
    `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `client_id`             INT UNSIGNED  NOT NULL,
    `product_id`            INT UNSIGNED  NOT NULL,
    `weekly_quantity_grams` DECIMAL(12,3) NOT NULL,
    `start_date`            DATE NOT NULL,
    `end_date`              DATE NULL,
    `notes`                 TEXT NULL,
    `active`                TINYINT(1) NOT NULL DEFAULT 1,
    `created_by`            INT UNSIGNED  NULL,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_alloc_client_product` (`client_id`, `product_id`, `active`),
    CONSTRAINT `fk_alloc_client`  FOREIGN KEY (`client_id`)  REFERENCES `clients` (`id`),
    CONSTRAINT `fk_alloc_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
    CONSTRAINT `fk_alloc_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- PAR Config
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `par_config` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `product_id`    INT UNSIGNED  NOT NULL,
    `warehouse_id`  INT UNSIGNED  NOT NULL,
    `par_level_g`   DECIMAL(12,3) NOT NULL,
    `reorder_qty_g` DECIMAL(12,3) NOT NULL,
    `vendor_id`     INT UNSIGNED  NULL,
    `active`        TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_par_product_wh` (`product_id`, `warehouse_id`),
    CONSTRAINT `fk_par_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
    CONSTRAINT `fk_par_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
    CONSTRAINT `fk_par_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Seed data
-- ─────────────────────────────────────────────────────────────

INSERT INTO `role` (`name`, `label`, `portal`, `description`) VALUES
('admin',        'Administrator', 'admin',  'Full system access'),
('manager',      'Manager',       'admin',  'Approval authority: POs, credit memos, holds, escalations'),
('sales',        'Sales',         'admin',  'Order entry, quotes, client management'),
('compliance',   'Compliance',    'admin',  'CoA verification, lot release, recall management, licensing'),
('warehouse',    'Warehouse',     'admin',  'Pick lists, packing, shipping, inventory receiving'),
('purchasing',   'Purchasing',    'admin',  'Purchase orders, vendor management, PAR replenishment'),
('finance',      'Finance',       'admin',  'Invoices, payments, credit notes, AR'),
('client_admin', 'Client Admin',  'client', 'Full access to client portal'),
('client_user',  'Client User',   'client', 'Place and track orders only');

-- Default admin user (password: Admin@123)
INSERT INTO `users` (`first_name`, `last_name`, `email`, `password`, `status`)
VALUES ('DS3', 'Admin', 'admin@ds3.local', '$2y$12$vLExbSIAemFceDotA5IS6e9CfFpfP.cI8bbAck2EJ/Cvzh4gNzzx6', 'active');

INSERT INTO `user_role_mapping` (`user_id`, `role_id`)
SELECT u.id, r.id FROM `users` u, `role` r
WHERE u.email = 'admin@ds3.local' AND r.name = 'admin';

SET FOREIGN_KEY_CHECKS = 1;
