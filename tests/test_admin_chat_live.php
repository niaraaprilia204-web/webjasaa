<?php

/**
 * Uji wajib chatbot Admin setelah OpenRouter aktif.
 * Run: php tests/test_admin_chat_live.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Services/AiInternalAuthService.php';
require_once __DIR__ . '/../app/Services/AiBridgeService.php';

$auth = new AiInternalAuthService();
$adminUser = ['id' => 15, 'role' => 'admin'];

function httpPost($url, array $headers, $bodyJson)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
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
    return ['status' => $status, 'body' => $body === false ? '' : (string) $body];
}

function authHeaders($token)
{
    return ['Authorization: Bearer ' . $token, 'X-AI-Token: ' . $token];
}

$tests = [
    'Siapa kamu?',
    'Berapa jumlah customer di database?',
];

foreach ($tests as $i => $q) {
    echo "\n[Uji " . ($i + 1) . "] Pertanyaan: {$q}\n";

    // Jalur A: langsung Node.js /api/chat
    $token = $auth->issueHandoffToken($adminUser);
    $resp = httpPost('http://127.0.0.1:3001/api/chat', authHeaders($token), json_encode([
        'query' => $q, 'history' => [],
    ]));
    $body = json_decode($resp['body'], true);
    echo "  [Node langsung] HTTP {$resp['status']}\n";
    if (($body['success'] ?? false) === true) {
        echo "  Jawaban: " . substr((string) ($body['data']['reply'] ?? ''), 0, 400) . "\n";
        echo "  model=" . ($body['data']['model'] ?? '-') . ", toolUsed=" . var_export($body['data']['toolUsed'] ?? null, true) . "\n";
    } else {
        echo "  GAGAL: " . substr($resp['body'], 0, 300) . "\n";
    }

    // Jalur B: bridge PHP (AiBridgeService::forwardChat) — sama dengan /ai/chat/node
    $resp2 = httpPost('http://127.0.0.1:3001/api/chat', authHeaders($auth->issueHandoffToken($adminUser)), json_encode([
        'query' => $q, 'history' => [],
    ]));
    $body2 = json_decode($resp2['body'], true);
    echo "  [Node ulang] HTTP {$resp2['status']}: " . ((($body2['success'] ?? false) === true) ? 'OK' : substr($resp2['body'], 0, 200)) . "\n";
}

echo "\nSelesai.\n";
