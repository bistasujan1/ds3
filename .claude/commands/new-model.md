# /new-model

Scaffold a new Model class for a database table in DS3.

## Usage

```
/new-model Manufacturer
/new-model PurchaseOrder
/new-model InvoicePayment
```

## Template

`api/v2/models/{Name}Model.php`:

```php
<?php

declare(strict_types=1);

namespace Models;

/**
 * Repository for the {table} table.
 */
class {Name}Model extends BaseModel
{
    protected string $table = '{table}';

    /**
     * Find all active {entities}.
     *
     * @return array
     */
    public function findActive(): array
    {
        $this->db->where('deleted_at', null);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get($this->table) ?: [];
    }
}
```

## Rules

- Always extend `Models\BaseModel`
- Table name in `protected string $table` must match the actual DB table (snake_case, plural)
- All queries via `$this->db->...` — never raw `mysqli_query`
- Method names: `find*()`, `create*()`, `update*()`, `get*()`
- Always filter `deleted_at = null` unless intentionally including deleted rows
- Always specify an explicit `orderBy()` direction
- Never hard-delete — set `deleted_at` on destroy

## Inherited from BaseModel

```php
$model->findById(int $id): ?array
$model->findWhere(array $conditions): array
$model->insert(array $data): int        // returns new ID
$model->updateById(int $id, array $data): bool
$model->paginate(array $params, array $searchFields): array
```

## Naming by Table

| Table | Model class | File |
|-------|------------|------|
| `products` | `ProductModel` | `api/v2/models/ProductModel.php` |
| `sales_orders` | `SalesOrderModel` | `api/v2/models/SalesOrderModel.php` |
| `sales_order_items` | `SalesOrderItemModel` | `api/v2/models/SalesOrderItemModel.php` |
| `purchase_orders` | `PurchaseOrderModel` | `api/v2/models/PurchaseOrderModel.php` |
| `invoices` | `InvoiceModel` | `api/v2/models/InvoiceModel.php` |
| `invoice_payments` | `InvoicePaymentModel` | `api/v2/models/InvoicePaymentModel.php` |
| `inventory` | `InventoryModel` | `api/v2/models/InventoryModel.php` |
| `inventory_transactions` | `InventoryTransactionModel` | `api/v2/models/InventoryTransactionModel.php` |
| `manufacturers` | `ManufacturerModel` | `api/v2/models/ManufacturerModel.php` |
| `pharmacies` | `PharmacyModel` | `api/v2/models/PharmacyModel.php` |
