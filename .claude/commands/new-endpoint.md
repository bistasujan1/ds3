# /new-endpoint

Scaffold a new API v2 endpoint in DS3: Router + Controller + Model.

## Usage

```
/new-endpoint admin Products
/new-endpoint admin SalesOrders
/new-endpoint admin Invoices
```

## Steps

### 1. Create or extend the Model

`api/v2/models/{Resource}Model.php` extending `Models\BaseModel`:

```php
<?php

declare(strict_types=1);

namespace Models;

/**
 * Repository for the products table.
 */
class ProductModel extends BaseModel
{
    protected string $table = 'products';

    /**
     * Find all active products for a category.
     *
     * @param int $categoryId
     * @return array
     */
    public function findByCategory(int $categoryId): array
    {
        $this->db->where('category_id', $categoryId);
        $this->db->where('deleted_at', null);
        $this->db->orderBy('name', 'ASC');
        return $this->db->get($this->table) ?: [];
    }
}
```

### 2. Create the Router

`api/v2/portal/admin/{Feature}/Router.php`:

```php
<?php

declare(strict_types=1);

use Reunite\Framework\Cloud;
use utils\Middleware;
use Portal\Admin\Products\ProductController;

$router = Cloud::router();

$router->group('/admin/products', static function () use ($router) {
    $router->get('',      [ProductController::class, 'index']);
    $router->post('',     [ProductController::class, 'store']);
    $router->get('/{id}', [ProductController::class, 'show']);
    $router->put('/{id}', [ProductController::class, 'update']);
    $router->delete('/{id}', [ProductController::class, 'destroy']);
}, Middleware::admin());
```

### 3. Create the Controller

`api/v2/portal/admin/{Feature}/{Feature}Controller.php`:

```php
<?php

declare(strict_types=1);

namespace Portal\Admin\Products;

use Models\ProductModel;
use Portal\Admin\BaseController;
use Reunite\Logger;

/**
 * Handles CRUD operations for products.
 */
class ProductController extends BaseController
{
    private ProductModel $productModel;

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->productModel = new ProductModel();
    }

    /**
     * List all products (paginated).
     *
     * @return void
     */
    public function index(): void
    {
        $params  = request_query();
        $allowed = ['created_at', 'name', 'sku', 'status'];
        if (!in_array($params['sort_by'] ?? '', $allowed, true)) {
            unset($params['sort_by']);
        }
        $results = $this->productModel->paginate($params, ['name', 'sku', 'description']);
        response(['code' => 200, 'data' => $results['data'], 'pagination' => $results['pagination']]);
    }

    /**
     * Get a single product by ID.
     *
     * @return void
     */
    public function show(): void
    {
        $id      = (int) ($_GET['id'] ?? 0);
        $product = $this->productModel->findById($id);
        if (!$product) {
            response(['code' => 404, 'message' => 'Product not found']);
            return;
        }
        response(['code' => 200, 'data' => $product]);
    }

    /**
     * Create a new product.
     *
     * @return void
     */
    public function store(): void
    {
        $data = request_post();
        $this->validate($data, [
            'name'        => 'required',
            'sku'         => 'required',
            'category_id' => 'required|numeric',
        ]);

        try {
            $id = $this->productModel->insert(array_merge($data, [
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]));
            response(['code' => 201, 'data' => ['id' => $id]]);
        } catch (Exception $e) {
            Logger::error($e->getMessage(), ['location' => 'ProductController::store', 'exception' => get_class($e)]);
            response(['code' => 500, 'message' => 'Failed to create product']);
        }
    }

    /**
     * Update an existing product.
     *
     * @return void
     */
    public function update(): void
    {
        $id   = (int) ($_GET['id'] ?? 0);
        $data = request_post();

        $product = $this->productModel->findById($id);
        if (!$product) {
            response(['code' => 404, 'message' => 'Product not found']);
            return;
        }

        $this->productModel->updateById($id, array_merge($data, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]));
        response(['code' => 200, 'message' => 'Product updated']);
    }

    /**
     * Soft-delete a product.
     *
     * @return void
     */
    public function destroy(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        $product = $this->productModel->findById($id);
        if (!$product) {
            response(['code' => 404, 'message' => 'Product not found']);
            return;
        }

        $this->productModel->updateById($id, [
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        response(['code' => 200, 'message' => 'Product deleted']);
    }
}
```

### 4. Register the Router in routes.php

Add to `api/v2/config/routes.php`:

```php
['prefix' => '', 'path' => __DIR__ . '/../portal/admin/Products/Router.php'],
```

### 5. Add Frontend Page (optional)

Create `v2/portals/admin/pages/{module}/index.php` following the standard page pattern — see CLAUDE.md § Adding a v2 Frontend Page.

## Checklist

- [ ] Model extends `BaseModel`, queries in model methods only
- [ ] Controller has `declare(strict_types=1)` and proper namespace
- [ ] Controller methods named: `index`, `show`, `store`, `update`, `destroy`
- [ ] `Middleware::admin()` on the router group
- [ ] Router registered in `api/v2/config/routes.php`
- [ ] Soft delete on `destroy()` — no hard deletes
- [ ] Sort columns whitelisted in `index()`
- [ ] Docblocks on class and all public methods
