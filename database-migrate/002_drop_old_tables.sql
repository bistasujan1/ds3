-- DS3 — Drop legacy tables from previous schema iteration
-- These were replaced by the current schema in 001_initial_schema.sql:
--   inventory            → inventory_lots + stock_ledger
--   inventory_transactions → stock_ledger
--   sales_orders         → orders
--   sales_order_items    → (orders is single-product per row)
--   product_categories   → categories
--   manufacturers        → vendors
--   pharmacies           → clients
--   purchase_order_items → purchase_order_lines
--   invoice_payments     → (not yet modelled — future migration)
--   shipments            → (carrier/tracking fields live on orders)

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `sales_order_items`;
DROP TABLE IF EXISTS `sales_orders`;
DROP TABLE IF EXISTS `inventory_transactions`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `invoice_payments`;
DROP TABLE IF EXISTS `shipments`;
DROP TABLE IF EXISTS `purchase_order_items`;
DROP TABLE IF EXISTS `product_categories`;
DROP TABLE IF EXISTS `manufacturers`;
DROP TABLE IF EXISTS `pharmacies`;

SET FOREIGN_KEY_CHECKS = 1;
