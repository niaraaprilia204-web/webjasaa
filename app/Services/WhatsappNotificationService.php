<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/FonnteService.php';
require_once __DIR__ . '/OtpService.php';
require_once __DIR__ . '/../Models/WhatsappNotificationLog.php';

/**
 * Notifikasi WhatsApp untuk Admin dan Customer (Fonnte).
 *
 * Aturan penting:
 *  - Nomor penerima HANYA dari dua sumber resmi:
 *      1. Admin  : ADMIN_WHATSAPP_NUMBER pada .env
 *      2. Customer: kolom users.phone pada database (dimiliki akun itu sendiri)
 *    Tidak pernah memakai input frontend/hidden field.
 *  - Notifikasi hanya dikirim SETELAH transaksi database berhasil.
 *  - Satu event + satu referensi + satu penerima hanya boleh menghasilkan satu
 *    notifikasi (idempotency lewat UNIQUE idempotency_key).
 *  - Kegagalan Fonnte TIDAK PERNAH membatalkan transaksi bisnis.
 *  - Token Fonnte tidak pernah masuk ke pesan, log, atau respons UI.
 *  - Dua status dipisahkan:
 *      * business_status : proses bisnis (success | failed)
 *      * status          : pengiriman WhatsApp (pending | sent | failed)
 */
class WhatsappNotificationService extends BaseService
{
    // Event notifikasi
    const EVENT_CUSTOMER_REGISTERED = 'customer_registered';
    const EVENT_ORDER_CREATED = 'order_created';
    const EVENT_REPORT_CREATED = 'report_created';
    const EVENT_ORDER_STATUS_UPDATED = 'order_status_updated';
    const EVENT_REPORT_STATUS_UPDATED = 'report_status_updated';
    const EVENT_ORDER_FAILED = 'order_failed';
    const EVENT_REPORT_FAILED = 'report_failed';

    // Jenis aktivitas (untuk filter di dashboard)
    const ACTIVITY_REGISTRATION = 'registration';
    const ACTIVITY_ORDER = 'order';
    const ACTIVITY_REPORT = 'report';
    const ACTIVITY_STATUS = 'status_update';

    // Peran penerima
    const RECIPIENT_ADMIN = 'admin';
    const RECIPIENT_CUSTOMER = 'customer';

    private $fonnte;
    private $logModel;

    /**
     * Optional factory override used by automated tests to inject a mocked
     * transport. Production always uses the default (real Fonnte service).
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

    public function __construct($fonnte = null, $logModel = null)
    {
        $this->fonnte = $fonnte instanceof FonnteService ? $fonnte : new FonnteService();
        $this->logModel = $logModel instanceof WhatsappNotificationLog ? $logModel : new WhatsappNotificationLog();
    }

    // ==============================================================
    // Status konfigurasi
    // ==============================================================

    /**
     * True when both the Fonnte token and the admin destination number exist.
     */
    public function isConfigured()
    {
        return $this->fonnte->isConfigured() && $this->getRecipient() !== '';
    }

    /**
     * The single configured admin destination number (server-side only).
     */
    public function getRecipient()
    {
        $raw = defined('ADMIN_WHATSAPP_NUMBER') ? (string) ADMIN_WHATSAPP_NUMBER : '';

        return $this->normalizeNumber($raw);
    }

    /**
     * Masked version of the destination number for the admin UI.
     */
    public function getMaskedRecipient()
    {
        return $this->maskNumber($this->getRecipient());
    }

    // ==============================================================
    // Notifikasi ke ADMIN
    // ==============================================================

