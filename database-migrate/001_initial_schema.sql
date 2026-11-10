-- DS3 — Initial Schema v2
-- Pharmaceutical compounding ingredient distribution platform
-- Updated 2026-11-10: added Manager role, expanded order/lot statuses, new tables from workflow v5 audit
-- Updated 2026-11-10: removed updated_at and deleted_at per project standard; use status/active for soft-delete
-- Updated 2026-11-10: replaced users.role ENUM with role + user_role_mapping + client_user_role_mapping tables
-- Run against the `ds3` database
-- Usage: mysql -u root ds3 < database-migrate/001_initial_schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────
-- Users (DS3 staff)
-- Roles assigned via user_role_mapping → role table
-- Soft delete: set status = 'inactive'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
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
-- portal = 'admin' for DS3 staff roles, 'client' for pharmacy contact roles
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `role` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL COMMENT 'Machine key: snake_case',
    `label`       VARCHAR(255) NOT NULL COMMENT 'Human-readable label',
    `portal`      ENUM('admin','client') NOT NULL,
    `description` TEXT         NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_role_name` (`name`),
    KEY `idx_role_portal` (`portal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- User Role Mapping (DS3 staff — many roles per user)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_role_mapping` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `role_id`    INT UNSIGNED NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_urm_user_role` (`user_id`, `role_id`),
    KEY `idx_urm_role` (`role_id`),
    CONSTRAINT `fk_urm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_urm_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Product Categories (ingredients grouped by type)
-- Soft delete: active = 0
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `note`       TEXT         NULL,
    `active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Products (pharmaceutical compounding ingredients, sold in grams)
-- Soft delete: set status = 'Inactive' or 'Discontinued'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(50)   NOT NULL COMMENT 'Internal code: letters, numbers, dot, dash',
    `name`        VARCHAR(255)  NOT NULL COMMENT 'API (active pharmaceutical ingredient) name',
    `category_id` INT UNSIGNED  NULL,
    `grade`       VARCHAR(100)  NOT NULL COMMENT 'USP, EP, ACS, etc.',
    `schedule`    ENUM('None','II','III','IV','V') NOT NULL DEFAULT 'None' COMMENT 'DEA controlled substance schedule',
    `storage`     ENUM('Ambient','Cold') NOT NULL DEFAULT 'Ambient' COMMENT 'Storage requirement',
    `list_price`  DECIMAL(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'List price per gram',
    `min_kg`      DECIMAL(10,3) NULL     COMMENT 'Minimum order quantity in grams',
    `status`      ENUM('Active','Inactive','Discontinued') NOT NULL DEFAULT 'Active',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_code` (`code`),
    KEY `idx_products_category` (`category_id`),
    KEY `idx_products_status` (`status`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Vendors (ingredient suppliers)
-- Soft delete: set status = 'Inactive'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vendors` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255) NOT NULL,
    `country`     VARCHAR(100) NOT NULL DEFAULT 'US',
    `contact`     VARCHAR(255) NULL,
    `email`       VARCHAR(255) NULL,
    `phone`       VARCHAR(50)  NULL,
    `lead_days`   SMALLINT UNSIGNED NOT NULL DEFAULT 14 COMMENT 'Default lead time in days',
    `address`     TEXT         NULL,
    `apis`        TEXT         NULL COMMENT 'Comma-separated list of APIs/ingredients supplied',
    `status`      ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vendors_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Clients (pharmacy customers)
