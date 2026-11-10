# DS3 Memory Index

## Rules (auto-loaded every session)
- [PHP Standards](rules/php-standards.md) — PascalCase classes, camelCase methods, REST controller names, docblocks
- [Database](rules/database.md) — Prepared statements, soft delete, transactions, inventory ledger pattern
- [Security](rules/security.md) — Auth, scope, audit logging, no hardcoded credentials
- [Model Architecture](rules/model-architecture.md) — All DB queries in models; controllers call models only
- [Frontend Validation](rules/frontend-validation.md) — Parsley.js inline form validation on every form
- [Sync Docs](rules/sync-docs.md) — Update CLAUDE.md after structural changes

## Commands (invocable as slash commands)
- [/new-endpoint](commands/new-endpoint.md) — Scaffold Router + Controller + Model
- [/new-model](commands/new-model.md) — Scaffold a Model class
- [/review-php](commands/review-php.md) — Security + standards audit

## Project
- DS3 is a pharmaceutical distribution platform: manufacturers → inventory → pharmacies
- v2-only architecture — no v1 legacy code anywhere
- Phase 1 target: mid-December 2026 — Order Management (admin + client ordering portal)
- Admin portal: `v2/portals/admin/` | Client portal: `v2/portals/client/` (both Phase 1)
- API: `api/v2/portal/admin/`
- Local URL: `http://local.ds3.com/v2/portals/admin/login`
- Default admin: `admin@ds3.local` / `Admin@123`
- [Phase 1 kickoff facts](memory/project_ds3_phase1.md) — scope, deadline, warehouses, contacts

## Key Architectural Facts
- `module_url()` is stubbed in `v2/portals/admin/bootstrap.php` — returns fallback URL, no DB table
- `V2_PATH` is defined in `v2/portals/admin/bootstrap.php` as `dirname(__DIR__, 2)` → `v2/`
- All CSS/JS loaded via CDN in `head.php` and `footer_scripts.php` — no local vendor bundle
- `portal-auth.js` redirects to `BASEURL + '/login'` = `/v2/portals/admin/login` — bridge file exists
- `inventory_transactions` is an append-only ledger — never update or delete rows
- Bearer token stored in `localStorage` as `admin_token` by `PortalAuth`
- `inventory_lots.status` naming: always `Quarantine` (noun), `Reserved` (not Allocated) — FEFO lot selection required when allocating
- `orders.status` has 14 states; granular warehouse steps (Pick List Ready → Pick Approved → Picked → Packed → Shipped → etc.) from workflow v5
- Lot reservation must be atomic (transaction) — never increment `qty_reserved` outside a transaction
- `Manager` role = approval authority for POs, credit memos, escalations (distinct from `Admin`)
- Client portal (`/v2/portals/client/`) is Phase 1 scope — highest priority unbuilt piece
- Tables added from workflow v5 audit: `pick_list_items`, `client_users`, `invoice_reminders`, `document_requirements`, `capa_log`
