# DS3 — Architecture

## Business Domain

DS3 Rx is a pharmaceutical distribution wholesaler that sources compounding ingredients (APIs) from manufacturers in China (~90%) and India (~10%) and distributes them to ~350 compounding pharmacy clients in the US. Products are measured in **grams** (all DB columns) / **kg** for bulk shipment volumes. Each physical receipt becomes a **lot** tracked with lot number, expiry date, storage location, and Certificate of Analysis (CoA).

**Two warehouses in operation:**
- **Irving, Texas** — DS3-owned, most active, primary fulfillment
- **LSL (Larry's / 3PL), Indiana** — third-party bulk storage; can also ship direct-to-pharmacy

**Phase 1 target: mid-December 2026** — scope is Order Management only (admin + client ordering portal). Vendor portal is a future phase.

**Two portals (both Phase 1):**
- **Admin portal** (`/v2/portals/admin/`) — DS3 staff: manage inventory, orders, clients, compliance
- **Client portal** (`/v2/portals/client/`) — Pharmacy customers: place orders, view invoices, upload documents

---

## Request Flow

```
Browser / API Client
    │
    ▼
Apache httpd (local.ds3.com → C:\xampp\htdocs\ds3)
    │
    ├─ /v2/portals/admin/*  → PHP file served directly (frontend pages)
    ├─ /v2/portals/client/* → PHP file served directly (client portal)
    │
    └─ /api/v2/*  ──────────────────────────────────────────────────────┐
                                                                        │
                                                                        ▼
                                                               api/v2/index.php
                                                                        │
                                                                        ▼
                                                               api/v2/bootstrap.php
                                                      ┌─────────────────────────────┐
                                                      │ 1. composer autoload         │
                                                      │ 2. Cloud framework init       │
                                                      │ 3. Request/Response objects  │
                                                      │ 4. config.php (DB creds)     │
                                                      │ 5. Database::setDbInstance() │
                                                      │ 6. AppConfig::load()         │
                                                      │ 7. Logger::init()            │
                                                      │ 8. Load routes.php           │
                                                      │ 9. Cloud::run() → dispatch   │
                                                      └─────────────────────────────┘
                                                                        │
                                                                        ▼
                                                       Router.php → middleware stack
                                                                        │
                                                                        ▼
                                                       Controller method (Portal\Admin\*)
                                                       → validate() → model → response()
```

---

## Directory Map

```
api/v2/
├── bootstrap.php              Entry point for all API requests
├── index.php                  Front controller
├── config/
│   ├── routes.php             All Router.php registrations — required for every new Router
│   ├── datatables.php         Per-table server-side pagination toggle
│   ├── scopes.php             Portal login key → db_scope/portal_folder mapping
│   └── lib_config.php         Reunite library credentials (email, SMS, storage, etc.)
├── functions/
│   ├── global.php             Helper functions: response(), request_post(), sendEmail(), etc.
│   └── constants.php          REUNITE_CONFIG_PATH, LOGIN_HOURS, ADMIN_ROLES
├── models/                    All DB models extending BaseModel
│   ├── BaseModel.php          Generic CRUD: findById, findWhere, insert, updateById, paginate
│   └── UserModel.php          Users table (login, token lookup)
├── utils/
│   ├── Auth.php               Bearer token verification, role checks
│   ├── Database.php           MysqliDb wrapper, scope management
│   ├── Middleware.php         Pre-built middleware stacks per portal
│   └── AppConfig.php          Loads configuration table into APCu cache
└── portal/
    ├── BaseController.php     validate(), success() helpers
    └── admin/
        ├── BaseController.php Admin-specific base
        └── App/
            ├── Router.php     Health + login routes
            └── AppController.php

v2/
├── shared/
│   ├── layouts/
│   │   ├── main.php           Main authenticated layout
│   │   └── auth.php           Login/unauthenticated layout
│   ├── partials/
│   │   ├── head.php           CDN CSS links (Bootstrap 5, Font Awesome 6)
│   │   └── footer_scripts.php CDN JS (jQuery, DataTables, Select2, Parsley, toastr, sweetalert2)
│   ├── assets/js/
│   │   ├── portal-auth.js     PortalAuth auth/token management + first_load overlay
│   │   └── portal-api.js      PortalApi AJAX wrapper (get/post/put/patch/del)
│   └── utils/helpers.php      v2_view(), datatable_server_side(), etc.
└── portals/
    └── admin/
        ├── bootstrap.php      Defines V2_PATH, loads helpers
        ├── config.php         Portal config (portal_name, base_url, login_url, etc.)
        ├── sidebar_menu.php   Sidebar navigation groups and items
        ├── login.php          Bridge → pages/login.php
        ├── dashboard.php      Bridge → pages/dashboard/index.php
        ├── pages/             All portal pages
        │   ├── login.php
        │   └── dashboard/index.php
        └── assets/
            └── js/admin/
                ├── admin.js       requireAuth() + attachAjaxHeaders() + logout
                └── admin-auth.js  Login form handler → setSession() → redirect

config.php                     DB credentials from $_SERVER env vars
constant.php                   root_path, assets_path, api_path constants
database-migrate/              SQL migration files (numbered, sequential)
docs/                          Architecture, schema, setup docs
composer.json                  Reunite framework dependencies
```

---

## Module Naming Pattern

| Sidebar Module | Model | Controller | Router prefix |
|----------------|-------|------------|---------------|
| Catalogue → products | `Models\ProductModel` | `Portal\Admin\Catalogue\ProductController` | `/admin/catalogue` |
| Catalogue → categories | `Models\CategoryModel` | `Portal\Admin\Catalogue\CategoryController` | `/admin/catalogue` |
| Outgoing → orders | `Models\OrderModel` | `Portal\Admin\Outgoing\OrderController` | `/admin/orders` |
| Outgoing → quotes | `Models\QuoteModel` | `Portal\Admin\Outgoing\QuoteController` | `/admin/quotes` |
| Outgoing → invoices | `Models\InvoiceModel` | `Portal\Admin\Outgoing\InvoiceController` | `/admin/invoices` |
| Outgoing → clients | `Models\ClientModel` | `Portal\Admin\Outgoing\ClientController` | `/admin/clients` |
| Outgoing → pricing | `Models\PricingTierModel` | `Portal\Admin\Outgoing\PricingController` | `/admin/pricing` |
| Incoming → pos | `Models\PurchaseOrderModel` | `Portal\Admin\Incoming\PurchaseOrderController` | `/admin/purchase-orders` |
| Incoming → vendors | `Models\VendorModel` | `Portal\Admin\Incoming\VendorController` | `/admin/vendors` |
| Inventory → inventory | `Models\InventoryLotModel` | `Portal\Admin\Inventory\LotController` | `/admin/inventory` |
| Inventory → transfers | `Models\TransferModel` | `Portal\Admin\Inventory\TransferController` | `/admin/inventory` |
| Sites → warehouses | `Models\WarehouseModel` | `Portal\Admin\Sites\WarehouseController` | `/admin/warehouses` |
| Sites → locations | `Models\StorageLocationModel` | `Portal\Admin\Sites\LocationController` | `/admin/warehouses` |
| QA → recalls | `Models\RecallModel` | `Portal\Admin\Quality\RecallController` | `/admin/recalls` |
| QA → library | `Models\DocumentModel` | `Portal\Admin\Quality\DocumentController` | `/admin/documents` |
| Administration → users | `Models\UserModel` | `Portal\Admin\Administration\UserController` | `/admin/users` |

---

## Key Framework Components

| Component | File | Purpose |
|-----------|------|---------|
| `Cloud` | `system/` (via `Reunite\Framework`) | Global service container, router, DI |
| `Database` | `utils/Database.php` | MysqliDb wrapper, two scopes: `admin` + `default` |
| `Auth` | `utils/Auth.php` | Bearer token verification, role/module checks |
| `Middleware` | `utils/Middleware.php` | Pre-built middleware stacks per portal |
| `BaseModel` | `models/BaseModel.php` | Generic CRUD: `findById`, `findWhere`, `insert`, `updateById`, `paginate` |
| `BaseController` | `portal/BaseController.php` | `validate()`, `success()` helpers |
| `AppConfig` | `utils/AppConfig.php` | Loads `configuration` table into APCu cache |
| `Logger` | `Reunite\Logger` (vendor) | PSR-3 structured JSON logging → `storage/logs/app.log` |

---

## Database Scopes

| Scope | Env var pair | Purpose |
|-------|-------------|---------|
| `admin` | `ADMIN_DB_USER_NAME` / `ADMIN_DB_PASSWORD` | Full read/write for admin portal routes |
| `default` | `DB_USER_NAME` / `DB_PASSWORD` | Token verification, public routes, cron |
| `audit` | `AUDIT_DB_*` | Audit log writes (separate DB) |

---

## Authentication Flow

1. `POST /api/v2/admin/user/login` → `AppController::login()` → verify email+password → generate SHA256 token → store in `users.token`
2. Protected routes use `Middleware::admin()`:
   - `AuthMiddleware` reads `Authorization: Bearer <token>` header
   - Looks up `users` table, checks expiry, sets `Cloud::get('user')`
   - `PortalScopeMiddleware` sets DB scope to `admin`
3. Controllers access the authenticated user via `Cloud::get('user')['id']`, `['role']`, etc.

---

## Key Business Workflows

### Inventory: Receiving a lot

1. Create a Purchase Order (PO) in `purchase_orders` + `purchase_order_lines`
2. When physically received, create a row in `inventory_lots` with `status = Quarantine`
3. Upload CoA to `documents`; Compliance verifies it → set `coa_received = 1`, `coa_verified = 1`
4. Compliance releases the lot: `status = Available`, write row to `stock_ledger` (move_type = `receive`)

### Order: Fulfillment workflow (Section 7, v5 workflow)

1. Client or Sales creates order → `orders.status = New`; system assigns order number
2. System logs receipt event → `status = Received`; notifies DS3 managers
3. Review (Compliance, Sales): verify client license, controlled-substance approval, CoA → `status = Review`
4. If `clients.credit_hold = 1` or `clients.license_expiry` is expired → hold order (`orders.held = 1`)
5. If backorder: flag `orders.backorder = 1`, notify client, DS3 managers, warehouse → `status = Backorder`
6. Warehouse allocates lot (FEFO — earliest expiry first; only `Available` lots):
   - Atomic transaction: `inventory_lots.qty_reserved += qty_g` where `status = Available AND qty_g - qty_reserved >= order.qty_g`
   - Create `pick_list_items` rows with lot + bin location
   - `orders.status = Pick List Ready`; notify warehouse manager
7. Warehouse manager approves pick list → `orders.pick_list_approved_by`, `pick_list_approved_at`, `status = Pick Approved`
8. Warehouse physically picks → `orders.picked_at`, `status = Picked`; update `pick_list_items.picked_qty_g`
9. Warehouse packs → `orders.packed_at`, `status = Packed`
10. Document checklist verified before shipping → `orders.documents_verified_at`, `status = Packed` (sub-step)
11. Carrier label generated → `orders.carrier`, `orders.tracking_number`
12. Carrier pickup confirmed → `stock_ledger` (move_type = `allocate`), `orders.shipment_at`, `status = Shipped`; notify client + DS3 managers with tracking
13. Carrier delivers → `orders.delivery_at`, `status = Delivered`; notify client to confirm receipt
14. Client confirms receipt in portal → `orders.client_confirmed_at`, `client_confirmed_by`, `status = Closed`; write `stock_ledger` (move_type = `release`)
15. Finance creates invoice from closed order → `orders.invoice_id`

**Credit / license hold gate (step 4):** Before allocating, always check:
- `clients.credit_hold = 1` → hold
- `licenses.expiry < NOW()` for the client's pharmacy license → hold
- Schedule II–V product AND `clients.controlled_approved = 0` → hold

**Lot reservation atomicity:** Step 6 must run inside a transaction. Never increment `qty_reserved` outside a transaction — race conditions can double-promise the same stock (workflow gap J-2).

### AR / Invoice workflow (Section 8, v5 workflow)

1. Finance creates invoice from closed order(s): `invoices.status = Draft`
2. Invoice sent to client and Hardshells (bookkeeping): `status = Sent`; `invoice_reminders` row logged
3. Hardshells posts to AR ledger: `status = Posted`
4. Payment received: `invoices.amount_paid += payment`, `status = Paid` (or `Partial` if underpaid)
5. If overdue: cron sets `status = Overdue`; AR owner receives notification
6. AR reminder cadence: log each contact attempt in `invoice_reminders`; escalate to Manager after N attempts
7. Credit memo approved by Manager: create `credit_notes` record, reduce outstanding balance
8. Invoice settled: `status = Closed`

### Compliance: Recalls

1. Create recall in `recalls` targeting a lot or all lots of a product
2. Affected lots move to `status = Recalled`
3. Any open orders containing affected lot are held (`orders.held = 1`)
4. `recall_checks` track compliance verification steps (CoA, packaging, temperature)
5. Close recall when all checks pass: `recalls.status = Closed`

---

## Coding Rules (enforced)

- No `$this->db` calls in Controllers — all queries go through injected Model instances
- All page AJAX through `window.PortalApi` — never raw `$.ajax` (DataTables `ajax:` config is exempt)
- Page JS in `.js` files via `$extra_scripts` — never inline `<script>` in content buffer
- Parsley.js on every form before the submit handler calls the API
- `sendEmail()` / `sendSms()` global helpers only — never `EmailService::send()` directly
- `Reunite\Logger::error/warning/info/debug()` only — never `error_log()`
- Soft delete (`deleted_at`) on user-facing entities — no hard deletes
- `stock_ledger`, `order_tracking`, `audit_trail` are append-only — never UPDATE or DELETE
- Quantities always in grams (column suffix `_g`), prices always per gram (column suffix `_g`)

---

## Adding a New Feature — Step-by-Step

1. **SQL migration**: Create `database-migrate/NNN_feature.sql` with CREATE TABLE + indexes
2. **Model**: Create `api/v2/models/FeatureModel.php` (extend `Models\BaseModel`)
3. **Controller**: Create `api/v2/portal/admin/Module/FeatureController.php` (extend `Portal\Admin\BaseController`)
4. **Router**: Create `api/v2/portal/admin/Module/Router.php` with routes wrapped in `Middleware::admin()`
5. **Register route**: Add entry to `api/v2/config/routes.php`
6. **Frontend page**: Create `v2/portals/admin/pages/module/feature.php`
7. **Page JS**: Create `v2/portals/admin/assets/js/admin/module/feature.js` (use `PortalApi.get/post/...`)
8. **Sidebar**: Add entry to `v2/portals/admin/sidebar_menu.php`