    /**
     * Customer baru mendaftar -> Admin diberi tahu.
     */
    public function notifyCustomerRegistered($customerId)
    {
        $customerId = (int) $customerId;
        if ($customerId < 1) {
            return $this->result(false, 'invalid_reference');
        }

        $db = new Database();
        if (!$db->isConnected()) {
            return $this->result(false, 'database_unavailable');
        }

        $db->query(
            "SELECT c.id AS customer_id, c.created_at AS registered_at,
                    u.id AS user_id, u.name, u.email, u.username
               FROM customers c
               JOIN users u ON u.id = c.user_id
              WHERE c.id = :customer_id
              LIMIT 1"
        );
        $db->bind(':customer_id', $customerId);
        $customer = $db->single();

        if (!$customer) {
            return $this->result(false, 'customer_not_found');
        }

        $message = "🔔 CUSTOMER BARU TERDAFTAR\n\n"
            . "Halo Admin TKJ Service Hub!\n\n"
            . "Ada customer baru yang berhasil mendaftar.\n\n"
            . "Nama: {$customer['name']}\n"
            . "Email: {$customer['email']}\n"
            . "ID Customer: {$customer['customer_id']}\n"
            . "Waktu: {$this->formatTimestamp($customer['registered_at'])}\n\n"
            . 'Silakan periksa dashboard Admin.';

        return $this->dispatch(
            self::EVENT_CUSTOMER_REGISTERED,
            self::ACTIVITY_REGISTRATION,
            'customer-' . $customerId,
            $this->adminRecipient(),
            $message,
            'success'
        );
    }

    /**
     * Pesanan baru -> Admin diberi tahu.
     */
    public function notifyOrderCreated($orderId)
    {
        $order = $this->loadOrder($orderId);
        if ($order === null) {
            return $this->result(false, 'order_not_found');
        }

        $message = "🛒 PESANAN BARU MASUK\n\n"
            . "Halo Admin TKJ Service Hub!\n\n"
            . "Ada pesanan layanan baru.\n\n"
            . "ID Pesanan: {$order['order_id']} ({$order['order_code']})\n"
            . "Customer: {$order['customer_name']}\n"
            . "Layanan: {$order['service_name']}\n"
            . "Harga: {$this->priceOf($order)}\n"
            . "Tanggal: {$this->formatTimestamp($order['order_date'])}\n"
            . "Status: {$order['status']}\n\n"
            . 'Silakan periksa dashboard Admin.';

        return $this->dispatch(
            self::EVENT_ORDER_CREATED,
            self::ACTIVITY_ORDER,
            'order-' . (int) $order['order_id'],
            $this->adminRecipient(),
            $message,
            'success'
        );
    }

    /**
     * Laporan baru -> Admin diberi tahu.
     */
    public function notifyReportCreated($reportId)
    {
        $report = $this->loadReport($reportId);
        if ($report === null) {
            return $this->result(false, 'report_not_found');
        }

        $message = "📝 LAPORAN BARU DARI CUSTOMER\n\n"
            . "Halo Admin TKJ Service Hub!\n\n"
            . "Ada laporan baru yang dikirim customer.\n\n"
            . "ID Laporan: {$report['report_id']} ({$report['report_code']})\n"
            . "Customer: {$report['customer_name']}\n"
            . "Judul Laporan: {$report['subject']}\n"
            . "Deskripsi Singkat: {$this->shorten($report['description'], 160)}\n"
            . "Tanggal Laporan: {$this->formatTimestamp($report['created_at'])}\n"
            . "Status Laporan: {$report['status']}\n\n"
            . 'Silakan periksa dashboard Admin.';

        return $this->dispatch(
            self::EVENT_REPORT_CREATED,
            self::ACTIVITY_REPORT,
            'report-' . (int) $report['report_id'],
            $this->adminRecipient(),
            $message,
            'success'
        );
    }

    // ==============================================================
    // Notifikasi ke CUSTOMER (pemilik data)
    // ==============================================================

    /**
     * Konfirmasi pesanan berhasil dibuat.
     */
    public function notifyCustomerOrderCreated($orderId)
    {
        $order = $this->loadOrder($orderId);
        if ($order === null) {
            return $this->result(false, 'order_not_found');
        }

        $recipient = $this->customerRecipient((int) $order['user_id']);
        if ($recipient === null) {
            return $this->result(false, 'invalid_recipient');
        }

        $message = "✅ PESANAN ANDA BERHASIL DIBUAT\n\n"
            . "Halo {$order['customer_name']}!\n\n"
            . "Aktivitas: Pembuatan Pesanan\n"
            . "Kode Pesanan: {$order['order_code']}\n"
            . "Layanan: {$order['service_name']}\n"
            . "Harga: {$this->priceOf($order)}\n"
            . "Status: Berhasil\n\n"
            . "Tindak lanjut: Pesanan Anda menunggu verifikasi operator. Pantau statusnya di halaman Riwayat Pesanan.";

        return $this->dispatch(
            self::EVENT_ORDER_CREATED,
            self::ACTIVITY_ORDER,
            'order-' . (int) $order['order_id'],
            $recipient,
            $message,
            'success'
        );
    }

