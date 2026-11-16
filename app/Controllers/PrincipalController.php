<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Models/Order.php';

class PrincipalController extends BaseController
{
    private $orderModel;

    public function __construct()
    {
        parent::__construct();
        // Guarded strictly for 'principal' role
        $roleGuard = new RoleMiddleware(['principal']);
        $roleGuard->handle();

        $this->orderModel = new Order();
    }

    public function dashboard()
    {
        $data = [
            'title'      => 'Dashboard Laporan & Monitoring - Kepala Sekolah',
            'activeMenu' => 'dashboard_principal',
            'user'       => $_SESSION['user']
        ];

        $this->renderLayout('principal/dashboard', $data);
    }
}
