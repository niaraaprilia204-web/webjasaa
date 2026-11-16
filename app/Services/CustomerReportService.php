<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/WhatsappNotificationService.php';
require_once __DIR__ . '/../Models/CustomerReport.php';
require_once __DIR__ . '/../Models/Customer.php';
require_once __DIR__ . '/../Models/User.php';

/**
 * Layanan laporan (komplain) customer.
 *
 * Alur:
 *   Customer kirim laporan
 *     -> validasi -> simpan ke customer_reports
 *        -> gagal : customer diberi tahu WhatsApp (tidak ada konfirmasi sukses)
 *        -> sukses: Admin diberi tahu + customer menerima konfirmasi
 *
 * Status laporan diubah oleh admin/operator
 *     -> simpan -> customer pemilik diberi tahu WhatsApp
 */
class CustomerReportService extends BaseService
{
    const STATUSES = ['submitted', 'processing', 'resolved', 'rejected'];

    const STATUS_LABELS = [
        'submitted'  => 'Terkirim',
        'processing' => 'Diproses',
        'resolved'   => 'Selesai',
        'rejected'   => 'Ditolak',
    ];

    const CATEGORIES = ['umum', 'layanan', 'pembayaran', 'teknisi', 'lainnya'];

    private $reportModel;
    private $customerModel;
    private $userModel;

    /**
     * Optional factory override used by automated tests.
     */
    private static $factory = null;

    public static function setFactory(callable $factory = null)
    {
        self::$factory = $factory;
    }

    public static function create()
    {
        if (self::$factory !== null) {
            $instance = call_user_func(self::$factory);
            if ($instance instanceof self) {
                return $instance;
            }
        }

        return new self();
    }

    public function __construct()
    {
        $this->reportModel = new CustomerReport();
        $this->customerModel = new Customer();
        $this->userModel = new User();
    }

    /**
     * Simpan laporan ke database.
     * Dipisah agar jalur kegagalan penyimpanan dapat diuji tanpa merusak data.
     */
    protected function persistReport(array $data)
    {
        return $this->reportModel->create($data);
    }

