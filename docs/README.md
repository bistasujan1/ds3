# DS3 — Distribution & Supply System

DS3 is a pharmaceutical distribution platform that:
- Receives goods from **manufacturers** (inbound supply chain)
- Manages **inventory** at the DS3 warehouse
- Fulfills **sales orders** to **pharmacies** (outbound distribution)
- Generates **invoices** and tracks payments

## Tech Stack

- PHP 7.4+ with `declare(strict_types=1)` throughout
- MySQL 8+, MysqliDb wrapper
- Auth: Bearer token, SHA256
- Frontend: Bootstrap 3 + AdminLTE 2 skin, Font Awesome 4, jQuery, DataTables, select2, toastr, sweetalert2, Parsley.js
- Reunite framework packages (PSR-4 namespaced) — framework, logger, validator, cache, email, sms, storage, pdf, payment, spreadsheet, notification
- Architecture: PSR-4 namespaced throughout (no legacy v1 code)

## Directory Structure

```
ds3/
├── api/v2/                    # REST API (PSR-4 namespaced)
│   ├── config/                # routes.php, datatables.php
│   ├── functions/             # global helpers (global.php, constants.php, utility.php)
│   ├── models/                # database models (all extend Models\BaseModel)
│   ├── portal/
│   │   ├── BaseController.php # shared validate() + success() helpers
│   │   └── admin/             # admin portal controllers + routers
│   ├── services/              # business logic services
│   ├── system/                # framework core (Cloud, Loader)
│   └── utils/                 # Auth, Database, Middleware, etc.
├── v2/portals/admin/          # admin frontend (PHP pages)
│   ├── pages/                 # dashboard, inventory, orders, invoices, partners
│   │   └── login.php
│   ├── assets/js/admin/       # portal JS (admin.js, admin-auth.js, per-page files)
│   ├── config.php             # portal meta (title, URLs, scripts)
│   ├── sidebar_menu.php       # sidebar navigation definition
│   └── bootstrap.php          # frontend bootstrap (loaded by every page)
├── v2/shared/                 # shared layouts, partials, assets (all portals)
│   ├── layouts/main.php       # authenticated page shell
│   ├── layouts/auth.php       # login/unauthenticated shell
│   └── assets/js/portal-api.js # PortalApi — all page AJAX goes through this
├── docs/                      # project documentation
├── database-migrate/          # SQL migration files (numbered, in order)
├── storage/logs/              # application logs (gitignored)
├── config.php                 # environment constants (DB creds, log config)
├── constant.php               # path constants (root_path, assets_path, api_path)
├── composer.json              # Composer dependencies
└── .env.example               # environment variable template
```

## Portals

| Portal | URL | Purpose |
|--------|-----|---------|
| Admin  | `http://local.ds3.com/v2/portals/admin/` | Internal DS3 staff — full system access |

## API Base URL

`http://local.ds3.com/api/v2/`

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET    | `/admin/health` | None | Health check |
| POST   | `/admin/user/login` | None | Admin login → returns Bearer token |

Protected routes require `Authorization: Bearer <token>` + `Portal: admin` headers.

## Development Setup

See `docs/setup.md` for step-by-step instructions.

1. Add `127.0.0.1 local.ds3.com` to `C:\Windows\System32\drivers\etc\hosts`
2. Add vhost to `C:\xampp\apache\conf\extra\httpd-vhosts.conf`
3. Add `SetEnv` vars to the vhost block (see `.env.example`)
4. Restart Apache
5. Create MySQL databases `ds3` and `ds3_audit`
6. Import `database-migrate/001_initial_schema.sql`
7. Run `composer install`

## Running Tests

```bash
php api/v2/tests/run_all.php
```

## Key Architecture Rules

- **All DB queries in models** — Controllers call model methods only, never `$this->db` directly
- **All AJAX through `PortalApi`** — `PortalApi.get/post/put/patch/del()` in page JS; never raw `$.ajax`
- **Page JS in `.js` files** — loaded via `$extra_scripts`, never inline `<script>` in page content buffer
- **Parsley.js on every form** — inline per-field validation before any API call
- **`sendEmail()`/`sendSms()` only** — never call library services directly (bypasses env guards)
- **`Reunite\Logger` only** — never `error_log()` directly
