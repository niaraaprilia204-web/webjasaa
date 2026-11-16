<?php

/**
 * TKJ Service Hub - Configuration File
 */

// Application Identity
define('APP_NAME', 'TKJ Service Hub');
define('APP_TAGLINE', 'Layanan Teknis & Solusi IT Unit Bisnis Siswa TKJ');
define('APP_VERSION', '1.0.0');

// Database Configuration
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tkj_service_hub');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

// Auto-load .env file if present in project root
$envPath = dirname(__DIR__, 2) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), '"\'');
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Application Environment (set APP_ENV=production on the live server)
$appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'local');
define('APP_ENV', strtolower(trim($appEnv)) ?: 'local');

// Public application URL (override with APP_URL in production, e.g. https://tkj.example.sch.id)
$appUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? 'http://localhost/webjasa');
if (!is_string($appUrl) || !preg_match('#^https?://#i', $appUrl)) {
    $appUrl = 'http://localhost/webjasa';
}
define('BASEURL', rtrim($appUrl, '/'));

// Align PHP date handling with the MySQL server's local timezone used by the app.
$appTimezone = getenv('APP_TIMEZONE') ?: ($_ENV['APP_TIMEZONE'] ?? 'Asia/Jakarta');
if (!is_string($appTimezone) || !in_array($appTimezone, timezone_identifiers_list(), true)) {
    $appTimezone = 'Asia/Jakarta';
}
date_default_timezone_set($appTimezone);

// Only trust X-Forwarded-Proto when the app is behind a trusted reverse proxy
// that overwrites the header. Otherwise a client could spoof HTTPS detection.
define(
    'APP_TRUST_PROXY_HEADERS',
    filter_var(getenv('APP_TRUST_PROXY_HEADERS') ?: ($_ENV['APP_TRUST_PROXY_HEADERS'] ?? 'false'), FILTER_VALIDATE_BOOLEAN)
);

// AI Integration Configuration (Google Gemini / Local Knowledge Engine)
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ''));
define('AI_MODEL_NAME', getenv('GEMINI_MODEL') ?: (getenv('AI_MODEL_NAME') ?: ($_ENV['GEMINI_MODEL'] ?? 'gemini-1.5-flash')));
define('AI_ASSISTANT_NAME', 'TKJ AI Assistant');

// ============================================================
// Internal AI bridge (PHP <-> Node.js chatbot)
// ============================================================
// The secret is server-side only. It must be at least 32 characters and must
// never be rendered in HTML, JavaScript, database rows, or API responses.
define('AI_BRIDGE_URL', rtrim((string) (getenv('AI_BRIDGE_URL') ?: ($_ENV['AI_BRIDGE_URL'] ?? 'http://127.0.0.1:3001')), '/'));
define('AI_INTERNAL_SECRET', (string) (getenv('AI_INTERNAL_SECRET') ?: ($_ENV['AI_INTERNAL_SECRET'] ?? '')));

$aiInternalTtl = (int) (getenv('AI_INTERNAL_TOKEN_TTL') ?: ($_ENV['AI_INTERNAL_TOKEN_TTL'] ?? 90));
if ($aiInternalTtl < 30 || $aiInternalTtl > 300) {
    $aiInternalTtl = 90;
}
define('AI_INTERNAL_TOKEN_TTL', $aiInternalTtl);

// ============================================================
// WhatsApp OTP provider
// ============================================================
// "fonnte" (default) or "meta_cloud". Fonnte is the official gateway used by
// this installation; the Meta Cloud API path is retained as an optional fallback.
$waProvider = strtolower(trim((string) (getenv('WHATSAPP_PROVIDER') ?: ($_ENV['WHATSAPP_PROVIDER'] ?? 'fonnte'))));
if (!in_array($waProvider, ['fonnte', 'meta_cloud'], true)) {
    $waProvider = 'fonnte';
}
define('WHATSAPP_PROVIDER', $waProvider);

