# DS3 — Database Schema Reference

All tables live in the `ds3` database. All quantities are in **grams** unless noted.
Soft-delete pattern: tables with a `status` ENUM use status transitions (e.g. `Inactive`, `Cancelled`). Tables without a status column use `active TINYINT(1)` (1=live, 0=soft-deleted). No `deleted_at`, no `updated_at` columns anywhere.
Append-only tables: `stock_ledger`, `order_tracking`, `audit_trail` — never UPDATE or DELETE rows.

---

## users

DS3 staff accounts. Roles assigned via `user_role_mapping` → `role`.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| first_name | VARCHAR(100) | |
| last_name | VARCHAR(100) | |
| email | VARCHAR(255) UNIQUE | Login email |
| password | VARCHAR(255) | bcrypt via PASSWORD_DEFAULT |
| status | ENUM | active, inactive |
| token | VARCHAR(255) | Bearer token (SHA256) |
| token_expires_at | DATETIME | Token expiry |
| last_login_at | DATETIME | |

---

## role

Role definitions for both portals.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| name | VARCHAR(100) UNIQUE | Machine key: snake_case (e.g. `warehouse`, `client_admin`) |
| label | VARCHAR(255) | Human-readable label |
| portal | ENUM | admin, client |
| description | TEXT | |

**Admin portal roles:** `admin`, `manager`, `sales`, `compliance`, `warehouse`, `purchasing`, `finance`.
**Client portal roles:** `client_admin`, `client_user`.
**Role notes:** `manager` = approval authority (POs, credit memos, holds, escalations). `admin` = full system access.

---

## user_role_mapping

Many-to-many: DS3 staff ↔ roles.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| user_id | INT UNSIGNED FK→users | |
| role_id | INT UNSIGNED FK→role | Must be a `portal='admin'` role |

---

## client_user_role_mapping

Many-to-many: pharmacy contacts ↔ roles.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| client_user_id | INT UNSIGNED FK→client_users | |
| role_id | INT UNSIGNED FK→role | Must be a `portal='client'` role |

---

## categories

Product categories for grouping ingredients.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| name | VARCHAR(255) UNIQUE | |
| note | TEXT | |
| active | TINYINT(1) | 1=active, 0=deleted |

---

## products

Pharmaceutical compounding ingredients (APIs). Sold and tracked in grams.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| code | VARCHAR(50) UNIQUE | Internal code (letters, numbers, dot, dash) |
| name | VARCHAR(255) | API (active pharmaceutical ingredient) name |
| category_id | INT UNSIGNED FK→categories | |
| grade | VARCHAR(100) | USP, EP, ACS, etc. |
| schedule | ENUM | None, II, III, IV, V (DEA controlled substance) |
| storage | ENUM | Ambient, Cold |
| list_price | DECIMAL(10,4) | List price per gram |
| min_kg | DECIMAL(10,3) | Minimum order quantity in grams |
| status | ENUM | Active, Inactive, Discontinued |

---

## vendors

Ingredient suppliers.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| name | VARCHAR(255) | |
| country | VARCHAR(100) | |
| contact | VARCHAR(255) | Contact person name |
| email | VARCHAR(255) | |
| phone | VARCHAR(50) | |
| lead_days | SMALLINT UNSIGNED | Default lead time days |
| address | TEXT | |
| apis | TEXT | Comma-separated APIs/ingredients supplied |
| status | ENUM | Active, Inactive |

Required documents: Quality agreement, GMP certificate, Supplier audit/questionnaire, W-9, Insurance certificate, Sample CoA.

---

## clients

Pharmacy customers. Each client may have a pricing tier and shipping locations.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| name | VARCHAR(255) | |
| contact | VARCHAR(255) | Contact person |
| email | VARCHAR(255) UNIQUE | Billing/main email |
| phone | VARCHAR(50) | |
| billing_address | TEXT | |
| credit_limit | DECIMAL(12,2) | |
| terms | ENUM | Prepaid, Net 15, Net 30, Net 45, Net 60 |
| tier_id | INT UNSIGNED FK→pricing_tiers | Pricing tier |
| controlled_approved | TINYINT(1) | Approved for controlled substances |
| license_number | VARCHAR(100) | State pharmacy license |
| license_expiry | DATE | |
| credit_hold | TINYINT(1) | On credit hold |
| status | ENUM | Active, Inactive, Suspended |
| portal_user_id | INT UNSIGNED | Links to client portal user (future) |

