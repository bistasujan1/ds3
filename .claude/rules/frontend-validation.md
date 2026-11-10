# Frontend Form Validation

All DS3 forms must show **inline per-field errors** on submit — never rely on browser validation bubbles or a single toastr for field-level errors.

## Library: Parsley.js

Parsley is loaded globally via CDN in every portal page (via `footer_scripts.php`). Do not add a separate `<script src="parsley...">` tag.

## HTML Attributes

```html
<!-- Required text field -->
<input type="text" class="form-control" id="product_name" required>

<!-- Email with custom message -->
<input type="email" class="form-control" id="email" required
       data-parsley-type="email"
       data-parsley-type-message="Enter a valid email address.">

<!-- Phone pattern -->
<input type="text" class="form-control" id="phone" required
       data-parsley-pattern="^\d{3}-\d{3}-\d{4}$"
       data-parsley-pattern-message="Format: ddd-ddd-dddd"
       placeholder="ddd-ddd-dddd">

<!-- Numeric min/max -->
<input type="number" class="form-control" id="quantity" required
       data-parsley-min="1" data-parsley-max="99999">
```

### Select2 Fields

Always add an explicit errors container — Parsley appends its `<ul>` after the element, which breaks inside a select2 wrapper:

```html
<div class="col-md-9">
    <select id="pharmacy_id" class="form-control" required
            data-parsley-errors-container="#pharmacy_id_errors"></select>
    <span id="pharmacy_id_errors"></span>
</div>
```

## JavaScript: Three Required Calls

### 1. Init — on page ready or modal show

```js
// Plain form
$('#createForm').parsley();

// Modal form — init on each open to pick up dynamic fields
$(document).on('show.bs.modal', '#createModal', function () {
    $('#createForm').parsley();
});
```

### 2. Validate — on submit, before any API call

```js
$('#createSubmitBtn').on('click', function (e) {
    e.preventDefault();
    if (!$('#createForm').parsley().validate()) return;
    // PortalApi.post(...)
});
```

### 3. Reset — on modal hide

```js
$(document).on('hidden.bs.modal', '#createModal', function () {
    $('#createForm').parsley().reset();
    $('#createForm')[0].reset();
});
```

## Two-Step Confirm Modal Pattern

When a create/edit modal leads to a "confirm details" step, **snapshot form values before hiding the first modal**:

```js
$('#createSubmitBtn').on('click', function (e) {
    e.preventDefault();
    if (!$('#createForm').parsley().validate()) return;

    // Snapshot NOW — hidden.bs.modal resets the form the instant it closes
    var pendingOrder = {
        pharmacy_id: $('#pharmacy_id').val(),
        notes: $('#notes').val(),
        // ... all fields
    };

    $('#createModal').modal('hide');
    // Show confirm modal, submit pendingOrder from the snapshot
    $('#confirmSubmitBtn').off('click').on('click', function () {
        PortalApi.post('admin/orders', pendingOrder, { ... });
    });
    $('#confirmModal').modal('show');
});
```

**Never** re-read `$('#field').val()` inside the confirm-submit handler — the form was reset when the first modal closed.

## Common Parsley Attributes

| Attribute | Validates |
|-----------|-----------|
| `required` | Field not empty |
| `data-parsley-type="email"` | Valid email format |
| `data-parsley-type="digits"` | Digits only |
| `data-parsley-type="number"` | Numeric |
| `data-parsley-pattern="regex"` | Regex match |
| `data-parsley-minlength="N"` | Min char count |
| `data-parsley-maxlength="N"` | Max char count |
| `data-parsley-min="N"` | Numeric minimum |
| `data-parsley-max="N"` | Numeric maximum |
| `data-parsley-equalto="#other"` | Must match other field |
| `data-parsley-errors-container="#id"` | Where to render errors |

## Rules

- **Always add Parsley before writing the submit handler** — never let the API return 400 for something the client can catch.
- **Never show toastr for a field-level validation error** — toastr is for API/network failures after validation passed.
- **Select2 fields always need `data-parsley-errors-container`**.
- **Reset form state on modal hide** — stale red borders must not persist.
- **Init once on page load; call only `.validate()` on submit and `.reset()` on hide.**
- **Two-step confirm flows must snapshot values before hiding the create/edit modal.**
