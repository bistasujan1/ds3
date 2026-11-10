<?php

declare(strict_types=1);

namespace Portal\Admin\Product;

use Models\ProductModel;

/**
 * Product endpoints — currently dropdown only; full CRUD deferred to Inventory module.
 */
class ProductController extends \Portal\Admin\BaseController
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
     * GET /admin/products/dropdown — active products for select2.
     *
     * @return void
     */
    public function dropdown(): void
    {
        $products = $this->productModel->findForDropdown();
        response(['code' => 200, 'data' => $products]);
    }
}
