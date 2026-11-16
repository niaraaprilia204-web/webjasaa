<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/../Models/Order.php';
require_once __DIR__ . '/../Models/Customer.php';
require_once __DIR__ . '/../Models/Technician.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/WorkOrder.php';
require_once __DIR__ . '/../Models/Review.php';
require_once __DIR__ . '/../Models/ChatLog.php';

/**
 * Operator Operational Management Service
 */
class OperatorService extends BaseService
{
    private $orderModel;
    private $customerModel;
    private $technicianModel;
    private $userModel;
    private $workOrderModel;
    private $notificationService;

    public function __construct()
    {
        $this->orderModel = new Order();
        $this->customerModel = new Customer();
        $this->technicianModel = new Technician();
        $this->userModel = new User();
        $this->workOrderModel = new WorkOrder();
        $this->notificationService = new NotificationService();
    }

    /**
     * Compute 8 Core Dashboard Metrics & Popular Services Ranking
     */
    public function getDashboardMetrics()
    {
        $db = new Database();
        if (!$db->isConnected()) {
            return [
                'totalOrders'      => 0,
                'newOrders'        => 0,
                'activeOrders'     => 0,
                'completedOrders'  => 0,
                'totalCustomers'   => 0,
                'activeTechnicians'=> 0,
                'estimatedRevenue' => 'Rp 0',
                'popularServices'  => []
            ];
        }

        // 1. Total Orders
        $db->query("SELECT COUNT(*) as cnt FROM orders");
        $totalOrders = (int)($db->single()['cnt'] ?? 0);

        // 2. New Orders (submitted)
        $db->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'submitted'");
        $newOrders = (int)($db->single()['cnt'] ?? 0);

        // 3. Active Orders
        $db->query("SELECT COUNT(*) as cnt FROM orders WHERE status IN ('submitted', 'verified', 'scheduled', 'assigned', 'in_progress', 'waiting_confirmation')");
        $activeOrders = (int)($db->single()['cnt'] ?? 0);

        // 4. Completed Orders
        $db->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'completed'");
        $completedOrders = (int)($db->single()['cnt'] ?? 0);

        // 5. Total Customers
        $db->query("SELECT COUNT(*) as cnt FROM customers");
        $totalCustomers = (int)($db->single()['cnt'] ?? 0);

        // 6. Active Technicians (available or on_duty)
        $db->query("SELECT COUNT(*) as cnt FROM technicians WHERE status IN ('available', 'on_duty')");
        $activeTechnicians = (int)($db->single()['cnt'] ?? 0);

        // 7. Actual revenue recorded from paid orders.
        try {
            $db->query("SELECT COALESCE(SUM(payment_amount), 0) AS revenue FROM orders WHERE payment_status = 'paid'");
            $revenueRaw = (float) (($db->single() ?: [])['revenue'] ?? 0);
        } catch (\Throwable $e) {
            $revenueRaw = 0;
        }
        $estRevenue = 'Rp ' . number_format($revenueRaw, 0, ',', '.');

        // 8. Popular Services Ranking
        $db->query("SELECT s.title, sc.name as category_name, COUNT(o.id) as order_count FROM services s LEFT JOIN orders o ON o.service_id = s.id LEFT JOIN service_categories sc ON s.category_id = sc.id GROUP BY s.id ORDER BY order_count DESC LIMIT 5");
        $popularServices = $db->resultSet();

        return [
            'totalOrders'       => $totalOrders,
            'newOrders'         => $newOrders,
            'activeOrders'      => $activeOrders,
            'completedOrders'   => $completedOrders,
            'totalCustomers'    => $totalCustomers,
            'activeTechnicians' => $activeTechnicians,
            'estimatedRevenue'  => $estRevenue,
            'popularServices'   => $popularServices
        ];
    }

