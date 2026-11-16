<?php

/**
 * Tes integrasi chatbot lengkap: HMAC internal + Node.js + OpenRouter.
 *
 * Run:
 *   php tests/ai_full_integration_test.php
 *
 * Prasyarat:
 *   - Node.js berjalan di 127.0.0.1:3001 (npm start di chatbot-node)
 *   - .env PHP dan Node sudah diisi AI_INTERNAL_SECRET yang sama
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Services/AiNonceStore.php';
require_once __DIR__ . '/../app/Services/AiInternalAuthService.php';
require_once __DIR__ . '/../app/Services/AiBridgeService.php';

$passed = 0;
$failed = 0;
$skipped = 0;

function check($name, $condition, $detail = '')
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
        return true;
    }
    $failed++;
    echo "  [FAIL] {$name}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    return false;
}

function skip($name, $reason)
{
    global $skipped;
    $skipped++;
    echo "  [SKIP] {$name} ({$reason})\n";
}

function httpGet($url, array $headers = [])
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'ok' => $body !== false && $status > 0,
        'status' => $status,
        'body' => $body === false ? '' : (string) $body,
    ];
}

function httpPost($url, array $headers = [], $bodyJson = '{}')
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $bodyJson,
        CURLOPT_HTTPHEADER => array_merge(
            ['Accept: application/json', 'Content-Type: application/json'],
            $headers
        ),
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'ok' => $body !== false && $status > 0,
        'status' => $status,
        'body' => $body === false ? '' : (string) $body,
    ];
}

function jsonBody($response)
{
    $decoded = json_decode($response['body'], true);
    return is_array($decoded) ? $decoded : [];
}

function authHeaders($token)
{
    return [
        'Authorization: Bearer ' . $token,
        'X-AI-Token: ' . $token,
    ];
}

$auth = new AiInternalAuthService();

// ========================================================
// 1. Cek konfigurasi secret
// ========================================================
echo "\n[1] Konfigurasi HMAC\n";
check('AI_INTERNAL_SECRET dikonfigurasi (>=32 char)', $auth->isConfigured());

// ========================================================
// 2. Token unit: admin dan customer
// ========================================================
echo "\n[2] Token HMAC unit\n";
$adminUser = ['id' => 15, 'role' => 'admin'];
$customerUser = ['id' => 18, 'role' => 'customer'];
$technicianUser = ['id' => 17, 'role' => 'technician'];

$adminHandoff = $auth->issueHandoffToken($adminUser);
$customerHandoff = $auth->issueHandoffToken($customerUser);

$adminClaims = $auth->verifyHandoffToken($adminHandoff);
$customerClaims = $auth->verifyHandoffToken($customerHandoff);

check('Admin handoff token verifikasi (role=admin)', $adminClaims['role'] === 'admin' && $adminClaims['user_id'] === 15);
check('Customer handoff token verifikasi (role=customer)', $customerClaims['role'] === 'customer' && $customerClaims['user_id'] === 18);
check('Teknisi tidak dapat token AI', (function () use ($auth, $technicianUser) {
    try {
        $auth->issueHandoffToken($technicianUser);
        return false;
    } catch (\RuntimeException $e) {
        return $e->getCode() === 403;
    }
})());

// ========================================================
// 3. PHP internal endpoints (dengan service token)
// ========================================================
echo "\n[3] PHP internal endpoints\n";

// Tidak ada token -> 401
$noToken = httpGet(BASEURL . '/api/internal/ai/context');
check('Internal endpoint tolak tanpa token', $noToken['status'] === 401, 'status=' . $noToken['status']);

// Customer context
$svcCust = $auth->issueServiceToken($customerUser);
$ctxCust = httpGet(BASEURL . '/api/internal/ai/context', authHeaders($svcCust));
$ctxCustBody = jsonBody($ctxCust);
check('Customer context sukses', $ctxCust['status'] === 200 && ($ctxCustBody['data']['role'] ?? '') === 'customer');
check('Customer read_all_orders=false', ($ctxCustBody['data']['permissions']['read_all_orders'] ?? true) === false);
check('Customer can_write=false', ($ctxCustBody['data']['can_write'] ?? true) === false);

// Admin context
$svcAdmin = $auth->issueServiceToken($adminUser);
$ctxAdmin = httpGet(BASEURL . '/api/internal/ai/context', authHeaders($svcAdmin));
$ctxAdminBody = jsonBody($ctxAdmin);
check('Admin context sukses', $ctxAdmin['status'] === 200 && ($ctxAdminBody['data']['role'] ?? '') === 'admin');
check('Admin read_all_orders=true', ($ctxAdminBody['data']['permissions']['read_all_orders'] ?? false) === true);
check('Admin can_write=false', ($ctxAdminBody['data']['can_write'] ?? true) === false);

// Services catalog (customer)
$svcCat = httpGet(BASEURL . '/api/internal/ai/services', authHeaders($auth->issueServiceToken($customerUser)));
$svcCatBody = jsonBody($svcCat);
check('Services catalog customer sukses', $svcCat['status'] === 200 && isset($svcCatBody['data']['services']));
check('Services catalog has items', count($svcCatBody['data']['services'] ?? []) > 0);

// Customer profile
$svcProf = httpGet(BASEURL . '/api/internal/ai/customer/profile', authHeaders($auth->issueServiceToken($customerUser)));
$svcProfBody = jsonBody($svcProf);
check('Customer profile sukses', $svcProf['status'] === 200 && ($svcProfBody['data']['role'] ?? '') === 'customer');
check('Customer profile has name', !empty($svcProfBody['data']['name'] ?? ''));

// Customer orders (customer 18)
$svcOrders = httpGet(BASEURL . '/api/internal/ai/customer/orders?limit=5', authHeaders($auth->issueServiceToken($customerUser)));
$svcOrdersBody = jsonBody($svcOrders);
check('Customer orders sukses', $svcOrders['status'] === 200);
check('Customer orders scope=customer', ($svcOrdersBody['data']['scope'] ?? '') === 'customer');
check('Customer orders read_only=true', ($svcOrdersBody['data']['read_only'] ?? false) === true);

// Admin stats
$svcStats = httpGet(BASEURL . '/api/internal/ai/admin/stats', authHeaders($auth->issueServiceToken($adminUser)));
$svcStatsBody = jsonBody($svcStats);
check('Admin stats sukses', $svcStats['status'] === 200);
check('Admin stats has total_customers', isset($svcStatsBody['data']['stats']['total_customers']));
check('Admin stats has total_orders', isset($svcStatsBody['data']['stats']['total_orders']));
check('Admin stats read_only=true', ($svcStatsBody['data']['read_only'] ?? false) === true);

// Admin activity summary
$svcActivity = httpGet(BASEURL . '/api/internal/ai/admin/activity-summary', authHeaders($auth->issueServiceToken($adminUser)));
$svcActivityBody = jsonBody($svcActivity);
check('Admin activity summary sukses', $svcActivity['status'] === 200);
check('Admin activity has orders_last_7_days', isset($svcActivityBody['data']['summary']['orders_last_7_days']));
check('Admin activity read_only=true', ($svcActivityBody['data']['read_only'] ?? false) === true);

// Customer coba akses admin stats -> 403
$custToAdmin = httpGet(BASEURL . '/api/internal/ai/admin/stats', authHeaders($auth->issueServiceToken($customerUser)));
check('Customer tidak bisa akses admin stats', $custToAdmin['status'] === 403, 'status=' . $custToAdmin['status']);

// Admin coba akses customer profile -> 403
$adminToCust = httpGet(BASEURL . '/api/internal/ai/customer/profile', authHeaders($auth->issueServiceToken($adminUser)));
check('Admin tidak bisa akses customer profile', $adminToCust['status'] === 403, 'status=' . $adminToCust['status']);

// ========================================================
// 4. Node.js bridge (butuh server berjalan)
// ========================================================
echo "\n[4] Node.js bridge (perlu npm start)\n";
$nodeHealth = httpGet('http://127.0.0.1:3001/api/internal/auth-check', authHeaders($adminHandoff));
if (!$nodeHealth['ok']) {
    skip('Node auth-check', 'Node server tidak berjalan: ' . $nodeHealth['error']);
} else {
    $nodeBody = jsonBody($nodeHealth);
    check('Node terima handoff admin', $nodeHealth['status'] === 200 && ($nodeBody['data']['role'] ?? '') === 'admin');

    // Node replay protection
    $nodeReplay = httpGet('http://127.0.0.1:3001/api/internal/auth-check', authHeaders($adminHandoff));
    check('Node tolak token replay', $nodeReplay['status'] === 401, 'status=' . $nodeReplay['status']);

    // Node chat customer (OpenRouter) - fresh handoff token
    $custHandoff2 = $auth->issueHandoffToken($customerUser);
    $chatCust = httpPost('http://127.0.0.1:3001/api/chat', authHeaders($custHandoff2), json_encode([
        'query' => 'Siapa kamu?',
        'history' => [],
    ]));
    $chatCustBody = jsonBody($chatCust);
    if ($chatCust['status'] === 503 && strpos($chatCustBody['message'] ?? '', 'OpenRouter belum dikonfigurasi') !== false) {
        skip('Node chat customer (OpenRouter belum dikonfigurasi)', 'Isi OPENROUTER_API_KEY dan MODEL di chatbot-node/.env');
    } else {
        check('Node chat customer sukses', $chatCust['status'] === 200 && ($chatCustBody['success'] ?? false) === true, 'status=' . $chatCust['status'] . ' body=' . substr($chatCust['body'], 0, 200));
        if ($chatCust['status'] === 200 && ($chatCustBody['success'] ?? false)) {
            check('Node chat has reply', !empty($chatCustBody['data']['reply'] ?? ''));
        }
    }

    // Node chat admin (OpenRouter) - fresh handoff token
    $adminHandoff2 = $auth->issueHandoffToken($adminUser);
    $chatAdmin = httpPost('http://127.0.0.1:3001/api/chat', authHeaders($adminHandoff2), json_encode([
        'query' => 'Siapa kamu?',
        'history' => [],
    ]));
    $chatAdminBody = jsonBody($chatAdmin);
    if ($chatAdmin['status'] === 503 && strpos($chatAdminBody['message'] ?? '', 'OpenRouter belum dikonfigurasi') !== false) {
        skip('Node chat admin (OpenRouter belum dikonfigurasi)', 'Isi OPENROUTER_API_KEY dan MODEL di chatbot-node/.env');
    } else {
        check('Node chat admin sukses', $chatAdmin['status'] === 200 && ($chatAdminBody['success'] ?? false) === true, 'status=' . $chatAdmin['status']);
        if ($chatAdmin['status'] === 200 && ($chatAdminBody['success'] ?? false)) {
            check('Node admin has reply', !empty($chatAdminBody['data']['reply'] ?? ''));
        }
    }

    // Node chat without token -> 401
    $noTokenNode = httpPost('http://127.0.0.1:3001/api/chat', [], json_encode(['query' => 'tes', 'history' => []]));
    check('Node chat tolak tanpa token', $noTokenNode['status'] === 401, 'status=' . $noTokenNode['status']);

    // Node chat empty query -> 422 (but needs valid token first, so use fresh token)
    $custHandoff3 = $auth->issueHandoffToken($customerUser);
    $emptyNode = httpPost('http://127.0.0.1:3001/api/chat', authHeaders($custHandoff3), json_encode(['query' => '', 'history' => []]));
    check('Node chat tolak query kosong', $emptyNode['status'] === 422, 'status=' . $emptyNode['status']);
}

// ========================================================
// 5. PHP -> Node bridge via AiBridgeService (chatNode)
// ========================================================
echo "\n[5] PHP -> Node bridge via AiBridgeService\n";
$bridge = new AiBridgeService();
check('Bridge configured', $bridge->isConfigured());

$forwardHandoff = $auth->issueHandoffToken($customerUser);
$forward = $bridge->forwardChat($forwardHandoff, 'Siapa kamu?', []);
$forwardBody = json_decode(json_encode($forward), true);
if (($forward['success'] ?? false) === false && strpos($forward['message'] ?? '', 'OpenRouter belum dikonfigurasi') !== false) {
    skip('PHP forward chat to Node (OpenRouter belum dikonfigurasi)', 'Isi OPENROUTER_API_KEY dan MODEL di chatbot-node/.env');
} else {
    check('PHP forward chat to Node sukses', ($forward['success'] ?? false) === true, 'message=' . ($forward['message'] ?? ''));
    if (($forward['success'] ?? false) && isset($forward['data']['reply'])) {
        check('Forward reply not empty', !empty($forward['data']['reply']));
    }
}

// ========================================================
// Ringkasan
// ========================================================
echo "\n========================================\n";
echo "Hasil: {$passed} passed, {$failed} failed, {$skipped} skipped\n";
echo "========================================\n";
exit($failed > 0 ? 1 : 0);
