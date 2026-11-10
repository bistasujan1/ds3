# v2/shared/

Shared frontend building blocks used by all portals. Contains the complete layout system, reusable partials, UI components, and PHP helper utilities. All portal pages reference files from this directory via the `v2_view()` helper.

## Subdirectories

| Directory | Purpose |
|-----------|---------|
| `layouts/` | Master page templates — authenticated main layout, login/auth layout, error layout |
| `partials/` | Reusable HTML snippets included by layouts — `<head>`, navbar, sidebar, footer |
| `components/` | Reusable UI components — confirmation modal, loading overlay |
| `utils/` | PHP helper functions — `v2_view()`, `asset()`, `portal_url()`, `portal_config()`. Auth is client-side only (localStorage token, no PHP session). |
