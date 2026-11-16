<?php

/**
 * Uji wajib alur notifikasi WhatsApp TKJ Service Hub.
 *  - Registrasi / laporan / pesanan (berhasil & gagal)
 *  - Perubahan status laporan & pesanan
 *  - Notifikasi admin & customer yang benar
 *  - Isolasi data antar customer
 *  - Nomor WhatsApp kosong / tidak valid
 *  - Fonnte tidak tersedia
 *  - Notifikasi ganda
 *  - Token tidak bocor
 *  - Kegagalan WhatsApp tidak membatalkan transaksi
 *
 * Run: php tests/whatsapp_notification_flows_test.php
 *
 * CATATAN: memakai transport simulasi (tanpa jaringan, tanpa token palsu di .env).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

// Nomor uji dibuat unik per eksekusi agar idempotency key tidak bentrok
// dengan baris log eksekusi sebelumnya (MySQL memakai ulang id AUTO_INCREMENT
// setelah data dihapus).
$runSuffix = (string) mt_rand(100000000, 999999999);

$adminNumber = '6289' . $runSuffix;   // nomor admin uji (dari .env saat test)
$numberA     = '6281' . $runSuffix;   // customer A: phone 081<suffix>
$numberB     = '6282' . $runSuffix;   // customer B: phone 082<suffix>

putenv('ADMIN_WHATSAPP_NUMBER=' . $adminNumber);
$_ENV['ADMIN_WHATSAPP_NUMBER'] = $adminNumber;
$_SERVER['ADMIN_WHATSAPP_NUMBER'] = $adminNumber;
putenv('FONNTE_TOKEN=test-token-offline-verification');
$_ENV['FONNTE_TOKEN'] = 'test-token-offline-verification';
$_SERVER['FONNTE_TOKEN'] = 'test-token-offline-verification';

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Services/AuthService.php';
require_once __DIR__ . '/../app/Services/OrderService.php';
require_once __DIR__ . '/../app/Services/CustomerReportService.php';
require_once __DIR__ . '/../app/Services/FonnteService.php';
require_once __DIR__ . '/../app/Services/WhatsappNotificationService.php';
require_once __DIR__ . '/../app/Models/WhatsappNotificationLog.php';
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/Customer.php';
require_once __DIR__ . '/../app/Models/Order.php';
require_once __DIR__ . '/../app/Models/CustomerReport.php';

$passed = 0;
$failed = 0;

function check($name, $condition, $detail = '')
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
        return true;
    }
    $failed++;
    echo "  [FAIL] {$name}";
    if ($detail !== '') echo "\n         {$detail}";
    echo "\n";
    return false;
}

/**
 * Transport simulasi Fonnte.
 */
class MockTransport
{
    public $calls = [];
    public $mode = 'success';

    public function __invoke($endpoint, $headers, $payload)
    {
        $this->calls[] = ['endpoint' => $endpoint, 'headers' => $headers, 'payload' => $payload];

        if ($this->mode === 'network_error') {
            return ['http_code' => 0, 'body' => '', 'error' => 28];
        }

        if ($this->mode === 'provider_error') {
            return [
                'http_code' => 200,
                'body'      => json_encode(['status' => false, 'reason' => 'token invalid']),
                'error'     => 0,
            ];
        }

        return [
            'http_code' => 200,
            'body'      => json_encode([
                'detail'    => 'success! message in queue',
                'id'        => ['80367170'],
                'process'   => 'pending',
                'requestid' => 2937124,
                'status'    => true,
                'target'    => [$payload['target'] ?? ''],
            ]),
            'error'     => 0,
        ];
    }
}

/**
 * OrderService yang selalu gagal menyimpan (simulasi kegagalan DB).
 */
class FailingOrderService extends OrderService
{
    protected function persistOrder(array $data)
    {
        return false;
    }
}

/**
 * CustomerReportService yang selalu gagal menyimpan.
 */
class FailingReportService extends CustomerReportService
{
    protected function persistReport(array $data)
    {
        return false;
    }
}

$transport = new MockTransport();

WhatsappNotificationService::setFactory(function () use ($transport) {
    $fonnte = new FonnteService('test-token-offline-verification', 'https://api.fonnte.com/send', $transport);
    return new WhatsappNotificationService($fonnte, new WhatsappNotificationLog());
});

