# DS3 Feature Implementation Checklist

Step-by-step guide for implementing a new feature module in DS3.

---

## Phase 1 — Plan

Before writing any code:

- [ ] Identify the DB tables involved (existing or new)
- [ ] List all API endpoints needed (GET list, GET single, POST, PUT, DELETE, any custom actions)
- [ ] List all frontend pages/modals needed
- [ ] Identify business rules (e.g. can't delete a product with active orders)
- [ ] Identify audit log entries required

---

## Phase 2 — Database

If new tables are needed:

- [ ] Create `database-migrate/00N_feature_name.sql` with CREATE TABLE statements
- [ ] Include `deleted_at DATETIME NULL` on all business entity tables
- [ ] Add `created_at`, `updated_at` timestamps
- [ ] Add appropriate indexes (foreign keys, frequently filtered columns)
- [ ] Run migration against local `ds3` database

---

## Phase 3 — Model

Create `api/v2/models/{Resource}Model.php`:

- [ ] Extends `Models\BaseModel`
- [ ] `protected string $table = 'table_name'`
- [ ] `declare(strict_types=1)` and proper namespace (`namespace Models`)
- [ ] Docblock on the class
- [ ] Custom `find*()` methods for domain-specific queries (JOINs, filters)
- [ ] Never call `$this->db` from a Controller — all queries in model methods
- [ ] `findActive()` or equivalent that filters `deleted_at = null`
- [ ] `orderBy()` with explicit direction on all query methods

---

## Phase 4 — Controller

Create `api/v2/portal/admin/{Feature}/{Feature}Controller.php`:

- [ ] `declare(strict_types=1)` and namespace `Portal\Admin\{Feature}`
- [ ] Extends `Portal\Admin\BaseController`
- [ ] `use Models\{Resource}Model` imports
- [ ] Model injected in `__construct()` with `parent::__construct()`
- [ ] REST method names: `index`, `show`, `store`, `update`, `destroy`
- [ ] `$this->validate($data, [...])` before any DB write
- [ ] All DB calls go through injected model — no `$this->db->`
- [ ] Soft delete in `destroy()` — set `deleted_at`, never hard-delete
- [ ] Sort column whitelist in `index()` before `paginate()`
- [ ] `try/catch` with `Logger::error()` and generic error response
- [ ] Audit log for sensitive actions (invoice paid, order cancelled, etc.)
- [ ] Docblocks on class and all public methods

---

## Phase 5 — Router

Create `api/v2/portal/admin/{Feature}/Router.php`:

- [ ] `declare(strict_types=1)`
- [ ] `use Reunite\Framework\Cloud` and `use utils\Middleware`
- [ ] `$router = Cloud::router();` — required, never skip
- [ ] `$router->group(...)` with `Middleware::admin()` — never pass middleware to `->get()`/`->post()`
- [ ] REST-style URL paths — no verbs in path segments
- [ ] Register in `api/v2/config/routes.php`

---

## Phase 6 — Frontend Page

Create `v2/portals/admin/pages/{module}/index.php`:

- [ ] `declare(strict_types=1)` at top
- [ ] `require_once dirname(__DIR__, N) . '/bootstrap.php'` with correct depth
- [ ] `$page_title` and `$active_tab` set before `ob_start()`
- [ ] Bootstrap 3 layout: `panel`, `panel-heading`, `panel-body`
- [ ] DataTable initialized with `initDataTable(selector, opts)` — never hand-rolled
- [ ] `$extra_scripts = ['/v2/portals/admin/assets/js/{module}/index.js']` before `ob_get_clean()`
- [ ] `include v2_view('shared/layouts/main')` at the bottom
- [ ] No `<html>`, `<head>`, `<body>` tags in the page file

---

## Phase 7 — Page JavaScript

Create `v2/portals/admin/assets/js/{module}/index.js`:

- [ ] All AJAX through `window.PortalApi` — never raw `$.ajax`
- [ ] DataTable initialized inside `$(function () {...})`
- [ ] Parsley.js initialized on all forms: `$('#createForm').parsley()`
- [ ] Parsley reset on modal hide: `$('#createForm').parsley().reset()`
- [ ] Parsley validate on submit before API call: `if (!$('#createForm').parsley().validate()) return`
- [ ] Two-step confirm flow: snapshot form values to object before `.modal('hide')`, never re-read `$('#field').val()` in the confirm handler
- [ ] Select2 on dropdown fields with `data-parsley-errors-container` on the select element
- [ ] `toastr.success()` / `toastr.error()` for feedback after API calls
- [ ] `swalConfirm()` for destructive actions (delete, cancel order, void invoice)
- [ ] No `<script>` tags in the page — all JS in this file, loaded via `$extra_scripts`

---

## Phase 8 — Sidebar

If this is a new module:

- [ ] Add sidebar entry to `v2/portals/admin/sidebar_menu.php`
- [ ] Match `'tab'` value to `$active_tab` in the page

---

## Phase 9 — Test

- [ ] `php -l api/v2/portal/admin/{Feature}/{Feature}Controller.php` — syntax check
- [ ] `php -l api/v2/portal/admin/{Feature}/Router.php` — syntax check
- [ ] `php -l api/v2/models/{Resource}Model.php` — syntax check
- [ ] Manual test: list page loads, create works, edit works, delete soft-deletes
- [ ] Verify sort + search in DataTable
- [ ] Verify Parsley validation shows inline errors (required fields, formats)
- [ ] Verify toastr feedback on success and error
- [ ] Verify deleted records don't appear in list (soft delete filter)

---

## Inventory-Specific Checklist

When a feature modifies stock levels (receive PO, fulfill SO):

- [ ] Read current state from `inventory.quantity_on_hand`
- [ ] Update `inventory` row with new quantities
- [ ] Write a row to `inventory_transactions` with `transaction_type`, `quantity`, `quantity_before`, `quantity_after`, `reference_type`, `reference_id`, `created_by`
- [ ] Wrap both updates in a transaction
- [ ] Never update or delete `inventory_transactions` rows

---

## Invoice-Specific Checklist

- [ ] Invoice status: `draft` → `sent` → `paid` / `overdue` / `voided`
- [ ] Record payment in `invoice_payments` (append-only)
- [ ] Audit log on status change
- [ ] Never hard-delete invoices or payments
