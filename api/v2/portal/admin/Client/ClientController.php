<?php

declare(strict_types=1);

namespace Portal\Admin\Client;

use Models\ClientModel;
use Reunite\Framework\Cloud;

/**
 * CRUD endpoints for pharmacy clients.
 */
class ClientController extends \Portal\Admin\BaseController
{
    private ClientModel $clientModel;

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->clientModel = new ClientModel();
    }

    /**
     * GET /admin/clients — list all clients.
     *
     * @return void
     */
    public function index(): void
    {
        $clients = $this->clientModel->findAll();
        response(['code' => 200, 'data' => $clients]);
    }

    /**
     * GET /admin/clients/dropdown — minimal list for select2.
     *
     * @return void
     */
    public function dropdown(): void
    {
        $clients = $this->clientModel->findForDropdown();
        response(['code' => 200, 'data' => $clients]);
    }

    /**
     * GET /admin/clients/{id} — single client.
     *
     * @return void
     */
    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            response(['code' => 400, 'message' => 'Invalid client ID']);
            return;
        }
        $client = $this->clientModel->findById($id);
        if (!$client) {
            response(['code' => 404, 'message' => 'Client not found']);
            return;
        }
        response(['code' => 200, 'data' => $client]);
    }

    /**
     * POST /admin/clients — create a new client.
     *
     * @return void
     */
    public function store(): void
    {
        $data = request_post();
        $this->validate($data, [
            'name'  => 'required',
            'email' => 'required|email',
            'phone' => 'required',
        ]);

        $id = $this->clientModel->insert(array_merge(
            $this->allowedFields($data),
            ['created_at' => date('Y-m-d H:i:s')]
        ));

        response(['code' => 201, 'data' => ['id' => $id]]);
    }

    /**
     * PUT /admin/clients/{id} — update a client.
     *
     * @return void
     */
    public function update(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            response(['code' => 400, 'message' => 'Invalid client ID']);
            return;
        }

        $data = request_post();
        $this->validate($data, [
            'name'  => 'required',
            'email' => 'required|email',
        ]);

        $this->clientModel->updateById($id, $this->allowedFields($data));

        response(['code' => 200, 'message' => 'Client updated']);
    }

    /**
     * DELETE /admin/clients/{id} — soft-delete a client.
     *
     * @return void
     */
    public function destroy(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            response(['code' => 400, 'message' => 'Invalid client ID']);
            return;
        }

        $this->clientModel->softDelete($id);
        response(['code' => 200, 'message' => 'Client deleted']);
    }

    /**
     * Extract only the columns allowed for insert/update.
     *
     * @param array $data
     * @return array
     */
    private function allowedFields(array $data): array
    {
        $allowed = [
            'name', 'contact', 'email', 'phone', 'billing_address',
            'credit_limit', 'terms', 'tier_id', 'controlled_approved',
            'license_number', 'license_expiry', 'credit_hold', 'status',
            'dea_number', 'dea_expiry', 'notes',
        ];
        return array_intersect_key($data, array_flip($allowed));
    }
}
