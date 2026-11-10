# v2/shared/partials/

HTML snippets included by layouts — not by page files directly. Each partial reads configuration from the portal's `config.php` via `portal_config()`.

## Files

| File | Purpose |
|------|---------|
| `head.php` | Renders the `<head>` element — loads Bootstrap 3 CSS, portal skin CSS, favicon, and meta tags driven by `portal_config()` |
| `navbar.php` | Top navigation bar — portal title, user menu, and logout link |
| `sidebar.php` | Data-driven sidebar renderer — iterates the portal's `sidebar_menu.php` array and highlights the active tab via `$active_tab` |
| `footer_scripts.php` | Loads vendor JS (jQuery, Bootstrap 3, DataTables, Select2, ApexCharts, toastr) plus portal `extra_scripts` |
| `footer.php` | Closes the page — `</body></html>` |
| `footer_tag.php` | Alternate footer tag partial for pages that need a different closing structure |
