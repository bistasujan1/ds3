<?php

declare(strict_types=1);

namespace Portal\Admin\App;

use Models\UserModel;
use Portal\Admin\BaseController;
use Reunite\Framework\Cloud;

/**
 * Core admin app endpoints: health check and login.
 */
class AppController extends BaseController
{
    private UserModel $userModel;

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->userModel = new UserModel();
    }

    /**
     * Health check — returns 200 when the API is reachable.
     *
     * @return void
     */
    public function health(): void
    {
        response(['code' => 200, 'message' => 'DS3 API is running', 'data' => ['version' => '1.0.0']]);
    }

    /**
     * Admin login — validates credentials and returns a bearer token.
     *
     * @return void
     */
    public function login(): void
    {
        $data = $this->validate(request_post(), [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ]);

        $email    = strtolower(trim($data['email']));
        $password = $data['password'];

        $user = $this->userModel->findActiveByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            response(['code' => 401, 'message' => 'Invalid email or password']);
            return;
        }

        // Load roles from user_role_mapping → role
        $roleRows = db()->rawQuery(
            'SELECT r.name FROM user_role_mapping urm
             JOIN role r ON r.id = urm.role_id
             WHERE urm.user_id = ?',
            [$user['id']]
        ) ?: [];
        $roles = array_column($roleRows, 'name');

        // Generate bearer token
        $token   = hash('sha256', $user['id'] . $user['email'] . time() . $_SERVER['SERVER_NAME'] . random_bytes(16));
        $expires = date('Y-m-d H:i:s', strtotime('+' . LOGIN_HOURS . ' minutes'));

        $this->userModel->updateById((int) $user['id'], [
            'token'            => $token,
            'token_expires_at' => $expires,
            'last_login_at'    => date('Y-m-d H:i:s'),
        ]);

        response([
            'code'    => 200,
            'message' => 'Login successful',
            'data'    => [
                'token'     => $token,
                'user'      => [
                    'id'         => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name'  => $user['last_name'],
                    'email'      => $user['email'],
                    'role'       => $roles[0] ?? '',
                    'roles'      => $roles,
                ],
                'user_data' => $user['first_name'] . ' ' . $user['last_name'],
            ],
        ]);
    }
}