    /**
     * Get Customer List (Never exposing password)
     */
    public function getCustomersList($search = '')
    {
        $db = new Database();
        if (!$db->isConnected()) return [];

        $sql = "SELECT c.id as customer_id, u.name, u.email, u.phone, c.address, c.institution, c.created_at, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) as total_orders FROM customers c JOIN users u ON c.user_id = u.id";
        
        if (!empty($search)) {
            $sql .= " WHERE u.name LIKE :search OR u.email LIKE :search OR c.institution LIKE :search";
        }
        $sql .= " ORDER BY c.id DESC";

        $db->query($sql);
        if (!empty($search)) {
            $db->bind(':search', '%' . $search . '%');
        }

        return $db->resultSet();
    }

    /**
     * Get Technicians List with skills & current workload
     */
    public function getTechniciansList($search = '')
    {
        $db = new Database();
        if (!$db->isConnected()) return [];

        $sql = "SELECT t.id as technician_id, t.nisn, t.class_grade, t.skills_summary, t.status, u.name, u.email, u.phone, (SELECT COUNT(*) FROM work_orders wo WHERE wo.technician_id = t.id AND wo.status IN ('assigned', 'accepted', 'in_progress')) as active_workload FROM technicians t JOIN users u ON t.user_id = u.id";

        if (!empty($search)) {
            $sql .= " WHERE u.name LIKE :search OR t.nisn LIKE :search OR t.class_grade LIKE :search OR t.skills_summary LIKE :search";
        }
        $sql .= " ORDER BY t.id DESC";

        $db->query($sql);
        if (!empty($search)) {
            $db->bind(':search', '%' . $search . '%');
        }

        return $db->resultSet();
    }