Required documents: Business license, W-9/resale certificate, Credit application, Signed terms of sale.
If controlled_approved = 1: also DEA registration.

---

## client_ship_locations

Shipping addresses for each client pharmacy.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| client_id | INT UNSIGNED FK→clients | |
| label | VARCHAR(255) | Short name, unique per client |
| address | TEXT | Full street address |
| city | VARCHAR(100) | |
| state | VARCHAR(50) | |
| zip | VARCHAR(20) | |
| is_default | TINYINT(1) | Default ship-to for this client |
| active | TINYINT(1) | 1=active, 0=deleted |

---

## warehouses

Physical storage facilities owned or used by DS3. Two in operation: **Irving, TX** (DS3-owned, primary) and **LSL / 3PL, Indiana** (third-party bulk storage, can ship direct-to-pharmacy).

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| name | VARCHAR(255) | |
| city | VARCHAR(100) | |
| state | VARCHAR(50) | |
| address | TEXT | |
| manager | VARCHAR(255) | |
| phone | VARCHAR(50) | |
| capacity_kg | DECIMAL(12,3) | Total capacity in grams (0 = unlimited) |
| storage | SET | Ambient, Cold, Controlled (can be multiple) |
| status | ENUM | Active, Inactive |

A warehouse can handle controlled substances only if it has a valid DEA registration in `licenses`.
Cold-chain products only if storage includes 'Cold' and has a cold chain certification.

Required documents: Lease/ownership proof, Insurance certificate, Operating SOP.
If cold: Temperature mapping report.

---

## storage_locations

Bins, shelves, or zones within a warehouse.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| warehouse_id | INT UNSIGNED FK→warehouses | |
| code | VARCHAR(50) | Location code, e.g. A-01, COLD-B-03 |
| zone | ENUM | Ambient, Cold, Controlled, Quarantine |
| capacity_kg | DECIMAL(12,3) | 0 = no limit |
| active | TINYINT(1) | |

UNIQUE: (warehouse_id, code)

---

## inventory_lots

The central stock tracking unit. One row per lot per warehouse.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| lot_id | VARCHAR(50) | Lot identifier, e.g. MET-26A01 |
| product_id | INT UNSIGNED FK→products | |
| warehouse_id | INT UNSIGNED FK→warehouses | |
| vendor_id | INT UNSIGNED FK→vendors | Source vendor |
| location_id | INT UNSIGNED FK→storage_locations | Physical location |
| qty_g | DECIMAL(12,3) | Current quantity on hand in grams |
| qty_reserved | DECIMAL(12,3) | Reserved for open orders |
| expiry_date | DATE | Lot expiry |
| status | ENUM | Quarantine, Available, Reserved, Picked, In Transit, Depleted, Rejected, Recalled, Expired, Written_off |
| coa_received | TINYINT(1) | CoA document received |
| coa_verified | TINYINT(1) | CoA verified against specification |
| po_id | INT UNSIGNED FK→purchase_orders | Source PO |

UNIQUE: (lot_id, warehouse_id)

**Status transitions**: `Quarantine` → `Available` (Compliance verifies CoA) → `Reserved` (order allocated, atomic transaction) → `Picked` (warehouse picks the lot) → `In Transit` (shipped, for internal transfers) → `Depleted` (fully consumed). `Rejected` = QA failed after receipt (lot rejected, not released). `Recalled` = safety recall. `Expired` / `Written_off` = write-off with stock_ledger row `move_type = write_off`.

**Naming:** Always use `Quarantine` (noun, not `Quarantined`). Always use `Reserved` (not `Allocated`) for lots locked to an order.

**FEFO rule:** When allocating a lot to an order, the system must select the lot with the earliest `expiry_date` among all `Available` lots of the product at the fulfilling warehouse (First Expired, First Out). Never allocate from a lot with `status != Available`.

**Available qty**: `qty_g - qty_reserved`

---

## stock_ledger

