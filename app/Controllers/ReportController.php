<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Core/Csrf.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Services/CustomerReportService.php';

/**
 * Laporan (komplain) customer.
 *
 * Customer hanya dapat membuat dan melihat laporan miliknya sendiri.
 * Otorisasi pemilik dilakukan di server (user_id dari session).
 */
class ReportController extends BaseController
{
    private $reportService;

    public function __construct()
    {
        parent::__construct();

        $roleGuard = new RoleMiddleware(['customer']);
        $roleGuard->handle();

        $this->reportService = CustomerReportService::create();
    }

    /**
     * Daftar laporan milik customer yang sedang login.
     */
    public function index()
    {
        $userId = (int) $_SESSION['user']['id'];

        $data = [
            'title'      => 'Laporan Saya - TKJ Service Hub',
            'activeMenu' => 'my_reports',
            'user'       => $_SESSION['user'],
            'reports'    => $this->reportService->getCustomerReports($userId),
            'categories' => CustomerReportService::CATEGORIES,
        ];

        $this->renderLayout('customer/reports/index', $data);
    }

    /**
     * Formulir kirim laporan baru.
     */
    public function create()
    {
        $data = [
            'title'      => 'Kirim Laporan - TKJ Service Hub',
            'activeMenu' => 'my_reports',
            'user'       => $_SESSION['user'],
            'categories' => CustomerReportService::CATEGORIES,
            'csrfToken'  => Csrf::token('report'),
        ];

        $this->renderLayout('customer/reports/create', $data);
    }

    /**
     * Simpan laporan baru lalu arahkan customer.
     */
    public function store()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Location: ' . BASEURL . '/customer/reports/create');
            exit;
        }

        if (!Csrf::validate('report')) {
            Flasher::setMessage('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/customer/reports/create');
            exit;
        }

        $userId = (int) $_SESSION['user']['id'];
        $result = $this->reportService->createReport($userId, $_POST);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/customer/reports/create');
            exit;
        }

        Flasher::setMessage(
            $result['message'] . ' Kode laporan: ' . ($result['data']['report_code'] ?? '-'),
            'Sukses',
            'success'
        );
        header('Location: ' . BASEURL . '/customer/reports');
        exit;
    }
}