$logModel = new WhatsappNotificationLog();
$authService = new AuthService();
$reportService = new CustomerReportService();
$userModel = new User();
$customerModel = new Customer();
$orderModel = new Order();
$reportModel = new CustomerReport();
$db = new Database();

$createdUserIds = [];
$createdOrderCodes = [];
$createdReportCodes = [];

// Catat id log terakhir sebelum pengujian agar baris log uji dapat dibersihkan.
$db->query("SELECT COALESCE(MAX(id), 0) AS max_id FROM whatsapp_notification_logs");
$maxLogIdAtStart = (int) ($db->single()['max_id'] ?? 0);

function logRows($logModel)
{
    return $logModel->recent(200);
}

function logCount($logModel)
{
    return count(logRows($logModel));
}

function payloadsTo(array $calls, $target)
{
    $result = [];
    foreach ($calls as $call) {
        if (($call['payload']['target'] ?? '') === $target) {
            $result[] = $call['payload'];
        }
    }
    return $result;
}

function lastMessageTo(array $calls, $target)
{
    $matched = payloadsTo($calls, $target);
    if (empty($matched)) {
        return '';
    }
    return (string) ($matched[count($matched) - 1]['message'] ?? '');
}

// ------------------------------------------------------------------
// Data uji: customer A & B (nomor WA valid), customer C (tanpa nomor).
// ------------------------------------------------------------------
$stamp = time();

function createTestCustomer($db, array $profile)
{
    $db->query(
        "INSERT INTO users (role, name, email, username, password, phone, is_active)
         VALUES ('customer', :name, :email, :username, :password, :phone, 1)"
    );
    $db->bind(':name', $profile['name']);
    $db->bind(':email', $profile['email']);
    $db->bind(':username', strtolower(preg_replace('/[^a-z0-9]/i', '.', $profile['email'])));
    $db->bind(':password', password_hash('RahasiaUji#2026', PASSWORD_BCRYPT));
    $db->bind(':phone', $profile['phone']);
    $db->execute();
    $userId = (int) $db->lastInsertId();

    $db->query("INSERT INTO customers (user_id, address, institution) VALUES (:uid, 'Jl. Uji Alur Notifikasi', 'Uji Integrasi')");
    $db->bind(':uid', $userId);
    $db->execute();
    $customerId = (int) $db->lastInsertId();

    return ['user_id' => $userId, 'customer_id' => $customerId];
}

$accountA = createTestCustomer($db, [
    'name'  => 'Customer Uji A',
    'email' => 'wa.flow.a.' . $stamp . '@tkj.local',
    'phone' => '081' . $runSuffix,
]);
$accountB = createTestCustomer($db, [
    'name'  => 'Customer Uji B',
    'email' => 'wa.flow.b.' . $stamp . '@tkj.local',
    'phone' => '082' . $runSuffix,
]);
$accountC = createTestCustomer($db, [
    'name'  => 'Customer Uji Tanpa Nomor',
    'email' => 'wa.flow.c.' . $stamp . '@tkj.local',
    'phone' => '',
]);

$createdUserIds[] = $accountA['user_id'];
$createdUserIds[] = $accountB['user_id'];
$createdUserIds[] = $accountC['user_id'];

echo "\n[1] Registrasi customer BERHASIL -> notifikasi Admin\n";
$registerResult = $authService->registerCustomer([
    'name'             => 'Customer Uji Registrasi',
    'email'            => 'wa.flow.reg.' . $stamp . '@tkj.local',
    'username'         => 'wa.flow.reg.' . $stamp,
    'phone'            => '081200011122',
    'address'          => 'Jl. Uji Registrasi',
    'institution'      => 'Uji Integrasi',
    'password'         => 'RahasiaUji#2026',
    'password_confirm' => 'RahasiaUji#2026',
]);
check('Registrasi berhasil', !empty($registerResult['success']));

$adminMessages = payloadsTo($transport->calls, $adminNumber);
check('Admin menerima tepat 1 notifikasi pendaftaran', count($adminMessages) === 1,
    'pesan_admin=' . count($adminMessages));
