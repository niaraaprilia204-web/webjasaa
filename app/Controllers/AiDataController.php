<?php

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Services/AiInternalAuthService.php';
require_once __DIR__ . '/../Services/AiConfigService.php';

/**
 * Internal, HMAC-protected, READ-ONLY data endpoints for the Node.js chatbot.
 *
 * Design rules:
 *  - GET only. No INSERT/UPDATE/DELETE/DROP/TRUNCATE anywhere in this file.
 *  - The acting user comes from the signed service token, never from a query
 *    parameter. A customer can never widen their scope by passing parameters.
 *  - Admin may read every row; a customer may only read their own rows.
 *  - Every query is a prepared statement.
 */
class AiDataController extends Controller
{
    private $auth;

    private const ORDER_STATUSES = [
        'submitted', 'verified', 'scheduled', 'assigned', 'in_progress',
        'waiting_confirmation', 'completed', 'cancelled',
    ];

    private const PAYMENT_STATUSES = ['unpaid', 'pending', 'paid', 'refunded'];

    public function __construct()
    {
        $this->auth = new AiInternalAuthService();
    }

    // ==============================================================
    // Identity
    // ==============================================================

    /**
     * GET /api/internal/ai/data/identity
     * Identitas akun yang sedang login, diambil dari database berdasarkan
     * user id pada token bertanda tangan.
     */
    public function identity()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $db->query(
            "SELECT id, name, email, username, phone, role, is_active, created_at
               FROM users
              WHERE id = :user_id
              LIMIT 1"
        );
        $db->bind(':user_id', (int) $claims['user_id']);
        $user = $db->single();

        if (!$user) {
            $this->json([
                'success' => false,
                'message' => 'Data akun tidak ditemukan.',
            ], 404);
        }

