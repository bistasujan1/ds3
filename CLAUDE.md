# DS3 — Pharmaceutical Compounding Distribution Platform

DS3 is a B2B platform for distributing pharmaceutical compounding ingredients (APIs — active pharmaceutical ingredients) to compounding pharmacies. Products are measured in **grams** for small quantities and **kg** for bulk shipments (all DB columns use grams internally). Each physical receipt becomes a **lot** tracked with lot number, expiry date, storage location, and Certificate of Analysis (CoA). Two portals: admin (DS3 staff) and client portal (pharmacies — ordering).

**v2-only architecture** — no v1 legacy code, no migration commands, no `module_versions` table.

## Business Domain

**Roles**: Admin, Sales, Compliance, Warehouse, Purchasing, Finance

**Clients**: ~350 pharmacy customers. Pricing is individually negotiated — not every client gets the same price (`pricing_tiers`).

**Vendors**: Manufacturers supplying ingredients — ~90% from China, ~10% from India. Products are NOT a fixed catalog; new ingredients are added as market demand shifts (e.g. peptides, GLP-1 analogues).

**Warehouses**: Two operational sites:
- **Irving, Texas** — DS3-owned, most active, day-to-day fulfillment
- **LSL (Larry's / 3PL), Indiana** — third-party bulk storage; can ship directly to pharmacies nationwide

**Key entities**: products (ingredients), vendors (suppliers), clients (pharmacies), inventory_lots, warehouses, storage_locations, orders, quotes, invoices, credit_notes, purchase_orders, recalls, documents, client_allocations

**Order workflow**: New → Review → Allocated → Shipped → Delivered (also: Cancelled)

**Inventory**: Each lot has status Quarantine → Available → Reserved → Depleted (also: Recalled, Expired, Written_off). Stock is measured in grams. Every movement writes a row to `stock_ledger` (append-only). When a lot expires: system alerts compliance, lot is written off (`status = Written_off`, `stock_ledger` row with `move_type = write_off`).

**Client allocations**: Clients may request a standing reservation (e.g. "50 g of product X every week"). Stored in `client_allocations` — the system pre-reserves that quantity so it is available when the client orders.

**Compliance**: Warehouses and clients need valid licenses (State distributor, DEA for controlled, cold-chain cert). License validity is typically ~1 year — the system must alert before expiry. Controlled substance orders (Schedule II–V) require client DEA approval. All lots need CoA before release.

## Phase 1 (Target: mid-December 2026)

**Scope**: Order Management — receive orders, allocate stock, ship, track.
**Portals in scope**: admin portal + client ordering portal (clients place orders via portal link).
**Explicitly out of scope for Phase 1**: vendor portal, email/SMS integration (but libraries must be wired in Composer now).

## Composer Libraries (wire now, use in Phase 2+)

`reunite/email` and `reunite/sms` must be in `composer.json` before Phase 1 ships, even though Phase 1 does not send email/SMS. Use `sendEmail()` / `sendSms()` helpers (never `EmailService::send()` directly) when Phase 2 activates them.

## Tech Stack

- PHP 7.4+, `declare(strict_types=1)` throughout
- MySQL 8+, MysqliDb wrapper
- Auth: Bearer token, SHA256
- Frontend: **Bootstrap 5**, Font Awesome 6, jQuery, DataTables, Select2, Parsley.js, toastr, sweetalert2 — **all via CDN** (no local vendor bundle)
- Architecture: PSR-4 namespaced throughout
- Reunite framework packages via Composer

## Directory Map

```
api/v2/
├── bootstrap.php              Entry point for all API requests
├── config/
│   ├── routes.php             All Router.php registrations — required for every new Router
│   └── datatables.php         Per-table server-side pagination toggle
├── functions/global.php       Helper functions: response(), request_post(), sendEmail(), etc.
├── models/                    All DB models extending BaseModel
├── utils/                     Auth, Database, Middleware, Cloud, etc.
└── portal/admin/
    ├── BaseController.php
    └── {Feature}/
        ├── Router.php
        └── {Feature}Controller.php

v2/
├── shared/
│   ├── layouts/main.php       Main layout (wraps $content)
│   ├── partials/
│   │   ├── head.php           CDN CSS links
│   │   └── footer_scripts.php CDN JS + logout handler
│   ├── assets/js/
│   │   ├── portal-auth.js     PortalAuth auth/token management
│   │   └── portal-api.js      PortalApi AJAX wrapper
│   └── utils/helpers.php      v2_view(), datatable_server_side(), etc.
└── portals/admin/
    ├── bootstrap.php          Defines V2_PATH, LOGIN_HOURS, loads helpers, stubs module_url()
    ├── config.php             Portal config (portal_name, base_url, api_url, etc.)
    ├── sidebar_menu.php       Sidebar navigation groups and items
    ├── login.php              Bridge → pages/login.php
    ├── dashboard.php          Bridge → pages/dashboard/index.php
    ├── pages/                 All portal pages
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

## `.claude/` Structure

| File | Purpose |
|------|---------|
| `.claude/rules/php-standards.md` | Naming, types, formatting, docblocks |
| `.claude/rules/database.md` | Query patterns, prepared statements, soft delete, transactions |
| `.claude/rules/security.md` | OWASP, auth, scope isolation, audit logging |
| `.claude/rules/model-architecture.md` | All DB queries in models; controllers call models only |
| `.claude/rules/frontend-validation.md` | Parsley.js inline form validation |
| `.claude/rules/sync-docs.md` | Update CLAUDE.md after structural changes |
| `docs/IMPLEMENTATION-CHECKLIST.md` | Step-by-step feature implementation guide |

## Slash Commands

| Command | Purpose |
|---|---|
| `/new-endpoint` | Scaffold Router + Controller + Model for a new feature |
| `/new-model` | Scaffold a Model class for a database table |
| `/review-php` | Security + standards audit of changed files |

## Common Workflows

### Adding an API v2 Endpoint

1. Create model in `api/v2/models/{Resource}Model.php` (extends `Models\BaseModel`)
2. Create `{Feature}/Router.php` under `api/v2/portal/admin/`; use `$router = Cloud::router();`
3. Register in `api/v2/config/routes.php`
4. Create `{Feature}Controller.php` extending `Portal\Admin\BaseController`
5. Use REST method names: `index`, `show`, `store`, `update`, `destroy`
6. Use `Middleware::admin()` on the router group
7. Return JSON via `response()` helper

### Adding a v2 Frontend Page

1. Create `v2/portals/admin/pages/{module}/{page}.php`
2. Pattern:
   ```php
   <?php
   declare(strict_types=1);
   require_once dirname(__DIR__, N) . '/bootstrap.php';
   $page_title = 'Page Title';
   $active_tab = 'sidebar_key';
   ob_start();
   ?>
   <!-- page HTML here -->
   <?php
   $content = ob_get_clean();
   include v2_view('shared/layouts/main');
   ```
3. Put page JS in `v2/portals/admin/assets/js/{module}/{page}.js` and load via `$extra_scripts`
4. Add sidebar entry in `v2/portals/admin/sidebar_menu.php` for new modules
5. All AJAX through `window.PortalApi` — never raw `$.ajax`

### Middleware Reference

| Stack | Use case |
|-------|----------|
| `Middleware::admin()` | Admin portal routes |
| `login_portal()` + `rate_limit(10, 60)` | Login endpoint |

```php
// Standard authenticated route
$router->group('/admin/products', static function () use ($router) {
    $router->get('', [ProductController::class, 'index']);
}, Middleware::admin());

// Login endpoint
$router->group('/admin/user', static function () use ($router) {
    $router->post('/login', [AppController::class, 'login']);
}, [login_portal(), rate_limit(10, 60)]);
```

**Never** pass middleware as 3rd arg to `->get()`/`->post()` — always use `->group()`.

### DataTables

All DataTables use the shared `initDataTable(selector, opts)` helper (loaded globally in `footer_scripts.php`). Never hand-roll a `.DataTable({...})` block.

Server-side pagination is toggled per-table in `api/v2/config/datatables.php`:
```php
'admin.products.products' => false,  // false = client-side (default)
```

Flipping to `true` requires the Controller's `index()` to also call `Model::paginate()` and return a `pagination` key — otherwise it silently loads all records.

## Common Pitfalls

1. **`$router = Cloud::router()` required** at the top of every Router.php — missing it fatals with "Call to member function group() on null".
2. **New Router.php must be registered in `routes.php`** — routes are never auto-discovered.
3. **Never put page JS in a `<script>` inside the content buffer** — `main.php` echoes `$content` before `footer_scripts.php` loads jQuery/DataTables. Use `$extra_scripts` → `.js` file.
4. **All AJAX through `window.PortalApi`** — never raw `$.ajax` (DataTables' own `ajax:` config is exempt).
5. **`portal-auth.js` redirects to `BASEURL + '/login'`** (e.g. `/v2/portals/admin/login`) — that path must exist as a PHP file, not just the `pages/login.php` path.
6. **Two-step confirm modals must snapshot form values before hiding the first modal** — `hidden.bs.modal` resets the form immediately, so re-reading `$('#field').val()` in the confirm handler returns empty strings.
7. **Never call `error_log()` directly** — use `Reunite\Logger`.
8. **Never call `EmailService::send()` directly** — use `sendEmail()` from `functions/global.php`.
9. **`module_url()` is stubbed** in `v2/portals/admin/bootstrap.php` to return the fallback URL — DS3 has no `module_versions` table.
10. **Soft delete on all business records** — never hard-delete products, orders, invoices, manufacturers, pharmacies.
11. **`inventory_transactions` is an append-only ledger** — never update or delete rows.
12. **Sort columns must be whitelisted** before forwarding to `orderBy()` in models.

## Modules (Sidebar Navigation)

| Module ID | Label | Sub-modules |
|-----------|-------|-------------|
| `dashboard` | Dashboard | — |
| `out` | Outgoing | orders, quotes, invoices, clients, shiplocs, pricing |
| `in` | Incoming | pos (purchase orders & receipts), vendors |
| `inv` | Inventory | inventory (lots), par (replenishment), transfers, ledger |
| `cat` | Catalogue | products, categories |
| `qa` | Quality & Compliance | recalls, library (documents), audit |
| `sites` | Sites | warehouses, locations |
| `ins` | Insights | reports |
| `adm` | Administration | users, notifications |

## Permissions Matrix

| Permission | Roles |
|-----------|-------|
| order.create / cancel | Admin, Sales |
| order.review | Admin, Sales, Compliance |
| order.verify / approve | Admin, Compliance |
| order.allocate | Admin, Warehouse |
| order.deliver | Admin, Warehouse, Sales |
| inv.write / inv.delete / xfer.write | Admin, Warehouse |
| inv.release | Admin, Compliance |
| po.write | Admin, Purchasing |
| inv.bill | Admin, Finance |
| pricing.write / settings.write | Admin, Finance |
| catalog.write | Admin, Purchasing, Compliance |
| recall.write / license.write / doc.verify | Admin, Compliance |
| record.delete / price.override | Admin only |
| profile.write / doc.upload | All roles |

## Development Commands

```bash
php -l api/v2/portal/admin/App/AppController.php   # syntax check
php api/v2/tests/run_all.php                        # API test suite
```

## Environment Setup

1. Create MySQL databases: `ds3` and `ds3_audit`
2. Run `database-migrate/001_initial_schema.sql` against `ds3`
3. Run `database-migrate/002_audit_schema.sql` against `ds3_audit`
4. Add `127.0.0.1 local.ds3.com` to hosts file (requires admin elevation)
5. Apache vhost in `httpd-vhosts.conf` at `C:/xampp/apache/conf/extra/`
6. Restart Apache
7. Access: `http://local.ds3.com/v2/portals/admin/login`

Default admin login: `admin@ds3.local` / `Admin@123`