    /**
     * Pesanan gagal dibuat -> customer diberi tahu (tidak ada konfirmasi sukses).
     *
     * @param int   $userId  Pemilik akun yang mencoba memesan.
     * @param string $reason  Alasan singkat yang aman untuk ditampilkan.
     */
    public function notifyCustomerOrderFailed($userId, $reason = '')
    {
        $recipient = $this->customerRecipient((int) $userId);
        if ($recipient === null) {
            return $this->result(false, 'invalid_recipient');
        }

        $reasonText = $this->shorten($reason !== '' ? $reason : 'Kesalahan internal server.', 140);

        $message = "❌ PESANAN ANDA GAGAL DIBUAT\n\n"
            . "Halo {$recipient['name']}!\n\n"
            . "Aktivitas: Pembuatan Pesanan\n"
            . "Status: Gagal\n"
            . "Keterangan: {$reasonText}\n\n"
            . "Tindak lanjut: Tidak ada pesanan yang tersimpan. Silakan coba lagi, atau hubungi Admin TKJ Service Hub bila kendala berlanjut.";

        return $this->dispatch(
            self::EVENT_ORDER_FAILED,
            self::ACTIVITY_ORDER,
            'user-' . (int) $userId . '-' . date('YmdHi'),
            $recipient,
            $message,
            'failed'
        );
    }

    /**
     * Konfirmasi laporan berhasil diterima.
     */
    public function notifyCustomerReportCreated($reportId)
    {
        $report = $this->loadReport($reportId);
        if ($report === null) {
            return $this->result(false, 'report_not_found');
        }

        $recipient = $this->customerRecipient((int) $report['user_id']);
        if ($recipient === null) {
            return $this->result(false, 'invalid_recipient');
        }

        $message = "✅ LAPORAN ANDA BERHASIL DITERIMA\n\n"
            . "Halo {$report['customer_name']}!\n\n"
            . "Aktivitas: Pengiriman Laporan\n"
            . "Kode Laporan: {$report['report_code']}\n"
            . "Judul Laporan: {$report['subject']}\n"
            . "Status: Berhasil\n\n"
            . "Tindak lanjut: Laporan Anda sedang ditinjau Admin. Pantau pembaruannya di halaman Laporan Saya.";

        return $this->dispatch(
            self::EVENT_REPORT_CREATED,
            self::ACTIVITY_REPORT,
            'report-' . (int) $report['report_id'],
            $recipient,
            $message,
            'success'
        );
    }

    /**
     * Laporan gagal disimpan -> customer diberi tahu.
     */
    public function notifyCustomerReportFailed($userId, $reason = '')
    {
        $recipient = $this->customerRecipient((int) $userId);
        if ($recipient === null) {
            return $this->result(false, 'invalid_recipient');
        }

        $reasonText = $this->shorten($reason !== '' ? $reason : 'Kesalahan internal server.', 140);

        $message = "❌ LAPORAN ANDA GAGAL DIKIRIM\n\n"
            . "Halo {$recipient['name']}!\n\n"
            . "Aktivitas: Pengiriman Laporan\n"
            . "Status: Gagal\n"
            . "Keterangan: {$reasonText}\n\n"
            . "Tindak lanjut: Laporan Anda tidak tersimpan. Silakan kirim ulang, atau hubungi Admin TKJ Service Hub bila kendala berlanjut.";

        return $this->dispatch(
            self::EVENT_REPORT_FAILED,
            self::ACTIVITY_REPORT,
            'user-' . (int) $userId . '-' . date('YmdHi'),
            $recipient,
            $message,
            'failed'
        );
    }

