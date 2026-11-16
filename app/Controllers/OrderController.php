<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Services/OrderService.php';
require_once __DIR__ . '/../Services/CatalogService.php';

/**
 * Service Request & Order Management Controller
 */
class OrderController extends BaseController
{
    private $orderService;
    private $catalogService;

    public function __construct()
    {
        parent::__construct();
        $this->orderService = OrderService::create();
        $this->catalogService = new CatalogService();
    }

    // ==========================================
    // CUSTOMER ORDER ACTIONS
    // ==========================================

    public function create($serviceId = null)
    {
        $roleGuard = new RoleMiddleware(['customer']);
        $roleGuard->handle();

        $selectedService = null;
        if (!empty($serviceId)) {
            $selectedService = $this->catalogService->getCategoryById($serviceId);
            // Fetch service by ID
            $db = new Database();
            $db->query("SELECT * FROM services WHERE id = :id AND is_active = 1");
            $db->bind(':id', $serviceId);
            $selectedService = $db->single();
        }

        $activeServices = $this->catalogService->getActiveServices();

        $data = [
            'title'           => 'Form Pemesanan Jasa - TKJ Service Hub',
            'activeMenu'      => 'create_order',
            'selectedService' => $selectedService,
            'services'        => $activeServices,
            'user'            => $_SESSION['user'],
            'csrfToken'       => Csrf::token('order'),
        ];

        $this->renderLayout('customer/orders/create', $data);
    }

    public function store()
    {
        $roleGuard = new RoleMiddleware(['customer']);
        $roleGuard->handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/customer/orders/create');
            exit;
        }

        if (!Csrf::validate('order')) {
            Flasher::setMessage('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/customer/orders/create');
            exit;
        }