    /**
     * Create New Technician (Student TKJ)
     */
    public function storeTechnician($data)
    {
        $name = trim(htmlspecialchars($data['name'] ?? ''));
        $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $phone = trim(htmlspecialchars($data['phone'] ?? ''));
        $nisn = trim(htmlspecialchars($data['nisn'] ?? ''));
        $classGrade = trim(htmlspecialchars($data['class_grade'] ?? ''));
        $skills = trim(htmlspecialchars($data['skills_summary'] ?? ''));
        $password = $data['password'] ?? 'password123';

        if (empty($name) || strlen($name) < 3) {
            return $this->response(false, 'Nama lengkap teknisi minimal 3 karakter.');
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response(false, 'Format email tidak valid.');
        }

        $existing = $this->userModel->findByEmail($email);
        if (!empty($existing)) {
            return $this->response(false, 'Email tersebut sudah terdaftar.');
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // 1. Create User with role technician
        $userCreated = $this->userModel->create([
            'role'      => 'technician',
            'name'      => $name,
            'email'     => $email,
            'password'  => $hashedPassword,
            'phone'     => $phone,
            'is_active' => 1
        ]);

        if (!$userCreated) {
            return $this->response(false, 'Gagal membuat akun teknisi.');
        }

        $newUser = $this->userModel->findByEmail($email);
        if (!empty($newUser)) {
            $newUserId = $newUser[0]['id'];
            $this->technicianModel->create([
                'user_id'        => $newUserId,
                'nisn'           => $nisn,
                'class_grade'    => $classGrade,
                'skills_summary' => $skills,
                'status'         => 'available'
            ]);
        }

        return $this->response(true, "Data siswa teknisi '{$name}' berhasil ditambahkan!");
    }

    /**
     * Update Technician Profile & Availability Status
     */
    public function updateTechnician($id, $data)
    {
        $technician = $this->technicianModel->find($id);
        if (!$technician) {
            return $this->response(false, 'Data teknisi tidak ditemukan.');
        }

        $nisn = trim(htmlspecialchars($data['nisn'] ?? ''));
        $classGrade = trim(htmlspecialchars($data['class_grade'] ?? ''));
        $skills = trim(htmlspecialchars($data['skills_summary'] ?? ''));
        $status = $data['status'] ?? 'available';

        $updated = $this->technicianModel->update($id, [
            'nisn'           => $nisn,
            'class_grade'    => $classGrade,
            'skills_summary' => $skills,
            'status'         => $status
        ]);

        if (!$updated) {
            return $this->response(false, 'Gagal memperbarui profil teknisi.');
        }

        return $this->response(true, 'Profil teknisi berhasil diperbarui!');
    }

    /**
     * Assign Technician to Order (Creates Work Order record & updates statuses)
     */
    public function assignTechnicianToOrder($orderId, $technicianId, $diagnosisNotes = '')
    {
        $order = $this->orderModel->find($orderId);
        if (!$order) {
            return $this->response(false, 'Pesanan tidak ditemukan.');
        }

        $technician = $this->technicianModel->find($technicianId);
        if (!$technician) {
            return $this->response(false, 'Teknisi tidak ditemukan.');
        }

        // 1. Create or Update Work Order
        $db = new Database();
        $db->query("SELECT id FROM work_orders WHERE order_id = :oid AND technician_id = :tid");
        $db->bind(':oid', $orderId);
        $db->bind(':tid', $technicianId);
        $existingWo = $db->single();
        $workOrderId = 0;

        if ($existingWo) {
            $workOrderId = (int) $existingWo['id'];
            $this->workOrderModel->update($existingWo['id'], [
                'status'          => 'assigned',
                'diagnosis_notes' => $diagnosisNotes
            ]);
        } else {
            $this->workOrderModel->create([
                'order_id'        => $orderId,
                'technician_id'   => $technicianId,
                'status'          => 'assigned',
                'diagnosis_notes' => $diagnosisNotes
            ]);
            $db->query("SELECT id FROM work_orders WHERE order_id = :oid AND technician_id = :tid ORDER BY id DESC LIMIT 1");
            $db->bind(':oid', $orderId);
            $db->bind(':tid', $technicianId);
            $createdWo = $db->single();
            $workOrderId = (int) ($createdWo['id'] ?? 0);
        }

        // 2. Update Technician Status to on_duty
        $this->technicianModel->update($technicianId, ['status' => 'on_duty']);

        // 3. Update Order Status to 'assigned'
        $this->orderModel->updateStatus($orderId, 'assigned', "Telah ditugaskan kepada teknisi siswa.");

        try {
            $db->query("SELECT user_id, name FROM technicians t JOIN users u ON u.id = t.user_id WHERE t.id = :tid LIMIT 1");
            $db->bind(':tid', (int) $technicianId);
            $techUser = $db->single();
            if ($techUser) {
                $this->notificationService->notifyUser(
                    (int) $techUser['user_id'],
                    'Tugas baru untuk Anda',
                    'Pesanan ' . $order['order_code'] . ' ditugaskan kepada Anda.',
                    '/technician/jobs/detail/' . $workOrderId
                );
            }

            $db->query("SELECT u.id FROM users u JOIN customers c ON c.user_id = u.id WHERE c.id = :cid LIMIT 1");
            $db->bind(':cid', (int) $order['customer_id']);
            $customerUser = $db->single();
            if ($customerUser) {
                $this->notificationService->notifyUser(
                    (int) $customerUser['id'],
                    'Teknisi telah ditugaskan',
                    'Pesanan ' . $order['order_code'] . ' sedang ditangani oleh teknisi.',
                    '/customer/orders/detail/' . $order['order_code']
                );
            }
        } catch (\Throwable $e) {
            // Notification failure must not roll back the assignment.
        }

        return $this->response(true, "Pesanan #{$order['order_code']} berhasil ditugaskan ke teknisi!");
    }

    /**
     * Additional dashboard metrics for reviews, complaints, and chatbot logs.
     */
    public function getEngagementMetrics()
    {
        $metrics = [
            'totalReviews'     => 0,
            'averageRating'    => 0,
            'activeComplaints' => 0,
            'totalChats'       => 0,
            'todayChats'       => 0,
        ];

        $db = new Database();
        if (!$db->isConnected()) {
            return $metrics;
        }

        try {
            $db->query("SELECT COUNT(*) AS total, COALESCE(ROUND(AVG(rating), 1), 0) AS average_rating FROM reviews");
            $row = $db->single() ?: [];
            $metrics['totalReviews'] = (int) ($row['total'] ?? 0);
            $metrics['averageRating'] = (float) ($row['average_rating'] ?? 0);
        } catch (\Throwable $e) {
        }

        try {
            $db->query("SELECT COUNT(*) AS cnt FROM orders WHERE status NOT IN ('completed', 'cancelled')");
            $row = $db->single() ?: [];
            $metrics['activeComplaints'] = (int) ($row['cnt'] ?? 0);
        } catch (\Throwable $e) {
        }

        try {
            $chatSummary = (new ChatLog())->getSummary();
            $metrics['totalChats'] = (int) ($chatSummary['total'] ?? 0);
            $metrics['todayChats'] = (int) ($chatSummary['today'] ?? 0);
        } catch (\Throwable $e) {
        }

        return $metrics;
    }

    /**
     * All customer reviews with order/service context.
     */
    public function getAllReviews($search = '')
    {
        $db = new Database();
        if (!$db->isConnected()) {
            return [];
        }

        $sql = "SELECT r.id, r.rating, r.comment, r.created_at,
                       o.order_code, o.status AS order_status,
                       s.title AS service_name,
                       u.name AS customer_name, u.email AS customer_email
                  FROM reviews r
                  JOIN orders o ON o.id = r.order_id
                  JOIN services s ON s.id = o.service_id
                  JOIN customers c ON c.id = r.customer_id
                  JOIN users u ON u.id = c.user_id";
        if ($search !== '') {
            $sql .= " WHERE r.comment LIKE :search
                        OR o.order_code LIKE :search
                        OR s.title LIKE :search
                        OR u.name LIKE :search";
        }
        $sql .= " ORDER BY r.created_at DESC, r.id DESC";

        try {
            $db->query($sql);
            if ($search !== '') {
                $db->bind(':search', '%' . $search . '%');
            }
            return $db->resultSet();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getReviewSummary()
    {
        $db = new Database();
        $summary = [
            'total'          => 0,
            'average_rating' => 0,
            'distribution'   => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
        ];
        if (!$db->isConnected()) {
            return $summary;
        }

        try {
            $db->query("SELECT COUNT(*) AS total, COALESCE(ROUND(AVG(rating), 1), 0) AS average_rating FROM reviews");
            $row = $db->single() ?: [];
            $summary['total'] = (int) ($row['total'] ?? 0);
            $summary['average_rating'] = (float) ($row['average_rating'] ?? 0);
        } catch (\Throwable $e) {
        }

        try {
            $db->query("SELECT rating, COUNT(*) AS cnt FROM reviews GROUP BY rating");
            foreach ($db->resultSet() as $row) {
                $rating = (int) $row['rating'];
                if (isset($summary['distribution'][$rating])) {
                    $summary['distribution'][$rating] = (int) $row['cnt'];
                }
            }
        } catch (\Throwable $e) {
        }

        return $summary;
    }

    /**
     * Unresolved orders whose problem description acts as the complaint queue.
     */
    public function getComplaints($search = '', $limit = 50)
    {
        $db = new Database();
        if (!$db->isConnected()) {
            return [];
        }

        $limit = max(1, min(200, (int) $limit));
        $sql = "SELECT o.id, o.order_code, o.status, o.problem_description, o.device_info,
                       o.operator_notes, o.created_at,
                       s.title AS service_name,
                       u.name AS customer_name, u.phone AS customer_phone,
                       c.institution
                  FROM orders o
                  JOIN services s ON s.id = o.service_id
                  JOIN customers c ON c.id = o.customer_id
                  JOIN users u ON u.id = c.user_id
                 WHERE o.status NOT IN ('completed', 'cancelled')";
        if ($search !== '') {
            $sql .= " AND (o.problem_description LIKE :search
                        OR o.order_code LIKE :search
                        OR s.title LIKE :search
                        OR u.name LIKE :search)";
        }
        $sql .= " ORDER BY o.created_at DESC, o.id DESC LIMIT :limit";

        try {
            $db->query($sql);
            if ($search !== '') {
                $db->bind(':search', '%' . $search . '%');
            }
            $db->bind(':limit', $limit, PDO::PARAM_INT);
            return $db->resultSet();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
