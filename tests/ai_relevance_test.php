<?php

/**
 * Uji guard relevansi topik di backend:
 *  1. Unit: AiTopicGuard menerima/reject pertanyaan sesuai daftar.
 *  2. E2E: pertanyaan off-topic lewat /ai/chat/node ditolak oleh PHP
 *     TANPA meneruskan ke Node/OpenRouter (source = topic_guard),
 *     dan sapaan diterima.
 * Run: php tests/ai_relevance_test.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Services/AiTopicGuard.php';

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

echo "\n=== UNIT: AiTopicGuard ===\n";

$relevant = [
    'Siapa saya?',
    'Berapa jumlah customer di database?',
    'Berapa total user?',
    'Siapa saja user yang terdaftar?',
    'Apa saja layanan yang tersedia?',
    'Berapa jumlah laporan?',
    'Siapa yang membuat laporan?',
    'Bagaimana alur kerja website?',
    'Bagaimana sistem WhatsApp bekerja?',
    'Berapa harga layanan CCTV?',
    'Apa status pesanan saya?',
    'Halo',
    'Terima kasih',
];
foreach ($relevant as $q) {
    check("RELEVAN: {$q}", AiTopicGuard::isRelevant($q) === true, 'reason=' . AiTopicGuard::reason($q));
}

$offtopic = [
    'Buatkan kode Python.',
    'Tolong buat program JavaScript.',
    'Buatkan menu makanan hari ini.',
    'Ceritakan dongeng untuk saya.',
    'Siapa presiden Indonesia?',
    'Resep rendang padang',
    'Film apa yang bagus minggu ini?',
];
foreach ($offtopic as $q) {
    check("OFF-TOPIC ditolak: {$q}", AiTopicGuard::isRelevant($q) === false, 'reason=' . AiTopicGuard::reason($q));
}

check('Pesan penolakan memakai frasa wajib', stripos(AiTopicGuard::rejectionMessage(), 'TKJ Service Hub AI Assistant') !== false);

echo "\n=== E2E: guard dijalankan PHP sebelum Node/OpenRouter ===\n";

function chatViaSession(string $base, string $login, string $password, string $dashboardPath, string $question): array
{
    $jar = sys_get_temp_dir() . '/webjasa_rel_' . md5($login) . '.txt';
    @unlink($jar);

    $ch = curl_init($base . '/auth/loginProcess');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['login' => $login, 'password' => $password]),
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
    ]);
    curl_exec($ch);
    curl_close($ch);

    $ch = curl_init($base . $dashboardPath);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar]);
    $dash = curl_exec($ch);
    curl_close($ch);

    if (!preg_match("/AI_CSRF_TOKEN['\" ]*[:=]['\" ]*([A-Za-z0-9_\-]+)/", (string) $dash, $m)) {
        return ['status' => 0, 'body' => ''];
    }

    $ch = curl_init($base . '/ai/chat/node');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['query' => $question, 'history' => []]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-CSRF-Token: ' . $m[1],
        ],
        CURLOPT_COOKIEFILE => $jar,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @unlink($jar);

    return ['status' => $status, 'body' => (string) $body];
}

// Admin: pertanyaan off-topic harus ditolak oleh PHP (source topic_guard).
$resp = chatViaSession($base, 'admin.tkj', 'TmpAdm!n-2026-x7K9', '/admin/dashboard', 'Buatkan kode Python.');
$decoded = json_decode($resp['body'], true);
$source = (string) ($decoded['source'] ?? '');
$reply = (string) ($decoded['data']['reply'] ?? '');
check('Admin pertanyaan Python -> HTTP 200', $resp['status'] === 200, 'HTTP ' . $resp['status']);
check('Admin pertanyaan Python -> source topic_guard (tidak ke Node/OpenRouter)', $source === 'topic_guard', 'source=' . $source . ' body=' . mb_substr($resp['body'], 0, 200));
check('Admin pertanyaan Python -> pesan penolakan TKJ', stripos($reply, 'TKJ Service Hub AI Assistant') !== false, 'reply=' . mb_substr($reply, 0, 200));

// Guard juga harus menolak lewat log: baris terakhir harus topic_guard.
require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';
$dbRel = new Database();
$dbRel->query("SELECT source FROM ai_chat_logs ORDER BY id DESC LIMIT 1");
$lastLog = $dbRel->single() ?: [];
check('Log mencatat sumber topic_guard', ($lastLog['source'] ?? '') === 'topic_guard', 'source=' . ($lastLog['source'] ?? '-'));

echo "\n========================================\n";
echo "Hasil: {$passed} passed, {$failed} failed\n";
echo "========================================\n";
exit($failed > 0 ? 1 : 0);
