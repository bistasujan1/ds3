# Security Rules (Enforced)

## Authentication & Authorization

- **Bearer token auth on all protected API routes** via `Auth::protect()` middleware — 401 on failure, no bypass.
- **Role check before sensitive operations** — sidebar visibility is not authorization; gate the API too.
- **Never trust scope/ID passed by the client** without verifying it belongs to the authenticated user. Admin portal reads all, but scope must still be validated in the middleware.
- **User data lives in `Cloud::get('user')`** after `Auth::protect()` runs. Read as:
  - `Cloud::get('user')['id']` — user ID
  - `Cloud::get('user')['role']` — primary role
  - `Cloud::get('user')['roles']` — all roles array
  - No `Auth::getUserId()` static method exists.

## Credentials & Secrets

- **Never hardcode credentials or API keys** — all secrets from `$_SERVER` env vars.
- **DB credentials** come from `config.php` constants (`DB_HOST_NAME`, `ADMIN_DB_USER_NAME`, etc.) sourced from `$_SERVER` — this is correct and should not be changed to `config()`.
- **Never commit `.env` files** — they are gitignored. Use `.env.example` for templates.

## Input Validation

- **Validate all user input** in the Controller using `$this->validate()` before passing to models.
- **Whitelist sort columns** before passing to `orderBy()` — never forward raw `sort_by` from the client.
- **Never use `unserialize()` on untrusted input** — use `json_decode($input, true)` and validate it's an array.
- **No command injection** — never `exec()`/`system()`/`passthru()` with unsanitized input.

## SQL Security

- **Prepared statements only** — MysqliDb `where()` / `rawQuery()` with bound params.
- **Never concatenate user input** into queries.

## XSS

- **Escape output** with `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')` in PHP.
- v2 API responses set `Content-Type: application/json` — no HTML escaping needed in JSON responses.

## Passwords

- `password_hash($password, PASSWORD_DEFAULT)` only — never md5/sha1.
- Verify with `password_verify()`.

## Logging & Error Responses

- **Never log PII or secrets** — log identifiers (`user_id`, `order_id`) and category only. No passwords, tokens, full emails, or patient data in logs.
- **Generic error responses to clients** — `'An error occurred'`; full detail logged server-side only. Never return stack traces, SQL, or internal paths.

## Audit Logging

- **Audit sensitive business actions** to the `ds3_audit` database: actor_id, action, resource_type, resource_id, old_values, new_values, ip_address, timestamp.
- Actions requiring audit: invoice paid/voided, order confirmed/cancelled, inventory adjusted, user created/deactivated.

## Notifications

- **Never call `EmailService::send()` directly** — use `sendEmail()` from `api/v2/functions/global.php`, which routes through `Utils\NotificationGuard` so non-prod environments never reach real recipients.
- **Never call `SmsService` directly** — use `sendSms()` from `api/v2/functions/global.php`.

## Code Review Checklist

- [ ] No hardcoded credentials or API keys
- [ ] All user input validated before use
- [ ] All DB queries use prepared statements
- [ ] Output escaped before rendering
- [ ] Auth required on all protected endpoints
- [ ] Role/scope verified before accessing data
- [ ] Sensitive business actions logged to audit DB
- [ ] Errors don't expose system details
- [ ] Sort columns whitelisted
- [ ] No SQL/XSS/command injection vulnerabilities