-- Soft delete: set status = 'Inactive' or 'Suspended'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `clients` (
    `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`                 VARCHAR(255)  NOT NULL,
    `contact`              VARCHAR(255)  NULL,
    `email`                VARCHAR(255)  NOT NULL,
    `phone`                VARCHAR(50)   NULL,
    `billing_address`      TEXT          NULL,
    `credit_limit`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `terms`                ENUM('Prepaid','Net 15','Net 30','Net 45','Net 60') NOT NULL DEFAULT 'Net 30',
    `tier_id`              INT UNSIGNED  NULL COMMENT 'Pricing tier',
    `controlled_approved`  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Approved to purchase controlled substances',
    `license_number`       VARCHAR(100)  NULL,
    `license_expiry`       DATE          NULL,
    `credit_hold`          TINYINT(1)   NOT NULL DEFAULT 0,
    `status`               ENUM('Active','Inactive','Suspended') NOT NULL DEFAULT 'Active',
    `portal_user_id`       INT UNSIGNED  NULL COMMENT 'Links to client portal user',
    `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_clients_email` (`email`),
    KEY `idx_clients_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Client Shipping Locations
-- Soft delete: active = 0
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `client_ship_locations` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`  INT UNSIGNED NOT NULL,
    `label`      VARCHAR(255) NOT NULL COMMENT 'Short name for this location',
    `address`    TEXT         NOT NULL,
    `city`       VARCHAR(100) NULL,
    `state`      VARCHAR(50)  NULL,
    `zip`        VARCHAR(20)  NULL,
    `is_default` TINYINT(1)  NOT NULL DEFAULT 0,
    `active`     TINYINT(1)  NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_shiplocs_client` (`client_id`, `is_default`),
    CONSTRAINT `fk_shiplocs_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Warehouses (physical storage facilities)
-- Soft delete: set status = 'Inactive'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `warehouses` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(255)  NOT NULL,
    `city`        VARCHAR(100)  NOT NULL,
    `state`       VARCHAR(50)   NOT NULL,
    `address`     TEXT          NULL,
    `manager`     VARCHAR(255)  NULL,
    `phone`       VARCHAR(50)   NULL,
    `capacity_kg` DECIMAL(12,3) NOT NULL DEFAULT 0 COMMENT 'Total capacity in grams (0 = unlimited)',
    `storage`     SET('Ambient','Cold','Controlled') NOT NULL DEFAULT 'Ambient' COMMENT 'Available storage types',
    `status`      ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_warehouses_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Storage Locations (bins/shelves within a warehouse)
-- Soft delete: active = 0
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `storage_locations` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `code`        VARCHAR(50)   NOT NULL COMMENT 'Location code e.g. A-01, COLD-B-03',
    `zone`        ENUM('Ambient','Cold','Controlled','Quarantine') NOT NULL DEFAULT 'Ambient',
    `capacity_kg` DECIMAL(12,3) NOT NULL DEFAULT 0 COMMENT '0 = no capacity limit',
    `active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_location_wh_code` (`warehouse_id`, `code`),
    KEY `idx_locations_zone` (`warehouse_id`, `zone`, `active`),
    CONSTRAINT `fk_locations_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Inventory Lots (each physical receipt of an ingredient)
-- Central stock tracking unit — one row per lot per warehouse
-- Soft delete: set status to terminal state (Depleted, Rejected, Recalled, Expired, Written_off)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `inventory_lots` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `lot_id`       VARCHAR(50)   NOT NULL COMMENT 'Lot identifier e.g. MET-26A01',
    `product_id`   INT UNSIGNED  NOT NULL,
    `warehouse_id` INT UNSIGNED  NOT NULL,
    `vendor_id`    INT UNSIGNED  NULL,
    `location_id`  INT UNSIGNED  NULL COMMENT 'Storage location within warehouse',
    `qty_g`        DECIMAL(12,3) NOT NULL DEFAULT 0 COMMENT 'Quantity on hand in grams',
    `qty_reserved` DECIMAL(12,3) NOT NULL DEFAULT 0 COMMENT 'Reserved for open orders',
    `expiry_date`  DATE          NULL,
    `status`       ENUM('Quarantine','Available','Reserved','Picked','In Transit','Depleted','Rejected','Recalled','Expired','Written_off') NOT NULL DEFAULT 'Quarantine',
    `coa_received` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Certificate of Analysis received',
    `coa_verified` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'CoA verified against specification',
    `po_id`        INT UNSIGNED  NULL COMMENT 'Source purchase order',
    `notes`        TEXT          NULL,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
-- Stock Ledger (append-only — NEVER UPDATE OR DELETE)
-- Every gram movement writes one row here
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stock_ledger` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lot_id`         INT UNSIGNED    NOT NULL,
    `product_id`     INT UNSIGNED    NOT NULL,
    `warehouse_id`   INT UNSIGNED    NOT NULL,
    `move_type`      ENUM('receive','release','allocate','deallocate','transfer_out','transfer_in','adjust','write_off','recall') NOT NULL,
    `qty_g`          DECIMAL(12,3)   NOT NULL COMMENT 'Positive = in, negative = out',
    `qty_before_g`   DECIMAL(12,3)   NOT NULL,
    `qty_after_g`    DECIMAL(12,3)   NOT NULL,
    `ref_type`       VARCHAR(50)     NULL COMMENT 'purchase_order, order, transfer, recall',
    `ref_id`         INT UNSIGNED    NULL,
    `notes`          TEXT            NULL,
    `actor`          VARCHAR(255)    NOT NULL COMMENT 'User email or system name',
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ledger_lot` (`lot_id`, `created_at`),
    KEY `idx_ledger_product` (`product_id`, `created_at`),
    KEY `idx_ledger_ref` (`ref_type`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Inventory Transfers (move lots between warehouses/locations)
-- Soft delete: set status = 'Cancelled'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `inventory_transfers` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `lot_id`       INT UNSIGNED  NOT NULL,
    `from_wh_id`   INT UNSIGNED  NOT NULL,
    `to_wh_id`     INT UNSIGNED  NOT NULL,
    `from_loc_id`  INT UNSIGNED  NULL,
    `to_loc_id`    INT UNSIGNED  NULL,
    `qty_g`        DECIMAL(12,3) NOT NULL,
    `status`       ENUM('Pending','In transit','Received','Closed','Cancelled') NOT NULL DEFAULT 'Pending',
    `notes`        TEXT          NULL,
    `created_by`   INT UNSIGNED  NOT NULL,
    `completed_at` DATETIME      NULL,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_xfer_lot` (`lot_id`),
    CONSTRAINT `fk_xfer_lot` FOREIGN KEY (`lot_id`) REFERENCES `inventory_lots` (`id`),
    CONSTRAINT `fk_xfer_from_wh` FOREIGN KEY (`from_wh_id`) REFERENCES `warehouses` (`id`),
    CONSTRAINT `fk_xfer_to_wh` FOREIGN KEY (`to_wh_id`) REFERENCES `warehouses` (`id`),
    CONSTRAINT `fk_xfer_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Pricing Tiers (groups of clients with custom pricing)
-- Soft delete: active = 0
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pricing_tiers` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `note`       TEXT         NULL,
    `active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tiers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Tier Prices (per-product per-tier pricing with quantity breaks)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tier_prices` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `tier_id`    INT UNSIGNED  NOT NULL,
    `product_id` INT UNSIGNED  NOT NULL,
    `min_qty_g`  DECIMAL(12,3) NOT NULL DEFAULT 0 COMMENT 'Minimum quantity in grams for this price',
    `price_g`    DECIMAL(10,4) NOT NULL COMMENT 'Price per gram',
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tier_price` (`tier_id`, `product_id`, `min_qty_g`),
    KEY `idx_tier_prices_product` (`product_id`),
    CONSTRAINT `fk_tier_prices_tier` FOREIGN KEY (`tier_id`) REFERENCES `pricing_tiers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tier_prices_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Purchase Orders (DS3 ← Vendor)
-- Soft delete: set status = 'Cancelled'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `po_number`   VARCHAR(50)   NOT NULL COMMENT 'PO-YYYYMMDD-NNNN',
    `vendor_id`   INT UNSIGNED  NOT NULL,
    `status`      ENUM('Draft','Submitted','Approved','Acknowledged','Partial','Received','Cancelled') NOT NULL DEFAULT 'Draft',
    `ordered_at`  DATETIME      NULL,
    `expected_at` DATE          NULL,
    `received_at` DATETIME      NULL,
    `subtotal_g`  DECIMAL(12,3) NOT NULL DEFAULT 0 COMMENT 'Total grams ordered',
    `total_cost`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `notes`       TEXT          NULL,
    `created_by`  INT UNSIGNED  NOT NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_po_number` (`po_number`),
    KEY `idx_po_vendor` (`vendor_id`, `status`),
    CONSTRAINT `fk_po_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
    CONSTRAINT `fk_po_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Purchase Order Lines
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `purchase_order_lines` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `po_id`          INT UNSIGNED  NOT NULL,
    `product_id`     INT UNSIGNED  NOT NULL,
    `qty_g_ordered`  DECIMAL(12,3) NOT NULL,
    `qty_g_received` DECIMAL(12,3) NOT NULL DEFAULT 0,
    `cost_per_g`     DECIMAL(10,4) NOT NULL,
    `total_cost`     DECIMAL(12,2) NOT NULL,
    `lot_id`         INT UNSIGNED  NULL COMMENT 'Filled in when received',
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_po_lines_po` (`po_id`),
    KEY `idx_po_lines_product` (`product_id`),
    CONSTRAINT `fk_po_lines_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_po_lines_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
    CONSTRAINT `fk_po_lines_lot` FOREIGN KEY (`lot_id`) REFERENCES `inventory_lots` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Quotes (sales quotes to clients, before converting to orders)
-- Soft delete: set status = 'Declined' or 'Expired'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `quotes` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ref`         VARCHAR(50)  NOT NULL COMMENT 'Quote reference number',
    `client_id`   INT UNSIGNED NOT NULL,
    `status`      ENUM('Draft','Sent','Accepted','Declined','Expired') NOT NULL DEFAULT 'Draft',
    `valid_until` DATE         NOT NULL,
    `ship_by`     DATE         NULL,
    `terms`       ENUM('Prepaid','Net 15','Net 30','Net 45','Net 60') NULL,
    `notes`       TEXT         NULL,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_quotes_ref` (`ref`),
    KEY `idx_quotes_client` (`client_id`, `status`),
    CONSTRAINT `fk_quotes_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
    CONSTRAINT `fk_quotes_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Quote Lines
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `quote_lines` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `quote_id`   INT UNSIGNED  NOT NULL,
    `product_id` INT UNSIGNED  NOT NULL,
    `qty_g`      DECIMAL(12,3) NOT NULL,
    `price_g`    DECIMAL(10,4) NOT NULL COMMENT 'Quoted price per gram',
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quote_lines_quote` (`quote_id`),
    CONSTRAINT `fk_quote_lines_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_quote_lines_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Orders (sales orders: DS3 → Client pharmacy)
-- Full 14-state workflow (see docs/architecture.md § Order: Fulfillment workflow)
-- New → Received → Review → Allocated → Pick List Ready → Pick Approved →
-- Picked → Packed → Shipped → In Transit → Delivered → Closed
-- (also: Backorder, Cancelled)
-- Soft delete: set status = 'Cancelled'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
    `id`                      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_number`            VARCHAR(50)   NOT NULL COMMENT 'ORD-YYYYMMDD-NNNN',
    `client_id`               INT UNSIGNED  NOT NULL,
    `product_id`              INT UNSIGNED  NOT NULL,
    `qty_g`                   DECIMAL(12,3) NOT NULL,
    `price_g`                 DECIMAL(10,4) NOT NULL COMMENT 'Price per gram at time of order',
    `total`                   DECIMAL(12,2) NOT NULL,
    `status`                  ENUM('New','Received','Review','Allocated','Pick List Ready','Pick Approved','Picked','Packed','Shipped','In Transit','Delivered','Closed','Backorder','Cancelled') NOT NULL DEFAULT 'New',
    `held`                    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'On hold (credit, compliance, etc.)',
    `hold_reason`             TEXT          NULL,
    `hold_kind`               ENUM('credit','compliance','other') NULL,
    `backorder`               TINYINT(1)   NOT NULL DEFAULT 0,
    `coa_verified`            TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'CoA verified for this order',
    `license_ok`              TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'License verified for controlled substances',
    `ship_by`                 DATE          NOT NULL,
    `ship_to_id`              INT UNSIGNED  NULL COMMENT 'Client shipping location',
    `warehouse_id`            INT UNSIGNED  NULL COMMENT 'Fulfilling warehouse',
    `lot_id`                  INT UNSIGNED  NULL COMMENT 'Allocated lot (FEFO selected)',
    `carrier`                 ENUM('UPS','FedEx','Other') NULL,
    `tracking_number`         VARCHAR(100)  NULL,
    `routed_via`              VARCHAR(100)  NULL COMMENT 'Additional routing notes',
    `pick_list_approved_by`   INT UNSIGNED  NULL COMMENT 'Warehouse manager who approved pick list',
    `pick_list_approved_at`   DATETIME      NULL,
    `picked_at`               DATETIME      NULL,
    `packed_at`               DATETIME      NULL,
    `documents_verified_at`   DATETIME      NULL COMMENT 'Pre-ship document checklist cleared',
    `shipment_at`             DATETIME      NULL COMMENT 'Carrier pickup confirmed',
    `delivery_at`             DATETIME      NULL COMMENT 'Carrier delivery scan',
    `client_confirmed_at`     DATETIME      NULL COMMENT 'Client confirms receipt in portal',
    `client_confirmed_by`     VARCHAR(255)  NULL COMMENT 'Client user email',
    `invoice_id`              INT UNSIGNED  NULL,
    `quote_id`                INT UNSIGNED  NULL COMMENT 'Source quote if applicable',
    `notes`                   TEXT          NULL,
    `created_by`              INT UNSIGNED  NOT NULL,
    `created_at`              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
-- Order Tracking Events (append-only)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_tracking` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`   INT UNSIGNED NOT NULL,
    `event`      VARCHAR(255) NOT NULL,
    `detail`     TEXT         NULL,
    `actor`      VARCHAR(255) NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tracking_order` (`order_id`),
    CONSTRAINT `fk_tracking_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Invoices (billing records for shipped orders)
-- Soft delete: set status = 'Void'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `invoices` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(50)   NOT NULL COMMENT 'INV-YYYYMMDD-NNNN',
    `client_id`      INT UNSIGNED  NOT NULL,
    `status`         ENUM('Draft','Sent','Posted','Paid','Partial','Overdue','Closed','Void') NOT NULL DEFAULT 'Draft',
    `issued_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `due_at`         DATE          NOT NULL,
    `subtotal`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `amount_paid`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `pdf_path`       VARCHAR(500)  NULL,
    `notes`          TEXT          NULL,
    `created_by`     INT UNSIGNED  NOT NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_invoice_number` (`invoice_number`),
    KEY `idx_invoices_client` (`client_id`, `status`),
    KEY `idx_invoices_due` (`due_at`, `status`),
    CONSTRAINT `fk_invoices_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
    CONSTRAINT `fk_invoices_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Invoice Lines (one row per order on the invoice)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `invoice_lines` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `invoice_id` INT UNSIGNED  NOT NULL,
    `order_id`   INT UNSIGNED  NOT NULL,
    `product_id` INT UNSIGNED  NOT NULL,
    `qty_g`      DECIMAL(12,3) NOT NULL,
    `price_g`    DECIMAL(10,4) NOT NULL,
    `total`      DECIMAL(12,2) NOT NULL,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_invoice_lines_invoice` (`invoice_id`),
    KEY `idx_invoice_lines_order` (`order_id`),
    CONSTRAINT `fk_invoice_lines_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invoice_lines_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
    CONSTRAINT `fk_invoice_lines_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Credit Notes (reduce what a client owes)
-- Soft delete: set status = 'Voided'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `credit_notes` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `credit_number`  VARCHAR(50)   NOT NULL,
    `client_id`      INT UNSIGNED  NOT NULL,
    `invoice_id`     INT UNSIGNED  NULL COMMENT 'Related invoice if applicable',
    `amount`         DECIMAL(12,2) NOT NULL,
    `remaining`      DECIMAL(12,2) NOT NULL COMMENT 'Unused credit balance',
    `reason`         TEXT          NULL,
    `status`         ENUM('Active','Used','Voided') NOT NULL DEFAULT 'Active',
    `created_by`     INT UNSIGNED  NOT NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_credit_number` (`credit_number`),
    KEY `idx_credits_client` (`client_id`, `status`),
    CONSTRAINT `fk_credits_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
    CONSTRAINT `fk_credits_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_credits_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- PAR (Periodic Automatic Replenishment) Configuration
-- Soft delete: active = 0
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `par_config` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `product_id`     INT UNSIGNED  NOT NULL,
    `warehouse_id`   INT UNSIGNED  NOT NULL,
    `par_level_g`    DECIMAL(12,3) NOT NULL COMMENT 'Minimum stock to trigger reorder',
    `reorder_qty_g`  DECIMAL(12,3) NOT NULL COMMENT 'How much to order',
    `vendor_id`      INT UNSIGNED  NULL COMMENT 'Preferred vendor',
    `active`         TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_par_product_wh` (`product_id`, `warehouse_id`),
    CONSTRAINT `fk_par_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
    CONSTRAINT `fk_par_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
    CONSTRAINT `fk_par_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Recalls (product safety recalls and quarantines)
-- Soft delete: set status = 'Closed'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `recalls` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recall_id`   VARCHAR(50)  NOT NULL COMMENT 'Recall reference number',
    `product_id`  INT UNSIGNED NOT NULL,
    `lot_id`      INT UNSIGNED NULL COMMENT 'Specific lot, or null for all lots of product',
    `title`       VARCHAR(255) NOT NULL,
    `reason`      TEXT         NOT NULL,
    `severity`    ENUM('Class I','Class II','Class III') NOT NULL DEFAULT 'Class II',
    `status`      ENUM('Open','In progress','Closed') NOT NULL DEFAULT 'Open',
    `initiated_at` DATE        NOT NULL,
    `closed_at`   DATETIME     NULL,
    `notes`       TEXT         NULL,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_recall_id` (`recall_id`),
    KEY `idx_recalls_product` (`product_id`, `status`),
    CONSTRAINT `fk_recalls_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
    CONSTRAINT `fk_recalls_lot` FOREIGN KEY (`lot_id`) REFERENCES `inventory_lots` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_recalls_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Recall Checks (compliance verification steps per lot/order)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `recall_checks` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recall_id`      INT UNSIGNED NOT NULL,
    `lot_id`         INT UNSIGNED NULL,
    `order_id`       INT UNSIGNED NULL,
    `check_type`     VARCHAR(100) NOT NULL COMMENT 'coa_received, coa_verified, packaging_intact, temp_in_range, etc.',
    `result`         ENUM('Pass','Fail','Pending') NOT NULL DEFAULT 'Pending',
    `notes`          TEXT         NULL,
    `checked_by`     INT UNSIGNED NULL,
    `checked_at`     DATETIME     NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_recall_checks_recall` (`recall_id`),
    CONSTRAINT `fk_recall_checks_recall` FOREIGN KEY (`recall_id`) REFERENCES `recalls` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_recall_checks_lot` FOREIGN KEY (`lot_id`) REFERENCES `inventory_lots` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_recall_checks_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_recall_checks_user` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Documents Library (CoAs, vendor certs, warehouse docs, etc.)
-- Soft delete: set status = 'Rejected' or 'Expired'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `documents` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `doc_type`   VARCHAR(100) NOT NULL COMMENT 'GMP certificate, CoA, License, etc.',
    `entity_type` ENUM('vendor','client','warehouse','lot','product','general') NOT NULL,
    `entity_id`  INT UNSIGNED NULL,
    `file_path`  VARCHAR(500) NULL COMMENT 'S3 or local path',
    `file_size`  INT UNSIGNED NULL COMMENT 'Bytes',
    `mime_type`  VARCHAR(100) NULL,
    `expiry`     DATE         NULL COMMENT 'Document expiry date if applicable',
    `status`     ENUM('Pending','Verified','Rejected','Expired') NOT NULL DEFAULT 'Pending',
    `verified_by` INT UNSIGNED NULL,
    `verified_at` DATETIME    NULL,
    `notes`      TEXT         NULL,
    `uploaded_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_docs_entity` (`entity_type`, `entity_id`),
    KEY `idx_docs_status` (`status`, `expiry`),
    CONSTRAINT `fk_docs_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_docs_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Licenses and Registrations (per entity: warehouse, client, vendor)
-- Soft delete: status is updated by cron to 'Expired'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `licenses` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type` ENUM('warehouse','client','vendor') NOT NULL,
    `entity_id`   INT UNSIGNED NOT NULL,
    `type`        ENUM('State distributor license','DEA registration','Cold chain certification','Business license','Other') NOT NULL,
    `number`      VARCHAR(100) NULL,
    `expiry`      DATE         NOT NULL,
    `issuer`      VARCHAR(255) NULL,
    `doc_id`      INT UNSIGNED NULL COMMENT 'Supporting document',
    `status`      ENUM('Valid','Expiring','Expired') NOT NULL DEFAULT 'Valid' COMMENT 'Computed field — update via cron',
    `notes`       TEXT         NULL,
    `created_by`  INT UNSIGNED NOT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_licenses_entity` (`entity_type`, `entity_id`),
    KEY `idx_licenses_expiry` (`expiry`, `status`),
    CONSTRAINT `fk_licenses_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_licenses_doc` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Notifications (in-app alerts for users)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `type`       VARCHAR(100) NOT NULL COMMENT 'order_held, recall_opened, license_expiring, etc.',
    `ref_type`   VARCHAR(50)  NULL,
    `ref_id`     INT UNSIGNED NULL,
    `message`    TEXT         NOT NULL,
    `read_at`    DATETIME     NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user` (`user_id`, `read_at`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Audit Trail (append-only, cryptographically chained)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_trail` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `seq`        BIGINT UNSIGNED NOT NULL COMMENT 'Monotonic sequence number',
    `when_at`    VARCHAR(50)     NOT NULL COMMENT 'Human-readable timestamp',
    `role`       VARCHAR(100)    NOT NULL COMMENT 'Acting user role',
    `entity`     VARCHAR(50)     NOT NULL COMMENT 'Entity type: order, lot, client, etc.',
    `ref`        VARCHAR(100)    NOT NULL COMMENT 'Entity identifier',
    `action`     VARCHAR(100)    NOT NULL COMMENT 'What was done',
    `detail`     TEXT            NULL,
    `prev_hash`  VARCHAR(64)     NULL,
    `entry_hash` VARCHAR(64)     NOT NULL COMMENT 'Chain hash for tamper detection',
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_audit_seq` (`seq`),
    KEY `idx_audit_entity` (`entity`, `ref`),
    KEY `idx_audit_when` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Application Configuration (key-value store)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `configuration` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `option_name`  VARCHAR(255) NOT NULL,
    `option_value` TEXT         NULL,
    `note`         VARCHAR(500) NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_config_name` (`option_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Client Allocations
-- Standing weekly reservations — clients pre-reserve a quantity
-- Soft delete: active = 0
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `client_allocations` (
    `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `client_id`             INT UNSIGNED  NOT NULL,
    `product_id`            INT UNSIGNED  NOT NULL,
    `weekly_quantity_grams` DECIMAL(12,3) NOT NULL COMMENT 'Weekly reservation in grams',
    `start_date`            DATE          NOT NULL,
    `end_date`              DATE          NULL COMMENT 'NULL = open-ended',
    `notes`                 TEXT          NULL,
    `active`                TINYINT(1)    NOT NULL DEFAULT 1,
    `created_by`            INT UNSIGNED  NULL,
    `created_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_alloc_client_product` (`client_id`, `product_id`, `active`),
    CONSTRAINT `fk_alloc_client`  FOREIGN KEY (`client_id`)  REFERENCES `clients` (`id`),
    CONSTRAINT `fk_alloc_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
    CONSTRAINT `fk_alloc_user`    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Standing client product allocation reservations';

-- ─────────────────────────────────────────────────────────────
-- Client Users (pharmacy contacts who log into the client portal)
-- Soft delete: set status = 'inactive'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `client_users` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`        INT UNSIGNED NOT NULL,
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
    UNIQUE KEY `uq_client_users_email` (`email`),
    KEY `idx_client_users_client` (`client_id`, `status`),
    CONSTRAINT `fk_client_users_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Client User Role Mapping (pharmacy contacts — many roles per user)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `client_user_role_mapping` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_user_id` INT UNSIGNED NOT NULL,
    `role_id`        INT UNSIGNED NOT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_curm_user_role` (`client_user_id`, `role_id`),
    KEY `idx_curm_role` (`role_id`),
    CONSTRAINT `fk_curm_user` FOREIGN KEY (`client_user_id`) REFERENCES `client_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_curm_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Pick List Items (lot-level allocation per order, FEFO selected)
-- Created when order moves to 'Pick List Ready'
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pick_list_items` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`    INT UNSIGNED  NOT NULL,
    `lot_id`      INT UNSIGNED  NOT NULL,
    `location_id` INT UNSIGNED  NULL,
    `qty_g`       DECIMAL(12,3) NOT NULL COMMENT 'Quantity to pick',
    `picked_qty_g` DECIMAL(12,3) NOT NULL DEFAULT 0 COMMENT 'Actual quantity picked',
    `picked_by`   INT UNSIGNED  NULL,
    `picked_at`   DATETIME      NULL,
    `status`      ENUM('Pending','Picked','Skipped') NOT NULL DEFAULT 'Pending',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_picklist_order` (`order_id`),
    KEY `idx_picklist_lot` (`lot_id`),
    CONSTRAINT `fk_picklist_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_picklist_lot` FOREIGN KEY (`lot_id`) REFERENCES `inventory_lots` (`id`),
    CONSTRAINT `fk_picklist_location` FOREIGN KEY (`location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_picklist_user` FOREIGN KEY (`picked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Invoice Reminders (AR contact log per overdue invoice, append-only)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `invoice_reminders` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id`   INT UNSIGNED NOT NULL,
    `contact_type` ENUM('email','phone','portal_message') NOT NULL DEFAULT 'email',
    `notes`        TEXT         NULL,
    `response`     TEXT         NULL,
    `actor`        VARCHAR(255) NOT NULL COMMENT 'DS3 user email',
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_inv_reminders_invoice` (`invoice_id`),
    CONSTRAINT `fk_inv_reminders_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Document Requirements (required-document checklist per entity type)
-- Soft delete: active = 0
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `document_requirements` (
    `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type`             ENUM('vendor','client','warehouse','lot') NOT NULL,
    `doc_type`                VARCHAR(100) NOT NULL,
    `required_for_controlled` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Only required when entity handles controlled substances',
    `notes`                   TEXT         NULL,
    `active`                  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_doc_req_entity` (`entity_type`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- CAPA Log (Corrective and Preventive Action records)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `capa_log` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255) NOT NULL,
    `description` TEXT         NOT NULL COMMENT 'Root cause and action plan',
    `entity_type` VARCHAR(50)  NOT NULL COMMENT 'lot, vendor, warehouse, product, etc.',
    `entity_id`   INT UNSIGNED NOT NULL,
    `assigned_to` INT UNSIGNED NULL,
    `due_date`    DATE         NULL,
    `status`      ENUM('Open','In Progress','Closed','Overdue') NOT NULL DEFAULT 'Open',
    `closed_at`   DATETIME     NULL,
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_capa_entity` (`entity_type`, `entity_id`),
    KEY `idx_capa_status` (`status`, `due_date`),
    CONSTRAINT `fk_capa_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_capa_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Seed Data
-- ─────────────────────────────────────────────────────────────

-- Role definitions
INSERT IGNORE INTO `role` (`name`, `label`, `portal`, `description`) VALUES
-- Admin portal roles
('admin',      'Administrator',    'admin',  'Full system access — user management, configuration, all modules'),
('manager',    'Manager',          'admin',  'Approval authority: POs, credit memos, holds, escalations'),
('sales',      'Sales',            'admin',  'Order entry, quotes, client management'),
('compliance', 'Compliance',       'admin',  'CoA verification, lot release, recall management, licensing'),
('warehouse',  'Warehouse',        'admin',  'Pick lists, packing, shipping, inventory receiving'),
('purchasing', 'Purchasing',       'admin',  'Purchase orders, vendor management, PAR replenishment'),
('finance',    'Finance',          'admin',  'Invoices, payments, credit notes, AR'),
-- Client portal roles (pharmacy contacts)
('client_admin', 'Client Admin',   'client', 'Full access to client portal: order, view invoices, manage users'),
('client_user',  'Client User',    'client', 'Place and track orders only');

-- Default admin user (password: Admin@123 — CHANGE ON FIRST LOGIN)
INSERT IGNORE INTO `users` (`first_name`, `last_name`, `email`, `password`, `status`)
VALUES ('DS3', 'Admin', 'admin@ds3.local', '$2y$12$vLExbSIAemFceDotA5IS6e9CfFpfP.cI8bbAck2EJ/Cvzh4gNzzx6', 'active');

-- Assign admin role to default admin user
INSERT IGNORE INTO `user_role_mapping` (`user_id`, `role_id`)
SELECT u.id, r.id FROM `users` u, `role` r
WHERE u.email = 'admin@ds3.local' AND r.name = 'admin';

-- Application settings
INSERT IGNORE INTO `configuration` (`option_name`, `option_value`, `note`) VALUES
('app_name',              'DS3',   'Application display name'),
('app_env',               'local', 'Environment: local, staging, production'),
('par_window_days',       '90',    'PAR analysis window in days'),
('safety_days',           '7',     'Safety stock buffer in days'),
('cover_days',            '30',    'Days of coverage target for reorder'),
('credit_overdue_days',   '30',    'Days past due before account goes on credit hold'),
('reminder_days',         '7',     'Days before expiry to send reminder notifications'),
('hardshells_email',      '',      'OPEN ITEM: Email address for Hardshells bookkeeping invoice export'),
('hardshells_format',     'PDF',   'OPEN ITEM: Format for Hardshells handoff: PDF or CSV'),
('carrier_ups_account',   '',      'OPEN ITEM: UPS account number for label generation'),
('carrier_fedex_account', '',      'OPEN ITEM: FedEx account number for label generation');

-- Document requirements seed (required docs per entity type)
INSERT IGNORE INTO `document_requirements` (`entity_type`, `doc_type`, `required_for_controlled`, `notes`) VALUES
('vendor',    'Quality agreement',              0, NULL),
('vendor',    'GMP certificate',                0, NULL),
('vendor',    'Supplier audit/questionnaire',   0, NULL),
('vendor',    'W-9',                            0, NULL),
('vendor',    'Insurance certificate',          0, NULL),
('vendor',    'Sample CoA',                     0, NULL),
('client',    'Business license',               0, NULL),
('client',    'W-9/resale certificate',         0, NULL),
('client',    'Credit application',             0, NULL),
('client',    'Signed terms of sale',           0, NULL),
('client',    'DEA registration',               1, 'Required for controlled substance orders'),
('warehouse', 'Lease/ownership proof',          0, NULL),
('warehouse', 'Insurance certificate',          0, NULL),
('warehouse', 'Operating SOP',                  0, NULL),
('warehouse', 'Temperature mapping report',     0, 'Required for cold-chain warehouses'),
('lot',       'CoA',                            0, 'Certificate of Analysis — must be verified before lot is released from Quarantine');

SET FOREIGN_KEY_CHECKS = 1;