**Append-only**. Every gram movement writes one row. Never UPDATE or DELETE.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED AUTO_INCREMENT | PK |
| lot_id | INT UNSIGNED FK→inventory_lots | |
| product_id | INT UNSIGNED FK→products | Denormalized for query speed |
| warehouse_id | INT UNSIGNED FK→warehouses | Denormalized |
| move_type | ENUM | receive, release, allocate, deallocate, transfer_out, transfer_in, adjust, write_off, recall |
| qty_g | DECIMAL(12,3) | Positive = in, negative = out |
| qty_before_g | DECIMAL(12,3) | Lot qty before this move |
| qty_after_g | DECIMAL(12,3) | Lot qty after this move |
| ref_type | VARCHAR(50) | purchase_order, order, transfer, recall |
| ref_id | INT UNSIGNED | PK of the reference record |
| notes | TEXT | |
| actor | VARCHAR(255) | User email or 'system' |

---

## inventory_transfers

Move lots between warehouses or locations.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| lot_id | INT UNSIGNED FK→inventory_lots | |
| from_wh_id | INT UNSIGNED FK→warehouses | |
| to_wh_id | INT UNSIGNED FK→warehouses | |
| from_loc_id | INT UNSIGNED FK→storage_locations | |
| to_loc_id | INT UNSIGNED FK→storage_locations | |
| qty_g | DECIMAL(12,3) | |
| status | ENUM | Pending, In transit, Received, Closed, Cancelled |
| created_by | INT UNSIGNED FK→users | |
| completed_at | DATETIME | |

---

## pricing_tiers

Named groups for custom per-product pricing.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| name | VARCHAR(255) UNIQUE | |
| note | TEXT | |
| active | TINYINT(1) | 1=active, 0=deleted |

---

## tier_prices

Per-product per-tier pricing with quantity breaks.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| tier_id | INT UNSIGNED FK→pricing_tiers | |
| product_id | INT UNSIGNED FK→products | |
| min_qty_g | DECIMAL(12,3) | Min grams for this price (0 = base price) |
| price_g | DECIMAL(10,4) | Price per gram |

UNIQUE: (tier_id, product_id, min_qty_g)

---

## purchase_orders

DS3 buying from vendors.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| po_number | VARCHAR(50) UNIQUE | PO-YYYYMMDD-NNNN |
| vendor_id | INT UNSIGNED FK→vendors | |
| status | ENUM | Draft, Submitted, Approved, Acknowledged, Partial, Received, Cancelled |
| ordered_at | DATETIME | When submitted to vendor |
| expected_at | DATE | Expected delivery |
| received_at | DATETIME | When fully received |
| subtotal_g | DECIMAL(12,3) | Total grams ordered |
| total_cost | DECIMAL(12,2) | |
| created_by | INT UNSIGNED FK→users | |

---

## purchase_order_lines

One row per product per PO.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| po_id | INT UNSIGNED FK→purchase_orders | |
| product_id | INT UNSIGNED FK→products | |
| qty_g_ordered | DECIMAL(12,3) | |
| qty_g_received | DECIMAL(12,3) | Updated on receipt |
| cost_per_g | DECIMAL(10,4) | |
| total_cost | DECIMAL(12,2) | |
| lot_id | INT UNSIGNED FK→inventory_lots | Filled when received |

---

## quotes

Sales quotes before converting to orders.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| ref | VARCHAR(50) UNIQUE | Quote reference number |
| client_id | INT UNSIGNED FK→clients | |
| status | ENUM | Draft, Sent, Accepted, Declined, Expired |
| valid_until | DATE | |
| ship_by | DATE | |
| terms | ENUM | Prepaid, Net 15, Net 30, Net 45, Net 60 |
| notes | TEXT | |
| created_by | INT UNSIGNED FK→users | |

---

## quote_lines

One row per product per quote.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| quote_id | INT UNSIGNED FK→quotes | |
| product_id | INT UNSIGNED FK→products | |
| qty_g | DECIMAL(12,3) | |
| price_g | DECIMAL(10,4) | Quoted price per gram |

---

## orders