check('Pesan ke Admin memuat nama & email customer',
    strpos((string) ($adminMessages[0]['message'] ?? ''), 'Customer Uji Registrasi') !== false
    && strpos((string) ($adminMessages[0]['message'] ?? ''), 'wa.flow.reg.') !== false);
check('Pesan ke Admin memuat ID customer',
    (bool) preg_match('/ID Customer: \d+/', (string) ($adminMessages[0]['message'] ?? '')));
check('Pesan ke Admin memuat waktu pendaftaran',
    strpos((string) ($adminMessages[0]['message'] ?? ''), 'Waktu:') !== false);

$registeredUser = $userModel->findByEmail('wa.flow.reg.' . $stamp . '@tkj.local');
if (!empty($registeredUser[0]['id'])) {
    $createdUserIds[] = (int) $registeredUser[0]['id'];
}

echo "\n[2] Registrasi GAGAL -> tidak ada notifikasi\n";
$logsBefore = logCount($logModel);
$callsBefore = count($transport->calls);
$duplicate = $authService->registerCustomer([
    'name'             => 'Customer Uji Registrasi',
    'email'            => 'wa.flow.reg.' . $stamp . '@tkj.local',
    'username'         => 'wa.flow.reg.dup.' . $stamp,
    'phone'            => '081200011122',
    'address'          => 'Jl. Uji Registrasi',
    'institution'      => 'Uji Integrasi',
    'password'         => 'RahasiaUji#2026',
    'password_confirm' => 'RahasiaUji#2026',
]);
check('Registrasi duplikat ditolak', empty($duplicate['success']));
check('Tidak ada panggilan Fonnte saat registrasi gagal', count($transport->calls) === $callsBefore);
check('Tidak ada log baru saat registrasi gagal', logCount($logModel) === $logsBefore);

echo "\n[3] Laporan BERHASIL -> notifikasi Admin + konfirmasi customer\n";
$callsBefore = count($transport->calls);
$reportResult = $reportService->createReport($accountA['user_id'], [
    'subject'     => 'Instalasi CCTV tidak berfungsi',
    'description' => 'Kamera CCTV menyala tetapi tidak merekam sama sekali sejak kemarin.',
    'category'    => 'layanan',
]);
check('Laporan berhasil dibuat', !empty($reportResult['success']),
    'pesan=' . ($reportResult['message'] ?? '-'));

$reportCode = (string) ($reportResult['data']['report_code'] ?? '');
if ($reportCode !== '') {
    $createdReportCodes[] = $reportCode;
}

$newCalls = array_slice($transport->calls, $callsBefore);
check('Ada 2 notifikasi terkirim (Admin + customer)', count($newCalls) === 2,
    'calls=' . count($newCalls));
check('Admin menerima notifikasi laporan', count(payloadsTo($newCalls, $adminNumber)) === 1);
check('Customer pemilik menerima konfirmasi laporan', count(payloadsTo($newCalls, $numberA)) === 1);
check('Customer lain TIDAK menerima notifikasi laporan ini', count(payloadsTo($newCalls, $numberB)) === 0);

$adminReportMsg = lastMessageTo($newCalls, $adminNumber);
check('Pesan laporan ke Admin memuat kode & judul laporan',
    strpos($adminReportMsg, $reportCode) !== false
    && strpos($adminReportMsg, 'Instalasi CCTV tidak berfungsi') !== false);
check('Pesan laporan ke Admin memuat deskripsi singkat',
    strpos($adminReportMsg, 'Kamera CCTV menyala') !== false);
check('Pesan laporan ke Admin memuat tanggal & status',
    strpos($adminReportMsg, 'Tanggal Laporan:') !== false
    && strpos($adminReportMsg, 'Status Laporan:') !== false);

$customerReportMsg = lastMessageTo($newCalls, $numberA);
check('Konfirmasi ke customer memuat kode laporan & status berhasil',
    strpos($customerReportMsg, $reportCode) !== false
    && strpos($customerReportMsg, 'Status: Berhasil') !== false);
check('Konfirmasi ke customer memuat tindak lanjut',
    strpos($customerReportMsg, 'Tindak lanjut:') !== false);

