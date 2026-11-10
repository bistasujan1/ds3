# Model Layer Architecture

Enforces clean separation: Controllers orchestrate, Models query.

## Core Principle

**All database queries must go through model methods.** Controllers never call `$this->db` directly.

**Exception — transaction control**: `db()->startTransaction()`, `db()->commit()`, `db()->rollback()` are allowed directly in Controllers when coordinating multiple model calls that must succeed or fail atomically.

## Directory Layout

```
api/v2/
├── models/
│   ├── BaseModel.php           ← inherited CRUD + paginate()
│   ├── ProductModel.php
│   ├── InventoryModel.php
│   ├── PurchaseOrderModel.php
│   ├── SalesOrderModel.php
│   ├── InvoiceModel.php
│   ├── ManufacturerModel.php
│   └── PharmacyModel.php
└── portal/admin/
    ├── BaseController.php
    ├── Inventory/
    │   ├── Router.php
    │   └── InventoryController.php
    ├── Orders/
    │   ├── Router.php
    │   └── OrderController.php
    └── ...
```

## What Models Do

- Encapsulate all `SELECT`, `INSERT`, `UPDATE`, `DELETE` operations
- Extend `Models\BaseModel` for inherited CRUD + `paginate()`
- Implement domain-specific query methods
- Handle JOINs, aggregations, filtering

## What Controllers Do

- Validate input via `$this->validate()`
- Orchestrate: call models, format response
- Handle business logic spanning multiple tables
- Never call `$this->db->where()`, `->get()`, `->insert()`, etc.

## BaseModel Inherited Methods

```php
$model->findById(123);
$model->findWhere(['status' => 'active']);
$model->insert(['name' => 'Aspirin', ...]);
$model->updateById(123, ['status' => 'inactive']);
$model->paginate($queryParams, ['name', 'sku']);   // server-side DataTable
```

## Custom Model Method Patterns

```php
class InventoryModel extends BaseModel {
    protected string $table = 'inventory';

    /**
     * Find inventory record for a product with its product details.
     *
     * @param int $productId
     * @return array|null  null if no inventory row exists
     */
    public function findWithProduct(int $productId): ?array
    {
        $this->db->join('products p', 'p.id = i.product_id', 'INNER');
        $this->db->where('i.product_id', $productId);
        return $this->db->getOne('inventory i', null, 'i.*, p.name, p.sku, p.unit') ?: null;
    }

    /**
     * Find all products with low stock (on_hand <= reorder_point).
     *
     * @return array
     */
    public function findLowStock(): array
    {
        $this->db->where('quantity_on_hand', $this->db->raw('reorder_point'), '<=');
        $this->db->where('deleted_at', null);
        $this->db->join('products p', 'p.id = i.product_id', 'INNER');
        return $this->db->get('inventory i', null, 'i.*, p.name, p.sku') ?: [];
    }
}
```

## Method Naming

| Pattern | Purpose | Example |
|---------|---------|---------|
| `find*()` | Read single or multiple rows | `findByPharmacy()`, `findWithItems()` |
| `create*()` | INSERT with business logic | `createWithItems()` |
| `update*()` | UPDATE with validation | `updateStatus()`, `markShipped()` |
| `get*()` | Alias for find (acceptable) | `getActiveByManufacturer()` |

## Model Injection in Controllers

```php
class OrderController extends BaseController {
    private SalesOrderModel $orderModel;
    private SalesOrderItemModel $itemModel;
    private InventoryModel $inventoryModel;

    public function __construct() {
        parent::__construct();
        $this->orderModel    = new SalesOrderModel();
        $this->itemModel     = new SalesOrderItemModel();
        $this->inventoryModel = new InventoryModel();
    }

    public function store(): void {
        $data = request_post();
        $this->validate($data, [
            'pharmacy_id' => 'required|numeric',
            'items'       => 'required',
        ]);

        try {
            db()->startTransaction();
            $orderId = $this->orderModel->insert([...]);
            foreach ($data['items'] as $item) {
                $this->itemModel->insert([..., 'sales_order_id' => $orderId]);
                $this->inventoryModel->reserveStock($item['product_id'], $item['quantity']);
            }
            db()->commit();
            response(['code' => 201, 'data' => ['id' => $orderId]]);
        } catch (Exception $e) {
            db()->rollback();
            Logger::error($e->getMessage(), ['location' => 'OrderController::store']);
            response(['code' => 500, 'message' => 'Failed to create order']);
        }
    }
}
```

## Anti-Patterns

```php
// ✗ Direct DB in Controller
public function index(): void {
    $this->db->where('status', 'active');  // WRONG
    $orders = $this->db->get('sales_orders');
}

// ✗ BaseController query methods
abstract class BaseController {
    protected function findOrFail(...) {}  // WRONG — belongs in model
}

// ✓ Always via model
public function index(): void {
    $orders = $this->orderModel->findActive();
}
```

## Pagination Pattern

```php
// Controller
public function index(): void {
    $params  = request_query();
    $allowed = ['created_at', 'order_number', 'total', 'status', 'pharmacy_id'];
    if (!in_array($params['sort_by'] ?? '', $allowed, true)) {
        unset($params['sort_by']);
    }
    $results = $this->orderModel->paginate($params, ['order_number']);
    response(['code' => 200, 'data' => $results['data'], 'pagination' => $results['pagination']]);
}
// No model changes needed — paginate() is inherited from BaseModel
```

## Code Review Checklist

- [ ] No `$this->db->` calls in Controller classes
- [ ] All models extend `Models\BaseModel`
- [ ] Custom query methods named per convention
- [ ] Controller has model injection in `__construct()`
- [ ] Validation in Controller, not model
- [ ] Soft delete used for business records
- [ ] Transactions in Controller only when coordinating multiple models
