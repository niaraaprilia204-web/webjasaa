<?php

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Services/AiInternalAuthService.php';

/**
 * Internal, HMAC-protected, read-only endpoints consumed by the Node.js
 * chatbot. Browser sessions and CSRF are intentionally not accepted here:
 * Node must present a short-lived service token minted with the shared secret.
 */
class AiInternalController extends Controller
{
    private $auth;

    public function __construct()
    {
        $this->auth = new AiInternalAuthService();
    }

    public function health()
    {
        $this->requireGet();
        $claims = $this->authenticate();

        $this->json([
            'success' => true,
            'data' => [
                'service' => 'php-internal-ai',
                'authenticated' => true,
                'read_only' => true,
                'user_id' => $claims['user_id'],
                'role' => $claims['role'],
                'scope' => $claims['scope'],
                'server_time' => gmdate('c'),
            ],
        ]);
    }

    /**
     * Profile pelanggan yang sedang login (hanya data milik sendiri).
     * Tidak ada data pribadi customer lain yang di-expose.
     */
    public function customerProfile()
    {
        $this->requireGet();
        $claims = $this->authenticate();

        if ($claims['role'] !== 'customer') {
            $this->json([
                'success' => false,
                'message' => 'Endpoint ini hanya untuk role customer.',
            ], 403);
        }

        require_once __DIR__ . '/../Models/User.php';
        require_once __DIR__ . '/../Models/Customer.php';

        $userModel = new User();
        $profile = $userModel->customerProfile((int) $claims['user_id']);

        if (!$profile) {
            $this->json([
                'success' => false,
                'message' => 'Profil customer tidak ditemukan.',
            ], 404);
        }

        $this->json([
            'success' => true,
            'data' => [
                'user_id' => (int) $claims['user_id'],
                'name' => htmlspecialchars((string) ($profile['name'] ?? '')),
                'email' => htmlspecialchars((string) ($profile['email'] ?? '')),
                'username' => htmlspecialchars((string) ($profile['username'] ?? '')),
                'phone' => htmlspecialchars((string) ($profile['phone'] ?? '')),
                'role' => 'customer',
                'is_active' => (bool) ($profile['is_active'] ?? 0),
            ],
        ]);
    }

    /**
     * Katalog layanan publik (aktif saja). Tidak membutuhkan autentikasi user spesifik,
     * tapi tetap memerlukan HMAC token karena ini endpoint internal.
     */
    public function services()
    {
        $this->requireGet();
        $this->authenticate();

        require_once __DIR__ . '/../Models/Service.php';
        $serviceModel = new Service();
        $services = $serviceModel->allWithCategory();

        $clean = [];
        foreach ($services as $s) {
            $clean[] = [
                'id' => (int) ($s['id'] ?? 0),
                'title' => htmlspecialchars((string) ($s['title'] ?? '')),
                'slug' => htmlspecialchars((string) ($s['slug'] ?? '')),
                'description' => htmlspecialchars((string) ($s['description'] ?? '')),
                'category' => htmlspecialchars((string) ($s['category_name'] ?? 'Layanan')),
                'estimated_duration' => htmlspecialchars((string) ($s['estimated_duration'] ?? '-')),
                'price_range' => htmlspecialchars((string) ($s['price_range'] ?? '-')),
                'is_active' => (bool) ($s['is_active'] ?? 0),
            ];
        }

        $this->json([
            'success' => true,
            'data' => [
                'services' => $clean,
                'count' => count($clean),
            ],
        ]);
    }