echo "\n[4] Laporan GAGAL disimpan -> customer diberi tahu, tanpa konfirmasi sukses\n";
$callsBefore = count($transport->calls);

CustomerReportService::setFactory(function () {
    return new FailingReportService();
});
$failingReport = CustomerReportService::create();
$failResult = $failingReport->createReport($accountA['user_id'], [
    'subject'     => 'Laporan gagal disimpan',
    'description' => 'Deskripsi laporan yang gagal disimpan karena masalah database.',
    'category'    => 'umum',
]);
CustomerReportService::setFactory(null);

check('Laporan gagal -> hasil bisnis gagal', empty($failResult['success']));
$failedReportCalls = array_slice($transport->calls, $callsBefore);
check('Laporan gagal -> customer menerima notifikasi GAGAL',
    count(payloadsTo($failedReportCalls, $numberA)) === 1);
$failureMsg = lastMessageTo($failedReportCalls, $numberA);
check('Pesan kegagalan memuat status Gagal (bukan Berhasil)',
    strpos($failureMsg, 'Status: Gagal') !== false
    && strpos($failureMsg, 'Status: Berhasil') === false);
check('Pesan kegagalan memuat keterangan & tindak lanjut',
    strpos($failureMsg, 'Keterangan:') !== false
    && strpos($failureMsg, 'Tindak lanjut:') !== false);
check('Laporan gagal -> TIDAK ada konfirmasi sukses ke customer',
    strpos($failureMsg, 'BERHASIL DITERIMA') === false);
check('Laporan gagal -> TIDAK ada notifikasi ke Admin',
    count(payloadsTo($failedReportCalls, $adminNumber)) === 0);
check('Laporan gagal -> log business_status = failed',
    count(array_filter(logRows($logModel), function ($row) {
        return $row['business_status'] === 'failed';
    })) >= 1);

echo "\n[5] Pesanan BERHASIL -> notifikasi Admin + konfirmasi customer\n";
$callsBefore = count($transport->calls);
$orderService = new OrderService();
$orderResult = $orderService->createOrder($accountA['user_id'], [
    'service_id'         => 1,
    'problem_description' => 'Uji otomatis pesanan berhasil untuk notifikasi WhatsApp.',
    'location_address'   => 'Jl. Uji Pesanan Berhasil',
    'phone'              => $customerA['phone'] ?? '081200011122',
    'scheduled_date'     => date('Y-m-d H:i:s', strtotime('+2 days')),
]);
check('Pesanan berhasil dibuat', !empty($orderResult['success']),
    'pesan=' . ($orderResult['message'] ?? '-'));

$orderCode = (string) ($orderResult['data']['order_code'] ?? '');
if ($orderCode !== '') {
    $createdOrderCodes[] = $orderCode;
}

$newCalls = array_slice($transport->calls, $callsBefore);
check('Ada 2 notifikasi terkirim (Admin + customer)', count($newCalls) === 2,
    'calls=' . count($newCalls));
check('Admin menerima notifikasi pesanan', count(payloadsTo($newCalls, $adminNumber)) === 1);
check('Customer menerima konfirmasi pesanan', count(payloadsTo($newCalls, $numberA)) === 1);
check('Customer lain TIDAK menerima notifikasi pesanan ini', count(payloadsTo($newCalls, $numberB)) === 0);

$adminOrderMsg = lastMessageTo($newCalls, $adminNumber);
check('Pesan pesanan ke Admin memuat kode, layanan, harga, tanggal, status',
    strpos($adminOrderMsg, $orderCode) !== false
    && strpos($adminOrderMsg, 'Instalasi CCTV') !== false
    && strpos($adminOrderMsg, '150.000') !== false
    && strpos($adminOrderMsg, 'Tanggal:') !== false
    && strpos($adminOrderMsg, 'Status:') !== false);

$customerOrderMsg = lastMessageTo($newCalls, $numberA);
check('Konfirmasi pesanan ke customer memuat kode & status berhasil',
    strpos($customerOrderMsg, $orderCode) !== false
    && strpos($customerOrderMsg, 'Status: Berhasil') !== false);

