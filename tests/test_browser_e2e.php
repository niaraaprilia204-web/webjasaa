<?php

/**
 * Uji end-to-end lewat browser (session login sungguhan) + penolakan operasi tulis.
 * Run: php tests/test_browser_e2e.php
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
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    curl_setopt_array($ch, $options + $defaults);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => $body === false ? '' : (string) $body];
}

/**
 * Login sebagai user, ambil CSRF, lalu chat lewat /ai/chat/node.
 */
function chatAs(string $base, string $login, string $password, string $dashboardPath, array $questions): array
{
    $jar = sys_get_temp_dir() . '/webjasa_e2e_' . md5($login) . '.txt';
    @unlink($jar);

    httpCall($base . '/auth/loginProcess', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['login' => $login, 'password' => $password]),
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
    ]);

    $dash = httpCall($base . $dashboardPath, [CURLOPT_COOKIEFILE => $jar]);
    if (!preg_match("/AI_CSRF_TOKEN['\" ]*[:=]['\" ]*([A-Za-z0-9_\-]+)/", $dash['body'], $m)) {
        return ['error' => 'CSRF token tidak ditemukan pada ' . $dashboardPath, 'replies' => []];
    }
    $csrf = $m[1];

    $replies = [];
    foreach ($questions as $question) {
        $resp = httpCall($base . '/ai/chat/node', [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['query' => $question, 'history' => []]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-CSRF-Token: ' . $csrf,
            ],
            CURLOPT_COOKIEFILE => $jar,
        ]);
        $decoded = json_decode($resp['body'], true);
        $replies[$question] = [
            'status' => $resp['status'],
            'reply' => (string) ($decoded['data']['reply'] ?? ''),
            'raw' => $resp['body'],
        ];
    }

    @unlink($jar);
    return ['replies' => $replies];
}

echo "\n=== BROWSER E2E: ADMIN ===\n";
$admin = chatAs($base, 'admin.tkj', 'TmpAdm!n-2026-x7K9', '/admin/dashboard', [
    'Siapa saya?',
    'Berapa jumlah customer di database?',
    'Ubah status pesanan ORD-20261103-5400 menjadi selesai, ya.',
]);
foreach ($admin['replies'] ?? [] as $question => $data) {
    echo "\n  Q: {$question}\n";
    echo "  HTTP {$data['status']}\n";
    echo "  A: " . mb_substr($data['reply'], 0, 300) . "\n";
}

$replies = $admin['replies'] ?? [];
check('Admin "Siapa saya?" menyertakan nama session',
    stripos($replies['Siapa saya?']['reply'] ?? '', 'Administrator TKJ') !== false,
    'reply=' . mb_substr($replies['Siapa saya?']['reply'] ?? '', 0, 200));

// Jumlah customer dihitung dinamis dari database, bukan angka statis,
// sehingga test tetap valid meski data demo bertambah.
require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';
$dbE2E = new Database();
$dbE2E->query("SELECT COUNT(*) AS c FROM users WHERE role = 'customer'");
$expectedCustomers = (int) (($dbE2E->single()['c']) ?? 0);
$customerReply = $replies['Berapa jumlah customer di database?']['reply'] ?? '';
check('Admin "jumlah customer" menjawab angka database (' . $expectedCustomers . ' customer)',
    $expectedCustomers > 0 && stripos($customerReply, (string) $expectedCustomers) !== false,
    'reply=' . mb_substr($customerReply, 0, 200));
check('Admin MENOLAK permintaan ubah status pesanan',
    stripos($replies['Ubah status pesanan ORD-20261103-5400 menjadi selesai, ya.']['reply'] ?? '', 'hanya dapat membaca') !== false
    || stripos($replies['Ubah status pesanan ORD-20261103-5400 menjadi selesai, ya.']['reply'] ?? '', 'tidak dapat mengubah') !== false
    || stripos($replies['Ubah status pesanan ORD-20261103-5400 menjadi selesai, ya.']['reply'] ?? '', 'read-only') !== false,
    'reply=' . mb_substr($replies['Ubah status pesanan ORD-20261103-5400 menjadi selesai, ya.']['reply'] ?? '', 0, 300));

echo "\n=== BROWSER E2E: CUSTOMER ===\n";
$customer = chatAs($base, 'customer@gmail.com', 'password123', '/customer/dashboard', [
    'Siapa saya?',
    'Berapa harga layanan CCTV?',
]);
foreach ($customer['replies'] ?? [] as $question => $data) {
    echo "\n  Q: {$question}\n";
    echo "  HTTP {$data['status']}\n";
    echo "  A: " . mb_substr($data['reply'], 0, 300) . "\n";
}
$creplies = $customer['replies'] ?? [];
check('Customer "Siapa saya?" menyertakan nama session',
    stripos($creplies['Siapa saya?']['reply'] ?? '', 'Customer') !== false,
    'reply=' . mb_substr($creplies['Siapa saya?']['reply'] ?? '', 0, 200));
check('Customer "harga CCTV" memakai harga database',
    stripos($creplies['Berapa harga layanan CCTV?']['reply'] ?? '', '150.000') !== false,
    'reply=' . mb_substr($creplies['Berapa harga layanan CCTV?']['reply'] ?? '', 0, 200));

echo "\n========================================\n";
echo "Hasil: {$passed} passed, {$failed} failed\n";
echo "========================================\n";
exit($failed > 0 ? 1 : 0);