    /**
     * Status pesanan berubah -> pemilik pesanan diberi tahu.
     */
    public function notifyOrderStatusChanged($orderId, $oldStatus = null, $newStatus = null)
    {
        $order = $this->loadOrder($orderId);
        if ($order === null) {
            return $this->result(false, 'order_not_found');
        }

        $recipient = $this->customerRecipient((int) $order['user_id']);
        if ($recipient === null) {
            return $this->result(false, 'invalid_recipient');
        }

        $oldStatus = $oldStatus !== null && $oldStatus !== '' ? $oldStatus : 'sebelumnya';
        $newStatus = $newStatus !== null && $newStatus !== ''
            ? $newStatus
            : (string) $order['status'];

        $message = "🔄 PERUBAHAN STATUS PESANAN\n\n"
            . "Halo {$order['customer_name']}!\n\n"
            . "Aktivitas: Perubahan Status Pesanan\n"
            . "Kode Pesanan: {$order['order_code']}\n"
            . "Status sebelumnya: {$oldStatus}\n"
            . "Status sekarang: {$newStatus}\n"
            . "Status: Berhasil\n\n"
            . 'Tindak lanjut: Silakan cek detail pesanan Anda untuk informasi terbaru.';

        // updated_at membuat kunci unik per perubahan status nyata.
        $reference = 'order-' . (int) $order['order_id'] . '-' . $this->formatTimestamp($order['updated_at']);

        return $this->dispatch(
            self::EVENT_ORDER_STATUS_UPDATED,
            self::ACTIVITY_STATUS,
            $reference,
            $recipient,
            $message,
            'success'
        );
    }

    /**
     * Status laporan berubah -> pemilik laporan diberi tahu.
     */
    public function notifyReportStatusChanged($reportId, $oldStatus = null, $newStatus = null)
    {
        $report = $this->loadReport($reportId);
        if ($report === null) {
            return $this->result(false, 'report_not_found');
        }

        $recipient = $this->customerRecipient((int) $report['user_id']);
        if ($recipient === null) {
            return $this->result(false, 'invalid_recipient');
        }

        $oldStatus = $oldStatus !== null && $oldStatus !== '' ? $oldStatus : 'sebelumnya';
        $newStatus = $newStatus !== null && $newStatus !== ''
            ? $newStatus
            : (string) $report['status'];

        $notes = trim((string) ($report['admin_notes'] ?? ''));

        $message = "🔄 PERUBAHAN STATUS LAPORAN\n\n"
            . "Halo {$report['customer_name']}!\n\n"
            . "Aktivitas: Perubahan Status Laporan\n"
            . "Kode Laporan: {$report['report_code']}\n"
            . "Judul Laporan: {$report['subject']}\n"
            . "Status sebelumnya: {$oldStatus}\n"
            . "Status sekarang: {$newStatus}\n"
            . "Status: Berhasil\n";

        if ($notes !== '') {
            $message .= "Catatan Admin: {$this->shorten($notes, 160)}\n";
        }

        $message .= "\nTindak lanjut: Silakan cek halaman Laporan Saya untuk informasi terbaru.";

        $reference = 'report-' . (int) $report['report_id'] . '-' . $this->formatTimestamp($report['updated_at']);

        return $this->dispatch(
            self::EVENT_REPORT_STATUS_UPDATED,
            self::ACTIVITY_STATUS,
            $reference,
            $recipient,
            $message,
            'success'
        );
    }

    // ==============================================================
    // Kirim ulang notifikasi gagal (Admin)
    // ==============================================================

