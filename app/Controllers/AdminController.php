<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Services/AdminService.php';
require_once __DIR__ . '/../Services/ReportService.php';
require_once __DIR__ . '/../Services/CatalogService.php';
require_once __DIR__ . '/../Models/ChatLog.php';

/**
 * Administrator dashboard: users, reports, chatbot history, and catalog.
 */
class AdminController extends BaseController
{
    private $adminService;
    private $reportService;
    private $catalogService;

    public function __construct()
    {
        parent::__construct();
        $roleGuard = new RoleMiddleware(['admin']);
        $roleGuard->handle();

        $this->adminService = new AdminService();
        $this->reportService = new ReportService();
        $this->catalogService = new CatalogService();
    }

    /**
     * Status integrasi WhatsApp (Fonnte) + riwayat notifikasi.
     * Token Fonnte tidak pernah dikirim ke view.
     */
    public function whatsapp()
    {
        require_once __DIR__ . '/../Services/WhatsappNotificationService.php';
        require_once __DIR__ . '/../Models/WhatsappNotificationLog.php';

        $notificationService = new WhatsappNotificationService();
        $logModel = new WhatsappNotificationLog();

        $data = [
            'title'      => 'Notifikasi WhatsApp Admin - TKJ Service Hub',
            'activeMenu' => 'admin_whatsapp',
            'user'       => $_SESSION['user'],
            'config'     => [
                'configured'    => $notificationService->isConfigured(),
                'masked_number' => $notificationService->getMaskedRecipient(),
                'provider'      => 'Fonnte',
            ],
            'summary'    => $logModel->summary(),
            'logs'       => $logModel->recent(50),
            'csrfToken'  => $this->csrfToken(),
        ];

        $this->renderLayout('admin/whatsapp', $data);
    }

    /**
     * Daftar seluruh laporan customer (admin/operator).
     */
    public function customerReports()
    {
        require_once __DIR__ . '/../Services/CustomerReportService.php';

        $statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
        $reportService = new CustomerReportService();

        $data = [
            'title'      => 'Laporan Customer - Admin TKJ Service Hub',
            'activeMenu' => 'admin_customer_reports',
            'user'       => $_SESSION['user'],
            'reports'    => $reportService->getAllReports($statusFilter),
            'statuses'   => CustomerReportService::STATUSES,
            'filters'    => ['status' => $statusFilter],
            'csrfToken'  => $this->csrfToken(),
        ];

        $this->renderLayout('admin/customer_reports', $data);
    }

