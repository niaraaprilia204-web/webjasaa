<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Services/OperatorService.php';

/**
 * Customer Management Controller (Operator Only)
 */
class CustomerManagementController extends BaseController
{
    private $operatorService;

    public function __construct()
    {
        parent::__construct();
        $roleGuard = new RoleMiddleware(['operator']);
        $roleGuard->handle();

        $this->operatorService = new OperatorService();
    }

    public function index()
    {
        $search = $_GET['search'] ?? '';
        $customers = $this->operatorService->getCustomersList($search);

        $data = [
            'title'      => 'Kelola Data Customer - TKJ Service Hub',
            'activeMenu' => 'operator_customers',
            'customers'  => $customers,
            'search'     => $search
        ];

        $this->renderLayout('operator/customers/index', $data);
    }
}
