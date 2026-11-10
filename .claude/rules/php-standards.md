# PHP Code Standards

Mandatory style and pattern rules for all PHP code in DS3.

## File Structure & Namespacing

Every file **must** start with:

```php
<?php

declare(strict_types=1);

namespace Portal\Admin\Inventory;

use Reunite\Framework\Cloud;
use Models\ProductModel;
// ... other imports
```

**Rules**:
- `declare(strict_types=1);` on line 1 after opening tag
- Namespace declaration on line 3
- All classes namespaced — PSR-4 path matches namespace
- `Portal\Admin\*` → `api/v2/portal/admin/*/`
- `Models\*` → `api/v2/models/`
- `Utils\*` → `api/v2/utils/`

## Naming Conventions

### Classes & Types
- **Format**: `PascalCase`
- **Examples**: `ProductModel`, `SalesOrderController`, `InventoryService`

### Methods & Functions
- **Format**: `camelCase` verb+noun
- **Examples**: `findByPharmacy()`, `createWithItems()`, `updateStatus()`

### Controller Method Names (REST standard)

| HTTP | Method | Purpose |
|------|--------|---------|
| GET list | `index()` | Paginated/filtered list |
| GET single | `show()` | Fetch by ID |
| POST | `store()` | Create new resource |
| PUT/PATCH | `update()` | Update existing |
| DELETE | `destroy()` | Soft delete |

Custom actions: `confirm()`, `ship()`, `void()`, `generatePdf()` etc.

**Never use**: `getAll()`, `create()`, `edit()`, `delete()`

### Properties & Variables
- **Format**: `camelCase`, always explicit visibility
- Type hints required on all properties in v2

### Constants
- **Format**: `UPPER_SNAKE_CASE`
- Use `define()` in config or class constants

### Database Tables & Columns
- **Format**: `snake_case`
- Tables: plural (`products`, `sales_orders`, `invoice_payments`)
- Columns: singular (`product_id`, `order_number`, `created_at`)

## Formatting

- **Indentation**: 4 spaces (no tabs)
- **Braces**: opening brace same line as statement
- **Strings**: single quotes for literals, double quotes for interpolation

## Type Hints

Always declare return types and parameter types:

```php
public function findByPharmacy(int $pharmacyId, string $status = null): array
{
    // ...
}

public function store(): void
{
    // ...
}
```

## Docblocks

Required on every class and every public/protected method:

```php
/**
 * Find all sales orders for a pharmacy, optionally filtered by status.
 *
 * @param int         $pharmacyId  Pharmacy to scope results to
 * @param string|null $status      Optional status filter
 * @return array<int, array<string, mixed>>  Empty array if none found
 */
public function findByPharmacy(int $pharmacyId, ?string $status = null): array
```

**Tags required**: `@param` (every param), `@return` (always, even `void`), `@throws` (any propagating exception).

**Content**: describe WHY and constraints, not what the code does. Never restate the signature.

## Error Handling

```php
// ✓ Catch specific exceptions, log full detail, return generic message
try {
    $id = $this->productModel->insert($data);
} catch (Exception $e) {
    Logger::error($e->getMessage(), ['location' => 'ProductController::store', 'exception' => get_class($e)]);
    response(['code' => 500, 'message' => 'Failed to create product']);
}

// ✗ Never expose internals to client
response(['code' => 500, 'message' => $e->getMessage()]);
```

## Logging

Always use `Reunite\Logger` (PSR-3) — never `error_log()`:

```php
use Reunite\Logger;

Logger::error($e->getMessage(), [
    'location'  => 'InventoryController::receive',
    'exception' => get_class($e),
    'file'      => $e->getFile(),
    'line'      => $e->getLine(),
]);

Logger::info('Purchase order received', ['po_id' => $id, 'manufacturer_id' => $manufacturerId]);
```

## Anti-Patterns

| Pattern | Problem | Fix |
|---------|---------|-----|
| `$_REQUEST` | Ambiguous | Use `request_post()` / `request_query()` |
| `eval()` | Security | Never |
| `error_log()` | Not PSR-3 | Use `Logger::error()` |
| `SELECT *` | Loads unused | Specify columns |
| Magic numbers | Unmaintainable | Named constants |
| Verb in URL path | Non-REST | HTTP method is the verb |