    /**
     * Ubah status laporan (hanya admin/operator yang punya halaman ini).
     */
    public function updateCustomerReportStatus()
    {
        require_once __DIR__ . '/../Services/CustomerReportService.php';

        $this->requirePostAndCsrf('/admin/customer-reports');

        $reportId = (int) ($_POST['report_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        $notes = trim((string) ($_POST['admin_notes'] ?? ''));

        $reportService = new CustomerReportService();
        $result = $reportService->updateStatus($reportId, $status, $notes, (int) ($_SESSION['user']['id'] ?? 0));

        $this->flashResult($result);
        $this->redirect('/admin/customer-reports');
    }

    /**
     * Kirim ulang notifikasi WhatsApp yang gagal (dengan proteksi duplikasi).
     */
    public function resendWhatsappNotification()
    {
        require_once __DIR__ . '/../Services/WhatsappNotificationService.php';

        $this->requirePostAndCsrf('/admin/whatsapp');

        $logId = (int) ($_POST['notification_id'] ?? 0);

        $service = WhatsappNotificationService::create();
        $result = $service->resend($logId);

        if (!empty($result['success'])) {
            Flasher::setMessage('Notifikasi WhatsApp berhasil dikirim ulang.', 'Sukses', 'success');
        } else {
            $messages = [
                'already_sent'           => 'Notifikasi ini sudah pernah berhasil dikirim, sehingga tidak dikirim ulang.',
                'notification_not_found' => 'Data notifikasi tidak ditemukan.',
                'no_message_stored'      => 'Isi pesan tidak tersimpan, sehingga tidak dapat dikirim ulang.',
                'invalid_recipient'      => 'Nomor WhatsApp penerima tidak valid atau kosong.',
                'not_configured'         => 'Fonnte belum dikonfigurasi pada file .env.',
            ];
            Flasher::setMessage(
                $messages[$result['reason'] ?? ''] ?? 'Gagal mengirim ulang notifikasi.',
                'Gagal',
                'danger'
            );
        }

        $this->redirect('/admin/whatsapp');
    }

    public function dashboard()
    {
        $data = [
            'title'        => 'Dashboard Administrator - TKJ Service Hub',
            'activeMenu'   => 'admin_dashboard',
            'user'         => $_SESSION['user'],
            'metrics'      => $this->adminService->getDashboardMetrics(),
            'recentUsers'  => $this->adminService->getRecentUsers(5),
            'recentOrders' => $this->reportService->getRecentOrders(5),
            'recentChats'  => $this->adminService->getRecentChatLogs(5),
        ];

        $this->renderLayout('admin/dashboard', $data);
    }

    public function users()
    {
        $search = trim((string) ($_GET['search'] ?? ''));
        $role = trim((string) ($_GET['role'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));

        $data = [
            'title'        => 'Kelola Data Pengguna - Admin TKJ Service Hub',
            'activeMenu'   => 'admin_users',
            'user'         => $_SESSION['user'],
            'users'        => $this->adminService->getUsers($search, $role, $status),
            'filters'      => ['search' => $search, 'role' => $role, 'status' => $status],
            'csrfToken'    => $this->csrfToken(),
            'currentUserId'=> (int) ($_SESSION['user']['id'] ?? 0),
        ];

        $this->renderLayout('admin/users/index', $data);
    }

    public function createUser()
    {
        $this->requirePostAndCsrf('/admin/users');
        $result = $this->adminService->createUser($_POST, (int) ($_SESSION['user']['id'] ?? 0));
        $this->flashResult($result);
        $this->redirect('/admin/users');
    }

    public function deleteUser($id = null)
    {
        $this->requirePostAndCsrf('/admin/users');
        $userId = $id !== null ? (int) $id : (int) ($_POST['user_id'] ?? 0);
        $result = $this->adminService->deleteUser($userId, (int) ($_SESSION['user']['id'] ?? 0));
        $this->flashResult($result);
        $this->redirect('/admin/users');
    }

    public function updateUser($id = null)
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->redirect('/admin/users');
        }

        if (!$this->validateCsrf()) {
            Flasher::setMessage('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 'Akses Ditolak', 'danger');
            $this->redirect('/admin/users');
        }

        $userId = $id !== null ? (int) $id : (int) ($_POST['user_id'] ?? 0);
        $result = $this->adminService->updateUser(
            $userId,
            $_POST,
            (int) ($_SESSION['user']['id'] ?? 0)
        );

        Flasher::setMessage(
            $result['message'],
            $result['success'] ? 'Sukses' : 'Gagal',
            $result['success'] ? 'success' : 'danger'
        );
        $this->redirect('/admin/users');
    }

    public function reports()
    {
        $startDate = trim((string) ($_GET['start_date'] ?? ''));
        $endDate = trim((string) ($_GET['end_date'] ?? ''));

        $orderReport = $this->reportService->getOrderReport($startDate, $endDate);

        $data = [
            'title'       => 'Laporan & Analitik - Admin TKJ Service Hub',
            'activeMenu'  => 'admin_reports',
            'user'        => $_SESSION['user'],
            'report'      => $orderReport,
            'userReport'  => $this->reportService->getUserReport(),
            'chatReport'  => $this->reportService->getChatReport(),
            'filters'     => [
                'start_date' => $orderReport['startDate'],
                'end_date'   => $orderReport['endDate'],
            ],
        ];

        $this->renderLayout('admin/reports', $data);
    }

    public function chatHistory()
    {
        $search = trim((string) ($_GET['search'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $chatLogModel = new ChatLog();
        $total = $chatLogModel->countAll($search);

        $data = [
            'title'      => 'Riwayat Chatbot - Admin TKJ Service Hub',
            'activeMenu' => 'admin_chat_history',
            'user'       => $_SESSION['user'],
            'chatLogs'   => $chatLogModel->getRecent($search, $limit, $offset),
            'summary'    => $chatLogModel->getSummary(),
            'search'     => $search,
            'pagination' => [
                'page'  => $page,
                'pages' => max(1, (int) ceil($total / $limit)),
                'total' => $total,
            ],
        ];

        $this->renderLayout('admin/chat_history', $data);
    }

    public function services()
    {
        $search = trim((string) ($_GET['search'] ?? ''));
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : null;
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? (int) $_GET['status'] : null;

        $filtered = $this->catalogService->getFilteredServices($search, $categoryId, $status, 1, 200);

        $data = [
            'title'      => 'Katalog Layanan - Admin TKJ Service Hub',
            'activeMenu' => 'admin_services',
            'user'       => $_SESSION['user'],
            'services'   => $filtered['services'],
            'categories' => $this->catalogService->getAllCategories(),
            'filters'    => [
                'search'      => $search,
                'category_id' => $categoryId,
                'status'      => $status,
            ],
            'csrfToken'  => $this->csrfToken(),
        ];

        $this->renderLayout('admin/services/index', $data);
    }

    public function storeService()
    {
        $this->requirePostAndCsrf('/admin/services');
        $result = $this->catalogService->storeService($_POST);
        $this->flashResult($result);
        $this->redirect('/admin/services');
    }

    public function updateService($id = null)
    {
        $this->requirePostAndCsrf('/admin/services');
        $serviceId = $id !== null ? (int) $id : (int) ($_POST['id'] ?? 0);
        $result = $this->catalogService->updateService($serviceId, $_POST);
        $this->flashResult($result);
        $this->redirect('/admin/services');
    }

    public function toggleService($id = null)
    {
        $this->requirePostAndCsrf('/admin/services');
        $serviceId = $id !== null ? (int) $id : (int) ($_POST['id'] ?? 0);
        $result = $this->catalogService->toggleServiceStatus($serviceId);
        $this->flashResult($result);
        $this->redirect('/admin/services');
    }

    public function deleteService($id = null)
    {
        $this->requirePostAndCsrf('/admin/services');
        $serviceId = $id !== null ? (int) $id : (int) ($_POST['id'] ?? 0);
        $result = $this->catalogService->deleteService($serviceId);
        $this->flashResult($result);
        $this->redirect('/admin/services');
    }

    private function requirePostAndCsrf($redirectPath)
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->redirect($redirectPath);
        }

        if (!$this->validateCsrf()) {
            Flasher::setMessage('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 'Akses Ditolak', 'danger');
            $this->redirect($redirectPath);
        }
    }

    private function flashResult($result)
    {
        Flasher::setMessage(
            $result['message'] ?? 'Permintaan tidak dapat diproses.',
            !empty($result['success']) ? 'Sukses' : 'Gagal',
            !empty($result['success']) ? 'success' : 'danger'
        );
    }

    private function csrfToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['admin_csrf']) || !is_string($_SESSION['admin_csrf'])) {
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['admin_csrf'];
    }

    private function validateCsrf()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected = $_SESSION['admin_csrf'] ?? '';

        return is_string($submitted)
            && is_string($expected)
            && $expected !== ''
            && hash_equals($expected, $submitted);
    }

    private function redirect($path)
    {
        header('Location: ' . BASEURL . $path);
        exit;
    }
}