// Fonnte API token (from Fonnte dashboard -> Device). Server-side only.
// FONNTE_TOKEN is the preferred variable name; FONNTE_API_TOKEN is kept as a
// legacy fallback so existing OTP installations keep working unchanged.
$fonnteToken = '';
foreach (['FONNTE_TOKEN', 'FONNTE_API_TOKEN'] as $fonnteTokenKey) {
    $fonnteTokenValue = getenv($fonnteTokenKey);
    if ($fonnteTokenValue === false || trim((string) $fonnteTokenValue) === '') {
        $fonnteTokenValue = $_ENV[$fonnteTokenKey] ?? '';
    }
    $fonnteTokenValue = trim((string) $fonnteTokenValue);
    if ($fonnteTokenValue !== '') {
        $fonnteToken = $fonnteTokenValue;
        break;
    }
}
define('FONNTE_API_TOKEN', $fonnteToken);
define('FONNTE_TOKEN', $fonnteToken);
define('FONNTE_API_URL', 'https://api.fonnte.com/send');

// Timeout (seconds) for Fonnte HTTP requests made by the notification service.
$fonnteTimeout = (int) (getenv('FONNTE_TIMEOUT_SECONDS') ?: ($_ENV['FONNTE_TIMEOUT_SECONDS'] ?? '15'));
if ($fonnteTimeout < 5 || $fonnteTimeout > 120) {
    $fonnteTimeout = 15;
}
define('FONNTE_TIMEOUT_SECONDS', $fonnteTimeout);

// ============================================================
// WhatsApp admin notifications
// ============================================================
// Destination number for admin notifications (customer registered / order
// created). Only this configured number may ever receive a notification;
// the recipient can never be supplied by the customer or the browser.
define('ADMIN_WHATSAPP_NUMBER', trim((string) (getenv('ADMIN_WHATSAPP_NUMBER') ?: ($_ENV['ADMIN_WHATSAPP_NUMBER'] ?? ''))));

// Meta Cloud API credentials (optional fallback, used only when
// WHATSAPP_PROVIDER=meta_cloud).
define('WHATSAPP_API_TOKEN', getenv('WHATSAPP_API_TOKEN') ?: ($_ENV['WHATSAPP_API_TOKEN'] ?? ''));
define('WHATSAPP_PHONE_NUMBER_ID', getenv('WHATSAPP_PHONE_NUMBER_ID') ?: ($_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? ''));

$waApiVersion = getenv('WHATSAPP_API_VERSION') ?: ($_ENV['WHATSAPP_API_VERSION'] ?? 'v26.0');
if (!is_string($waApiVersion) || !preg_match('/^v\d+\.\d+$/', $waApiVersion)) {
    $waApiVersion = 'v26.0';
}
define('WHATSAPP_API_VERSION', $waApiVersion);

// Authentication templates are REQUIRED by Meta for business-initiated OTP messages.
// Leave empty only for local testing with an already-open 24-hour conversation window.
define('WHATSAPP_OTP_TEMPLATE_NAME', getenv('WHATSAPP_OTP_TEMPLATE_NAME') ?: ($_ENV['WHATSAPP_OTP_TEMPLATE_NAME'] ?? ''));
define('WHATSAPP_OTP_TEMPLATE_LANGUAGE', getenv('WHATSAPP_OTP_TEMPLATE_LANGUAGE') ?: ($_ENV['WHATSAPP_OTP_TEMPLATE_LANGUAGE'] ?? 'id'));
define(
    'WHATSAPP_OTP_TEMPLATE_BUTTON',
    filter_var(getenv('WHATSAPP_OTP_TEMPLATE_BUTTON') ?: ($_ENV['WHATSAPP_OTP_TEMPLATE_BUTTON'] ?? 'false'), FILTER_VALIDATE_BOOLEAN)
);

// OTP policy (kept server-side; never accepted from the browser)
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_MAX_REQUESTS', 3);
define('OTP_REQUEST_WINDOW_MINUTES', 15);
define('OTP_MAX_REQUESTS_PER_IP', 30);
define('OTP_MAX_VERIFY_ATTEMPTS', 5);
define('OTP_RESEND_COOLDOWN_SECONDS', 60);

