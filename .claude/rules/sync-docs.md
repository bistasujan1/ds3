# Sync Docs on Structural Changes

Whenever a structural change is made to DS3, **always update `CLAUDE.md` and `.claude/` files** in the same session before finishing.

## What Counts as a Structural Change

- New portal added
- New Router.php or middleware pattern introduced
- New model or shared utility class added
- Directory structure changes
- Auth/scope pattern changed
- New slash command created
- New rule file added

## Which Files to Update

| Change type | Update these files |
|---|---|
| New portal | `CLAUDE.md` § Directory Map + Portal Setup |
| New Router.php pattern | `CLAUDE.md` § Middleware Reference, `.claude/commands/new-endpoint.md` |
| New shared utility | `CLAUDE.md` § Directory Map |
| New slash command | `CLAUDE.md` § Slash Commands table |
| New rule file | `CLAUDE.md` § Rules, `.claude/MEMORY.md` |
| Directory structure | `CLAUDE.md` § Directory Map |
| Auth/scope changes | `CLAUDE.md` § Pitfalls + Middleware Reference |

## How to Apply

1. Identify which categories above apply
2. Open each affected file and update the relevant section
3. Keep examples in sync with actual code — stale examples cause future bugs
4. Update `.claude/MEMORY.md` index if a new rule/command file was added
