# v2/shared/assets/js/

Shared JS loaded on every v2 page (both `main` and `auth` layouts), via `shared/partials/head.php`.

## Files

| File | Purpose |
|------|---------|
| `portal-auth.js` | `window.PortalAuth` — token/session storage (localStorage), `attachAjaxHeaders()` ($.ajaxSetup Bearer + User-Data headers), login/logout form wiring, v1 token fallback |
| `portal-utils.js` | `window.PortalUtils` — hasRole, toasts (`showSuccess`, `showError`, `showWarning`), ajax loader, form reset (Parsley/Select2), modal reset binding, TTL cache (`createTtlCache`, `ttlCache`) |
| `portal-api.js` | `window.PortalApi` — shared AJAX wrapper. **Every page's AJAX call should go through this, not raw `$.ajax`.** |

## PortalApi

Thin wrapper over `$.ajax` so page JS stops repeating `APIURL + endpoint` / `contentType: 'application/json'` /
`JSON.stringify(data)` / default error-toast boilerplate. Auth headers are already attached globally by
`PortalAuth.attachAjaxHeaders()` — this file does not touch auth.

```javascript
// GET — params is a plain object or null
PortalApi.get('admin/users', { active: 'yes' }, {
    success: function (res) { /* ... */ }
});

// POST / PUT / PATCH — data is JSON-encoded automatically; pass null for no body
PortalApi.post('admin/users', { first_name: 'A', last_name: 'B' }, {
    success: function (res) {
        if (PortalApi.ok(res, 201)) { toastr.success('Created'); table.ajax.reload(null, false); }
        else { $('#formError').text(res.message || 'Failed').show(); }
    },
    error: function () { $('#formError').text('Request failed').show(); },
    complete: function () { PortalApi.setBtnLoading('#submitBtn', '#btnText', '#btnLoader', false); }
});

// DELETE — no body
PortalApi.del('admin/users/' + id, {
    success: function (res) { if (PortalApi.ok(res, 200)) { table.ajax.reload(null, false); } }
});

// DELETE (or any verb) with a JSON body — use request() directly
PortalApi.request('DELETE', 'admin/rrx-assist/discounts/' + id, { guid: guid }, { success: function (res) { /* ... */ } });

// File upload — pass a FormData instance, auto-detected (processData/contentType disabled automatically)
var fd = new FormData();
fd.append('file', fileInput.files[0]);
PortalApi.post('admin/rrx-assist/enrollments/' + id + '/files', fd, { success: function (res) { /* ... */ } });
```

**`opts`**: `{ success, error, complete, errorMessage }`.
- Omit `error` entirely to get a default `toastr.error()` using the server's `message` field, or `errorMessage` as
  the fallback string if the server gave none. Only pass a custom `error` function when you need to update page
  state (e.g. write into an inline `<div class="alert">` instead of a toast, or revert a checkbox).
- `PortalApi.ok(res, code)` — `res && res.code === code`. Optional sugar for the very common
  `if (res.code === 201) {...} else {...}` check.
- `PortalApi.setBtnLoading(btnSel, textSel, loaderSel, loading)` — selectors need the `#`/`.` prefix (unlike the old
  per-page `setBtnLoading(id, ...)` helpers many pages hand-rolled, which took bare ids). If a page already has a
  local `setBtnLoading(id, ...)` used all over its call sites, keep the local wrapper and have it delegate:
  ```javascript
  function setBtnLoading(btnId, textId, loaderId, loading) {
      PortalApi.setBtnLoading('#' + btnId, '#' + textId, '#' + loaderId, loading);
  }
  ```
  rather than rewriting every call site.

**Do not** use `PortalApi` for the DataTables `ajax:` config object itself (the `{url, type, data, dataSrc}` block
passed to `.DataTable({...})` or `initDataTable(...)`) — that's a DataTables-owned lifecycle, not a raw `$.ajax`
call, and is out of scope for this wrapper. `PortalApi` is for the plain `$.ajax` calls pages made outside of
DataTables: creates, updates, deletes, dropdown population, status toggles, file uploads.