    /**
     * Statistik admin (read-only). Hanya role admin yang diizinkan.
     */
    public function adminStats()
    {
        $this->requireGet();
        $claims = $this->authenticate();

        if ($claims['role'] !== 'admin') {
            $this->json([
                'success' => false,
                'message' => 'Endpoint ini hanya untuk role admin.',
            ], 403);
        }

        $db = new Database();
        if (!$db->isConnected()) {
            $this->json([
                'success' => false,
                'message' => 'Database tidak tersedia.',
            ], 503);
        }

        $stats = [];

        // Total users by role
        $db->query("SELECT role, COUNT(*) as total FROM users GROUP BY role");
        $usersByRole = $db->resultSet();
        $stats['users_by_role'] = is_array($usersByRole) ? $usersByRole : [];

        // Total customers
        $db->query("SELECT COUNT(*) as total FROM customers");
        $row = $db->single();
        $stats['total_customers'] = (int) ($row['total'] ?? 0);

        // Total orders
        $db->query("SELECT COUNT(*) as total FROM orders");
        $row = $db->single();
        $stats['total_orders'] = (int) ($row['total'] ?? 0);

        // Orders today
        $db->query("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = CURDATE()");
        $row = $db->single();
        $stats['orders_today'] = (int) ($row['total'] ?? 0);

        // Orders this month
        $db->query("SELECT COUNT(*) as total FROM orders WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
        $row = $db->single();
        $stats['orders_this_month'] = (int) ($row['total'] ?? 0);

        // Orders by status
        $db->query("SELECT status, COUNT(*) as total FROM orders GROUP BY status");
        $ordersByStatus = $db->resultSet();
        $stats['orders_by_status'] = is_array($ordersByStatus) ? $ordersByStatus : [];

        // Orders not completed
        $db->query("SELECT COUNT(*) as total FROM orders WHERE status != 'completed'");
        $row = $db->single();
        $stats['orders_not_completed'] = (int) ($row['total'] ?? 0);

        // Total revenue (paid orders)
        $db->query("SELECT COALESCE(SUM(payment_amount), 0) as total_revenue FROM orders WHERE payment_status = 'paid'");
        $row = $db->single();
        $stats['total_revenue_paid'] = (float) ($row['total_revenue'] ?? 0);

        // Popular services
        $db->query("SELECT s.title, COUNT(o.id) as order_count FROM orders o JOIN services s ON s.id = o.service_id GROUP BY s.id, s.title ORDER BY order_count DESC LIMIT 5");
        $popularServices = $db->resultSet();
        $stats['popular_services'] = is_array($popularServices) ? $popularServices : [];

        // Technicians
        $db->query("SELECT t.id, u.name as technician_name, t.status, t.skills_summary FROM technicians t JOIN users u ON u.id = t.user_id ORDER BY u.name");
        $technicians = $db->resultSet();
        $stats['technicians'] = is_array($technicians) ? $technicians : [];

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Riwayat pesanan customer yang sedang login (read-only, scoped by signed user_id).
     */
    public function customerOrders()
    {
        $this->requireGet();
        $claims = $this->authenticate();

        if ($claims['role'] !== 'customer') {
            $this->json([
                'success' => false,
                'message' => 'Endpoint ini hanya untuk role customer.',
            ], 403);
        }

        $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT, [
            'options' => ['default' => 10, 'min_range' => 1, 'max_range' => 50],
        ]);
        if ($limit === false) {
            $limit = 10;
        }

        $db = new Database();
        if (!$db->isConnected()) {
            $this->json([
                'success' => false,
                'message' => 'Database tidak tersedia.',
            ], 503);
        }

        $sql = "SELECT o.order_code, o.status, o.payment_status, o.created_at,
                       s.title AS service_name, s.price_range AS service_price
                  FROM orders o
                  JOIN customers c ON c.id = o.customer_id
                  JOIN services s ON s.id = o.service_id
                 WHERE c.user_id = :signed_user_id
                 ORDER BY o.created_at DESC, o.id DESC
                 LIMIT :row_limit";

        $db->query($sql);
        $db->bind(':signed_user_id', (int) $claims['user_id']);
        $db->bind(':row_limit', (int) $limit);

        $orders = $db->resultSet();
        if (!is_array($orders)) {
            $orders = [];
        }

        $this->json([
            'success' => true,
            'data' => [
                'user_id' => (int) $claims['user_id'],
                'scope' => 'customer',
                'read_only' => true,
                'count' => count($orders),
                'orders' => $orders,
            ],
        ]);
    }

    /**
     * Ringkasan aktivitas admin (read-only). Gabungan beberapa statistik untuk laporan.
     */
    public function adminActivitySummary()
    {
        $this->requireGet();
        $claims = $this->authenticate();

        if ($claims['role'] !== 'admin') {
            $this->json([
                'success' => false,
                'message' => 'Endpoint ini hanya untuk role admin.',
            ], 403);
        }

        $db = new Database();
        if (!$db->isConnected()) {
            $this->json([
                'success' => false,
                'message' => 'Database tidak tersedia.',
            ], 503);
        }

        $summary = [];

        // Orders per last 7 days
        $db->query("SELECT DATE(created_at) as tanggal, COUNT(*) as jumlah FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY tanggal ASC");
        $summary['orders_last_7_days'] = is_array($db->resultSet()) ? $db->resultSet() : [];

        // Orders per last 30 days
        $db->query("SELECT DATE(created_at) as tanggal, COUNT(*) as jumlah FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at) ORDER BY tanggal ASC");
        $summary['orders_last_30_days'] = is_array($db->resultSet()) ? $db->resultSet() : [];

        // Payment status distribution
        $db->query("SELECT payment_status, COUNT(*) as jumlah FROM orders GROUP BY payment_status");
        $summary['payment_status_distribution'] = is_array($db->resultSet()) ? $db->resultSet() : [];

        // Recent orders (latest 10)
        $db->query("SELECT o.order_code, o.status, o.payment_status, o.created_at, s.title as service_name, u.name as customer_name FROM orders o JOIN customers c ON c.id = o.customer_id JOIN users u ON u.id = c.user_id JOIN services s ON s.id = o.service_id ORDER BY o.created_at DESC LIMIT 10");
        $summary['recent_orders'] = is_array($db->resultSet()) ? $db->resultSet() : [];

        // Top customers by order count
        $db->query("SELECT u.name as customer_name, u.email, COUNT(o.id) as order_count FROM orders o JOIN customers c ON c.id = o.customer_id JOIN users u ON u.id = c.user_id GROUP BY u.id, u.name, u.email ORDER BY order_count DESC LIMIT 5");
        $summary['top_customers'] = is_array($db->resultSet()) ? $db->resultSet() : [];

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'summary' => $summary,
            ],
        ]);
    }

    public function context()
    {
        $this->requireGet();
        $claims = $this->authenticate();

        $isAdmin = $claims['role'] === 'admin';
        $this->json([
            'success' => true,
            'data' => [
                'role' => $claims['role'],
                'scope' => $claims['scope'],
                'read_only' => true,
                'can_write' => false,
                'permissions' => [
                    'read_services' => true,
                    'read_own_orders' => !$isAdmin,
                    'read_all_orders' => $isAdmin,
                    'read_customers' => $isAdmin,
                    'read_reports' => $isAdmin,
                    'write_any_data' => false,
                ],
                'implemented_endpoints' => [
                    '/api/internal/ai/orders',
                ],
            ],
        ]);
    }

    /**
     * Reference read-only endpoint used to verify scope isolation in Stage 3.
     * Customer requests are always filtered by the signed user id; a customer
     * cannot request another customer's data through a query parameter.
     */
    public function orders()
    {
        $this->requireGet();
        $claims = $this->authenticate();

        $limit = filter_var($_GET['limit'] ?? 20, FILTER_VALIDATE_INT, [
            'options' => ['default' => 20, 'min_range' => 1, 'max_range' => 50],
        ]);
        if ($limit === false) {
            $limit = 20;
        }

        $allowedStatuses = [
            'submitted', 'verified', 'scheduled', 'assigned', 'in_progress',
            'waiting_confirmation', 'completed', 'cancelled',
        ];
        $status = strtolower(trim((string) ($_GET['status'] ?? '')));
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $this->json([
                'success' => false,
                'message' => 'Status pesanan tidak valid.',
            ], 422);
        }

        $db = new Database();
        if (!$db->isConnected()) {
            $this->json([
                'success' => false,
                'message' => 'Database tidak tersedia.',
            ], 503);
        }

        $sql = "SELECT o.order_code, o.status, o.payment_status, o.created_at,
                       s.title AS service_title
                  FROM orders o
                  JOIN customers c ON c.id = o.customer_id
                  JOIN services s ON s.id = o.service_id
                 WHERE 1 = 1";
        $params = [];

        if ($claims['role'] === 'customer') {
            // Never accept customer_id/user_id from the query string here.
            $sql .= " AND c.user_id = :signed_user_id";
            $params[':signed_user_id'] = (int) $claims['user_id'];
        }

        if ($status !== '') {
            $sql .= " AND o.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY o.created_at DESC, o.id DESC LIMIT :row_limit";

        $db->query($sql);
        foreach ($params as $name => $value) {
            $db->bind($name, $value);
        }
        $db->bind(':row_limit', (int) $limit);

        $orders = $db->resultSet();
        if (!is_array($orders)) {
            $orders = [];
        }

        $this->json([
            'success' => true,
            'data' => [
                'scope' => $claims['scope'],
                'read_only' => true,
                'user_id' => $claims['user_id'],
                'count' => count($orders),
                'orders' => $orders,
            ],
        ]);
    }

    private function requireGet()
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
            header('Allow: GET');
            $this->json([
                'success' => false,
                'message' => 'Endpoint internal hanya menerima GET.',
            ], 405);
        }
    }

    private function authenticate()
    {
        try {
            return $this->auth->authenticateRequest();
        } catch (\RuntimeException $e) {
            $code = (int) $e->getCode();
            if (!in_array($code, [401, 403, 503], true)) {
                $code = 500;
            }

            $message = $code === 503
                ? 'Layanan AI internal belum dikonfigurasi.'
                : ($code === 403 ? 'Role tidak diizinkan.' : 'Autentikasi internal gagal.');

            $this->json([
                'success' => false,
                'message' => $message,
            ], $code);
        }
    }

    private function json($payload, $status = 200)
    {
        if (!headers_sent()) {
            http_response_code((int) $status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-Content-Type-Options: nosniff');
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