        $this->json([
            'success' => true,
            'data' => [
                'user_id' => (int) $user['id'],
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
                'username' => (string) ($user['username'] ?? ''),
                'phone' => (string) ($user['phone'] ?? ''),
                'role' => (string) $user['role'],
                'role_label' => $this->roleLabel((string) $user['role']),
                'is_active' => (bool) $user['is_active'],
                'member_since' => (string) ($user['created_at'] ?? ''),
                'source' => 'session_token',
            ],
        ]);
    }

    // ==============================================================
    // Public information (customer + admin)
    // ==============================================================

    /**
     * GET /api/internal/ai/data/public-info
     * Informasi publik layanan: kategori, jumlah layanan, dan ringkasan
     * kategori resmi (bukan data pribadi pengguna).
     */
    public function publicInfo()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $db->query(
            "SELECT sc.id, sc.name, sc.slug, sc.description, sc.icon,
                    (SELECT COUNT(*) FROM services s
                      WHERE s.category_id = sc.id AND s.is_active = 1) AS service_count
               FROM service_categories sc
              ORDER BY sc.name ASC"
        );
        $categories = $db->resultSet();

        $db->query("SELECT COUNT(*) AS total FROM services WHERE is_active = 1");
        $activeServices = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM service_categories");
        $totalCategories = (int) ($db->single()['total'] ?? 0);

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'public',
                'read_only' => true,
                'platform_name' => 'TKJ Service Hub',
                'platform_description' => 'Platform unit bisnis siswa Teknik Komputer & Jaringan (TKJ) yang menyediakan layanan instalasi, konfigurasi, maintenance, dan perbaikan perangkat keras maupun jaringan.',
                'total_active_services' => $activeServices,
                'total_categories' => $totalCategories,
                'categories' => is_array($categories) ? $categories : [],
                'service_order_flow' => [
                    'Pilih layanan yang dibutuhkan',
                    'Isi detail masalah, alamat, dan jadwal kunjungan',
                    'Pesanan masuk dengan status "submitted"',
                    'Operator memverifikasi dan menjadwalkan kunjungan',
                    'Teknisi ditugaskan (work order) dan mengerjakan pekerjaan',
                    'Pelanggan mengonfirmasi, pesanan berstatus "completed"',
                    'Pelanggan dapat memberikan ulasan',
                ],
                'payment_flow' => [
                    'Pembayaran dicatat pada pesanan melalui kolom payment_status (unpaid, pending, paid, refunded)',
                    'Nominal dan waktu pembayaran tersimpan pada payment_amount dan paid_at',
                    'Pelanggan dapat menanyakan status pembayaran pesanannya sendiri',
                ],
                'registration_flow' => [
                    'Pengunjung membuka halaman registrasi dan mengisi nama, email, username, nomor HP, serta alamat/instansi',
                    'Sistem mengirim kode OTP melalui WhatsApp (Fonnte) ke nomor HP yang didaftarkan',
                    'Pengguna memverifikasi kode OTP sebelum akun aktif',
                    'Akun baru dibuat dengan role "customer" secara server-side (role tidak bisa dipilih sendiri)',
                    'Customer dapat login dan mengakses dashboard customer',
                ],
                'report_flow' => [
                    'Customer membuat laporan kerusakan/keluhan pada halaman Laporan Saya',
                    'Laporan disimpan pada tabel customer_reports dengan kode laporan unik',
                    'Admin/Operator memeriksa laporan dan mengubah statusnya (submitted, processing, resolved, rejected)',
                    'Catatan tindak lanjut disimpan di admin_notes',
                    'Customer dapat melihat status laporan miliknya sendiri',
                ],
                'whatsapp_flow' => [
                    'Notifikasi WhatsApp dikirim melalui gateway Fonnte menggunakan token server-side (FONNTE_TOKEN)',
                    'Event notifikasi: pendaftaran customer baru dan pembuatan pesanan baru',
                    'Penerima notifikasi admin diambil dari ADMIN_WHATSAPP_NUMBER pada .env (tidak bisa diatur dari browser)',
                    'Setiap pengiriman dicatat pada tabel whatsapp_notification_logs beserta status dan pesan error (jika ada)',
                    'Admin dapat melihat riwayat dan mengirim ulang notifikasi yang gagal',
                ],
                'website_features' => [
                    'Katalog layanan dengan kategori dan rentang harga',
                    'Registrasi akun customer dengan verifikasi OTP WhatsApp',
                    'Pembuatan pesanan dan pelacakan status pesanan',
                    'Laporan kerusakan/keluhan untuk customer',
                    'Dashboard Admin, Operator, Teknisi, dan Kepala Sekolah',
                    'Notifikasi WhatsApp otomatis untuk pendaftaran dan pesanan',
                    'Asisten AI TKJ Service Hub untuk customer dan admin',
                ],
                'data_flow' => [
                    'Customer melakukan registrasi -> data tersimpan pada users + customers',
                    'Customer membuat pesanan -> data tersimpan pada orders (relasi customers dan services)',
                    'Operator memverifikasi dan menugaskan teknisi -> data work_orders',
                    'Teknisi mengerjakan -> status work_order diperbarui',
                    'Customer mengonfirmasi -> orders.status menjadi completed dan bisa memberi review',
                    'Admin membaca keseluruhan data agregat; customer hanya membaca data miliknya sendiri',
                ],
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/services
     * Query: view=catalog|prices, keyword=..., category=...
     */
    public function services()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $view = strtolower(trim((string) ($_GET['view'] ?? 'catalog')));
        if (!in_array($view, ['catalog', 'prices'], true)) {
            $view = 'catalog';
        }

        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        $category = trim((string) ($_GET['category'] ?? ''));

        $sql = "SELECT s.id, s.title, s.slug, s.description, s.estimated_duration,
                       s.price_range, s.is_active, sc.name AS category_name,
                       sc.description AS category_description
                  FROM services s
                  JOIN service_categories sc ON sc.id = s.category_id
                 WHERE s.is_active = 1";
        $params = [];

        if ($keyword !== '') {
            $sql .= " AND (s.title LIKE :keyword OR s.description LIKE :keyword OR sc.name LIKE :keyword)";
            $params[':keyword'] = '%' . $keyword . '%';
        }

        if ($category !== '') {
            $sql .= " AND sc.name LIKE :category";
            $params[':category'] = '%' . $category . '%';
        }

        $sql .= " ORDER BY sc.name ASC, s.title ASC";

        $db->query($sql);
        foreach ($params as $name => $value) {
            $db->bind($name, $value);
        }
        $rows = $db->resultSet();
        $rows = is_array($rows) ? $rows : [];

        if ($view === 'prices') {
            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'service' => (string) $row['title'],
                    'category' => (string) $row['category_name'],
                    'price_range' => (string) ($row['price_range'] ?? '-'),
                    'estimated_duration' => (string) ($row['estimated_duration'] ?? '-'),
                ];
            }
        } else {
            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'id' => (int) $row['id'],
                    'title' => (string) $row['title'],
                    'category' => (string) $row['category_name'],
                    'category_description' => (string) ($row['category_description'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'price_range' => (string) ($row['price_range'] ?? '-'),
                    'estimated_duration' => (string) ($row['estimated_duration'] ?? '-'),
                ];
            }
        }

        $this->json([
            'success' => true,
            'data' => [
                'view' => $view,
                'read_only' => true,
                'count' => count($items),
                'services' => $items,
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/my-orders
     * Pesanan milik customer yang sedang login (tidak bisa diarahkan ke
     * customer lain karena filter berasal dari token).
     */
    public function myOrders()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $limit = $this->intParam('limit', 10, 1, 50);

        $sql = "SELECT o.order_code, o.status, o.payment_status, o.payment_method,
                       o.payment_amount, o.paid_at, o.scheduled_date, o.completed_at,
                       o.created_at, s.title AS service_title, s.price_range AS service_price
                  FROM orders o
                  JOIN customers c ON c.id = o.customer_id
                  JOIN services s ON s.id = o.service_id
                 WHERE c.user_id = :signed_user_id
                 ORDER BY o.created_at DESC, o.id DESC
                 LIMIT :row_limit";

        $db->query($sql);
        $db->bind(':signed_user_id', (int) $claims['user_id']);
        $db->bind(':row_limit', $limit);
        $rows = $db->resultSet();
        $rows = is_array($rows) ? $rows : [];

        $summary = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'unpaid' => 0];
        foreach ($rows as $row) {
            $summary['total']++;
            if (($row['status'] ?? '') === 'completed') {
                $summary['completed']++;
            }
            if (in_array($row['status'] ?? '', ['submitted', 'verified', 'scheduled', 'assigned', 'in_progress', 'waiting_confirmation'], true)) {
                $summary['in_progress']++;
            }
            if (($row['payment_status'] ?? '') !== 'paid') {
                $summary['unpaid']++;
            }
        }

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'customer',
                'read_only' => true,
                'user_id' => (int) $claims['user_id'],
                'count' => count($rows),
                'summary' => $summary,
                'orders' => $rows,
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/order-detail?code=ORD-...
     * Customer hanya boleh melihat pesanan miliknya sendiri.
     */
    public function orderDetail()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '' || !preg_match('/^ORD-[A-Za-z0-9\-]{4,40}$/', $code)) {
            $this->json([
                'success' => false,
                'message' => 'Kode pesanan tidak valid. Gunakan format ORD-YYYYMMDD-XXXX.',
            ], 422);
        }

        $sql = "SELECT o.id, o.order_code, o.status, o.payment_status, o.payment_method,
                       o.payment_amount, o.paid_at, o.problem_description, o.device_info,
                       o.location_address, o.scheduled_date, o.completed_at,
                       o.operator_notes, o.created_at, o.updated_at,
                       s.title AS service_title, s.price_range AS service_price,
                       s.estimated_duration, sc.name AS category_name
                  FROM orders o
                  JOIN services s ON s.id = o.service_id
                  JOIN service_categories sc ON sc.id = s.category_id
                  JOIN customers c ON c.id = o.customer_id
                  JOIN users u ON u.id = c.user_id
                 WHERE o.order_code = :code";

        if ($claims['role'] === 'customer') {
            $sql .= " AND c.user_id = :signed_user_id";
        }

        $db->query($sql);
        $db->bind(':code', $code);
        if ($claims['role'] === 'customer') {
            $db->bind(':signed_user_id', (int) $claims['user_id']);
        }
        $order = $db->single();

        if (!$order) {
            $this->json([
                'success' => false,
                'message' => $claims['role'] === 'customer'
                    ? 'Pesanan dengan kode tersebut tidak ditemukan pada akun Anda.'
                    : 'Pesanan dengan kode tersebut tidak ditemukan.',
            ], 404);
        }

        // Detail pengerjaan teknisi (khusus admin).
        if ($claims['role'] === 'admin') {
            $db->query(
                "SELECT w.status AS work_status, w.diagnosis_notes, w.action_taken,
                        w.parts_used, w.assigned_at, w.finished_at,
                        t.nisn, t.class_grade, tu.name AS technician_name
                   FROM work_orders w
                   JOIN technicians t ON t.id = w.technician_id
                   JOIN users tu ON tu.id = t.user_id
                  WHERE w.order_id = :order_id
                  ORDER BY w.id DESC"
            );
            $db->bind(':order_id', (int) $order['id']);
            $work = $db->resultSet();
            $order['work_orders'] = is_array($work) ? $work : [];
        }

        $db->query("SELECT rating, comment, created_at FROM reviews WHERE order_id = :order_id LIMIT 1");
        $db->bind(':order_id', (int) $order['id']);
        $review = $db->single();
        $order['review'] = $review ?: null;

        $this->json([
            'success' => true,
            'data' => [
                'scope' => $claims['scope'],
                'read_only' => true,
                'order' => $order,
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/my-reports?limit=
     * Laporan milik customer yang sedang login (filter dari token bertanda
     * tangan, parameter query tidak dapat melebarkan cakupan).
     */
    public function myReports()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $limit = $this->intParam('limit', 10, 1, 50);

        $sql = "SELECT r.id, r.report_code, r.subject, r.category, r.status,
                       r.admin_notes, r.created_at, r.updated_at
                  FROM customer_reports r
                  JOIN customers c ON c.id = r.customer_id
                 WHERE c.user_id = :signed_user_id
                 ORDER BY r.created_at DESC, r.id DESC
                 LIMIT :row_limit";

        $db->query($sql);
        $db->bind(':signed_user_id', (int) $claims['user_id']);
        $db->bind(':row_limit', $limit);
        $rows = $db->resultSet();
        $rows = is_array($rows) ? $rows : [];

        $summary = ['total' => 0, 'submitted' => 0, 'processing' => 0, 'resolved' => 0, 'rejected' => 0];
        foreach ($rows as $row) {
            $summary['total']++;
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
        }

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'customer',
                'read_only' => true,
                'user_id' => (int) $claims['user_id'],
                'count' => count($rows),
                'summary' => $summary,
                'reports' => $rows,
            ],
        ]);
    }

    // ==============================================================
    // Runtime configuration (non-secret, all roles)
    // ==============================================================

    /**
     * GET /api/internal/ai/data/config
     * Konfigurasi chatbot non-secret yang disimpan lewat halaman Admin
     * "AI Configuration": parameter model, pengetahuan sistem aktif, dan
     * prompt aktif per role. TIDAK pernah berisi API key.
     * Melemahkan dengan anggun bila tabel konfigurasi belum dimigrasi.
     */
    public function config()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $provider = 'openrouter';
        $model = '';
        $temperature = 0.3;
        $maxTokens = 1800;
        $timeoutMs = 30000;
        $modelSource = 'default';

        try {
            $db->query("SELECT provider, model, temperature, max_tokens, timeout_ms FROM ai_model_config ORDER BY id ASC LIMIT 1");
            $row = $db->single();
            if (is_array($row)) {
                $provider = (string) ($row['provider'] ?? $provider);
                $model = (string) ($row['model'] ?? '');
                $temperature = (float) ($row['temperature'] ?? $temperature);
                $maxTokens = (int) ($row['max_tokens'] ?? $maxTokens);
                $timeoutMs = (int) ($row['timeout_ms'] ?? $timeoutMs);
                $modelSource = 'database';
            }
        } catch (\Throwable $e) {
            // Tabel belum dimigrasi -> pakai default Node.
        }

        $temperature = max(0.0, min(1.5, $temperature));
        $maxTokens = max(256, min(4000, $maxTokens));
        $timeoutMs = max(5000, min(120000, $timeoutMs));

        $knowledge = [];
        try {
            $db->query(
                "SELECT key_name, title, content, is_active
                   FROM ai_system_knowledge
                  WHERE is_active = 1
                  ORDER BY id ASC"
            );
            $knowledge = $db->resultSet();
            $knowledge = is_array($knowledge) ? $knowledge : [];
        } catch (\Throwable $e) {
            $knowledge = [];
        }

        $promptAdmin = '';
        $promptCustomer = '';
        try {
            $db->query(
                "SELECT role_key, system_prompt
                   FROM ai_prompt_config
                  WHERE is_active = 1
                    AND role_key IN ('admin', 'customer')"
            );
            $promptRows = $db->resultSet();
            foreach (is_array($promptRows) ? $promptRows : [] as $row) {
                $roleKey = (string) ($row['role_key'] ?? '');
                $prompt = trim((string) ($row['system_prompt'] ?? ''));
                if ($roleKey === 'admin' && $prompt !== '') {
                    $promptAdmin = $prompt;
                } elseif ($roleKey === 'customer' && $prompt !== '') {
                    $promptCustomer = $prompt;
                }
            }
        } catch (\Throwable $e) {
            $promptAdmin = '';
            $promptCustomer = '';
        }

        $this->json([
            'success' => true,
            'data' => [
                'read_only' => true,
                'provider' => $provider,
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'timeout_ms' => $timeoutMs,
                'model_source' => $modelSource,
                'api_key' => (new AiConfigService())->getDecryptedApiKey(),
                'api_key_source' => (new AiConfigService())->getApiKeyStatus()['data']['source'] ?? 'env',
                'system_knowledge' => array_map(static function ($row) {
                    return [
                        'key' => (string) ($row['key_name'] ?? ''),
                        'title' => (string) ($row['title'] ?? ''),
                        'content' => (string) ($row['content'] ?? ''),
                        'is_active' => (int) ($row['is_active'] ?? 1),
                    ];
                }, $knowledge),
                'prompt_admin' => $promptAdmin,
                'prompt_customer' => $promptCustomer,
            ],
        ]);
    }

    // ==============================================================
    // Admin analytics (read-only)
    // ==============================================================

    /**
     * GET /api/internal/ai/data/admin/totals
     */
    public function totals()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        if (!$this->requireAdmin($claims)) {
            return;
        }
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $totals = [];

        $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'customer'");
        $totals['total_users'] = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM customers");
        $totals['total_customers'] = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM services WHERE is_active = 1");
        $totals['total_active_services'] = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM technicians");
        $totals['total_technicians'] = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM orders");
        $totals['total_orders'] = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM orders WHERE DATE(created_at) = CURDATE()");
        $totals['orders_today'] = (int) ($db->single()['total'] ?? 0);

        $db->query(
            "SELECT COUNT(*) AS total FROM orders
              WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        );
        $totals['orders_this_month'] = (int) ($db->single()['total'] ?? 0);

        $db->query(
            "SELECT COUNT(*) AS total FROM orders
              WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
                AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        );
        $totals['orders_last_month'] = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM orders WHERE status <> 'completed'");
        $totals['orders_not_completed'] = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'cancelled'");
        $totals['orders_cancelled'] = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM orders WHERE payment_status = 'paid'");
        $totals['orders_paid'] = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM orders WHERE payment_status <> 'paid'");
        $totals['orders_unpaid'] = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COALESCE(SUM(payment_amount), 0) AS total FROM orders WHERE payment_status = 'paid'");
        $totals['total_revenue_paid'] = (float) ($db->single()['total'] ?? 0);

        $db->query("SELECT COALESCE(AVG(payment_amount), 0) AS total FROM orders WHERE payment_status = 'paid'");
        $totals['average_paid_order'] = (float) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM reviews");
        $totals['total_reviews'] = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM notifications");
        $totals['total_notifications'] = (int) ($db->single()['total'] ?? 0);

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'totals' => $totals,
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/admin/customers?limit=&keyword=
     */
    public function customers()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        if (!$this->requireAdmin($claims)) {
            return;
        }
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $limit = $this->intParam('limit', 20, 1, 100);
        $keyword = trim((string) ($_GET['keyword'] ?? ''));

        $sql = "SELECT u.id AS user_id, u.name, u.email, u.username, u.phone, u.is_active,
                       u.created_at AS registered_at,
                       c.id AS customer_id, c.institution, c.address,
                       COUNT(o.id) AS order_count,
                       COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.payment_amount ELSE 0 END), 0) AS total_paid,
                       MAX(o.created_at) AS last_order_at
                  FROM users u
                  JOIN customers c ON c.user_id = u.id
                  LEFT JOIN orders o ON o.customer_id = c.id
                 WHERE u.role = 'customer'";
        $params = [];

        if ($keyword !== '') {
            $sql .= " AND (u.name LIKE :keyword OR u.email LIKE :keyword OR u.username LIKE :keyword)";
            $params[':keyword'] = '%' . $keyword . '%';
        }

        $sql .= " GROUP BY u.id, u.name, u.email, u.username, u.phone, u.is_active,
                         u.created_at, c.id, c.institution, c.address
                  ORDER BY order_count DESC, u.name ASC
                  LIMIT :row_limit";

        $db->query($sql);
        foreach ($params as $name => $value) {
            $db->bind($name, $value);
        }
        $db->bind(':row_limit', $limit);
        $rows = $db->resultSet();
        $rows = is_array($rows) ? $rows : [];

        $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'customer'");
        $totalRegistered = (int) ($db->single()['total'] ?? 0);

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'total_registered_customers' => $totalRegistered,
                'count' => count($rows),
                'customers' => $rows,
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/admin/users?role=&keyword=&limit=
     * Daftar user + agregat per role (total user, admin, customer, customer
     * terbaru, dan tanggal pendaftaran customer terbaru).
     */
    public function adminUsers()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        if (!$this->requireAdmin($claims)) {
            return;
        }
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $limit = $this->intParam('limit', 20, 1, 100);
        $role = strtolower(trim((string) ($_GET['role'] ?? '')));
        $keyword = trim((string) ($_GET['keyword'] ?? ''));

        $allowedRoles = ['admin', 'customer', 'technician', 'operator', 'principal'];
        if ($role !== '' && !in_array($role, $allowedRoles, true)) {
            $role = '';
        }

        // Agregat per role (selalu lengkap, tidak terpengaruh filter daftar).
        $db->query("SELECT role, COUNT(*) AS total FROM users GROUP BY role");
        $byRoleRows = $db->resultSet();
        $byRole = array_fill_keys($allowedRoles, 0);
        foreach (is_array($byRoleRows) ? $byRoleRows : [] as $row) {
            $byRole[(string) $row['role']] = (int) $row['total'];
        }

        $db->query("SELECT COUNT(*) AS total FROM users");
        $totalUsers = (int) ($db->single()['total'] ?? 0);

        // Customer terbaru (berdasarkan users.created_at).
        $db->query(
            "SELECT u.id, u.name, u.email, u.username, u.phone, u.created_at
               FROM users u
              WHERE u.role = 'customer'
              ORDER BY u.created_at DESC, u.id DESC
              LIMIT 1"
        );
        $newestCustomer = $db->single() ?: null;

        // Daftar user dengan filter.
        $sql = "SELECT id, name, email, username, phone, role, is_active, created_at
                  FROM users
                 WHERE 1 = 1";
        $params = [];

        if ($role !== '') {
            $sql .= " AND role = :role";
            $params[':role'] = $role;
        }
        if ($keyword !== '') {
            $sql .= " AND (name LIKE :keyword OR email LIKE :keyword OR username LIKE :keyword)";
            $params[':keyword'] = '%' . $keyword . '%';
        }

        $sql .= " ORDER BY created_at DESC, id DESC LIMIT :row_limit";

        $db->query($sql);
        foreach ($params as $name => $value) {
            $db->bind($name, $value);
        }
        $db->bind(':row_limit', $limit);
        $rows = $db->resultSet();
        $rows = is_array($rows) ? $rows : [];

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'total_users' => $totalUsers,
                'total_admin' => $byRole['admin'],
                'total_customer' => $byRole['customer'],
                'total_technician' => $byRole['technician'],
                'total_operator' => $byRole['operator'],
                'total_principal' => $byRole['principal'],
                'users_by_role' => $byRole,
                'newest_customer' => $newestCustomer,
                'role_filter' => $role !== '' ? $role : null,
                'count' => count($rows),
                'users' => $rows,
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/admin/reports?status=&limit=
     * Daftar laporan customer (tabel customer_reports) + agregat status.
     */
    public function adminReports()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        if (!$this->requireAdmin($claims)) {
            return;
        }
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $limit = $this->intParam('limit', 20, 1, 100);
        $status = strtolower(trim((string) ($_GET['status'] ?? '')));
        $allowedStatuses = ['submitted', 'processing', 'resolved', 'rejected'];
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            $this->json([
                'success' => false,
                'message' => 'Status laporan tidak valid.',
            ], 422);
        }

        $db->query("SELECT COUNT(*) AS total FROM customer_reports");
        $totalReports = (int) ($db->single()['total'] ?? 0);

        $db->query(
            "SELECT status, COUNT(*) AS total
               FROM customer_reports
              GROUP BY status"
        );
        $statusRows = $db->resultSet();
        $byStatus = array_fill_keys($allowedStatuses, 0);
        foreach (is_array($statusRows) ? $statusRows : [] as $row) {
            $byStatus[(string) $row['status']] = (int) $row['total'];
        }

        $db->query(
            "SELECT COUNT(*) AS total FROM customer_reports WHERE status IN ('submitted', 'processing')"
        );
        $unfinished = (int) ($db->single()['total'] ?? 0);

        $sql = "SELECT r.id, r.report_code, r.subject, r.category, r.status,
                       r.admin_notes, r.created_at, r.updated_at,
                       u.name AS customer_name, u.email AS customer_email,
                       u.phone AS customer_phone, c.institution AS customer_institution
                  FROM customer_reports r
                  JOIN customers c ON c.id = r.customer_id
                  JOIN users u ON u.id = c.user_id
                 WHERE 1 = 1";
        $params = [];

        if ($status !== '') {
            $sql .= " AND r.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY r.created_at DESC, r.id DESC LIMIT :row_limit";

        $db->query($sql);
        foreach ($params as $name => $value) {
            $db->bind($name, $value);
        }
        $db->bind(':row_limit', $limit);
        $rows = $db->resultSet();
        $rows = is_array($rows) ? $rows : [];

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'total_reports' => $totalReports,
                'total_unfinished' => $unfinished,
                'by_status' => $byStatus,
                'status_filter' => $status !== '' ? $status : null,
                'count' => count($rows),
                'reports' => $rows,
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/admin/orders?period=&status=&service_id=&limit=
     */
    public function orders()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        if (!$this->requireAdmin($claims)) {
            return;
        }
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $limit = $this->intParam('limit', 20, 1, 100);
        $period = strtolower(trim((string) ($_GET['period'] ?? 'all')));
        $status = strtolower(trim((string) ($_GET['status'] ?? '')));
        $serviceId = $this->intParam('service_id', 0, 0, 100000);

        if ($status !== '' && !in_array($status, self::ORDER_STATUSES, true)) {
            $this->json([
                'success' => false,
                'message' => 'Status pesanan tidak valid.',
            ], 422);
        }

        $sql = "SELECT o.order_code, o.status, o.payment_status, o.payment_method,
                       o.payment_amount, o.paid_at, o.scheduled_date, o.completed_at,
                       o.created_at, s.title AS service_title, s.price_range AS service_price,
                       u.name AS customer_name, u.email AS customer_email
                  FROM orders o
                  JOIN customers c ON c.id = o.customer_id
                  JOIN users u ON u.id = c.user_id
                  JOIN services s ON s.id = o.service_id
                 WHERE 1 = 1";
        $params = [];

        $periodSql = $this->periodSql($period);
        if ($periodSql !== '') {
            $sql .= ' AND ' . $periodSql;
        }

        if ($status !== '') {
            $sql .= " AND o.status = :status";
            $params[':status'] = $status;
        }

        if ($serviceId > 0) {
            $sql .= " AND o.service_id = :service_id";
            $params[':service_id'] = $serviceId;
        }

        $sql .= " ORDER BY o.created_at DESC, o.id DESC LIMIT :row_limit";

        $db->query($sql);
        foreach ($params as $name => $value) {
            $db->bind($name, $value);
        }
        $db->bind(':row_limit', $limit);
        $rows = $db->resultSet();
        $rows = is_array($rows) ? $rows : [];

        $db->query("SELECT COUNT(*) AS total FROM orders");
        $totalAll = (int) ($db->single()['total'] ?? 0);

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'period' => $period,
                'status_filter' => $status !== '' ? $status : null,
                'total_orders_all_time' => $totalAll,
                'count' => count($rows),
                'orders' => $rows,
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/admin/orders-summary?group_by=status|payment_status|service|customer|technician
     */
    public function ordersSummary()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        if (!$this->requireAdmin($claims)) {
            return;
        }
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $groupBy = strtolower(trim((string) ($_GET['group_by'] ?? 'status')));
        $allowed = ['status', 'payment_status', 'service', 'customer', 'technician'];
        if (!in_array($groupBy, $allowed, true)) {
            $groupBy = 'status';
        }

        switch ($groupBy) {
            case 'payment_status':
                $db->query(
                    "SELECT o.payment_status AS label, COUNT(*) AS total,
                            COALESCE(SUM(o.payment_amount), 0) AS amount
                       FROM orders o
                      GROUP BY o.payment_status
                      ORDER BY total DESC"
                );
                break;
            case 'service':
                $db->query(
                    "SELECT s.title AS label, COUNT(o.id) AS total,
                            COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.payment_amount ELSE 0 END), 0) AS amount
                       FROM services s
                       LEFT JOIN orders o ON o.service_id = s.id
                      GROUP BY s.id, s.title
                      ORDER BY total DESC, s.title ASC"
                );
                break;
            case 'customer':
                $db->query(
                    "SELECT u.name AS label, u.email AS secondary, COUNT(o.id) AS total,
                            COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.payment_amount ELSE 0 END), 0) AS amount
                       FROM orders o
                       JOIN customers c ON c.id = o.customer_id
                       JOIN users u ON u.id = c.user_id
                      GROUP BY u.id, u.name, u.email
                      ORDER BY total DESC, u.name ASC"
                );
                break;
            case 'technician':
                $db->query(
                    "SELECT tu.name AS label, COUNT(w.id) AS total
                       FROM work_orders w
                       JOIN technicians t ON t.id = w.technician_id
                       JOIN users tu ON tu.id = t.user_id
                      GROUP BY tu.id, tu.name
                      ORDER BY total DESC, tu.name ASC"
                );
                break;
            default:
                $db->query(
                    "SELECT o.status AS label, COUNT(*) AS total,
                            COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.payment_amount ELSE 0 END), 0) AS amount
                       FROM orders o
                      GROUP BY o.status
                      ORDER BY total DESC, o.status ASC"
                );
                break;
        }

        $rows = $db->resultSet();
        $rows = is_array($rows) ? $rows : [];

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'group_by' => $groupBy,
                'count' => count($rows),
                'groups' => $rows,
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/admin/payments
     */
    public function payments()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        if (!$this->requireAdmin($claims)) {
            return;
        }
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $db->query(
            "SELECT o.payment_status,
                    COUNT(*) AS total,
                    COALESCE(SUM(o.payment_amount), 0) AS amount,
                    MIN(o.paid_at) AS first_paid_at,
                    MAX(o.paid_at) AS last_paid_at
               FROM orders o
              GROUP BY o.payment_status
              ORDER BY total DESC"
        );
        $byStatus = $db->resultSet();

        $db->query(
            "SELECT COALESCE(NULLIF(o.payment_method, ''), 'tidak dicatat') AS payment_method,
                    COUNT(*) AS total,
                    COALESCE(SUM(o.payment_amount), 0) AS amount
               FROM orders o
              GROUP BY COALESCE(NULLIF(o.payment_method, ''), 'tidak dicatat')
              ORDER BY total DESC"
        );
        $byMethod = $db->resultSet();

        $db->query(
            "SELECT COALESCE(SUM(payment_amount), 0) AS revenue,
                    COUNT(*) AS paid_orders
               FROM orders
              WHERE payment_status = 'paid'"
        );
        $paid = $db->single();

        $db->query(
            "SELECT COALESCE(SUM(payment_amount), 0) AS outstanding
               FROM orders
              WHERE payment_status IN ('unpaid', 'pending')"
        );
        $outstanding = $db->single();

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'revenue_paid' => (float) ($paid['revenue'] ?? 0),
                'paid_orders' => (int) ($paid['paid_orders'] ?? 0),
                'outstanding_amount' => (float) ($outstanding['outstanding'] ?? 0),
                'by_status' => is_array($byStatus) ? $byStatus : [],
                'by_method' => is_array($byMethod) ? $byMethod : [],
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/admin/technicians
     */
    public function technicians()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        if (!$this->requireAdmin($claims)) {
            return;
        }
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $db->query(
            "SELECT u.id AS user_id, u.name, u.email, u.phone,
                    t.id AS technician_id, t.nisn, t.class_grade, t.skills_summary, t.status,
                    COUNT(w.id) AS job_count,
                    SUM(CASE WHEN w.status = 'done' THEN 1 ELSE 0 END) AS job_done,
                    SUM(CASE WHEN w.status = 'in_progress' THEN 1 ELSE 0 END) AS job_in_progress
               FROM technicians t
               JOIN users u ON u.id = t.user_id
               LEFT JOIN work_orders w ON w.technician_id = t.id
              GROUP BY u.id, u.name, u.email, u.phone,
                       t.id, t.nisn, t.class_grade, t.skills_summary, t.status
              ORDER BY job_count DESC, u.name ASC"
        );
        $rows = $db->resultSet();
        $rows = is_array($rows) ? $rows : [];

        $db->query(
            "SELECT w.status AS label, COUNT(*) AS total
               FROM work_orders w
              GROUP BY w.status
              ORDER BY total DESC"
        );
        $workStatus = $db->resultSet();

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'count' => count($rows),
                'technicians' => $rows,
                'work_order_status_summary' => is_array($workStatus) ? $workStatus : [],
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/admin/popular-services?limit=
     */
    public function popularServices()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        if (!$this->requireAdmin($claims)) {
            return;
        }
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $limit = $this->intParam('limit', 5, 1, 20);

        $db->query(
            "SELECT s.id, s.title, s.price_range, sc.name AS category_name,
                    COUNT(o.id) AS order_count,
                    COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.payment_amount ELSE 0 END), 0) AS paid_amount
               FROM services s
               JOIN service_categories sc ON sc.id = s.category_id
               LEFT JOIN orders o ON o.service_id = s.id
              GROUP BY s.id, s.title, s.price_range, sc.name
              ORDER BY order_count DESC, s.title ASC
              LIMIT :row_limit"
        );
        $db->bind(':row_limit', $limit);
        $rows = $db->resultSet();
        $rows = is_array($rows) ? $rows : [];

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'count' => count($rows),
                'services' => $rows,
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/admin/period-comparison
     */
    public function periodComparison()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        if (!$this->requireAdmin($claims)) {
            return;
        }
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $db->query(
            "SELECT
                SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) AS this_month_orders,
                SUM(CASE WHEN created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
                          AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) AS last_month_orders,
                SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND payment_status = 'paid' THEN 1 ELSE 0 END) AS this_month_paid,
                SUM(CASE WHEN created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
                          AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') AND payment_status = 'paid' THEN 1 ELSE 0 END) AS last_month_paid,
                COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND payment_status = 'paid' THEN payment_amount ELSE 0 END), 0) AS this_month_revenue,
                COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
                                   AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01') AND payment_status = 'paid' THEN payment_amount ELSE 0 END), 0) AS last_month_revenue
               FROM orders"
        );
        $summary = $db->single();
        $summary = is_array($summary) ? $summary : [];

        $db->query(
            "SELECT DATE(created_at) AS tanggal, COUNT(*) AS jumlah
               FROM orders
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
              GROUP BY DATE(created_at)
              ORDER BY tanggal ASC"
        );
        $daily = $db->resultSet();

        $db->query(
            "SELECT s.title AS label, COUNT(*) AS jumlah
               FROM orders o
               JOIN services s ON s.id = o.service_id
              WHERE o.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
              GROUP BY s.id, s.title
              ORDER BY jumlah DESC, s.title ASC"
        );
        $thisMonthServices = $db->resultSet();

        $db->query(
            "SELECT s.title AS label, COUNT(*) AS jumlah
               FROM orders o
               JOIN services s ON s.id = o.service_id
              WHERE o.created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
                AND o.created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')
              GROUP BY s.id, s.title
              ORDER BY jumlah DESC, s.title ASC"
        );
        $lastMonthServices = $db->resultSet();

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'this_month_orders' => (int) ($summary['this_month_orders'] ?? 0),
                'last_month_orders' => (int) ($summary['last_month_orders'] ?? 0),
                'this_month_paid_orders' => (int) ($summary['this_month_paid'] ?? 0),
                'last_month_paid_orders' => (int) ($summary['last_month_paid'] ?? 0),
                'this_month_revenue' => (float) ($summary['this_month_revenue'] ?? 0),
                'last_month_revenue' => (float) ($summary['last_month_revenue'] ?? 0),
                'daily_last_30_days' => is_array($daily) ? $daily : [],
                'this_month_by_service' => is_array($thisMonthServices) ? $thisMonthServices : [],
                'last_month_by_service' => is_array($lastMonthServices) ? $lastMonthServices : [],
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/admin/schema?table=
     * Struktur database nyata, dibaca langsung dari information_schema MySQL.
     */
    public function schema()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        if (!$this->requireAdmin($claims)) {
            return;
        }
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $table = trim((string) ($_GET['table'] ?? ''));

        $sql = "SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name,
                       DATA_TYPE AS data_type, COLUMN_TYPE AS column_type,
                       IS_NULLABLE AS is_nullable, COLUMN_KEY AS column_key,
                       COLUMN_DEFAULT AS column_default, EXTRA AS extra,
                       ORDINAL_POSITION AS position
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()";
        $params = [];
        if ($table !== '') {
            $sql .= " AND TABLE_NAME = :table";
            $params[':table'] = $table;
        }
        $sql .= " ORDER BY TABLE_NAME, ORDINAL_POSITION";

        $db->query($sql);
        foreach ($params as $name => $value) {
            $db->bind($name, $value);
        }
        $columns = $db->resultSet();
        $columns = is_array($columns) ? $columns : [];

        $fkSql = "SELECT TABLE_NAME AS table_name, CONSTRAINT_NAME AS constraint_name,
                         COLUMN_NAME AS column_name, REFERENCED_TABLE_NAME AS referenced_table,
                         REFERENCED_COLUMN_NAME AS referenced_column
                    FROM information_schema.KEY_COLUMN_USAGE
                   WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL";
        if ($table !== '') {
            $fkSql .= " AND TABLE_NAME = :table";
        }
        $fkSql .= " ORDER BY TABLE_NAME, CONSTRAINT_NAME";

        $db->query($fkSql);
        foreach ($params as $name => $value) {
            $db->bind($name, $value);
        }
        $foreignKeys = $db->resultSet();
        $foreignKeys = is_array($foreignKeys) ? $foreignKeys : [];

        $db->query(
            "SELECT TABLE_NAME AS table_name
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
              ORDER BY TABLE_NAME"
        );
        $tableNames = $db->resultSet();
        $tableNames = is_array($tableNames) ? $tableNames : [];

        $tables = [];
        foreach ($tableNames as $row) {
            $name = (string) $row['table_name'];
            if ($table !== '' && $name !== $table) {
                continue;
            }

            $db->query("SELECT COUNT(*) AS total FROM `" . str_replace('`', '', $name) . "`");
            $rowCount = (int) ($db->single()['total'] ?? 0);

            $tableColumns = [];
            foreach ($columns as $column) {
                if ((string) $column['table_name'] !== $name) {
                    continue;
                }
                $tableColumns[] = [
                    'name' => (string) $column['column_name'],
                    'type' => (string) $column['column_type'],
                    'nullable' => (string) $column['is_nullable'] === 'YES',
                    'key' => (string) $column['column_key'],
                    'default' => $column['column_default'],
                    'extra' => (string) $column['extra'],
                ];
            }

            $tableFks = [];
            foreach ($foreignKeys as $fk) {
                if ((string) $fk['table_name'] !== $name) {
                    continue;
                }
                $tableFks[] = [
                    'constraint' => (string) $fk['constraint_name'],
                    'column' => (string) $fk['column_name'],
                    'references_table' => (string) $fk['referenced_table'],
                    'references_column' => (string) $fk['referenced_column'],
                ];
            }

            $primaryKey = [];
            foreach ($tableColumns as $column) {
                if ($column['key'] === 'PRI') {
                    $primaryKey[] = $column['name'];
                }
            }

            $tables[] = [
                'table' => $name,
                'row_count' => $rowCount,
                'primary_key' => $primaryKey,
                'columns' => $tableColumns,
                'foreign_keys' => $tableFks,
            ];
        }

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'database' => DB_NAME,
                'source' => 'information_schema',
                'table_filter' => $table !== '' ? $table : null,
                'total_tables' => count($tables),
                'tables' => $tables,
            ],
        ]);
    }

    /**
     * GET /api/internal/ai/data/admin/activity?days=
     */
    public function activity()
    {
        $this->requireGet();
        $claims = $this->authenticate();
        if (!$this->requireAdmin($claims)) {
            return;
        }
        $db = $this->db();
        if ($db === null) {
            return;
        }

        $days = $this->intParam('days', 7, 1, 90);

        $db->query(
            "SELECT DATE(created_at) AS tanggal, COUNT(*) AS jumlah,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS selesai,
                    SUM(CASE WHEN status <> 'completed' AND status <> 'cancelled' THEN 1 ELSE 0 END) AS berjalan
               FROM orders
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
              GROUP BY DATE(created_at)
              ORDER BY tanggal ASC"
        );
        $db->bind(':days', $days);
        $daily = $db->resultSet();

        $db->query(
            "SELECT DATE(created_at) AS tanggal, COUNT(*) AS jumlah
               FROM users
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
              GROUP BY DATE(created_at)
              ORDER BY tanggal ASC"
        );
        $db->bind(':days', $days);
        $newUsers = $db->resultSet();

        $db->query(
            "SELECT DATE(created_at) AS tanggal, COUNT(*) AS jumlah
               FROM ai_chat_logs
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
              GROUP BY DATE(created_at)
              ORDER BY tanggal ASC"
        );
        $db->bind(':days', $days);
        $chatLogs = $db->resultSet();

        $db->query(
            "SELECT source AS label, COUNT(*) AS total
               FROM ai_chat_logs
              GROUP BY source
              ORDER BY total DESC"
        );
        $chatSources = $db->resultSet();

        // notifications tidak memiliki kolom status; ringkasan memakai is_read.
        $db->query(
            "SELECT is_read AS label, COUNT(*) AS total
               FROM notifications
              GROUP BY is_read"
        );
        $notificationStatus = $db->resultSet();

        $db->query("SELECT COUNT(*) AS total FROM notifications");
        $totalNotifications = (int) ($db->single()['total'] ?? 0);

        $db->query("SELECT COUNT(*) AS total FROM ai_chat_logs");
        $totalChatLogs = (int) ($db->single()['total'] ?? 0);

        $this->json([
            'success' => true,
            'data' => [
                'scope' => 'admin',
                'read_only' => true,
                'period_days' => $days,
                'orders_per_day' => is_array($daily) ? $daily : [],
                'new_users_per_day' => is_array($newUsers) ? $newUsers : [],
                'chat_logs_per_day' => is_array($chatLogs) ? $chatLogs : [],
                'chat_log_sources' => is_array($chatSources) ? $chatSources : [],
                'notification_read_summary' => is_array($notificationStatus) ? $notificationStatus : [],
                'total_notifications' => $totalNotifications,
                'total_chat_logs' => $totalChatLogs,
            ],
        ]);
    }

    // ==============================================================
    // Helpers
    // ==============================================================

    private function roleLabel($role)
    {
        $map = [
            'admin' => 'Admin',
            'customer' => 'Customer',
            'technician' => 'Technician',
            'operator' => 'Operator',
            'principal' => 'Principal',
        ];
        return $map[$role] ?? ucfirst($role);
    }

    private function periodSql($period)
    {
        switch ($period) {
            case 'today':
                return 'o.created_at >= CURDATE() AND o.created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
            case 'yesterday':
                return 'o.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND o.created_at < CURDATE()';
            case 'this_week':
                return 'o.created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)';
            case 'this_month':
                return "o.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
            case 'last_month':
                return "o.created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND o.created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')";
            case 'last_7_days':
                return 'o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)';
            case 'last_30_days':
                return 'o.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)';
            default:
                return '';
        }
    }

    private function intParam($name, $default, $min, $max)
    {
        $value = filter_var($_GET[$name] ?? $default, FILTER_VALIDATE_INT, [
            'options' => ['default' => $default, 'min_range' => $min, 'max_range' => $max],
        ]);
        if ($value === false) {
            return $default;
        }
        return (int) $value;
    }

    private function requireAdmin(array $claims)
    {
        if ($claims['role'] !== 'admin') {
            $this->json([
                'success' => false,
                'message' => 'Endpoint analisis ini hanya untuk role admin.',
            ], 403);
            return false;
        }
        return true;
    }

    private function db()
    {
        $db = new Database();
        if (!$db->isConnected()) {
            $this->json([
                'success' => false,
                'message' => 'Database tidak tersedia.',
            ], 503);
            return null;
        }
        return $db;
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