    /**
     * Kirim ulang satu notifikasi yang gagal.
     *
     * Perlindungan duplikasi:
     *  - Hanya notifikasi berstatus 'failed' yang boleh dikirim ulang.
     *  - Nomor penerima di-resolve ulang dari sumber resmi (.env / users.phone).
     *  - Pesan yang dikirim ulang adalah pesan yang tersimpan (message_body).
     */
    public function resend($logId)
    {
        $logId = (int) $logId;
        if ($logId < 1) {
            return $this->result(false, 'invalid_reference');
        }

        $log = $this->logModel->find($logId);
        if (!$log) {
            return $this->result(false, 'notification_not_found');
        }

        if (($log['status'] ?? '') === 'sent') {
            return $this->result(false, 'already_sent');
        }

        $message = trim((string) ($log['message_body'] ?? ''));
        if ($message === '') {
            return $this->result(false, 'no_message_stored');
        }

        if (!$this->fonnte->isConfigured()) {
            return $this->result(false, 'not_configured');
        }

        // Resolve ulang nomor penerima dari sumber resmi.
        $recipientUserId = (int) ($log['recipient_user_id'] ?? 0);
        if ($recipientUserId > 0) {
            $recipient = $this->customerRecipient($recipientUserId);
            if ($recipient === null) {
                return $this->result(false, 'invalid_recipient');
            }
        } else {
            $recipient = $this->adminRecipient();
            if ($recipient === null || $recipient['number'] === '') {
                return $this->result(false, 'invalid_recipient');
            }
        }

        try {
            $response = $this->fonnte->sendText($recipient['number'], $message);
        } catch (\Throwable $e) {
            $response = ['success' => false, 'error_code' => 'unexpected_error', 'http_code' => 0];
        }

        if (!empty($response['success'])) {
            $this->logModel->markSent(
                $logId,
                $response['http_code'] ?? null,
                $response['message_id'] ?? null,
                (int) ($log['attempts'] ?? 1) + 1
            );
            return $this->result(true, 'sent');
        }

        $errorCode = (string) ($response['error_code'] ?? 'provider_error');
        $reason = (string) ($response['reason'] ?? $errorCode);
        $this->logModel->markFailed($logId, $reason, $response['http_code'] ?? null, (int) ($log['attempts'] ?? 1) + 1);

        return $this->result(false, $errorCode);
    }

    // ==============================================================
    // Helper internal
    // ==============================================================

    /**
     * Kirim satu notifikasi dengan idempotency + logging.
     *
     * @param array $recipient ['number' => '62...', 'name' => '...', 'role' => 'admin|customer', 'user_id' => int]
     */
    private function dispatch($event, $activityType, $referenceId, $recipient, $message, $businessStatus = 'success')
    {
        if ($recipient === null || $recipient['number'] === '') {
            return $this->result(false, 'invalid_recipient');
        }

        if (!$this->fonnte->isConfigured()) {
            // Tidak ada percobaan kirim, jadi tidak ada baris log.
            return $this->result(false, 'not_configured');
        }

        $idempotencyKey = hash('sha1', $event . '|' . $referenceId . '|' . $recipient['role'] . '|' . $recipient['number']);

        try {
            // Idempotency: baris log dibuat lebih dulu. Bila kunci sudah ada,
            // insert diabaikan dan pesan TIDAK dikirim ulang.
            $logId = $this->logModel->createIfAbsent([
                'event'            => $event,
                'activity_type'    => $activityType,
                'reference_id'     => $referenceId,
                'idempotency_key'  => $idempotencyKey,
                'recipient'        => $this->maskNumber($recipient['number']),
                'recipient_name'   => mb_substr((string) $recipient['name'], 0, 120),
                'recipient_user_id' => (int) ($recipient['user_id'] ?? 0) ?: null,
                'provider'         => 'fonnte',
                'status'           => 'pending',
                'business_status'  => $businessStatus,
                'message_preview'  => mb_substr($this->firstLine($message), 0, 255),
                'message_body'     => $message,
                'attempts'         => 1,
            ]);

            if ($logId === null) {
                return $this->result(true, 'duplicate_skipped');
            }
        } catch (\Throwable $e) {
            // Gagal menulis log tidak boleh membatalkan alur bisnis.
            error_log('[WhatsApp Notify] Gagal menulis log notifikasi.');
            return $this->result(false, 'log_write_failed');
        }

        try {
            $response = $this->fonnte->sendText($recipient['number'], $message);
        } catch (\Throwable $e) {
            $response = ['success' => false, 'error_code' => 'unexpected_error', 'http_code' => 0];
        }

        if (!empty($response['success'])) {
            $this->logModel->markSent(
                $logId,
                $response['http_code'] ?? null,
                $response['message_id'] ?? null
            );
            return $this->result(true, 'sent');
        }

        $errorCode = (string) ($response['error_code'] ?? 'provider_error');
        $reason = (string) ($response['reason'] ?? $errorCode);

        $this->logModel->markFailed($logId, $reason, $response['http_code'] ?? null);

        return $this->result(false, $errorCode);
    }

