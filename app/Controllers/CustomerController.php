<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Models/Order.php';
require_once __DIR__ . '/../Services/OrderService.php';

class CustomerController extends BaseController
{
    private $orderService;

    public function __construct()
    {
        parent::__construct();
        // Guarded strictly for 'customer' role
        $roleGuard = new RoleMiddleware(['customer']);
        $roleGuard->handle();

        $this->orderService = new OrderService();
    }

    public function dashboard()
    {
        $userId = (int) $_SESSION['user']['id'];
        $orders = $this->orderService->getCustomerOrders($userId);
        $stats = ['total' => count($orders), 'active' => 0, 'completed' => 0, 'cancelled' => 0];
        foreach ($orders as $order) {
            if ($order['status'] === 'completed') {
                $stats['completed']++;
            } elseif ($order['status'] === 'cancelled') {
                $stats['cancelled']++;
            } else {
                $stats['active']++;
            }
        }

        $data = [
            'title'      => 'Dashboard Customer - TKJ Service Hub',
            'activeMenu' => 'dashboard_customer',
            'user'       => $_SESSION['user'],
            'orders'     => array_slice($orders, 0, 5),
            'stats'      => $stats,
        ];

        $this->renderLayout('customer/dashboard', $data);
    }
}