Sales orders (DS3 → client pharmacy). One order = one product line.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| order_number | VARCHAR(50) UNIQUE | ORD-YYYYMMDD-NNNN |
| client_id | INT UNSIGNED FK→clients | |
| product_id | INT UNSIGNED FK→products | |
| qty_g | DECIMAL(12,3) | |
| price_g | DECIMAL(10,4) | Price per gram at time of order |
| total | DECIMAL(12,2) | qty_g × price_g |
| status | ENUM | New, Received, Review, Allocated, Pick List Ready, Pick Approved, Picked, Packed, Shipped, In Transit, Delivered, Closed, Backorder, Cancelled |
| held | TINYINT(1) | On hold |
| hold_reason | TEXT | |
| hold_kind | ENUM | credit, compliance, other |
| backorder | TINYINT(1) | Accepted as backorder |
| coa_verified | TINYINT(1) | CoA verified for this order |
| license_ok | TINYINT(1) | License verified (required for Schedule II–V) |
| ship_by | DATE | Required ship-by date |
| ship_to_id | INT UNSIGNED FK→client_ship_locations | |
| warehouse_id | INT UNSIGNED FK→warehouses | Fulfilling warehouse |
| lot_id | INT UNSIGNED FK→inventory_lots | Allocated lot |
| carrier | ENUM | UPS, FedEx, Other |
| tracking_number | VARCHAR(100) | Carrier tracking number |
| routed_via | VARCHAR(100) | Carrier/routing notes |
| pick_list_approved_by | INT UNSIGNED FK→users | Warehouse manager who approved pick |
| pick_list_approved_at | DATETIME | |
| picked_at | DATETIME | When physically picked |
| packed_at | DATETIME | When packed |
| documents_verified_at | DATETIME | Pre-ship document checklist cleared |
| shipment_at | DATETIME | When shipped (carrier pickup) |
| delivery_at | DATETIME | Carrier delivery scan |
| client_confirmed_at | DATETIME | Client confirms receipt in portal |
| client_confirmed_by | VARCHAR(255) | Client user email |
| invoice_id | INT UNSIGNED FK→invoices | |
| quote_id | INT UNSIGNED FK→quotes | Source quote |
| created_by | INT UNSIGNED FK→users | |

---

## order_tracking

**Append-only** event log per order.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| order_id | INT UNSIGNED FK→orders | |
| event | VARCHAR(255) | Event name |
| detail | TEXT | |
| actor | VARCHAR(255) | User email |

---

## invoices

Billing records for shipped orders.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| invoice_number | VARCHAR(50) UNIQUE | INV-YYYYMMDD-NNNN |
| client_id | INT UNSIGNED FK→clients | |
| status | ENUM | Draft, Sent, Posted, Paid, Partial, Overdue, Closed, Void |
| issued_at | DATETIME | |
| due_at | DATE | |
| subtotal | DECIMAL(12,2) | |
| discount | DECIMAL(12,2) | |
| tax | DECIMAL(12,2) | |
| total | DECIMAL(12,2) | |
| amount_paid | DECIMAL(12,2) | Running total paid |
| pdf_path | VARCHAR(500) | |
| created_by | INT UNSIGNED FK→users | |

---

## invoice_lines

One row per order on the invoice.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| invoice_id | INT UNSIGNED FK→invoices | |
| order_id | INT UNSIGNED FK→orders | |
| product_id | INT UNSIGNED FK→products | Denormalized |
| qty_g | DECIMAL(12,3) | |
| price_g | DECIMAL(10,4) | |
| total | DECIMAL(12,2) | |

---

## credit_notes

Reduce what a client owes.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| credit_number | VARCHAR(50) UNIQUE | |
| client_id | INT UNSIGNED FK→clients | |
| invoice_id | INT UNSIGNED FK→invoices | Related invoice (nullable) |
| amount | DECIMAL(12,2) | Total credit amount |
| remaining | DECIMAL(12,2) | Unused balance |
| reason | TEXT | |
| status | ENUM | Active, Used, Voided |
| created_by | INT UNSIGNED FK→users | |

---

## par_config

