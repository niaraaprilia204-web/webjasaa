<?php

/**
 * Uji E2E integrasi notifikasi WhatsApp + fitur laporan lewat HTTP (browser nyata).
 * Run: php tests/test_whatsapp_http_e2e.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$base = 'http://localhost/webjasa';
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

function httpCall(string $url, array $options = []): array
{
    $ch = curl_init($url);
    $defaults = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    curl_setopt_array($ch, $options + $defaults);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body === false ? '' : (string) $body];
}

function login(string $base, string $login, string $password): string
{
    $jar = sys_get_temp_dir() . '/webjasa_wa_' . md5($login) . '.txt';
    @unlink($jar);
    httpCall($base . '/auth/loginProcess', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['login' => $login, 'password' => $password]),
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
    ]);
    return $jar;
}

function csrfFrom(string $url, string $jar): string
{
    $page = httpCall($url, [CURLOPT_COOKIEFILE => $jar]);

    // Token formulir (name="csrf_token") dipakai lebih dulu.
    if (preg_match("/name=['\"]csrf_token['\"] value=['\"]([A-Za-z0-9_\-]+)['\"]/", $page['body'], $m)) {
        return $m[1];
    }

    if (preg_match("/AI_CSRF_TOKEN['\" ]*[:=]['\" ]*([A-Za-z0-9_\-]+)/", $page['body'], $m)) {
        return $m[1];
    }

    return '';
}

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';
$db = new Database();

$stamp = time();

echo "\n=== Registrasi customer lewat HTTP ===\n";
$email = 'wa.http.' . $stamp . '@tkj.local';
$regJar = sys_get_temp_dir() . '/webjasa_wa_reg.txt';
@unlink($regJar);

$resp = httpCall($base . '/auth/registerProcess', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'name'             => 'Customer Uji HTTP',
        'email'            => $email,
        'username'         => 'wa.http.' . $stamp,
        'phone'            => '081234567890',
        'address'          => 'Jl. Uji HTTP Notifikasi',
        'institution'      => 'Uji Integrasi',
        'password'         => 'RahasiaUji#2026',
        'password_confirm' => 'RahasiaUji#2026',
    ]),
    CURLOPT_COOKIEJAR => $regJar,
    CURLOPT_COOKIEFILE => $regJar,
    CURLOPT_FOLLOWLOCATION => true,
]);

check('Pendaftaran HTTP tidak error (5xx)', $resp['status'] < 500, 'HTTP ' . $resp['status']);
check('Halaman konfirmasi registrasi memuat pesan sukses',
    stripos($resp['body'], 'berhasil') !== false || stripos($resp['body'], 'Registrasi') !== false);

$db->query("SELECT id, role FROM users WHERE email = :email");
$db->bind(':email', $email);
$created = $db->single();
check('Akun customer tersimpan di database dengan role customer',
    !empty($created['id']) && ($created['role'] ?? '') === 'customer',
    'role=' . ($created['role'] ?? '-'));

echo "\n=== Halaman dashboard Notifikasi WhatsApp (Admin) ===\n";
$adminJar = login($base, 'admin.tkj', 'TmpAdm!n-2026-x7K9');

$page = httpCall($base . '/admin/whatsapp', [CURLOPT_COOKIEFILE => $adminJar]);
check('Halaman /admin/whatsapp dapat diakses (HTTP 200)', $page['status'] === 200, 'HTTP ' . $page['status']);
check('Halaman menampilkan status koneksi Fonnte', stripos($page['body'], 'Status Koneksi Fonnte') !== false);
check('Halaman menampilkan nomor tujuan disamarkan', (bool) preg_match('/\d{4}\*+\d{4}/', $page['body']));
check('Halaman menampilkan riwayat notifikasi', stripos($page['body'], 'Riwayat Notifikasi') !== false);
check('Halaman menampilkan kolom jenis aktivitas & penerima',
    stripos($page['body'], 'Jenis Aktivitas') !== false
    && stripos($page['body'], 'Penerima') !== false);
check('Halaman menampilkan status proses bisnis & pengiriman terpisah',
    stripos($page['body'], 'Proses Bisnis') !== false
    && stripos($page['body'], 'Pengiriman') !== false);
check('Halaman TIDAK menampilkan token Fonnte',
    stripos($page['body'], 'FONNTE_TOKEN=') === false
    && stripos($page['body'], 'sk-or-') === false
    && stripos($page['body'], 'test-token') === false);

echo "\n=== Customer mengirim laporan (HTTP) ===\n";
$custJar = login($base, 'customer@gmail.com', 'password123');
$reportCsrf = csrfFrom($base . '/customer/reports/create', $custJar);
check('Formulir laporan menyediakan CSRF token', $reportCsrf !== '', 'csrf_len=' . strlen($reportCsrf));

$reportPage = httpCall($base . '/customer/reports/create', [CURLOPT_COOKIEFILE => $custJar]);
check('Halaman kirim laporan dapat diakses customer', $reportPage['status'] === 200, 'HTTP ' . $reportPage['status']);
check('Halaman kirim laporan tidak menampilkan token Fonnte',
    stripos($reportPage['body'], 'FONNTE_TOKEN=') === false);

$storeResp = httpCall($base . '/customer/reports/store', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'csrf_token'   => $reportCsrf,
        'subject'      => 'Laporan uji E2E HTTP',
        'description' => 'Laporan ini dibuat melalui pengujian end-to-end HTTP.',
        'category'     => 'layanan',
    ]),
    CURLOPT_COOKIEFILE => $custJar,
    CURLOPT_FOLLOWLOCATION => true,
]);
check('Penyimpanan laporan tidak error (5xx)', $storeResp['status'] < 500, 'HTTP ' . $storeResp['status']);

$db->query(
    "SELECT r.id, r.report_code, r.status, u.email
       FROM customer_reports r
       JOIN users u ON u.id = r.user_id
      WHERE r.subject = :subject
      ORDER BY r.id DESC LIMIT 1"
);
$db->bind(':subject', 'Laporan uji E2E HTTP');
$report = $db->single();
check('Laporan tersimpan di database', !empty($report['id']),
    'kode=' . ($report['report_code'] ?? '-') . ' status=' . ($report['status'] ?? '-'));

if (!empty($report['id'])) {
    $adminReports = httpCall($base . '/admin/customer-reports', [CURLOPT_COOKIEFILE => $adminJar]);
    check('Halaman admin laporan customer dapat diakses', $adminReports['status'] === 200, 'HTTP ' . $adminReports['status']);
    check('Laporan baru tampil di halaman admin',
        stripos($adminReports['body'], 'Laporan uji E2E HTTP') !== false);
    check('Halaman admin laporan tidak menampilkan token Fonnte',
        stripos($adminReports['body'], 'FONNTE_TOKEN=') === false);

    $adminCsrf = csrfFrom($base . '/admin/customer-reports', $adminJar);
    check('Formulir status laporan menyediakan CSRF token', $adminCsrf !== '', 'csrf_len=' . strlen($adminCsrf));

    $statusResp = httpCall($base . '/admin/customer-reports/status', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'csrf_token'  => $adminCsrf,
            'report_id'   => (int) $report['id'],
            'status'      => 'processing',
            'admin_notes' => 'Sedang ditinjau (uji E2E).',
        ]),
        CURLOPT_COOKIEFILE => $adminJar,
    ]);
    check('Perubahan status laporan diproses (redirect 302)', $statusResp['status'] === 302, 'HTTP ' . $statusResp['status']);

    $db->query("SELECT status, admin_notes FROM customer_reports WHERE id = :id");
    $db->bind(':id', (int) $report['id']);
    $updated = $db->single();
    check('Status laporan tersimpan di database', ($updated['status'] ?? '') === 'processing',
        'status=' . ($updated['status'] ?? '-'));
    check('Catatan admin tersimpan', ($updated['admin_notes'] ?? '') !== '');
}

echo "\n=== Isolasi akses ===\n";
$customerOnAdminPage = httpCall($base . '/admin/customer-reports', [CURLOPT_COOKIEFILE => $custJar]);
check('Customer tidak bisa membuka halaman admin laporan customer',
    in_array($customerOnAdminPage['status'], [302, 403], true),
    'HTTP ' . $customerOnAdminPage['status']);

$customerOnWhatsapp = httpCall($base . '/admin/whatsapp', [CURLOPT_COOKIEFILE => $custJar]);
check('Customer tidak bisa membuka halaman notifikasi admin',
    in_array($customerOnWhatsapp['status'], [302, 403], true),
    'HTTP ' . $customerOnWhatsapp['status']);

$resendNoCsrf = httpCall($base . '/admin/whatsapp/resend', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['notification_id' => 1]),
    CURLOPT_COOKIEFILE => $adminJar,
]);
check('Kirim ulang tanpa CSRF ditolak', in_array($resendNoCsrf['status'], [302, 403], true),
    'HTTP ' . $resendNoCsrf['status']);

$resendByCustomer = httpCall($base . '/admin/whatsapp/resend', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['csrf_token' => 'x', 'notification_id' => 1]),
    CURLOPT_COOKIEFILE => $custJar,
]);
check('Customer tidak bisa memicu kirim ulang notifikasi',
    in_array($resendByCustomer['status'], [302, 403], true),
    'HTTP ' . $resendByCustomer['status']);

$customerOwnReports = httpCall($base . '/customer/reports', [CURLOPT_COOKIEFILE => $custJar]);
check('Customer melihat halaman laporan sendiri', $customerOwnReports['status'] === 200, 'HTTP ' . $customerOwnReports['status']);
check('Halaman laporan customer tidak menampilkan nomor customer lain',
    strpos($customerOwnReports['body'], '6281234567890') === false);

echo "\n=== Pembersihan data uji ===\n";
if (!empty($report['id'])) {
    $db->query("DELETE FROM customer_reports WHERE id = :id");
    $db->bind(':id', (int) $report['id']);
    $db->execute();
}
if (!empty($created['id'])) {
    $db->query("DELETE FROM customers WHERE user_id = :uid");
    $db->bind(':uid', (int) $created['id']);
    $db->execute();
    $db->query("DELETE FROM users WHERE id = :uid");
    $db->bind(':uid', (int) $created['id']);
    $db->execute();
}
@unlink($regJar);
@unlink($adminJar);
@unlink($custJar);
echo "  Data uji dibersihkan.\n";

echo "\n========================================\n";
echo "Hasil: {$passed} passed, {$failed} failed\n";
echo "========================================\n";
exit($failed > 0 ? 1 : 0);
