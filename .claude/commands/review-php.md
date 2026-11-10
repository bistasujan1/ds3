# /review-php

Security and standards audit of changed PHP files in DS3.

## Usage

```
/review-php
/review-php api/v2/portal/admin/Orders/OrderController.php
```

## What to Check

### Security

- [ ] No hardcoded credentials or API keys
- [ ] All user input validated before use (`$this->validate()` in Controller)
- [ ] All DB queries use prepared statements (`MysqliDb::where()` / `rawQuery()`)
- [ ] Sort columns whitelisted before `orderBy()`
- [ ] Auth middleware on all routes (`Middleware::admin()`)
- [ ] No SQL/XSS/command injection vulnerabilities
- [ ] Sensitive actions logged to audit DB
- [ ] Error responses are generic — no stack traces or SQL exposed to client
- [ ] No `error_log()` — use `Logger::error()`
- [ ] No direct `EmailService::send()` — use `sendEmail()`

### Model Architecture

- [ ] No `$this->db->` calls in Controller classes
- [ ] All models extend `Models\BaseModel`
- [ ] Query methods are in model, not controller
- [ ] Controller has model injection in `__construct()`
- [ ] `findWhere()` / `findById()` preferred over inline queries

### PHP Standards

- [ ] `declare(strict_types=1)` at top of every file
- [ ] Proper namespace matching directory structure
- [ ] Controller methods named: `index`, `show`, `store`, `update`, `destroy`
- [ ] No verb in URL path (HTTP method is the verb)
- [ ] Explicit return types on all methods
- [ ] Docblocks on class and all public/protected methods
- [ ] No `SELECT *` — specify columns
- [ ] `orderBy()` always has explicit direction (ASC/DESC)

### DS3-Specific

- [ ] Soft delete used (`deleted_at`) — no hard deletes on business records
- [ ] Inventory transactions never updated or deleted (append-only ledger)
- [ ] `$router = Cloud::router();` at top of every Router.php
- [ ] New Router.php registered in `api/v2/config/routes.php`
- [ ] Parsley.js validation on all frontend forms

## Automated Syntax Check

```bash
php -l api/v2/portal/admin/Orders/OrderController.php
```

(Runs automatically on every file save via `.claude/settings.json` hook.)
