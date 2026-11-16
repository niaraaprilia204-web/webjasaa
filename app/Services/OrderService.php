<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/../Models/Order.php';
require_once __DIR__ . '/../Models/Customer.php';
require_once __DIR__ . '/../Models/Service.php';
require_once __DIR__ . '/../Models/Review.php';

/**
 * Order & Service Request Management Service
 */
class OrderService extends BaseService
{
    private $orderModel;
    private $customerModel;
    private $serviceModel;
    private $reviewModel;
    private $notificationService;

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
        $this->orderModel = new Order();
        $this->customerModel = new Customer();
        $this->serviceModel = new Service();
        $this->reviewModel = new Review();
        $this->notificationService = new NotificationService();
    }

    /**
     * Simpan pesanan ke database.
     * Dipisah agar jalur kegagalan penyimpanan dapat diuji tanpa merusak data.
     */
    protected function persistOrder(array $data)
    {
        return $this->orderModel->create($data);
    }

    /**
     * Generate unique Order Code: ORD-YYYYMMDD-XXXX
     */
    public function generateOrderCode()
    {
        $prefix = 'ORD-' . date('Ymd') . '-';
        $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . $random;
    }

    /**
     * Get Customer Record ID by User ID
     */
    public function getCustomerRecordId($userId)
    {
        $customer = $this->customerModel->where('user_id', $userId);
        if (empty($customer)) {
            // Auto create customer profile if missing
            $this->customerModel->create([
                'user_id' => $userId,
                'address' => '',
                'institution' => 'Masyarakat Umum'
            ]);
            $customer = $this->customerModel->where('user_id', $userId);
        }
        return $customer[0]['id'] ?? null;
    }

    /**
     * Create New Service Request Order by Customer
     */
    public function createOrder($userId, $data, $file = null)
    {
        $customerId = $this->getCustomerRecordId($userId);
        if (!$customerId) {
            return $this->response(false, 'Profil pelanggan tidak ditemukan.');
        }

        $serviceId = (int)($data['service_id'] ?? 0);
        $problemDescription = trim(htmlspecialchars($data['problem_description'] ?? ''));
        $deviceInfo = trim(htmlspecialchars($data['device_info'] ?? ''));
        $locationAddress = trim(htmlspecialchars($data['location_address'] ?? ''));
        $phone = trim(htmlspecialchars($data['phone'] ?? ''));
        $scheduledDate = trim(htmlspecialchars($data['scheduled_date'] ?? ''));

        // Input Validations
        if (empty($serviceId)) {
            return $this->response(false, 'Pilih jenis layanan yang diinginkan.');
        }

        $service = $this->serviceModel->find($serviceId);
        if (!$service || empty($service['is_active'])) {
            return $this->response(false, 'Layanan yang dipilih tidak tersedia.');
        }

        if (empty($problemDescription) || strlen($problemDescription) < 10) {
            return $this->response(false, 'Deskripsi masalah/kebutuhan minimal 10 karakter.');
        }

        if (empty($locationAddress) || strlen($locationAddress) < 5) {
            return $this->response(false, 'Alamat lokasi pekerjaan wajib diisi.');
        }

        if (empty($phone) || strlen($phone) < 8) {
            return $this->response(false, 'Nomor telepon/WhatsApp wajib diisi.');
        }

        if (empty($scheduledDate) || strtotime($scheduledDate) === false) {
            return $this->response(false, 'Pilih jadwal/tanggal pengerjaan yang diinginkan.');
        }

        $fileValidation = $this->validateImageUpload($file);
        if (!$fileValidation['ok']) {
            return $this->response(false, $fileValidation['message']);
        }

        $formattedScheduledDate = date('Y-m-d H:i:s', strtotime($scheduledDate));

        $orderCode = $this->generateOrderCode();

        $orderData = [
            'order_code'          => $orderCode,
            'customer_id'         => $customerId,
            'service_id'          => $serviceId,
            'status'              => 'submitted',
            'problem_description' => $problemDescription,
            'device_info'         => $deviceInfo,
            'location_address'    => $locationAddress,
            'scheduled_date'      => $formattedScheduledDate
        ];

        $created = $this->persistOrder($orderData);
        if (!$created) {
            // Penyimpanan gagal: customer diberi tahu, TIDAK ada konfirmasi sukses.
            $this->notifyAdminOrderFailed((int) $userId, 'Pesanan gagal disimpan di database.');
            return $this->response(false, 'Gagal membuat pesanan layanan. Silakan coba lagi.');
        }

        $order = $this->orderModel->findByCode($orderCode);

        // Notifikasi WhatsApp ke Admin HANYA setelah pesanan benar-benar
        // tersimpan di database. Kegagalan notifikasi tidak pernah
        // membatalkan pesanan yang sudah berhasil dibuat.
        if (!empty($order['id'])) {
            $this->notifyAdminOrderCreated((int) $order['id']);
            $this->notifyCustomerOrderCreated((int) $order['id']);
        }

        if ($order && $fileValidation['has_file']) {
            $upload = $this->storeOrderFile((int) $order['id'], $file, 'damage', 'Foto kerusakan dari customer');
            if (!$upload['success']) {
                // The order already exists; keep it and surface a non-fatal warning.
                $this->notificationService->notifyRole(
                    'operator',
                    'Pesanan baru masuk',
                    'Pesanan ' . $orderCode . ' dibuat, tetapi foto kerusakan gagal diunggah.',
                    '/operator/orders/detail/' . $orderCode
                );
            }
        }

        $this->notificationService->notifyRole(
            'operator',
            'Pesanan baru masuk',
            'Pesanan ' . $orderCode . ' dari ' . ($_SESSION['user']['name'] ?? 'customer') . ' menunggu verifikasi.',
            '/operator/orders/detail/' . $orderCode
        );
        $this->notificationService->notifyUser(
            (int) $userId,
            'Pesanan berhasil dibuat',
            'Kode pesanan Anda: ' . $orderCode . '. Pantau status pekerjaan di halaman pesanan.',
            '/customer/orders/detail/' . $orderCode
        );

        return $this->response(true, 'Permintaan layanan berhasil diajukan! Kode Pesanan Anda: ' . $orderCode, [
            'order_code' => $orderCode,
            'order_id'   => $order['id'] ?? null,
        ]);
    }

    /**
     * Kirim notifikasi WhatsApp ke Admin tentang pesanan baru.
     * Semua error ditangkap: notifikasi tidak boleh menggagalkan pemesanan.
     */
    private function notifyAdminOrderCreated($orderId)
    {
        try {
            require_once __DIR__ . '/WhatsappNotificationService.php';
            $service = WhatsappNotificationService::create();
            $service->notifyOrderCreated((int) $orderId);
        } catch (\Throwable $e) {
            error_log('[WhatsApp Notify] Notifikasi pesanan baru gagal diproses.');
        }
    }

    /**
     * Kirim konfirmasi WhatsApp ke customer pemilik pesanan.
     */
    private function notifyCustomerOrderCreated($orderId)
    {
        try {
            require_once __DIR__ . '/WhatsappNotificationService.php';
            $service = WhatsappNotificationService::create();
            $service->notifyCustomerOrderCreated((int) $orderId);
        } catch (\Throwable $e) {
            error_log('[WhatsApp Notify] Konfirmasi pesanan ke customer gagal diproses.');
        }
    }

    /**
     * Beri tahu customer bahwa pesanannya GAGAL dibuat (tidak ada konfirmasi sukses).
     */
    private function notifyAdminOrderFailed($userId, $reason = '')
    {
        try {
            require_once __DIR__ . '/WhatsappNotificationService.php';
            $service = WhatsappNotificationService::create();
            $service->notifyCustomerOrderFailed((int) $userId, $reason);
        } catch (\Throwable $e) {
            error_log('[WhatsApp Notify] Notifikasi kegagalan pesanan gagal diproses.');
        }
    }

    /**
     * Validate an optional image upload. Returns ok=true when no file was sent.
     */
    public function validateImageUpload($file)
    {
        if (!is_array($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'has_file' => false, 'message' => ''];
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'has_file' => false, 'message' => 'Foto gagal diunggah. Silakan pilih ulang file.'];
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'has_file' => false, 'message' => 'File foto tidak valid.'];
        }

        if ((int) $file['size'] > 5 * 1024 * 1024) {
            return ['ok' => false, 'has_file' => true, 'message' => 'Ukuran foto maksimal 5 MB.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'has_file' => true, 'message' => 'Format foto harus JPG, PNG, atau WEBP.'];
        }

        return ['ok' => true, 'has_file' => true, 'message' => '', 'mime' => $mime, 'extension' => $allowed[$mime]];
    }

    /**
     * Move an uploaded image into public/uploads/orders and persist its row.
     */
    public function storeOrderFile($orderId, $file, $type = 'image', $caption = '')
    {
        $orderId = (int) $orderId;
        $validation = $this->validateImageUpload($file);
        if (!$validation['ok']) {
            return $this->response(false, $validation['message']);
        }
        if (!$validation['has_file']) {
            return $this->response(false, 'Tidak ada file yang diunggah.');
        }

        $relativeDir = 'uploads/orders';
        $absoluteDir = dirname(__DIR__, 2) . '/public/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            return $this->response(false, 'Folder upload tidak dapat dibuat.');
        }

        $filename = 'order-' . $orderId . '-' . bin2hex(random_bytes(12)) . '.' . $validation['extension'];
        $absolutePath = $absoluteDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            return $this->response(false, 'Foto gagal disimpan di server.');
        }

        $db = new Database();
        if (!$db->isConnected()) {
            @unlink($absolutePath);
            return $this->response(false, 'Database tidak tersedia.');
        }

        try {
            $db->query(
                "INSERT INTO order_files (order_id, file_path, file_type, caption)
                 VALUES (:order_id, :file_path, :file_type, :caption)"
            );
            $db->bind(':order_id', $orderId);
            $db->bind(':file_path', $relativeDir . '/' . $filename);
            $db->bind(':file_type', mb_substr((string) $type, 0, 50));
            $db->bind(':caption', mb_substr(trim((string) $caption), 0, 255));
            $db->execute();
        } catch (\Throwable $e) {
            @unlink($absolutePath);
            return $this->response(false, 'Foto gagal dicatat di database.');
        }

        return $this->response(true, 'Foto berhasil diunggah.', [
            'path' => $relativeDir . '/' . $filename,
        ]);
    }

    /**
     * Get Orders belonging to specific Customer (Security Isolation)
     */
    public function getCustomerOrders($userId)
    {
        $customerId = $this->getCustomerRecordId($userId);
        if (!$customerId) return [];
        return $this->customerModel->orders($customerId);
    }

    /**
     * Get Single Order Detail for Customer (Ensures Customer Ownership)
     */
    public function getCustomerOrderDetail($orderCode, $userId)
    {
        $customerId = $this->getCustomerRecordId($userId);
        $order = $this->orderModel->findByCode($orderCode);

        if (!$order) {
            return $this->response(false, 'Pesanan tidak ditemukan.');
        }

        // SECURITY CHECK: Customer can ONLY access their OWN orders!
        if ($order['customer_id'] != $customerId) {
            return $this->response(false, 'Anda tidak memiliki hak akses untuk melihat pesanan ini.');
        }

        $workOrders = $this->orderModel->workOrders($order['id']);
        $files = $this->orderModel->files($order['id']);
        $review = $this->orderModel->review($order['id']);

        return $this->response(true, 'Detail pesanan ditemukan.', [
            'order'      => $order,
            'workOrders' => $workOrders,
            'files'      => $files,
            'review'     => $review,
        ]);
    }

    /**
     * Get one order with full operator context.
     */
    public function getOrderDetailForOperator($identifier)
    {
        $db = new Database();
        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak tersedia.');
        }

        $identifier = trim((string) $identifier);
        try {
            $db->query(
                "SELECT o.*, s.title AS service_name, s.price_range, s.estimated_duration,
                        c.address AS customer_address, c.institution,
                        u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
                   FROM orders o
                   JOIN services s ON s.id = o.service_id
                   JOIN customers c ON c.id = o.customer_id
                   JOIN users u ON u.id = c.user_id
                  WHERE o.order_code = :code OR o.id = :id
                  LIMIT 1"
            );
            $db->bind(':code', $identifier);
            $db->bind(':id', (int) $identifier);
            $order = $db->single();
        } catch (\Throwable $e) {
            return $this->response(false, 'Pesanan tidak dapat dimuat.');
        }

        if (!$order) {
            return $this->response(false, 'Pesanan tidak ditemukan.');
        }

        return $this->response(true, 'Detail pesanan ditemukan.', [
            'order'      => $order,
            'workOrders' => $this->orderModel->workOrders($order['id']),
            'files'      => $this->orderModel->files($order['id']),
            'review'     => $this->orderModel->review($order['id']),
        ]);
    }

    /**
     * Update order information from the operator detail screen.
     */
    public function updateOrderDetails($orderId, $data)
    {
        $order = $this->orderModel->find((int) $orderId);
        if (!$order) {
            return $this->response(false, 'Pesanan tidak ditemukan.');
        }

        $serviceId = (int) ($data['service_id'] ?? 0);
        $problem = trim(htmlspecialchars((string) ($data['problem_description'] ?? '')));
        $deviceInfo = trim(htmlspecialchars((string) ($data['device_info'] ?? '')));
        $address = trim(htmlspecialchars((string) ($data['location_address'] ?? '')));
        $scheduledDate = trim((string) ($data['scheduled_date'] ?? ''));
        $operatorNotes = trim(htmlspecialchars((string) ($data['operator_notes'] ?? '')));

        $service = $this->serviceModel->find($serviceId);
        if (!$service) {
            return $this->response(false, 'Layanan yang dipilih tidak ditemukan.');
        }
        if (strlen($problem) < 10) {
            return $this->response(false, 'Deskripsi masalah minimal 10 karakter.');
        }
        if (strlen($address) < 5) {
            return $this->response(false, 'Alamat lokasi minimal 5 karakter.');
        }
        if ($scheduledDate === '' || strtotime($scheduledDate) === false) {
            return $this->response(false, 'Jadwal pengerjaan tidak valid.');
        }

        $updated = $this->orderModel->update((int) $orderId, [
            'service_id'          => $serviceId,
            'problem_description' => $problem,
            'device_info'         => $deviceInfo,
            'location_address'    => $address,
            'scheduled_date'      => date('Y-m-d H:i:s', strtotime($scheduledDate)),
            'operator_notes'      => $operatorNotes,
        ]);

        if (!$updated) {
            return $this->response(false, 'Perubahan pesanan gagal disimpan.');
        }

        $this->notificationService->notifyUser(
            $this->customerUserIdForOrder((int) $orderId),
            'Pesanan diperbarui operator',
            'Detail pesanan ' . $order['order_code'] . ' telah diperbarui. Silakan periksa kembali.',
            '/customer/orders/detail/' . $order['order_code']
        );

        return $this->response(true, 'Informasi pesanan berhasil diperbarui.');
    }

    /**
     * Update payment data on an order.
     */
    public function updatePayment($orderId, $data)
    {
        $order = $this->orderModel->find((int) $orderId);
        if (!$order) {
            return $this->response(false, 'Pesanan tidak ditemukan.');
        }

        $status = (string) ($data['payment_status'] ?? 'unpaid');
        $method = trim(strip_tags((string) ($data['payment_method'] ?? '')));
        $amountRaw = trim((string) ($data['payment_amount'] ?? ''));

        if (!in_array($status, ['unpaid', 'pending', 'paid', 'refunded'], true)) {
            return $this->response(false, 'Status pembayaran tidak valid.');
        }
        if ($amountRaw !== '' && (!is_numeric($amountRaw) || (float) $amountRaw < 0)) {
            return $this->response(false, 'Nominal pembayaran tidak valid.');
        }
        $amount = $amountRaw === '' ? null : round((float) $amountRaw, 2);

        $db = new Database();
        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak tersedia.');
        }

        try {
            $db->query(
                "UPDATE orders
                    SET payment_status = :status,
                        payment_method = :method,
                        payment_amount = :amount,
                        paid_at = :paid_at
                  WHERE id = :id"
            );
            $db->bind(':status', $status);
            $db->bind(':method', $method !== '' ? $method : null);
            $db->bind(':amount', $amount);
            $db->bind(':paid_at', $status === 'paid' ? date('Y-m-d H:i:s') : null);
            $db->bind(':id', (int) $orderId);
            $db->execute();
        } catch (\Throwable $e) {
            return $this->response(false, 'Data pembayaran gagal disimpan. Pastikan migrasi pembayaran sudah dijalankan.');
        }

        $this->notificationService->notifyUser(
            $this->customerUserIdForOrder((int) $orderId),
            'Status pembayaran diperbarui',
            'Pembayaran pesanan ' . $order['order_code'] . ' kini berstatus ' . strtoupper($status) . '.',
            '/customer/orders/detail/' . $order['order_code']
        );

        return $this->response(true, 'Data pembayaran berhasil diperbarui.');
    }

    /**
     * Submit a customer review once the order is completed.
     */
    public function submitReview($orderCode, $userId, $rating, $comment)
    {
        $customerId = $this->getCustomerRecordId($userId);
        $order = $this->orderModel->findByCode($orderCode);
        if (!$order || (int) $order['customer_id'] !== (int) $customerId) {
            return $this->response(false, 'Pesanan tidak ditemukan atau bukan milik Anda.');
        }
        if ($order['status'] !== 'completed') {
            return $this->response(false, 'Ulasan hanya dapat diberikan setelah pekerjaan selesai.');
        }

        $rating = (int) $rating;
        $comment = trim(strip_tags((string) $comment));
        if ($rating < 1 || $rating > 5) {
            return $this->response(false, 'Rating harus bernilai 1 sampai 5.');
        }
        if (mb_strlen($comment) > 1000) {
            return $this->response(false, 'Komentar maksimal 1000 karakter.');
        }
        if (!empty($this->orderModel->review($order['id']))) {
            return $this->response(false, 'Ulasan untuk pesanan ini sudah pernah dikirim.');
        }

        try {
            $this->reviewModel->create([
                'order_id'    => (int) $order['id'],
                'customer_id' => (int) $customerId,
                'rating'      => $rating,
                'comment'     => $comment,
            ]);
        } catch (\Throwable $e) {
            return $this->response(false, 'Ulasan gagal disimpan.');
        }

        $this->notificationService->notifyRole(
            'operator',
            'Ulasan customer baru',
            'Pesanan ' . $orderCode . ' mendapat rating ' . $rating . '/5.',
            '/operator/reviews'
        );

        return $this->response(true, 'Terima kasih! Ulasan Anda berhasil dikirim.');
    }

    private function customerUserIdForOrder($orderId)
    {
        $db = new Database();
        if (!$db->isConnected()) return 0;

        $db->query("SELECT u.id FROM users u JOIN customers c ON c.user_id = u.id JOIN orders o ON o.customer_id = c.id WHERE o.id = :id");
        $db->bind(':id', (int) $orderId);
        $row = $db->single();
        return (int) ($row['id'] ?? 0);
    }

    /**
     * Get All Orders for Operator
     */
    public function getAllOrdersForOperator($statusFilter = '')
    {
        $db = new Database();
        if (!$db->isConnected()) return [];

        $sql = "SELECT o.*, s.title as service_name, u.name as customer_name, u.phone as customer_phone FROM orders o JOIN services s ON o.service_id = s.id JOIN customers c ON o.customer_id = c.id JOIN users u ON c.user_id = u.id";
        
        if (!empty($statusFilter)) {
            $sql .= " WHERE o.status = :status";
        }
        $sql .= " ORDER BY o.created_at DESC";

        $db->query($sql);
        if (!empty($statusFilter)) {
            $db->bind(':status', $statusFilter);
        }

        return $db->resultSet();
    }

    /**
     * Update Workflow Status by Operator
     */
    public function updateOrderStatusByOperator($orderId, $status, $operatorNotes = '')
    {
        $validStatuses = [
            'submitted', 'verified', 'scheduled', 'assigned',
            'in_progress', 'waiting_confirmation', 'completed', 'cancelled'
        ];

        if (!in_array($status, $validStatuses)) {
            return $this->response(false, 'Status workflow tidak valid.');
        }

        $order = $this->orderModel->find($orderId);
        if (!$order) {
            return $this->response(false, 'Pesanan tidak ditemukan.');
        }

        $oldStatus = (string) $order['status'];

        $updated = $this->orderModel->updateStatus($orderId, $status, $operatorNotes);
        if (!$updated) {
            return $this->response(false, 'Gagal memperbarui status pesanan.');
        }

        $this->notificationService->notifyUser(
            $this->customerUserIdForOrder((int) $orderId),
            'Status pesanan diperbarui',
            'Pesanan ' . $order['order_code'] . ' kini berstatus ' . strtoupper($status) . '.',
            '/customer/orders/detail/' . $order['order_code']
        );

        // WhatsApp ke customer pemilik pesanan (dari nomor di users.phone).
        $this->notifyCustomerOrderStatusChanged((int) $orderId, $oldStatus, $status);

        return $this->response(true, "Status pesanan {$order['order_code']} berhasil diubah menjadi '" . strtoupper($status) . "'.");
    }

    /**
     * Kirim notifikasi perubahan status pesanan ke customer pemilik.
     */
    private function notifyCustomerOrderStatusChanged($orderId, $oldStatus, $newStatus)
    {
        try {
            require_once __DIR__ . '/WhatsappNotificationService.php';
            $service = WhatsappNotificationService::create();
            $service->notifyOrderStatusChanged((int) $orderId, $oldStatus, $newStatus);
        } catch (\Throwable $e) {
            error_log('[WhatsApp Notify] Notifikasi perubahan status pesanan gagal diproses.');
        }
    }
}
