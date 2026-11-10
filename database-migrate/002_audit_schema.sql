-- DS3 Audit Schema
-- Run against the `ds3_audit` database
-- Usage: mysql -u root ds3_audit < database-migrate/002_audit_schema.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_id`      INT UNSIGNED    NULL     COMMENT 'users.id — NULL for system/cron actions',
    `actor_email`   VARCHAR(255)    NULL     COMMENT 'Snapshot at time of action',
    `action`        VARCHAR(100)    NOT NULL COMMENT 'e.g. invoice.paid, order.cancelled',
    `resource_type` VARCHAR(100)    NOT NULL COMMENT 'invoice, sales_order, etc.',
    `resource_id`   INT UNSIGNED    NULL,
    `old_values`    JSON            NULL,
    `new_values`    JSON            NULL,
    `ip_address`    VARCHAR(45)     NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_actor` (`actor_id`, `created_at`),
    KEY `idx_audit_resource` (`resource_type`, `resource_id`),
    KEY `idx_audit_action` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
