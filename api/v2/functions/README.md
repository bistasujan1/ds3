# api/v2/functions/

Global PHP helper functions automatically loaded by `bootstrap.php`. These are procedural functions available throughout all v2 code without requiring explicit imports.

## Files

| File | Purpose |
|------|---------|
| `global.php` | Core helpers — `request_post()`, `request_query()`, `request_param()`, `response()`, `auth()`, `login_portal()`, `role()`, `rate_limit()`, `webhook()`, `cron_auth()`, and middleware factory functions |
| `utility.php` | Miscellaneous utility helpers — string, array, and formatting helpers used across portal handlers |
