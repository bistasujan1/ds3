# Database Rules & Patterns

Patterns for safe, efficient database operations using the MysqliDb wrapper.

## Core Rules

### Always Use Prepared Statements

```php
// ✓ MysqliDb handles parameterization
$product = $db->where('sku', $sku)->getOne('products');

// ✓ Explicit bound params
$results = $db->rawQuery(
    'SELECT * FROM products WHERE category_id = ? AND status = ?',
    [$categoryId, 'active']
);

// ✗ Never concatenate user input
$query = "WHERE sku = '" . $sku . "'";
```

### Always Scope Queries

Every query must include the appropriate soft-delete filter:

```php
// ✓ Tables with status ENUM — filter by status
$orders = $db->where('status', 'Cancelled', '!=')->get('orders');

// ✓ Tables with active column — filter by active = 1
$tiers = $db->where('active', 1)->get('pricing_tiers');

// ✗ Never return all rows without filtering
$db->get('orders');
```

### Soft Delete Pattern

DS3 uses **two patterns** — choose based on the table, not preference:

**Pattern A — `status` ENUM** (tables that already have a meaningful status column):
Use status transitions to soft-delete. Never set a `deleted_at` column (it doesn't exist).

```php
// Tables: users, products, vendors, clients, warehouses, inventory_lots,
//         inventory_transfers, purchase_orders, quotes, orders, invoices,
//         credit_notes, recalls, documents, licenses, client_users

// Mark inactive
$db->where('id', $productId)->update('products', ['status' => 'Inactive']);

// Filter active records
$active = $db->where('status', 'Active')->get('products');
```

**Pattern B — `active TINYINT(1)`** (lookup/config tables without a status column):
Set `active = 0` to soft-delete; `1 = live`.

```php
// Tables: categories, client_ship_locations, pricing_tiers, client_allocations,
//         par_config, storage_locations, document_requirements

// Soft-delete
$db->where('id', $tierId)->update('pricing_tiers', ['active' => 0]);

// Filter live records
$active = $db->where('active', 1)->get('pricing_tiers');

// Restore
$db->where('id', $tierId)->update('pricing_tiers', ['active' => 1]);
```

**Never use `deleted_at` or `updated_at`** — these columns do not exist in the DS3 schema.

Immutable ledgers — never soft-delete: `stock_ledger`, `order_tracking`, `audit_trail`, `invoice_reminders`, `recall_checks`.

### UPDATE Always Has WHERE

```php
// ✓ Explicit WHERE
$db->where('id', $invoiceId)->update('invoices', ['status' => 'paid']);

// ✗ UPDATE without WHERE modifies all rows
$db->update('invoices', ['status' => 'paid']);
```

### ORDER BY Always Has Direction

```php
// ✓ Explicit direction
$db->orderBy('created_at', 'DESC')->get('orders');

// ✗ Implicit direction is confusing
$db->orderBy('created_at')->get('orders');
```

### Whitelist Sort Columns

```php
$allowed = ['created_at', 'order_number', 'total', 'status'];
$sortBy  = in_array($params['sort_by'] ?? '', $allowed, true)
    ? $params['sort_by']
    : 'created_at';
$db->orderBy($sortBy, 'DESC');
```

## Transactions

Multi-step operations that must be atomic:

```php
try {
    $db->beginTransaction();

    $orderId = $db->insert('orders', $orderData);

    // Reserve inventory — must be atomic
    $lot = $db->where('id', $lotId)->getOne('inventory_lots', 'qty_g, qty_reserved');
    $db->where('id', $lotId)->update('inventory_lots', [
        'qty_reserved' => $lot['qty_reserved'] + $qtyG,
    ]);

    $db->insert('stock_ledger', [
        'lot_id'       => $lotId,
        'move_type'    => 'allocate',
        'qty_g'        => $qtyG,
        'qty_before_g' => $lot['qty_g'] - $lot['qty_reserved'],
        'qty_after_g'  => $lot['qty_g'] - $lot['qty_reserved'] - $qtyG,
        'ref_type'     => 'order',
        'ref_id'       => $orderId,
        'actor'        => $actor,
        'created_at'   => date('Y-m-d H:i:s'),
    ]);

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
```

## Pagination

Standard pattern for all list endpoints:

```php
// In Controller (server-side DataTable path)
$params   = request_query();
$allowed  = ['created_at', 'order_number', 'total', 'status'];
if (!in_array($params['sort_by'] ?? '', $allowed, true)) {
    unset($params['sort_by']);
}
$results = $this->orderModel->paginate($params, ['order_number', 'client_id']);
response(['code' => 200, 'data' => $results['data'], 'pagination' => $results['pagination']]);

// In Model — paginate() is inherited from BaseModel, no additional code needed
```

## Stock Ledger Pattern

`stock_ledger` is an append-only ledger. Never update or delete rows:

```php
// ✓ Always read qty_g / qty_reserved from `inventory_lots` for current state
// ✓ Always write a ledger row on every gram movement
// ✗ Never update stock_ledger rows
// ✗ Never increment qty_reserved outside a transaction

public function allocateLot(int $lotId, float $qtyG, int $orderId, string $actor): void
{
    $db = $this->db;

    try {
        $db->beginTransaction();

        $lot = $db->where('id', $lotId)->getOne('inventory_lots', 'qty_g, qty_reserved');
        $before = (float) $lot['qty_g'] - (float) $lot['qty_reserved'];
        $after  = $before - $qtyG;

        $db->where('id', $lotId)->update('inventory_lots', [
            'qty_reserved' => (float) $lot['qty_reserved'] + $qtyG,
            'status'       => 'Reserved',
        ]);

        $db->insert('stock_ledger', [
            'lot_id'       => $lotId,
            'move_type'    => 'allocate',
            'qty_g'        => -$qtyG,
            'qty_before_g' => $before,
            'qty_after_g'  => $after,
            'ref_type'     => 'order',
            'ref_id'       => $orderId,
            'actor'        => $actor,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
```

## Common Mistakes

| Mistake | Impact | Prevention |
|---------|--------|-----------|
| No LIMIT on SELECT | OOM | Always paginate list endpoints |
| SELECT * | Loads unused data | Specify columns |
| Missing WHERE on UPDATE | All rows modified | Test in dev first |
| Using `deleted_at` | Column does not exist | Use `active = 0` or status transition |
| Adding `updated_at` | Column does not exist | Omit — not in DS3 schema |
| No transaction on lot reservation | Race condition / oversell | Wrap every `qty_reserved` change |
| User-controlled sort column | SQL injection | Whitelist first |
| Deleting stock_ledger rows | Breaks audit trail | Never delete ledger rows |
