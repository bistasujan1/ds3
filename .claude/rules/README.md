# .claude/rules/

Coding standards and architectural rules automatically loaded by Claude Code at the start of every session.

## Files

| File | Purpose |
|------|---------|
| `php-standards.md` | Naming conventions, type hints, formatting, docblocks, error handling |
| `database.md` | Query patterns, prepared statements, soft delete, transactions, pagination |
| `security.md` | OWASP Top 10, auth patterns, scope isolation, audit logging |
| `model-architecture.md` | Enforces all DB queries in models; controllers call models only |
| `frontend-validation.md` | Parsley.js inline form validation pattern for all v2 portal forms |
| `sync-docs.md` | Rule requiring CLAUDE.md and `.claude/` files to be updated after structural changes |