    /**
     * Responder Admin: nomor dari .env.
     */
    private function adminRecipient()
    {
        return [
            'number'  => $this->getRecipient(),
            'name'    => 'Admin TKJ Service Hub',
            'role'    => self::RECIPIENT_ADMIN,
            'user_id' => 0,
        ];
    }

    /**
     * Responder Customer: nomor WA dari akun miliknya sendiri (users.phone).
     */
    private function customerRecipient($userId)
    {
        $userId = (int) $userId;
        if ($userId < 1) {
            return null;
        }

        $db = new Database();
        if (!$db->isConnected()) {
            return null;
        }

        $db->query("SELECT id, name, phone FROM users WHERE id = :id LIMIT 1");
        $db->bind(':id', $userId);
        $user = $db->single();

        if (!$user) {
            return null;
        }

        $phone = OtpService::normalizePhone($user['phone'] ?? '');
        if ($phone === null) {
            return null;
        }

        return [
            'number'  => $phone,
            'name'    => (string) $user['name'],
            'role'    => self::RECIPIENT_CUSTOMER,
            'user_id' => $userId,
        ];
    }

    /**
     * Ambil data pesanan lengkap (beserta pemilik & layanan) dari database.
     */
    private function loadOrder($orderId)
    {
        $orderId = (int) $orderId;
        if ($orderId < 1) {
            return null;
        }

        $db = new Database();
        if (!$db->isConnected()) {
            return null;
        }

        $db->query(
            "SELECT o.id AS order_id, o.order_code, o.status, o.payment_status,
                    o.payment_amount, o.created_at AS order_date, o.updated_at,
                    c.user_id, u.name AS customer_name, u.phone AS customer_phone,
                    s.title AS service_name, s.price_range AS service_price
               FROM orders o
               JOIN customers c ON c.id = o.customer_id
               JOIN users u ON u.id = c.user_id
               JOIN services s ON s.id = o.service_id
              WHERE o.id = :order_id
              LIMIT 1"
        );
        $db->bind(':order_id', $orderId);
        $order = $db->single();

        return $order ?: null;
    }

    /**
     * Ambil data laporan lengkap (beserta pemilik) dari database.
     */
    private function loadReport($reportId)
    {
        $reportId = (int) $reportId;
        if ($reportId < 1) {
            return null;
        }

        $db = new Database();
        if (!$db->isConnected()) {
            return null;
        }

        $db->query(
            "SELECT r.id AS report_id, r.report_code, r.subject, r.description,
                    r.category, r.status, r.admin_notes, r.created_at, r.updated_at,
                    r.user_id, u.name AS customer_name, u.phone AS customer_phone
               FROM customer_reports r
               JOIN users u ON u.id = r.user_id
              WHERE r.id = :report_id
              LIMIT 1"
        );
        $db->bind(':report_id', $reportId);
        $report = $db->single();

        return $report ?: null;
    }

    /**
     * Normalize an Indonesian phone number to the 62xxx format used by Fonnte.
     */
    private function normalizeNumber($number)
    {
        $number = trim((string) $number);
        if ($number === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $number);
        if (!is_string($digits) || $digits === '') {
            return '';
        }

        if (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }

        if (strpos($digits, '0') === 0) {
            $digits = '62' . substr($digits, 1);
        }

        if (!preg_match('/^62[0-9]{8,15}$/', $digits)) {
            return '';
        }

        return $digits;
    }

    private function maskNumber($number)
    {
        $number = (string) $number;
        if ($number === '') {
            return '-';
        }

        $length = strlen($number);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($number, 0, 4) . str_repeat('*', max(2, $length - 8)) . substr($number, -4);
    }

    private function priceOf(array $order)
    {
        $price = trim((string) ($order['service_price'] ?? ''));

        return $price !== '' ? $price : '-';
    }

    private function firstLine($message)
    {
        $lines = preg_split('/\R/', (string) $message);
        $first = is_array($lines) && isset($lines[0]) ? trim($lines[0]) : '';

        return $first !== '' ? $first : 'notifikasi';
    }

    private function shorten($text, $maxLength)
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));

        return mb_substr($text, 0, $maxLength);
    }

    private function formatTimestamp($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function result($success, $reason)
    {
        return [
            'success' => (bool) $success,
            'reason'  => (string) $reason,
        ];
    }
}
