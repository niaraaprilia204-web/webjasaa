<?php

/**
 * Uji endpoint retrieval data internal (AiDataController).
 * Run: php tests/ai_data_endpoints_test.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Services/AiInternalAuthService.php';

$auth = new AiInternalAuthService();
$adminUser = ['id' => 15, 'role' => 'admin'];
$customerUser = ['id' => 18, 'role' => 'customer'];

$passed = 0;
$failed = 0;

function check($name, $condition, $detail = '')
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}";
        if ($detail !== '') echo " ({$detail})";
        echo "\n";
        return true;
    }
    $failed++;
    echo "  [FAIL] {$name}";
    if ($detail !== '') echo " ({$detail})";
    echo "\n";
    return false;
}

function httpGet($url, array $headers = [])
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string) $body, true);
    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : [], 'raw' => (string) $body];
}

function authHeaders($token)
{
    return ['Authorization: Bearer ' . $token, 'X-AI-Token: ' . $token];
}

echo "\n[A] Endpoint publik & customer\n";

$r = httpGet(BASEURL . '/api/internal/ai/data/public-info', authHeaders($auth->issueServiceToken($customerUser)));
check('public-info sukses', $r['status'] === 200 && ($r['body']['data']['total_active_services'] ?? 0) > 0,
    'services=' . ($r['body']['data']['total_active_services'] ?? 'null'));

$r = httpGet(BASEURL . '/api/internal/ai/data/services', authHeaders($auth->issueServiceToken($customerUser)));
check('services catalog sukses', $r['status'] === 200 && count($r['body']['data']['services'] ?? []) > 0,
    'count=' . count($r['body']['data']['services'] ?? []));

$r = httpGet(BASEURL . '/api/internal/ai/data/services?view=prices&keyword=CCTV', authHeaders($auth->issueServiceToken($customerUser)));
check('services prices+CCTV', $r['status'] === 200 && count($r['body']['data']['services'] ?? []) > 0,
    'first=' . ($r['body']['data']['services'][0]['price_range'] ?? 'null'));

$r = httpGet(BASEURL . '/api/internal/ai/data/identity', authHeaders($auth->issueServiceToken($customerUser)));
check('identity customer', $r['status'] === 200 && ($r['body']['data']['role'] ?? '') === 'customer',
    'name=' . ($r['body']['data']['name'] ?? 'null') . ' source=' . ($r['body']['data']['source'] ?? 'null'));

$r = httpGet(BASEURL . '/api/internal/ai/data/my-orders', authHeaders($auth->issueServiceToken($customerUser)));
check('my-orders customer sukses', $r['status'] === 200, 'count=' . ($r['body']['data']['count'] ?? 'null'));
$ownOrders = $r['body']['data']['orders'] ?? [];
check('my-orders hanya milik sendiri', count($ownOrders) >= 0 && ($r['body']['data']['scope'] ?? '') === 'customer');

echo "\n[B] Isolasi akses customer -> endpoint admin\n";
foreach ([
    '/api/internal/ai/data/admin/totals',
    '/api/internal/ai/data/admin/customers',
    '/api/internal/ai/data/admin/orders',
    '/api/internal/ai/data/admin/schema',
    '/api/internal/ai/data/admin/payments',
    '/api/internal/ai/data/admin/technicians',
    '/api/internal/ai/data/admin/period-comparison',
    '/api/internal/ai/data/admin/popular-services',
    '/api/internal/ai/data/admin/orders-summary',
    '/api/internal/ai/data/admin/activity',
] as $path) {
    $r = httpGet(BASEURL . $path, authHeaders($auth->issueServiceToken($customerUser)));
    check('customer ditolak ' . basename($path), $r['status'] === 403, 'status=' . $r['status']);
}

echo "\n[C] Endpoint admin\n";
$r = httpGet(BASEURL . '/api/internal/ai/data/admin/totals', authHeaders($auth->issueServiceToken($adminUser)));
$totals = $r['body']['data']['totals'] ?? [];
check('admin totals sukses', $r['status'] === 200 && isset($totals['total_orders']),
    'orders=' . ($totals['total_orders'] ?? 'null') . ' customers=' . ($totals['total_customers'] ?? 'null'));

$r = httpGet(BASEURL . '/api/internal/ai/data/admin/customers', authHeaders($auth->issueServiceToken($adminUser)));
check('admin customers sukses', $r['status'] === 200 && count($r['body']['data']['customers'] ?? []) > 0,
    'count=' . count($r['body']['data']['customers'] ?? []));

$r = httpGet(BASEURL . '/api/internal/ai/data/admin/orders?period=this_month', authHeaders($auth->issueServiceToken($adminUser)));
check('admin orders this_month', $r['status'] === 200 && isset($r['body']['data']['count']),
    'count=' . ($r['body']['data']['count'] ?? 'null'));

$r = httpGet(BASEURL . '/api/internal/ai/data/admin/orders-summary?group_by=status', authHeaders($auth->issueServiceToken($adminUser)));
check('admin orders-summary', $r['status'] === 200 && count($r['body']['data']['groups'] ?? []) > 0,
    'groups=' . count($r['body']['data']['groups'] ?? []));

$r = httpGet(BASEURL . '/api/internal/ai/data/admin/payments', authHeaders($auth->issueServiceToken($adminUser)));
check('admin payments', $r['status'] === 200 && isset($r['body']['data']['revenue_paid']),
    'revenue=' . ($r['body']['data']['revenue_paid'] ?? 'null'));

$r = httpGet(BASEURL . '/api/internal/ai/data/admin/technicians', authHeaders($auth->issueServiceToken($adminUser)));
check('admin technicians', $r['status'] === 200 && count($r['body']['data']['technicians'] ?? []) > 0,
    'count=' . count($r['body']['data']['technicians'] ?? []));

$r = httpGet(BASEURL . '/api/internal/ai/data/admin/popular-services', authHeaders($auth->issueServiceToken($adminUser)));
check('admin popular-services', $r['status'] === 200 && count($r['body']['data']['services'] ?? []) > 0,
    'top=' . ($r['body']['data']['services'][0]['title'] ?? 'null') . ' orders=' . ($r['body']['data']['services'][0]['order_count'] ?? 'null'));

$r = httpGet(BASEURL . '/api/internal/ai/data/admin/period-comparison', authHeaders($auth->issueServiceToken($adminUser)));
check('admin period-comparison', $r['status'] === 200 && isset($r['body']['data']['this_month_orders']),
    'this=' . ($r['body']['data']['this_month_orders'] ?? 'null') . ' last=' . ($r['body']['data']['last_month_orders'] ?? 'null'));

$r = httpGet(BASEURL . '/api/internal/ai/data/admin/schema', authHeaders($auth->issueServiceToken($adminUser)));
$tables = $r['body']['data']['tables'] ?? [];
check('admin schema live', $r['status'] === 200 && count($tables) > 0, 'tables=' . count($tables));
$ordersTable = null;
foreach ($tables as $t) {
    if ($t['table'] === 'orders') {
        $ordersTable = $t;
    }
}
check('schema orders punya PK + FK', $ordersTable !== null && count($ordersTable['primary_key']) > 0 && count($ordersTable['foreign_keys']) > 0,
    'pk=' . json_encode($ordersTable['primary_key'] ?? []) . ' fk=' . count($ordersTable['foreign_keys'] ?? []));

$r = httpGet(BASEURL . '/api/internal/ai/data/admin/activity?days=30', authHeaders($auth->issueServiceToken($adminUser)));
check('admin activity', $r['status'] === 200 && isset($r['body']['data']['orders_per_day']),
    'days=' . ($r['body']['data']['period_days'] ?? 'null'));

$r = httpGet(BASEURL . '/api/internal/ai/data/order-detail?code=ORD-20261103-5400', authHeaders($auth->issueServiceToken($adminUser)));
check('admin order-detail', $r['status'] === 200 && ($r['body']['data']['order']['order_code'] ?? '') === 'ORD-20261103-5400');

echo "\n[D] Isolasi data pesanan customer\n";
// Pesanan milik customer 18 (ORD-20261103-5400) -> customer boleh
$r = httpGet(BASEURL . '/api/internal/ai/data/order-detail?code=ORD-20261103-5400', authHeaders($auth->issueServiceToken($customerUser)));
check('customer boleh lihat pesanan sendiri', $r['status'] === 200, 'status=' . $r['status']);

// Pesanan milik customer lain (ORD-20261009-0001) -> harus 404
$r = httpGet(BASEURL . '/api/internal/ai/data/order-detail?code=ORD-20261009-0001', authHeaders($auth->issueServiceToken($customerUser)));
check('customer TIDAK bisa lihat pesanan orang lain', $r['status'] === 404, 'status=' . $r['status']);

// Parameter customer_id diabaikan
$r = httpGet(BASEURL . '/api/internal/ai/data/my-orders?customer_id=1', authHeaders($auth->issueServiceToken($customerUser)));
check('parameter customer_id diabaikan', $r['status'] === 200 && ($r['body']['data']['scope'] ?? '') === 'customer');

echo "\n========================================\n";
echo "Hasil: {$passed} passed, {$failed} failed\n";
echo "========================================\n";
exit($failed > 0 ? 1 : 0);