echo "\n[6] Pesanan GAGAL disimpan -> customer diberi tahu, tanpa konfirmasi sukses\n";
$callsBefore = count($transport->calls);
$failingOrder = new FailingOrderService();
$failOrderResult = $failingOrder->createOrder($accountA['user_id'], [
    'service_id'         => 1,
    'problem_description' => 'Uji otomatis pesanan yang gagal disimpan ke database.',
    'location_address'   => 'Jl. Uji Pesanan Gagal',
    'phone'              => '081200011122',
    'scheduled_date'     => date('Y-m-d H:i:s', strtotime('+2 days')),
]);
check('Pesanan gagal -> hasil bisnis gagal', empty($failOrderResult['success']));
$failOrderCalls = array_slice($transport->calls, $callsBefore);
check('Pesanan gagal -> customer menerima notifikasi GAGAL',
    count(payloadsTo($failOrderCalls, $numberA)) === 1);
check('Pesanan gagal -> TIDAK ada konfirmasi sukses',
    strpos(lastMessageTo($failOrderCalls, $numberA), 'BERHASIL DIBUAT') === false);
check('Pesanan gagal -> TIDAK ada notifikasi ke Admin',
    count(payloadsTo($failOrderCalls, $adminNumber)) === 0);

echo "\n[7] Perubahan status LAPORAN -> hanya pemilik laporan yang diberi tahu\n";
$reportRow = $reportModel->where('report_code', $reportCode);
$reportRow = $reportRow[0] ?? null;
$callsBefore = count($transport->calls);
$statusResult = $reportService->updateStatus((int) $reportRow['id'], 'processing', 'Sedang ditinjau tim teknis.', (int) $accountA['user_id']);
check('Status laporan berhasil diubah', !empty($statusResult['success']),
    'pesan=' . ($statusResult['message'] ?? '-'));

$statusCalls = array_slice($transport->calls, $callsBefore);
check('Pemilik laporan menerima notifikasi perubahan status',
    count(payloadsTo($statusCalls, $numberA)) === 1);
check('Customer lain TIDAK menerima notifikasi laporan ini',
    count(payloadsTo($statusCalls, $numberB)) === 0);
$statusMsg = lastMessageTo($statusCalls, $numberA);
check('Pesan perubahan status memuat kode laporan & status baru',
    strpos($statusMsg, $reportCode) !== false
    && strpos($statusMsg, 'processing') !== false);
check('Pesan perubahan status memuat catatan admin',
    strpos($statusMsg, 'Sedang ditinjau tim teknis.') !== false);

echo "\n[8] Perubahan status PESANAN -> hanya pemilik pesanan yang diberi tahu\n";
$orderRow = $orderModel->findByCode($orderCode);
$callsBefore = count($transport->calls);
$orderStatusResult = $orderService->updateOrderStatusByOperator((int) $orderRow['id'], 'verified', 'Pesanan diverifikasi.');
check('Status pesanan berhasil diubah', !empty($orderStatusResult['success']),
    'pesan=' . ($orderStatusResult['message'] ?? '-'));

$orderStatusCalls = array_slice($transport->calls, $callsBefore);
check('Pemilik pesanan menerima notifikasi perubahan status',
    count(payloadsTo($orderStatusCalls, $numberA)) === 1);
check('Customer lain TIDAK menerima notifikasi pesanan ini',
    count(payloadsTo($orderStatusCalls, $numberB)) === 0);
check('Pesan perubahan status pesanan memuat kode pesanan',
    strpos(lastMessageTo($orderStatusCalls, $numberA), $orderCode) !== false);

echo "\n[9] Isolasi: customer tidak dapat mengakses data customer lain\n";
$otherReport = $reportModel->findForUser((int) $reportRow['id'], (int) $accountB['user_id']);
check('Laporan customer lain tidak bisa diambil oleh customer B', $otherReport === null);

echo "\n[10] Nomor WhatsApp kosong / tidak valid\n";
$callsBefore = count($transport->calls);
$invalidResult = $reportService->createReport($accountC['user_id'], [
    'subject'     => 'Laporan dari customer tanpa nomor WhatsApp',
    'description' => 'Customer ini tidak memiliki nomor WhatsApp yang valid pada akunnya.',
    'category'    => 'umum',
]);
check('Laporan tetap berhasil dibuat tanpa nomor WA customer',
    !empty($invalidResult['success']), 'pesan=' . ($invalidResult['message'] ?? '-'));
