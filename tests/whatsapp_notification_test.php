<?php

/**
 * Uji wajib integrasi notifikasi WhatsApp Admin (Fonnte).
 *
 * Run: php tests/whatsapp_notification_test.php
 *
 * CATATAN PENTING:
 * Pengujian ini memakai "transport" simulasi yang disuntikkan ke
 * FonnteService, sehingga TIDAK ada permintaan jaringan dan TIDAK ada
 * token Fonnte palsu di dalam .env. Yang diuji adalah seluruh logika
 * integrasi: pemicu, idempotency, logging, penanganan error, dan sumber
 * nomor tujuan. Pengiriman nyata ke WhatsApp memerlukan token Fonnte asli
 * milik Anda di .env.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

// Konfigurasi uji: nomor tujuan + token simulasi (hanya untuk proses uji).
// Token simulasi tidak pernah dikirim ke jaringan karena transport disimulasikan.
putenv('ADMIN_WHATSAPP_NUMBER=6281234567890');
$_ENV['ADMIN_WHATSAPP_NUMBER'] = '6281234567890';
$_SERVER['ADMIN_WHATSAPP_NUMBER'] = '6281234567890';
putenv('FONNTE_TOKEN=test-token-offline-verification');
$_ENV['FONNTE_TOKEN'] = 'test-token-offline-verification';
$_SERVER['FONNTE_TOKEN'] = 'test-token-offline-verification';

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Services/AuthService.php';
require_once __DIR__ . '/../app/Services/OrderService.php';
require_once __DIR__ . '/../app/Services/FonnteService.php';
require_once __DIR__ . '/../app/Services/WhatsappNotificationService.php';
require_once __DIR__ . '/../app/Models/WhatsappNotificationLog.php';
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/Customer.php';
require_once __DIR__ . '/../app/Models/Order.php';

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
 * Transport simulasi: mencatat payload dan mengembalikan respons Fonnte.
 */
class MockTransport
{
    public $calls = [];
    public $mode = 'success';

