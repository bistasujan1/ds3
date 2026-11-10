<?php

declare(strict_types=1);

namespace Portal\Admin\Order;

use Models\OrderModel;
use Reunite\Framework\Cloud;

/**
 * CRUD endpoints for sales orders.
 */
class OrderController extends \Portal\Admin\BaseController
{
    private OrderModel $orderModel;

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->orderModel = new OrderModel();
    }

    /**
     * GET /admin/orders — list all orders.
     *
     * @return void
     */
    public function index(): void
    {
        $orders = $this->orderModel->findAll();
        response(['code' => 200, 'data' => $orders]);
    }

    /**
     * GET /admin/orders/{id} — single order detail.
     *
     * @return void
     */
    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            response(['code' => 400, 'message' => 'Invalid order ID']);
            return;
        }
        $order = $this->orderModel->findById($id);
        if (!$order) {
            response(['code' => 404, 'message' => 'Order not found']);
            return;
        }
        response(['code' => 200, 'data' => $order]);
    }

    /**
     * POST /admin/orders — create a new order.
     *
     * @return void
     */
    public function store(): void
    {
        $data = request_post();
        $this->validate($data, [
            'client_id'  => 'required|numeric',
            'product_id' => 'required|numeric',
            'qty_g'      => 'required|numeric',
            'price_g'    => 'required|numeric',
        ]);

        $qty   = (float) $data['qty_g'];
        $price = (float) $data['price_g'];

        $id = $this->orderModel->insert([
            'order_number'    => $this->orderModel->generateOrderNumber(),
            'client_id'       => (int) $data['client_id'],
            'product_id'      => (int) $data['product_id'],
            'qty_g'           => $qty,
            'price_g'         => $price,
            'total'           => round($qty * $price, 2),
            'status'          => 'New',
            'ship_by'         => $data['ship_by'] ?? null,
            'carrier'         => $data['carrier'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'created_by'      => (int) (Cloud::get('user')['id'] ?? 0),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        response(['code' => 201, 'data' => ['id' => $id]]);
    }

    /**
     * PUT /admin/orders/{id} — update order fields.
     *
     * @return void
     */
    public function update(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            response(['code' => 400, 'message' => 'Invalid order ID']);
            return;
        }

        $data = request_post();
        $this->validate($data, [
            'client_id'  => 'required|numeric',
            'product_id' => 'required|numeric',
            'qty_g'      => 'required|numeric',
            'price_g'    => 'required|numeric',
        ]);

        $allowed = [
            'client_id', 'product_id', 'qty_g', 'price_g',
            'status', 'ship_by', 'shipment_at',
            'carrier', 'tracking_number', 'notes',
        ];
        $update = array_intersect_key($data, array_flip($allowed));

        $this->orderModel->updateById($id, $update);
        response(['code' => 200, 'message' => 'Order updated']);
    }

    /**
     * DELETE /admin/orders/{id} — soft-delete an order.
     *
     * @return void
     */
    public function destroy(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            response(['code' => 400, 'message' => 'Invalid order ID']);
            return;
        }

        $this->orderModel->softDelete($id);
        response(['code' => 200, 'message' => 'Order deleted']);
    }
}