$invalidCalls = array_slice($transport->calls, $callsBefore);
check('Tidak ada pesan ke customer tanpa nomor valid',
    count($invalidCalls) === 1, 'total_panggilan=' . count($invalidCalls));
check('Admin tetap menerima notifikasi laporan',
    count(payloadsTo($invalidCalls, $adminNumber)) === 1);

echo "\n[11] Fonnte tidak tersedia -> transaksi bisnis tetap berhasil\n";
$transport->mode = 'network_error';
$callsBefore = count($transport->calls);

$downReport = $reportService->createReport($accountA['user_id'], [
    'subject'     => 'Laporan saat Fonnte bermasalah',
    'description' => 'Pengujian bahwa kegagalan Fonnte tidak membatalkan laporan.',
    'category'    => 'umum',
]);
check('Laporan tetap BERHASIL saat Fonnte down', !empty($downReport['success']),
    'pesan=' . ($downReport['message'] ?? '-'));
check('Percobaan kirim tetap dicatat', count(array_slice($transport->calls, $callsBefore)) >= 1);

$downOrder = $orderService->createOrder($accountA['user_id'], [
    'service_id'         => 1,
    'problem_description' => 'Pengujian bahwa kegagalan Fonnte tidak membatalkan pesanan.',
    'location_address'   => 'Jl. Uji Fonnte Down',
    'phone'              => '081200011122',
    'scheduled_date'     => date('Y-m-d H:i:s', strtotime('+3 days')),
]);
check('Pesanan tetap BERHASIL saat Fonnte down', !empty($downOrder['success']),
    'pesan='. ($downOrder['message'] ?? '-'));
if (!empty($downOrder['data']['order_code'])) {
    $createdOrderCodes[] = $downOrder['data']['order_code'];
}

$failedRows = array_filter(logRows($logModel), function ($row) {
    return $row['status'] === 'failed';
});
check('Ada log berstatus failed (bisa dilacak & dikirim ulang)', count($failedRows) >= 1,
    'jumlah=' . count($failedRows));

$transport->mode = 'success';

echo "\n[12] Notifikasi ganda -> dicegah\n";
// Pesanan baru yang belum pernah dinotifikasi (dibuat langsung di database).
$db->query(
    "INSERT INTO orders (order_code, customer_id, service_id, status, problem_description, location_address, scheduled_date)
     VALUES (:code, :cid, 1, 'submitted', 'Uji idempotensi notifikasi ganda.', 'Jl. Uji Idempotensi', NOW())"
);
$db->bind(':code', 'ORD-IDEM-' . $stamp);
$db->bind(':cid', $accountA['customer_id']);
$db->execute();
$idemOrderId = (int) $db->lastInsertId();
$createdOrderCodes[] = 'ORD-IDEM-' . $stamp;

$service = WhatsappNotificationService::create();
$first = $service->notifyCustomerOrderCreated($idemOrderId);
$second = $service->notifyCustomerOrderCreated($idemOrderId);
check('Pemanggilan pertama mengirim', ($first['reason'] ?? '') === 'sent', 'reason=' . ($first['reason'] ?? '-'));
check('Pemanggilan kedua dilewati (duplicate_skipped)', ($second['reason'] ?? '') === 'duplicate_skipped',
    'reason=' . ($second['reason'] ?? '-'));

$keys = [];
$duplicates = 0;
foreach (logRows($logModel) as $row) {
    $key = (string) ($row['idempotency_key'] ?? '');
    if ($key === '') {
        continue;
    }
    if (isset($keys[$key])) {
        $duplicates++;
    }
    $keys[$key] = true;
}
check('Tidak ada idempotency_key duplikat di log', $duplicates === 0, 'duplikat=' . $duplicates);

