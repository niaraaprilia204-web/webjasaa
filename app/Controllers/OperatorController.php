<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Services/OperatorService.php';
require_once __DIR__ . '/../Services/ReportService.php';
require_once __DIR__ . '/../Services/AiSettingsService.php';
require_once __DIR__ . '/../Models/ChatLog.php';

class OperatorController extends BaseController
{
    private $operatorService;
    private $reportService;
    private $aiSettingsService;

    public function __construct()
    {
        parent::__construct();
        // Guarded strictly for 'operator' role
        $roleGuard = new RoleMiddleware(['operator']);
        $roleGuard->handle();

        $this->operatorService = new OperatorService();
        $this->reportService = new ReportService();
        $this->aiSettingsService = new AiSettingsService();
    }

    public function dashboard()
    {
        $metrics = array_merge(
            $this->operatorService->getDashboardMetrics(),
            $this->operatorService->getEngagementMetrics()
        );

        $data = [
            'title'      => 'Dashboard Operator Operasional - TKJ Service Hub',
            'activeMenu' => 'dashboard_operator',
            'user'       => $_SESSION['user'],
            'metrics'    => $metrics
        ];

        $this->renderLayout('operator/dashboard', $data);
    }

    public function reviews()
    {
        $search = trim((string) ($_GET['search'] ?? ''));

        $data = [
            'title'      => 'Semua Ulasan Customer - Operator TKJ Service Hub',
            'activeMenu' => 'operator_reviews',
            'user'       => $_SESSION['user'],
            'reviews'    => $this->operatorService->getAllReviews($search),
            'summary'    => $this->operatorService->getReviewSummary(),
            'search'     => $search,
        ];

        $this->renderLayout('operator/reviews', $data);
    }

    public function aiConfig()
    {
        $settings = $this->aiSettingsService->getSettings();

        $data = [
            'title'           => 'Konfigurasi AI - Operator TKJ Service Hub',
            'activeMenu'      => 'operator_ai_config',
            'user'            => $_SESSION['user'],
            'settings'        => $settings,
            'apiKeyConfigured'=> $this->aiSettingsService->isApiKeyConfigured(),
            'models'          => $this->aiSettingsService->getModelOptions(),
            'csrfToken'       => $this->csrfToken(),
        ];

        $this->renderLayout('operator/ai_config', $data);
    }

    public function updateAiConfig()
    {
        $this->requirePostAndCsrf('/operator/ai-config');
        $result = $this->aiSettingsService->save($_POST);
        $this->flashResult($result);
        $this->redirect('/operator/ai-config');
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
            'title'       => 'Riwayat Chat & Keluhan - Operator TKJ Service Hub',
            'activeMenu'  => 'operator_chat_history',
            'user'        => $_SESSION['user'],
            'chatLogs'    => $chatLogModel->getRecent($search, $limit, $offset),
            'complaints'  => $this->operatorService->getComplaints($search, 100),
            'summary'     => $chatLogModel->getSummary(),
            'search'      => $search,
            'pagination'  => [
                'page'  => $page,
                'pages' => max(1, (int) ceil($total / $limit)),
                'total' => $total,
            ],
        ];

        $this->renderLayout('operator/chat_history', $data);
    }

    public function reports()
    {
        $startDate = trim((string) ($_GET['start_date'] ?? ''));
        $endDate = trim((string) ($_GET['end_date'] ?? ''));

        $orderReport = $this->reportService->getOrderReport($startDate, $endDate);

        $data = [
            'title'      => 'Laporan Operasional - Operator TKJ Service Hub',
            'activeMenu' => 'operator_reports',
            'user'       => $_SESSION['user'],
            'report'     => $orderReport,
            'chatReport' => $this->reportService->getChatReport(),
            'filters'    => [
                'start_date' => $orderReport['startDate'],
                'end_date'   => $orderReport['endDate'],
            ],
        ];

        $this->renderLayout('operator/reports', $data);
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

        if (empty($_SESSION['operator_csrf']) || !is_string($_SESSION['operator_csrf'])) {
            $_SESSION['operator_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['operator_csrf'];
    }

    private function validateCsrf()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected = $_SESSION['operator_csrf'] ?? '';

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