Periodic Automatic Replenishment — triggers reorder suggestions when stock falls below PAR level.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| product_id | INT UNSIGNED FK→products | |
| warehouse_id | INT UNSIGNED FK→warehouses | |
| par_level_g | DECIMAL(12,3) | Min stock to trigger reorder |
| reorder_qty_g | DECIMAL(12,3) | How much to order |
| vendor_id | INT UNSIGNED FK→vendors | Preferred vendor |
| active | TINYINT(1) | |

UNIQUE: (product_id, warehouse_id)

---

## client_allocations

Standing reservations — clients may request a weekly allocation of a product so stock is pre-reserved for them (e.g. "50 g of metformin API every week"). The order management workflow checks `client_allocations` when calculating available-to-promise quantities.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| client_id | INT UNSIGNED FK→clients | |
| product_id | INT UNSIGNED FK→products | |
| weekly_quantity_grams | DECIMAL(12,3) | Weekly reservation in grams |
| start_date | DATE | Allocation effective from |
| end_date | DATE | Allocation ends (null = open-ended) |
| notes | TEXT | |
| created_by | INT UNSIGNED FK→users | |
| created_at | DATETIME | |
| active | TINYINT(1) | 1=active, 0=deleted |

---

## recalls

Product safety recalls. Can target a specific lot or all lots of a product.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| recall_id | VARCHAR(50) UNIQUE | Recall reference |
| product_id | INT UNSIGNED FK→products | |
| lot_id | INT UNSIGNED FK→inventory_lots | Specific lot (null = all lots) |
| title | VARCHAR(255) | |
| reason | TEXT | |
| severity | ENUM | Class I, Class II, Class III |
| status | ENUM | Open, In progress, Closed |
| initiated_at | DATE | |
| closed_at | DATETIME | |
| created_by | INT UNSIGNED FK→users | |

---

## recall_checks

Compliance verification steps per lot/order during a recall.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| recall_id | INT UNSIGNED FK→recalls | |
| lot_id | INT UNSIGNED FK→inventory_lots | |
| order_id | INT UNSIGNED FK→orders | |
| check_type | VARCHAR(100) | coa_received, coa_verified, packaging_intact, temp_in_range |
| result | ENUM | Pass, Fail, Pending |
| notes | TEXT | |
| checked_by | INT UNSIGNED FK→users | |
| checked_at | DATETIME | |

---

## documents

Document library — CoAs, vendor certs, licenses, warehouse docs.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| name | VARCHAR(255) | |
| doc_type | VARCHAR(100) | GMP certificate, CoA, License, etc. |
| entity_type | ENUM | vendor, client, warehouse, lot, product, general |
| entity_id | INT UNSIGNED | PK of the related entity |
| file_path | VARCHAR(500) | S3 or local path |
| file_size | INT UNSIGNED | Bytes |
| mime_type | VARCHAR(100) | |
| expiry | DATE | Document expiry date |
| status | ENUM | Pending, Verified, Rejected, Expired |
| verified_by | INT UNSIGNED FK→users | |
| verified_at | DATETIME | |
| uploaded_by | INT UNSIGNED FK→users | |

---

## licenses

Regulatory licenses and registrations per entity.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| entity_type | ENUM | warehouse, client, vendor |
| entity_id | INT UNSIGNED | PK of the related entity |
| type | ENUM | State distributor license, DEA registration, Cold chain certification, Business license, Other |
| number | VARCHAR(100) | License/registration number |
| expiry | DATE | |
| issuer | VARCHAR(255) | Issuing authority |
| doc_id | INT UNSIGNED FK→documents | Supporting document |
| status | ENUM | Valid, Expiring, Expired (updated by cron) |
| created_by | INT UNSIGNED FK→users | |

---

## notifications

In-app alerts for users.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| user_id | INT UNSIGNED FK→users | |
| type | VARCHAR(100) | order_received, order_held, pick_list_ready, backorder, order_shipped, invoice_sent, ar_reminder, ar_escalated, recall_opened, license_expiring, lot_expiring_soon, coa_pending |
| ref_type | VARCHAR(50) | Entity type |
| ref_id | INT UNSIGNED | Entity PK |
| message | TEXT | |
| read_at | DATETIME | null = unread |

---

## audit_trail

