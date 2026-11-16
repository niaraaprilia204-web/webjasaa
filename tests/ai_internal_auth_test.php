<?php

/**
 * Stage 3 internal authentication tests.
 *
 * Run from the project root:
 *   php tests/ai_internal_auth_test.php
 *
 * Node.js must be running on 127.0.0.1:3001 for the bridge checks:
 *   cd chatbot-node && npm start
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Services/AiNonceStore.php';
require_once __DIR__ . '/../app/Services/AiInternalAuthService.php';

$passed = 0;
$failed = 0;
$skipped = 0;

function check($name, $condition, $detail = '')
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
        return;
    }

    $failed++;
    echo "  [FAIL] {$name}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
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
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'ok' => $body !== false && $status > 0,
        'status' => $status,
        'body' => $body === false ? '' : (string) $body,
        'error' => $error,
    ];
}

function httpPost($url, array $headers = [])
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '{}',
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers),
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => $body === false ? '' : (string) $body];
}

function authHeaders($token)
{
    return [
        'Authorization: Bearer ' . $token,
        'X-AI-Token: ' . $token,
    ];
}

function jsonBody($response)
{
    $decoded = json_decode($response['body'], true);
    return is_array($decoded) ? $decoded : [];
}

$auth = new AiInternalAuthService();
$admin = ['id' => 15, 'role' => 'admin'];
$customer = ['id' => 18, 'role' => 'customer'];

echo "Stage 3 — Internal HMAC authentication\n\n";

echo "[1] Token unit checks\n";
check('Secret is configured (>= 32 chars)', $auth->isConfigured());
$adminHandoff = $auth->issueHandoffToken($admin);
$customerHandoff = $auth->issueHandoffToken($customer);
$adminClaims = $auth->verifyHandoffToken($adminHandoff);
$customerClaims = $auth->verifyHandoffToken($customerHandoff);
check('Admin handoff token verifies with role admin', $adminClaims['role'] === 'admin' && $adminClaims['user_id'] === 15);
check('Customer handoff token verifies with role customer', $customerClaims['role'] === 'customer' && $customerClaims['user_id'] === 18);
check('Handoff token cannot be used as Node -> PHP service token', (function () use ($auth, $adminHandoff) {
    try {
        $auth->verifyServiceToken($adminHandoff, false);
        return false;
    } catch (\RuntimeException $e) {
        return $e->getCode() === 401;
    }
})());
$tampered = substr($adminHandoff, 0, -1) . (substr($adminHandoff, -1) === 'A' ? 'B' : 'A');
check('Tampered token is rejected', (function () use ($auth, $tampered) {
    try {
        $auth->verifyHandoffToken($tampered, false);
        return false;
    } catch (\RuntimeException $e) {
        return $e->getCode() === 401;
    }
})());
check('Technician cannot receive an AI token', (function () use ($auth) {
    try {
        $auth->issueHandoffToken(['id' => 17, 'role' => 'technician']);
        return false;
    } catch (\RuntimeException $e) {
        return $e->getCode() === 403;
    }
})());
$replayToken = $auth->issueServiceToken($customer);
$auth->verifyServiceToken($replayToken, true);
check('Service token replay is rejected', (function () use ($auth, $replayToken) {
    try {
        $auth->verifyServiceToken($replayToken, true);
        return false;
    } catch (\RuntimeException $e) {
        return $e->getCode() === 401;
    }
})());

echo "\n[2] PHP internal endpoints\n";
$base = rtrim(BASEURL, '/');
$noToken = httpGet($base . '/api/internal/ai/context');
check('Internal endpoint rejects missing token', $noToken['status'] === 401, 'status=' . $noToken['status']);

$contextCustomer = httpGet($base . '/api/internal/ai/context', authHeaders($auth->issueServiceToken($customer)));
$contextCustomerBody = jsonBody($contextCustomer);
check('Customer context succeeds', $contextCustomer['status'] === 200 && ($contextCustomerBody['data']['role'] ?? '') === 'customer');
check('Customer cannot read all orders', ($contextCustomerBody['data']['permissions']['read_all_orders'] ?? true) === false);
check('Customer cannot write data', ($contextCustomerBody['data']['can_write'] ?? true) === false);

$contextAdmin = httpGet($base . '/api/internal/ai/context', authHeaders($auth->issueServiceToken($admin)));
$contextAdminBody = jsonBody($contextAdmin);
check('Admin context succeeds', $contextAdmin['status'] === 200 && ($contextAdminBody['data']['role'] ?? '') === 'admin');
check('Admin can read all orders but cannot write', ($contextAdminBody['data']['permissions']['read_all_orders'] ?? false) === true && ($contextAdminBody['data']['can_write'] ?? true) === false);

$methodPost = httpPost($base . '/api/internal/ai/context');
check('Internal endpoint rejects POST', in_array($methodPost['status'], [404, 405], true), 'status=' . $methodPost['status']);

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $totalOrders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();

    $stmt = $pdo->prepare('SELECT o.order_code FROM orders o JOIN customers c ON c.id = o.customer_id WHERE c.user_id = :uid');
    $stmt->execute([':uid' => 18]);
    $customerOrderCodes = array_column($stmt->fetchAll(), 'order_code');

    $otherCode = $pdo->query('SELECT o.order_code FROM orders o JOIN customers c ON c.id = o.customer_id WHERE c.user_id <> 18 LIMIT 1')->fetchColumn();

    $customerOrders = httpGet($base . '/api/internal/ai/orders?limit=50&user_id=1&customer_id=1', authHeaders($auth->issueServiceToken($customer)));
    $customerOrdersBody = jsonBody($customerOrders);
    $actualCustomerCodes = array_column($customerOrdersBody['data']['orders'] ?? [], 'order_code');

    check('Customer order endpoint succeeds', $customerOrders['status'] === 200);
    check('Customer only receives own orders', count(array_diff($actualCustomerCodes, $customerOrderCodes)) === 0, implode(',', $actualCustomerCodes));
    check('Customer cannot see another customer order', $otherCode === false || !in_array($otherCode, $actualCustomerCodes, true));
    check('Customer scope is enforced server-side', ($customerOrdersBody['data']['scope'] ?? '') === 'customer');

    $adminOrders = httpGet($base . '/api/internal/ai/orders?limit=50', authHeaders($auth->issueServiceToken($admin)));
    $adminOrdersBody = jsonBody($adminOrders);
    check('Admin order endpoint succeeds', $adminOrders['status'] === 200);
    check('Admin sees all orders', (int) ($adminOrdersBody['data']['count'] ?? -1) === $totalOrders, 'count=' . ($adminOrdersBody['data']['count'] ?? 'null') . ' expected=' . $totalOrders);
    check('Admin order endpoint remains read-only', ($adminOrdersBody['data']['read_only'] ?? false) === true);

    $replayService = $auth->issueServiceToken($customer);
    $firstReplay = httpGet($base . '/api/internal/ai/context', authHeaders($replayService));
    $secondReplay = httpGet($base . '/api/internal/ai/context', authHeaders($replayService));
    check('PHP rejects a replayed service token', $firstReplay['status'] === 200 && $secondReplay['status'] === 401, 'first=' . $firstReplay['status'] . ' second=' . $secondReplay['status']);
} catch (\Throwable $e) {
    check('Database checks', false, $e->getMessage());
}

echo "\n[3] Node.js bridge (requires npm start)\n";
$nodeHealth = httpGet('http://127.0.0.1:3001/api/internal/auth-check', authHeaders($adminHandoff));
if (!$nodeHealth['ok']) {
    skip('Node.js auth-check', 'Node server not running: ' . $nodeHealth['error']);
} else {
    $nodeHealthBody = jsonBody($nodeHealth);
    check('Node accepts PHP admin handoff token', $nodeHealth['status'] === 200 && ($nodeHealthBody['data']['role'] ?? '') === 'admin', 'status=' . $nodeHealth['status']);
    $nodeReplay = httpGet('http://127.0.0.1:3001/api/internal/auth-check', authHeaders($adminHandoff));
    check('Node rejects a replayed handoff token', $nodeReplay['status'] === 401, 'status=' . $nodeReplay['status']);

    $nodeCustomerContext = httpGet('http://127.0.0.1:3001/api/internal/php-context', authHeaders($customerHandoff));
    $nodeCustomerBody = jsonBody($nodeCustomerContext);
    check('Node -> PHP customer context succeeds', $nodeCustomerContext['status'] === 200 && ($nodeCustomerBody['data']['php']['role'] ?? '') === 'customer', 'status=' . $nodeCustomerContext['status']);
    check('Node -> PHP customer scope is read-only', ($nodeCustomerBody['data']['php']['permissions']['write_any_data'] ?? true) === false);

    $adminHandoff2 = $auth->issueHandoffToken($admin);
    $nodeAdminContext = httpGet('http://127.0.0.1:3001/api/internal/php-context', authHeaders($adminHandoff2));
    $nodeAdminBody = jsonBody($nodeAdminContext);
    check('Node -> PHP admin context succeeds', $nodeAdminContext['status'] === 200 && ($nodeAdminBody['data']['php']['role'] ?? '') === 'admin', 'status=' . $nodeAdminContext['status']);
    check('Admin context allows all-order reads but not writes', ($nodeAdminBody['data']['php']['permissions']['read_all_orders'] ?? false) === true && ($nodeAdminBody['data']['php']['can_write'] ?? true) === false);

    $tamperedNode = substr($customerHandoff, 0, -1) . (substr($customerHandoff, -1) === 'A' ? 'B' : 'A');
    $badNode = httpGet('http://127.0.0.1:3001/api/internal/auth-check', authHeaders($tamperedNode));
    check('Node rejects a tampered token', $badNode['status'] === 401, 'status=' . $badNode['status']);
}

echo "\nResult: {$passed} passed, {$failed} failed, {$skipped} skipped\n";
exit($failed > 0 ? 1 : 0);
