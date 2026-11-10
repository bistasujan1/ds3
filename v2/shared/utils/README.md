# v2/shared/utils/

PHP helper functions for v2 frontend pages. Included via `require_once v2_view('shared/utils/...')` at the top of page files.

## Files

| File | Purpose |
|------|---------|
| `helpers.php` | Core helpers — `v2_view(path)` resolves template paths, `asset(path)` generates asset URLs, `portal_url(portal, page)` generates portal page URLs, `portal_config(key)` reads the active portal config |

Auth is client-side only (no PHP session): each portal's `{portal}.js` reads its Bearer token from
`localStorage` and redirects to login if missing; login pages check `localStorage` for an existing
token and redirect to the dashboard if present. There is no `session.php`/`auth.php` — matches the
v1 pattern of storing the token in `localStorage` and gating pages purely in JS.