        $userId = $_SESSION['user']['id'];
        $result = $this->orderService->createOrder($userId, $_POST, $_FILES['damage_photo'] ?? null);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/customer/orders/create/' . ($_POST['service_id'] ?? ''));
            exit;
        }

        Flasher::setMessage($result['message'], 'Sukses', 'success');
        header('Location: ' . BASEURL . '/customer/orders/detail/' . $result['data']['order_code']);
        exit;
    }

    public function index()
    {
        $roleGuard = new RoleMiddleware(['customer']);
        $roleGuard->handle();

        $userId = $_SESSION['user']['id'];
        $orders = $this->orderService->getCustomerOrders($userId);

        $data = [
            'title'      => 'Riwayat Pesanan Saya - TKJ Service Hub',
            'activeMenu' => 'my_orders',
            'orders'     => $orders,
            'user'       => $_SESSION['user']
        ];

        $this->renderLayout('customer/orders/index', $data);
    }

    public function detail($code = null)
    {
        $roleGuard = new RoleMiddleware(['customer']);
        $roleGuard->handle();

        if (empty($code)) {
            header('Location: ' . BASEURL . '/customer/orders');
            exit;
        }

        $userId = $_SESSION['user']['id'];
        $result = $this->orderService->getCustomerOrderDetail($code, $userId);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Akses Ditolak', 'danger');
            header('Location: ' . BASEURL . '/customer/orders');
            exit;
        }

        $data = [
            'title'      => 'Detail Pesanan #' . $code . ' - TKJ Service Hub',
            'activeMenu' => 'my_orders',
            'order'      => $result['data']['order'],
            'workOrders' => $result['data']['workOrders'],
            'files'      => $result['data']['files'],
            'review'     => $result['data']['review'] ?? null,
            'csrfToken'  => Csrf::token('order'),
        ];

        $this->renderLayout('customer/orders/detail', $data);
    }

    public function submitReview($code = null)
    {
        $roleGuard = new RoleMiddleware(['customer']);
        $roleGuard->handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate('order')) {
            Flasher::setMessage('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/customer/orders');
            exit;
        }

        $orderCode = (string) ($code ?? ($_POST['order_code'] ?? ''));
        $result = $this->orderService->submitReview(
            $orderCode,
            (int) $_SESSION['user']['id'],
            (int) ($_POST['rating'] ?? 0),
            (string) ($_POST['comment'] ?? '')
        );

        Flasher::setMessage($result['message'], $result['success'] ? 'Berhasil' : 'Gagal', $result['success'] ? 'success' : 'danger');
        header('Location: ' . BASEURL . '/customer/orders/detail/' . rawurlencode($orderCode));
        exit;
    }

    // ==========================================
    // OPERATOR ORDER WORKFLOW ACTIONS
    // ==========================================

    public function operatorIndex()
    {
        $roleGuard = new RoleMiddleware(['operator']);
        $roleGuard->handle();

        require_once __DIR__ . '/../Services/OperatorService.php';
        $operatorService = new OperatorService();

        $statusFilter = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';

        // Query filtered orders
        $db = new Database();
        if ($db->isConnected()) {
            $sql = "SELECT o.*, s.title as service_name, u.name as customer_name, u.phone as customer_phone FROM orders o JOIN services s ON o.service_id = s.id JOIN customers c ON o.customer_id = c.id JOIN users u ON c.user_id = u.id WHERE 1=1";
            
            if (!empty($statusFilter)) {
                $sql .= " AND o.status = :status";
            }
            if (!empty($search)) {
                $sql .= " AND (o.order_code LIKE :search OR u.name LIKE :search OR s.title LIKE :search)";
            }
            if (!empty($startDate)) {
                $sql .= " AND DATE(o.created_at) >= :start_date";
            }
            if (!empty($endDate)) {
                $sql .= " AND DATE(o.created_at) <= :end_date";
            }

            $sql .= " ORDER BY o.created_at DESC";

            $db->query($sql);
            if (!empty($statusFilter)) $db->bind(':status', $statusFilter);
            if (!empty($search)) $db->bind(':search', '%' . $search . '%');
            if (!empty($startDate)) $db->bind(':start_date', $startDate);
            if (!empty($endDate)) $db->bind(':end_date', $endDate);

            $orders = $db->resultSet();
        } else {
            $orders = [];
        }

        // Fetch technicians list for assignment modal
        $technicians = $operatorService->getTechniciansList();

        $data = [
            'title'        => 'Kelola Pesanan & Workflow - Operator',
            'activeMenu'   => 'operator_orders',
            'orders'       => $orders,
            'technicians'  => $technicians,
            'filters'      => [
                'status'     => $statusFilter,
                'search'     => $search,
                'start_date' => $startDate,
                'end_date'   => $endDate
            ],
            'csrfToken'    => Csrf::token('operator_order'),
        ];

        $this->renderLayout('operator/orders/index', $data);
    }

    public function assignTechnician()
    {
        $roleGuard = new RoleMiddleware(['operator']);
        $roleGuard->handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/operator/orders');
            exit;
        }

        if (!Csrf::validate('operator_order')) {
            Flasher::setMessage('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/operator/orders');
            exit;
        }

        require_once __DIR__ . '/../Services/OperatorService.php';
        $operatorService = new OperatorService();

        $orderId = (int)($_POST['order_id'] ?? 0);
        $technicianId = (int)($_POST['technician_id'] ?? 0);
        $notes = $_POST['notes'] ?? '';

        $result = $operatorService->assignTechnicianToOrder($orderId, $technicianId, $notes);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
        } else {
            Flasher::setMessage($result['message'], 'Sukses', 'success');
        }

        header('Location: ' . BASEURL . '/operator/orders');
        exit;
    }

    public function updateStatus()
    {
        $roleGuard = new RoleMiddleware(['operator']);
        $roleGuard->handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/operator/orders');
            exit;
        }

        if (!Csrf::validate('operator_order')) {
            Flasher::setMessage('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/operator/orders');
            exit;
        }

        $orderId = (int)($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $notes = $_POST['operator_notes'] ?? '';

        $result = $this->orderService->updateOrderStatusByOperator($orderId, $status, $notes);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
        } else {
            Flasher::setMessage($result['message'], 'Sukses', 'success');
        }

        header('Location: ' . BASEURL . '/operator/orders');
        exit;
    }

    public function operatorDetail($identifier = null)
    {
        $roleGuard = new RoleMiddleware(['operator']);
        $roleGuard->handle();

        if (empty($identifier)) {
            Flasher::setMessage('Kode pesanan tidak valid.', 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/operator/orders');
            exit;
        }

        $result = $this->orderService->getOrderDetailForOperator($identifier);
        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/operator/orders');
            exit;
        }

        require_once __DIR__ . '/../Services/OperatorService.php';
        $operatorService = new OperatorService();

        $data = [
            'title'       => 'Detail Pesanan #' . $result['data']['order']['order_code'] . ' - Operator',
            'activeMenu'  => 'operator_orders',
            'order'       => $result['data']['order'],
            'workOrders'  => $result['data']['workOrders'],
            'files'       => $result['data']['files'],
            'review'      => $result['data']['review'],
            'technicians' => $operatorService->getTechniciansList(),
            'services'    => $this->catalogService->getActiveServices(),
            'csrfToken'   => Csrf::token('operator_order'),
        ];

        $this->renderLayout('operator/orders/detail', $data);
    }

    public function updateOrderDetails()
    {
        $roleGuard = new RoleMiddleware(['operator']);
        $roleGuard->handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate('operator_order')) {
            Flasher::setMessage('Sesi keamanan tidak valid.', 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/operator/orders');
            exit;
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $result = $this->orderService->updateOrderDetails($orderId, $_POST);
        Flasher::setMessage($result['message'], $result['success'] ? 'Berhasil' : 'Gagal', $result['success'] ? 'success' : 'danger');

        $redirect = $orderId > 0 ? BASEURL . '/operator/orders/detail/' . $orderId : BASEURL . '/operator/orders';
        header('Location: ' . $redirect);
        exit;
    }

    public function updatePayment()
    {
        $roleGuard = new RoleMiddleware(['operator']);
        $roleGuard->handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate('operator_order')) {
            Flasher::setMessage('Sesi keamanan tidak valid.', 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/operator/orders');
            exit;
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $result = $this->orderService->updatePayment($orderId, $_POST);
        Flasher::setMessage($result['message'], $result['success'] ? 'Berhasil' : 'Gagal', $result['success'] ? 'success' : 'danger');
        header('Location: ' . BASEURL . '/operator/orders/detail/' . $orderId);
        exit;
    }
}
