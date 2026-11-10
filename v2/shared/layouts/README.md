# v2/shared/layouts/

Master page templates. Portal page files include one of these at the end — never write `<html>`, `<head>`, or `<body>` tags directly in page files. The layout reads `$content`, `$page_title`, `$active_tab`, and optionally `$extra_scripts` from the page scope.

## Files

| File | Purpose |
|------|---------|
| `main.php` | Authenticated portal layout — renders `<head>`, navbar, sidebar, `$content`, and footer scripts for logged-in users |
| `auth.php` | Login and unauthenticated page layout — minimal layout without sidebar or navbar |
| `error.php` | Error page layout — renders 404 and 500 error messages with a clean minimal frame |