    public function __invoke($endpoint, $headers, $payload)
    {
        $this->calls[] = [
            'endpoint' => $endpoint,
            'headers'  => $headers,
            'payload'  => $payload,
        ];

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

$transport = new MockTransport();

// Inject service dengan transport simulasi ke seluruh pemicu.
WhatsappNotificationService::setFactory(function () use ($transport) {
    $fonnte = new FonnteService('test-token-offline-verification', 'https://api.fonnte.com/send', $transport);
    return new WhatsappNotificationService($fonnte, new WhatsappNotificationLog());
});

$logModel = new WhatsappNotificationLog();
$authService = new AuthService();
$orderService = new OrderService();
$userModel = new User();
$customerModel = new Customer();
$orderModel = new Order();

function logCount($logModel)
{
    $rows = $logModel->recent(200);
    return count($rows);
}

$createdUserIds = [];
$createdOrderCodes = [];

echo "\n[1] Customer baru mendaftar -> notifikasi ke Admin\n";

$email = 'wa.test.' . time() . '@tkj.local';
$username = 'wa.test.' . time();

$registerResult = $authService->registerCustomer([
    'name'            => 'Customer Uji WhatsApp',
    'email'           => $email,
    'username'        => $username,
    'phone'           => '081234567890',
    'address'         => 'Jl. Uji Notifikasi No. 1',
    'institution'     => 'Uji Integrasi',
    'password'        => 'RahasiaUji#2026',
    'password_confirm' => 'RahasiaUji#2026',
]);

check('Registrasi customer berhasil', !empty($registerResult['success']),
    'pesan=' . ($registerResult['message'] ?? 'tidak ada'));

check('Fonnte dipanggil 1x setelah registrasi (khusus Admin)', count($transport->calls) === 1,
    'calls=' . count($transport->calls));

$call = $transport->calls[0] ?? null;
check('Endpoint Fonnte resmi yang dipakai', ($call['endpoint'] ?? '') === 'https://api.fonnte.com/send',
    'endpoint=' . ($call['endpoint'] ?? '-'));
check('Nomor tujuan dari konfigurasi (.env)', ($call['payload']['target'] ?? '') === '6281234567890',
    'target=' . ($call['payload']['target'] ?? '-'));
check('Pesan memuat nama customer', strpos((string) ($call['payload']['message'] ?? ''), 'Customer Uji WhatsApp') !== false);
check('Pesan memuat email customer', strpos((string) ($call['payload']['message'] ?? ''), $email) !== false);
check('Pesan memuat ID customer', (bool) preg_match('/ID Customer: \d+/', (string) ($call['payload']['message'] ?? '')));
check('Header Authorization memakai token (tanpa Bearer)',
    in_array('Authorization: test-token-offline-verification', $call['headers'] ?? [], true),
    'headers=' . json_encode($call['headers'] ?? []));
check('Body Fonnte memakai field resmi (target, message, countryCode)',
    isset($call['payload']['target'], $call['payload']['message'], $call['payload']['countryCode']));

$newUser = $userModel->findByEmail($email);
$newUserId = (int) ($newUser[0]['id'] ?? 0);
$customerRow = $newUserId ? $customerModel->findByUserId($newUserId) : null;
$customerId = (int) ($customerRow['id'] ?? 0);
if ($newUserId) {
    $createdUserIds[] = $newUserId;
}

$logs = $logModel->recent(5);
$latest = $logs[0] ?? [];
check('Log notifikasi tersimpan dengan status sent', ($latest['status'] ?? '') === 'sent',
    'status=' . ($latest['status'] ?? '-') . ' event=' . ($latest['event'] ?? '-'));
check('Log menyimpan event customer_registered', ($latest['event'] ?? '') === 'customer_registered');
check('Log tidak menyimpan nomor lengkap (disamarkan)', ($latest['recipient'] ?? '') !== '6281234567890',
    'recipient=' . ($latest['recipient'] ?? '-'));
check('Log tidak menyimpan token Fonnte',
    strpos(json_encode($latest), 'test-token-offline-verification') === false);

echo "\n[2] Pesanan baru -> notifikasi ke Admin\n";
$callsBeforeOrder = count($transport->calls);

$orderResult = $orderService->createOrder($newUserId, [
    'service_id'         => 1, // Instalasi CCTV
    'problem_description' => 'Uji otomatis notifikasi WhatsApp untuk pesanan baru.',
    'device_info'        => 'CCTV uji',
    'location_address'   => 'Jl. Uji Notifikasi No. 2',
    'phone'              => '081234567890',
    'scheduled_date'     => date('Y-m-d H:i:s', strtotime('+2 days')),
]);

check('Pemesanan berhasil', !empty($orderResult['success']),
    'pesan=' . ($orderResult['message'] ?? 'tidak ada'));

$orderCode = (string) ($orderResult['data']['order_code'] ?? '');
if ($orderCode !== '') {
    $createdOrderCodes[] = $orderCode;
}

check('Fonnte dipanggil untuk pesanan baru (Admin + konfirmasi customer)', count($transport->calls) === $callsBeforeOrder + 2,
    'calls=' . count($transport->calls));
$orderCall = $transport->calls[$callsBeforeOrder] ?? null;
$customerOrderCall = $transport->calls[$callsBeforeOrder + 1] ?? null;
check('Pesan pesanan ke Admin memuat kode pesanan', strpos((string) ($orderCall['payload']['message'] ?? ''), $orderCode) !== false,
    'kode=' . $orderCode);
check('Pesan pesanan ke Admin memuat nama layanan', strpos((string) ($orderCall['payload']['message'] ?? ''), 'Instalasi CCTV') !== false);
check('Pesan pesanan ke Admin memuat harga dari database', strpos((string) ($orderCall['payload']['message'] ?? ''), '150.000') !== false);
check('Nomor tujuan pesanan tetap dari konfigurasi', ($orderCall['payload']['target'] ?? '') === '6281234567890');
check('Konfirmasi customer memuat kode pesanan & status berhasil',
    strpos((string) ($customerOrderCall['payload']['message'] ?? ''), $orderCode) !== false
    && strpos((string) ($customerOrderCall['payload']['message'] ?? ''), 'Status: Berhasil') !== false);

echo "\n[3] Pendaftaran gagal -> tidak ada notifikasi\n";
$logsBefore = logCount($logModel);
$callsBefore = count($transport->calls);

$duplicate = $authService->registerCustomer([
    'name'            => 'Customer Uji WhatsApp',
    'email'           => $email, // sudah terdaftar
    'username'        => $username . 'x',
    'phone'           => '081234567890',
    'address'         => 'Jl. Uji Notifikasi No. 3',
    'institution'     => 'Uji Integrasi',
    'password'        => 'RahasiaUji#2026',
    'password_confirm' => 'RahasiaUji#2026',
]);

check('Pendaftaran duplikat ditolak', empty($duplicate['success']),
    'pesan=' . ($duplicate['message'] ?? 'tidak ada'));
check('Tidak ada panggilan Fonnte saat pendaftaran gagal', count($transport->calls) === $callsBefore,
    'calls=' . count($transport->calls));
check('Tidak ada log baru saat pendaftaran gagal', logCount($logModel) === $logsBefore);

echo "\n[4] Pemesanan gagal -> tidak ada notifikasi\n";
$logsBefore = logCount($logModel);
$callsBefore = count($transport->calls);

$invalidOrder = $orderService->createOrder($newUserId, [
    'service_id'         => 0, // tidak valid
    'problem_description' => 'Uji otomatis notifikasi WhatsApp untuk pesanan gagal.',
    'location_address'   => 'Jl. Uji Notifikasi No. 4',
    'phone'              => '081234567890',
    'scheduled_date'     => date('Y-m-d H:i:s', strtotime('+2 days')),
]);

check('Pemesanan tidak valid ditolak', empty($invalidOrder['success']),
    'pesan=' . ($invalidOrder['message'] ?? 'tidak ada'));
check('Tidak ada panggilan Fonnte saat pemesanan gagal', count($transport->calls) === $callsBefore);
check('Tidak ada log baru saat pemesanan gagal', logCount($logModel) === $logsBefore);

echo "\n[5] Fonnte tidak tersedia -> pendaftaran & pemesanan tetap berhasil\n";
$transport->mode = 'network_error';
$callsBefore = count($transport->calls);

$email2 = 'wa.test.err.' . time() . '@tkj.local';
$registerError = $authService->registerCustomer([
    'name'            => 'Customer Uji Error Fonnte',
    'email'           => $email2,
    'username'        => 'wa.err.' . time(),
    'phone'           => '081234567890',
    'address'         => 'Jl. Uji Notifikasi No. 5',
    'institution'     => 'Uji Integrasi',
    'password'        => 'RahasiaUji#2026',
    'password_confirm' => 'RahasiaUji#2026',
]);

check('Pendaftaran tetap BERHASIL saat Fonnte down', !empty($registerError['success']),
    'pesan=' . ($registerError['message'] ?? 'tidak ada'));
check('Panggilan Fonnte tetap dilakukan (kegagalan tercatat)', count($transport->calls) === $callsBefore + 1);

$logs = $logModel->recent(3);
$latest = $logs[0] ?? [];
check('Status log = failed', ($latest['status'] ?? '') === 'failed',
    'status=' . ($latest['status'] ?? '-') . ' error=' . ($latest['error_message'] ?? '-'));
check('Log menyimpan alasan aman (tanpa token)',
    strpos((string) ($latest['error_message'] ?? ''), 'test-token') === false);

$user2 = $userModel->findByEmail($email2);
$user2Id = (int) ($user2[0]['id'] ?? 0);
if ($user2Id) {
    $createdUserIds[] = $user2Id;
}

$orderError = $orderService->createOrder($user2Id, [
    'service_id'         => 1,
    'problem_description' => 'Uji otomatis pemesanan tetap berhasil saat Fonnte bermasalah.',
    'location_address'   => 'Jl. Uji Notifikasi No. 6',
    'phone'              => '081234567890',
    'scheduled_date'     => date('Y-m-d H:i:s', strtotime('+3 days')),
]);

check('Pemesanan tetap BERHASIL saat Fonnte down', !empty($orderError['success']),
    'pesan=' . ($orderError['message'] ?? 'tidak ada'));
if (!empty($orderError['data']['order_code'])) {
    $createdOrderCodes[] = $orderError['data']['order_code'];
}

echo "\n[6] Satu event tidak mengirim notifikasi ganda\n";
$transport->mode = 'success';
$callsBefore = count($transport->calls);
$logsBefore = logCount($logModel);

// Customer khusus uji idempotency (dibuat langsung, tanpa memicu notifikasi).
$db = new Database();
$email3 = 'wa.test.idem.' . time() . '@tkj.local';
$username3 = 'wa.idem.' . time();
$db->query(
    "INSERT INTO users (role, name, email, username, password, phone, is_active)
     VALUES ('customer', 'Customer Uji Idempotency', :email, :username, :password, '081234567890', 1)"
);
$db->bind(':email', $email3);
$db->bind(':username', $username3);
$db->bind(':password', password_hash('RahasiaUji#2026', PASSWORD_BCRYPT));
$db->execute();
$idemUserId = (int) $db->lastInsertId();

$db->query("INSERT INTO customers (user_id, address, institution) VALUES (:uid, 'Jl. Uji Idempotency', 'Uji Integrasi')");
$db->bind(':uid', $idemUserId);
$db->execute();
$idemCustomerId = (int) $db->lastInsertId();
$createdUserIds[] = $idemUserId;

$service = WhatsappNotificationService::create();
$first = $service->notifyCustomerRegistered($idemCustomerId);
$second = $service->notifyCustomerRegistered($idemCustomerId);

check('Pemanggilan pertama mengirim notifikasi', ($first['reason'] ?? '') === 'sent',
    'reason=' . ($first['reason'] ?? '-'));
check('Pemanggilan kedua dilewati (duplicate)', ($second['reason'] ?? '') === 'duplicate_skipped',
    'reason=' . ($second['reason'] ?? '-'));
check('Fonnte hanya dipanggil 1x untuk event yang sama', count($transport->calls) === $callsBefore + 1,
    'calls=' . count($transport->calls) . ' sebelum=' . $callsBefore);
check('Hanya 1 baris log untuk event yang sama', logCount($logModel) === $logsBefore + 1,
    'logs=' . logCount($logModel) . ' sebelum=' . $logsBefore);

echo "\n[7] Nomor tujuan berasal dari konfigurasi Admin\n";
$configuredNumber = '6281234567890';
$service = WhatsappNotificationService::create();
check('Service terkonfigurasi', $service->isConfigured());
check('Nomor tujuan = konfigurasi .env', $service->getRecipient() === $configuredNumber,
    'recipient=' . $service->getRecipient());
check('Nomor disamarkan untuk UI', $service->getMaskedRecipient() !== $configuredNumber
    && strpos($service->getMaskedRecipient(), '*') !== false,
    'masked=' . $service->getMaskedRecipient());
check('Nomor tujuan tidak bisa diubah oleh input customer',
    ($transport->calls[count($transport->calls) - 1]['payload']['target'] ?? '') === $configuredNumber);

// Pesan tidak boleh memuat data sensitif.
$allMessages = '';
foreach ($transport->calls as $c) {
    $allMessages .= ($c['payload']['message'] ?? '') . "\n";
}
check('Pesan tidak memuat password', stripos($allMessages, 'RahasiaUji') === false);
check('Pesan tidak memuat token Fonnte', stripos($allMessages, 'test-token') === false);

echo "\n[8] Normalisasi nomor tujuan\n";
$serviceRef = new ReflectionClass(WhatsappNotificationService::class);
$normalize = $serviceRef->getMethod('normalizeNumber');
$normalize->setAccessible(true);
$serviceInstance = new WhatsappNotificationService(new FonnteService('test-token-offline-verification'), new WhatsappNotificationLog());
check('Nomor 08... dinormalisasi ke 62...', $normalize->invoke($serviceInstance, '081234567890') === '6281234567890');
check('Nomor 62... tetap utuh', $normalize->invoke($serviceInstance, '6281234567890') === '6281234567890');
check('Nomor +62... dinormalisasi', $normalize->invoke($serviceInstance, '+62 812-3456-7890') === '6281234567890');
check('Nomor tidak valid ditolak', $normalize->invoke($serviceInstance, 'abc') === '');

echo "\n[9] Pembersihan data uji\n";
foreach ($createdOrderCodes as $code) {
    $order = $orderModel->findByCode($code);
    if (!empty($order['id'])) {
        $db = new Database();
        $db->query("DELETE FROM order_files WHERE order_id = :oid");
        $db->bind(':oid', (int) $order['id']);
        $db->execute();
        $db->query("DELETE FROM orders WHERE id = :oid");
        $db->bind(':oid', (int) $order['id']);
        $db->execute();
    }
}
foreach ($createdUserIds as $uid) {
    $db = new Database();
    $db->query("DELETE FROM orders WHERE customer_id IN (SELECT id FROM customers WHERE user_id = :uid)");
    $db->bind(':uid', (int) $uid);
    $db->execute();
    $db->query("DELETE FROM customers WHERE user_id = :uid");
    $db->bind(':uid', (int) $uid);
    $db->execute();
    $db->query("DELETE FROM users WHERE id = :uid");
    $db->bind(':uid', (int) $uid);
    $db->execute();
}
echo "  Data uji dibersihkan: " . count($createdUserIds) . " user, " . count($createdOrderCodes) . " order\n";

echo "\n========================================\n";
echo "Hasil: {$passed} passed, {$failed} failed\n";
echo "========================================\n";
exit($failed > 0 ? 1 : 0);