echo "\n[13] Kirim ulang notifikasi gagal (dengan proteksi duplikasi)\n";
$failedRow = null;
foreach (logRows($logModel) as $row) {
    if (($row['status'] ?? '') === 'failed' && !empty($row['message_body'])) {
        $failedRow = $row;
        break;
    }
}
if ($failedRow) {
    $resend = $service->resend((int) $failedRow['id']);
    check('Kirim ulang notifikasi gagal berhasil', !empty($resend['success']),
        'reason=' . ($resend['reason'] ?? '-'));

    $resentRow = $logModel->find((int) $failedRow['id']);
    check('Status log berubah menjadi sent', ($resentRow['status'] ?? '') === 'sent',
        'status=' . ($resentRow['status'] ?? '-'));
    check('Percobaan kirim bertambah', (int) ($resentRow['attempts'] ?? 0) >= 2,
        'attempts=' . ($resentRow['attempts'] ?? '-'));

    $resendAgain = $service->resend((int) $failedRow['id']);
    check('Kirim ulang kedua DITOLAK (tidak ada duplikasi)',
        empty($resendAgain['success']) && ($resendAgain['reason'] ?? '') === 'already_sent',
        'reason=' . ($resendAgain['reason'] ?? '-'));
} else {
    check('Ada log failed untuk diuji kirim ulang', false, 'tidak ditemukan baris failed dengan message_body');
}

echo "\n[14] Token Fonnte tidak bocor\n";
$allMessages = '';
foreach ($transport->calls as $call) {
    $allMessages .= ($call['payload']['message'] ?? '') . "\n";
}
check('Token tidak ada di pesan notifikasi', stripos($allMessages, 'test-token') === false);
check('Token tidak ada di baris log',
    stripos(json_encode(logRows($logModel)), 'test-token') === false);
check('Password tidak ada di pesan notifikasi', stripos($allMessages, 'RahasiaUji') === false);
check('Nomor customer tidak bocor ke pesan Admin',
    strpos(lastMessageTo($transport->calls, $adminNumber), $numberA) === false);

echo "\n[15] Ringkasan pengiriman\n";
$summary = $logModel->summary();
check('Ringkasan log tersedia', isset($summary['total'], $summary['sent'], $summary['failed']),
    'total=' . ($summary['total'] ?? '-') . ' sent=' . ($summary['sent'] ?? '-') . ' failed=' . ($summary['failed'] ?? '-'));

echo "\n[16] Pembersihan data uji\n";
foreach ($createdOrderCodes as $code) {
    $row = $orderModel->findByCode($code);
    if (!empty($row['id'])) {
        $db->query("DELETE FROM order_files WHERE order_id = :oid");
        $db->bind(':oid', (int) $row['id']);
        $db->execute();
        $db->query("DELETE FROM orders WHERE id = :oid");
        $db->bind(':oid', (int) $row['id']);
        $db->execute();
    }
}
foreach ($createdReportCodes as $code) {
    $row = $reportModel->where('report_code', $code);
    if (!empty($row[0]['id'])) {
        $db->query("DELETE FROM customer_reports WHERE id = :rid");
        $db->bind(':rid', (int) $row[0]['id']);
        $db->execute();
    }
}
foreach ($createdUserIds as $uid) {
    $db->query("DELETE FROM orders WHERE customer_id IN (SELECT id FROM customers WHERE user_id = :uid)");
    $db->bind(':uid', (int) $uid);
    $db->execute();
    $db->query("DELETE FROM customer_reports WHERE user_id = :uid");
    $db->bind(':uid', (int) $uid);
    $db->execute();
    $db->query("DELETE FROM customers WHERE user_id = :uid");
    $db->bind(':uid', (int) $uid);
    $db->execute();
    $db->query("DELETE FROM users WHERE id = :uid");
    $db->bind(':uid', (int) $uid);
    $db->execute();
}
echo "  Data uji dibersihkan: " . count($createdUserIds) . " user, "
    . count($createdOrderCodes) . " order, " . count($createdReportCodes) . " report\n";

$db->query("DELETE FROM whatsapp_notification_logs WHERE id > :max_id");
$db->bind(':max_id', $maxLogIdAtStart);
$db->execute();
echo "  Baris log uji dibersihkan (id > {$maxLogIdAtStart})\n";

echo "\n========================================\n";
echo "Hasil: {$passed} passed, {$failed} failed\n";
echo "========================================\n";
exit($failed > 0 ? 1 : 0);