    /**
     * Buat laporan baru milik customer yang sedang login.
     */
    public function createReport($userId, array $data)
    {
        $userId = (int) $userId;
        if ($userId < 1) {
            return $this->response(false, 'Sesi pengguna tidak valid.');
        }

        $subject = trim((string) ($data['subject'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $category = strtolower(trim((string) ($data['category'] ?? 'umum')));

        if (mb_strlen($subject) < 5) {
            return $this->response(false, 'Judul laporan minimal 5 karakter.');
        }

        if (mb_strlen($subject) > 150) {
            return $this->response(false, 'Judul laporan maksimal 150 karakter.');
        }

        if (mb_strlen($description) < 10) {
            return $this->response(false, 'Isi laporan minimal 10 karakter.');
        }

        if (!in_array($category, self::CATEGORIES, true)) {
            $category = 'umum';
        }

        $customerId = $this->resolveCustomerId($userId);
        if (!$customerId) {
            $this->notifyFailure($userId, 'Profil pelanggan tidak ditemukan.');
            return $this->response(false, 'Profil pelanggan tidak ditemukan.');
        }

        $created = $this->persistReport([
            'report_code' => $this->generateReportCode(),
            'customer_id' => $customerId,
            'user_id'     => $userId,
            'subject'     => $subject,
            'description' => $description,
            'category'    => $category,
            'status'      => 'submitted',
        ]);

        if (!$created) {
            // Penyimpanan gagal: customer diberi tahu, TIDAK ada konfirmasi sukses.
            $this->notifyFailure($userId, 'Laporan gagal disimpan di database.');
            return $this->response(false, 'Laporan gagal disimpan. Silakan coba lagi.');
        }

        $report = $this->reportModel->findForUser($this->latestReportId($userId), $userId);

        // Notifikasi hanya setelah data benar-benar tersimpan.
        $this->notifySuccess($report);

        return $this->response(true, 'Laporan Anda berhasil dikirim dan sedang ditinjau Admin.', [
            'report_id'   => (int) ($report['id'] ?? 0),
            'report_code' => (string) ($report['report_code'] ?? ''),
        ]);
    }

    /**
     * Ubah status laporan (admin/operator) lalu beri tahu pemilik.
     */
    public function updateStatus($reportId, $status, $adminNotes = '', $handledBy = null)
    {
        $reportId = (int) $reportId;
        $status = strtolower(trim((string) $status));

        if ($reportId < 1) {
            return $this->response(false, 'Laporan tidak valid.');
        }

        if (!in_array($status, self::STATUSES, true)) {
            return $this->response(false, 'Status laporan tidak valid.');
        }

        $report = $this->reportModel->find($reportId);
        if (!$report) {
            return $this->response(false, 'Laporan tidak ditemukan.');
        }

        $oldStatus = (string) $report['status'];

        $updated = $this->reportModel->updateStatus(
            $reportId,
            $status,
            $adminNotes !== '' ? $adminNotes : null,
            $handledBy
        );

        if (!$updated) {
            // Gagal menyimpan -> tidak ada notifikasi sukses.
            return $this->response(false, 'Gagal memperbarui status laporan.');
        }

        $fresh = $this->reportModel->find($reportId);
        $this->notifyStatusChange($fresh ?: $report, $oldStatus, $status);

        return $this->response(true, 'Status laporan berhasil diperbarui menjadi "' . self::statusLabel($status) . '".');
    }

    /**
     * Laporan milik customer yang sedang login.
     */
    public function getCustomerReports($userId)
    {
        return $this->reportModel->forUser((int) $userId);
    }

    /**
     * Semua laporan untuk admin/operator.
     */
    public function getAllReports($statusFilter = '')
    {
        return $this->reportModel->all($statusFilter);
    }

    /**
     * Ambil satu laporan milik user (otorisasi pemilik).
     */
    public function findReportForUser($reportId, $userId)
    {
        return $this->reportModel->findForUser((int) $reportId, (int) $userId);
    }

    public static function statusLabel($status)
    {
        return self::STATUS_LABELS[$status] ?? ucfirst((string) $status);
    }

    // ==============================================================
    // Helper
    // ==============================================================

    /**
     * ID baris customers milik user (dibuat bila belum ada).
     */
    private function resolveCustomerId($userId)
    {
        $customer = $this->customerModel->findByUserId((int) $userId);
        if (!empty($customer['id'])) {
            return (int) $customer['id'];
        }

        $created = $this->customerModel->create([
            'user_id'     => (int) $userId,
            'address'     => '',
            'institution' => 'Masyarakat Umum',
        ]);

        if (!$created) {
            return 0;
        }

        $customer = $this->customerModel->findByUserId((int) $userId);

        return (int) ($customer['id'] ?? 0);
    }

    private function generateReportCode()
    {
        $prefix = 'RPT-' . date('Ymd') . '-';
        do {
            $code = $prefix . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $exists = $this->reportModel->where('report_code', $code);
        } while (!empty($exists));

        return $code;
    }

    private function latestReportId($userId)
    {
        $reports = $this->reportModel->forUser((int) $userId);

        return (int) ($reports[0]['id'] ?? 0);
    }

    private function notifySuccess($report)
    {
        if (empty($report['id'])) {
            return;
        }

        try {
            $service = WhatsappNotificationService::create();
            $service->notifyReportCreated((int) $report['id']);
            $service->notifyCustomerReportCreated((int) $report['id']);
        } catch (\Throwable $e) {
            error_log('[WhatsApp Notify] Notifikasi laporan gagal diproses.');
        }
    }

    private function notifyFailure($userId, $reason)
    {
        try {
            $service = WhatsappNotificationService::create();
            $service->notifyCustomerReportFailed((int) $userId, $reason);
        } catch (\Throwable $e) {
            error_log('[WhatsApp Notify] Notifikasi kegagalan laporan gagal diproses.');
        }
    }

    private function notifyStatusChange($report, $oldStatus, $newStatus)
    {
        if (empty($report['id'])) {
            return;
        }

        try {
            $service = WhatsappNotificationService::create();
            $service->notifyReportStatusChanged((int) $report['id'], $oldStatus, $newStatus);
        } catch (\Throwable $e) {
            error_log('[WhatsApp Notify] Notifikasi perubahan status laporan gagal diproses.');
        }
    }
}
