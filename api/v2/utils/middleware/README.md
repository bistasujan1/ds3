# api/v2/utils/middleware/

PSR-style middleware classes for the v2 API. Each middleware implements `before()` (runs before the handler) and optionally `after()` (runs after). Middleware must `return false` to halt the chain — never call `exit()`.

Use `Middleware::admin()`, `Middleware::pharmacy()`, etc. from `utils\Middleware` rather than composing these classes manually.

## Files

| File | Purpose |
|------|---------|
| `Middleware.php` | Abstract base class defining the `before()` and `after()` lifecycle hooks |
| `AuthMiddleware.php` | Bearer token guard — calls `Auth::protect()` to verify the token and set the authenticated user on `Cloud` |
| `CronMiddleware.php` | IP allowlist and shared-secret guard for scheduled job routes |
| `PortalScopeMiddleware.php` | Asserts `Cloud('api_scope')` matches the expected portal — must run after `AuthMiddleware` |
| `RateLimitMiddleware.php` | APCu-based per-IP/URI throttle — fails open if APCu is unavailable; do not rely on it as the sole protection |
| `PatientAuthMiddleware.php` | Patient portal JWT guard (Bearer + Platform header) — sets scope via auth; use via `patient_auth()`, not `Middleware::patient()` |
| `AccessLogMiddleware.php` | Thin wrapper around `Utils\PortalAccessLog` (audit_db `user_login_history`) — use via `access_log()` |
| `ServiceMiddleware.php` | `X-Service-Key` API key guard for internal bot/service routes; validates against `service_api_keys` table |
| `WebhookMiddleware.php` | Vendor signature guard for inbound webhooks — delegates to `WebhookAuth` for HMAC/ECDSA verification |