**Append-only**. Cryptographically chained log of all significant actions.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED AUTO_INCREMENT | PK |
| seq | BIGINT UNSIGNED UNIQUE | Monotonic sequence |
| when_at | VARCHAR(50) | Human-readable timestamp |
| role | VARCHAR(100) | Acting user's role |
| entity | VARCHAR(50) | order, lot, client, etc. |
| ref | VARCHAR(100) | Entity identifier |
| action | VARCHAR(100) | What was done |
| detail | TEXT | |
| prev_hash | VARCHAR(64) | Previous entry hash |
| entry_hash | VARCHAR(64) | Chain hash for tamper detection |

---

## client_users

Client portal accounts — one per pharmacy contact that can log in to place orders.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| client_id | INT UNSIGNED FK→clients | Parent client |
| first_name | VARCHAR(100) | |
| last_name | VARCHAR(100) | |
| email | VARCHAR(255) UNIQUE | Login email |
| password | VARCHAR(255) | bcrypt via PASSWORD_DEFAULT |
| status | ENUM | active, inactive |
| token | VARCHAR(255) | Bearer token (SHA256) |
| token_expires_at | DATETIME | |
| last_login_at | DATETIME | |

---

## pick_list_items

One row per lot allocation per order. Generated when order moves to `Pick List Ready`. Warehouse manager approves the list before physical pick begins.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| order_id | INT UNSIGNED FK→orders | |
| lot_id | INT UNSIGNED FK→inventory_lots | Lot to pick from (FEFO selected) |
| location_id | INT UNSIGNED FK→storage_locations | Bin location |
| qty_g | DECIMAL(12,3) | Quantity to pick |
| picked_qty_g | DECIMAL(12,3) | Actual picked (filled at pick time) |
| picked_by | INT UNSIGNED FK→users | Warehouse user who picked |
| picked_at | DATETIME | |
| status | ENUM | Pending, Picked, Skipped |

---

## invoice_reminders

Log of every AR contact attempt on an overdue invoice.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| invoice_id | INT UNSIGNED FK→invoices | |
| contact_type | ENUM | email, phone, portal_message |
| notes | TEXT | What was communicated |
| response | TEXT | Client response if any |
| actor | VARCHAR(255) | DS3 user email |
| created_at | DATETIME | |

---

## document_requirements

Checklist of required documents per entity type. Compliance can flag an entity incomplete if any required document is missing or expired.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| entity_type | ENUM | vendor, client, warehouse, lot |
| doc_type | VARCHAR(100) | GMP certificate, CoA, License, DEA registration, etc. |
| required_for_controlled | TINYINT(1) | Only required if entity handles controlled substances |
| notes | TEXT | |
| active | TINYINT(1) | |

---

## capa_log

Corrective and Preventive Action records. Generated from recall checks, QA failures, or compliance gaps.

| Column | Type | Notes |
|--------|------|-------|
| id | INT UNSIGNED AUTO_INCREMENT | PK |
| title | VARCHAR(255) | |
| description | TEXT | Root cause and action plan |
| entity_type | VARCHAR(50) | lot, vendor, warehouse, product, etc. |
| entity_id | INT UNSIGNED | PK of related entity |
| assigned_to | INT UNSIGNED FK→users | Responsible user |
| due_date | DATE | |
| status | ENUM | Open, In Progress, Closed, Overdue |
| closed_at | DATETIME | |
| created_by | INT UNSIGNED FK→users | |
| created_at | DATETIME | |

---

## configuration

App settings key-value store (read via `config()` in v2 code).

| Column | Type | Notes |
|--------|------|-------|
| option_name | VARCHAR(255) UNIQUE | Setting key |
| option_value | TEXT | Setting value |
| note | VARCHAR(500) | Description |

Default settings: app_name, app_env, par_window_days (90), safety_days (7), cover_days (30), credit_overdue_days (30), reminder_days (7).

**Hardshells (bookkeeping):** External bookkeeper that receives invoice data from DS3. Configuration rows to add: `hardshells_email` (recipient for invoice exports), `hardshells_format` (CSV/PDF — to be decided by DS3 Finance). **Open item** — format/process TBD with Ashwin/Finance team.

**Carrier integration:** `carrier_ups_account`, `carrier_fedex_account` — API credentials for label generation. **Open item** for Phase 1 — manual tracking number entry until credentials are provided.
