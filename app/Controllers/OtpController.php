<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/OtpService.php';
require_once __DIR__ . '/../Models/User.php';

/**
 * WhatsApp OTP verification page and JSON/form endpoints.
 *
 * URLs:
 *   GET  /otp
 *   POST /otp/send
 *   POST /otp/verify
 */
class OtpController extends BaseController
{
    private $otpService;

    public function __construct()
    {
        parent::__construct();
        $this->otpService = new OtpService();
    }

    public function index()
    {
        $this->enforceHttps();
        $this->securityHeaders();

        $user = $_SESSION['user'] ?? null;
        $phone = '';
        $verifiedAt = null;

        if (!empty($_SESSION['otp_pending_phone'])) {
            $phone = (string) $_SESSION['otp_pending_phone'];
        }

        if (!empty($user['id'])) {
            try {
                $userRow = (new User())->find((int) $user['id']);
                if ($userRow) {
                    if ($phone === '') {
                        $phone = (string) ($userRow['phone'] ?? '');
                    }
                    $verifiedAt = $userRow['phone_verified_at'] ?? null;
                }
            } catch (\Throwable $e) {
                // The page must still render if the optional verification column
                // has not been migrated yet; the endpoint will report the error.
            }
        }

        $data = [
            'title'                => 'Verifikasi WhatsApp - TKJ Service Hub',
            'activeMenu'           => 'otp',
            'csrfToken'            => $this->csrfToken(),
            'phone'                => $phone,
            'verifiedAt'           => $verifiedAt,
            'otpSent'              => !empty($_SESSION['otp_sent']),
            'isLoggedIn'           => !empty($user['id']),
            'userName'             => $user['name'] ?? '',
            'whatsappConfigured'   => $this->otpService->isWhatsAppConfigured(),
            'expiryMinutes'        => (int) OTP_EXPIRY_MINUTES,
            'maxRequests'          => (int) OTP_MAX_REQUESTS,
            'requestWindowMinutes' => (int) OTP_REQUEST_WINDOW_MINUTES,
        ];

        $this->renderLayout('otp/index', $data);
    }

    public function send()
    {
        $this->enforceHttps();
        $this->securityHeaders();

        if (!$this->isPost()) {
            $this->respondWithError('method_not_allowed', 'Metode permintaan tidak valid.', 405);
        }

        if (!$this->validateCsrf()) {
            $this->respondWithError('csrf_failed', 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 403);
        }

        if ($this->honeypotTriggered()) {
            $this->respondWithError('invalid_request', 'Permintaan tidak valid.', 422);
        }

        $phone = (string) $this->input('phone', '');
        $userId = $_SESSION['user']['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $result = $this->otpService->requestOtp($phone, $userId, $ip);

        if (!empty($result['success'])) {
            $normalized = OtpService::normalizePhone($phone);
            if ($normalized !== null) {
                $_SESSION['otp_pending_phone'] = $normalized;
            }
            $_SESSION['otp_sent'] = true;
        } else {
            unset($_SESSION['otp_sent']);
        }

        $this->respond($result);
    }

    public function verify()
    {
        $this->enforceHttps();
        $this->securityHeaders();

        if (!$this->isPost()) {
            $this->respondWithError('method_not_allowed', 'Metode permintaan tidak valid.', 405);
        }

        if (!$this->validateCsrf()) {
            $this->respondWithError('csrf_failed', 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 403);
        }

        if ($this->honeypotTriggered()) {
            $this->respondWithError('invalid_request', 'Permintaan tidak valid.', 422);
        }

        $phone = (string) $this->input('phone', '');
        $code = (string) $this->input('code', '');
        $userId = $_SESSION['user']['id'] ?? null;

        $result = $this->otpService->verifyOtp($phone, $code, $userId);

        if (!empty($result['success']) && !empty($_SESSION['user']['id'])) {
            $normalized = OtpService::normalizePhone($phone);
            if ($normalized !== null) {
                $_SESSION['user']['phone'] = $normalized;
            }
        }

        if (!empty($result['success'])) {
            unset($_SESSION['otp_sent'], $_SESSION['otp_pending_phone']);
        }

        $this->respond($result);
    }

    private function respond($result, $successStatus = 200)
    {
        $success = !empty($result['success']);
        $message = (string) ($result['message'] ?? 'Permintaan tidak dapat diproses.');
        $errorCode = $result['error_code'] ?? null;
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        if ($this->isJsonRequest()) {
            $status = $success ? $successStatus : $this->statusForError($errorCode);

            if (!empty($data['retry_after']) && is_numeric($data['retry_after'])) {
                header('Retry-After: ' . max(1, (int) $data['retry_after']));
            }

            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'    => $success,
                'message'    => $message,
                'error_code' => $errorCode,
                'data'       => $data,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        Flasher::setMessage($message, '', $success ? 'success' : 'danger');
        header('Location: ' . BASEURL . '/otp');
        exit;
    }

    private function respondWithError($errorCode, $message, $status)
    {
        if ($this->isJsonRequest()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'    => false,
                'message'    => $message,
                'error_code' => $errorCode,
                'data'       => [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        Flasher::setMessage($message, '', 'danger');
        header('Location: ' . BASEURL . '/otp');
        exit;
    }

    private function statusForError($errorCode)
    {
        switch ($errorCode) {
            case 'csrf_failed':
                return 403;
            case 'method_not_allowed':
                return 405;
            case 'rate_limited':
            case 'too_many_attempts':
                return 429;
            case 'invalid_phone':
            case 'invalid_code':
            case 'invalid_request':
                return 422;
            case 'not_configured':
            case 'send_failed':
                return 502;
            case 'server_error':
                return 500;
            default:
                return 400;
        }
    }

    private function isPost()
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    private function isJsonRequest()
    {
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return strcasecmp($requestedWith, 'XMLHttpRequest') === 0
            || stripos($accept, 'application/json') !== false;
    }

    private function csrfToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['otp_csrf']) || !is_string($_SESSION['otp_csrf'])) {
            $_SESSION['otp_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['otp_csrf'];
    }

    private function validateCsrf()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected = $_SESSION['otp_csrf'] ?? '';

        return is_string($submitted)
            && is_string($expected)
            && $expected !== ''
            && hash_equals($expected, $submitted);
    }

    private function honeypotTriggered()
    {
        return trim((string) $this->input('website', '')) !== '';
    }

    private function input($key, $default = '')
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }

        static $jsonInput = null;
        if ($jsonInput === null) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string) $raw, true);
            $jsonInput = is_array($decoded) ? $decoded : [];
        }

        return $jsonInput[$key] ?? $default;
    }

    private function securityHeaders()
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        if (defined('APP_ENV') && APP_ENV === 'production' && $this->isHttpsRequest()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private function isHttpsRequest()
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (
                defined('APP_TRUST_PROXY_HEADERS')
                && APP_TRUST_PROXY_HEADERS
                && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            );
    }

    /**
     * Refuse OTP traffic over plain HTTP in production.
     * Set APP_ENV=production and terminate TLS at Apache/nginx or a trusted proxy.
     */
    private function enforceHttps()
    {
        if (!defined('APP_ENV') || APP_ENV !== 'production') {
            return;
        }

        $isHttps = $this->isHttpsRequest();

        if (!$isHttps) {
            if ($this->isJsonRequest()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success'    => false,
                    'message'    => 'Koneksi aman (HTTPS) diperlukan untuk verifikasi OTP.',
                    'error_code' => 'https_required',
                    'data'       => [],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>HTTPS Diperlukan</title></head>'
                . '<body style="font-family:system-ui,Arial,sans-serif;padding:2rem;">'
                . '<h1>HTTPS Diperlukan</h1>'
                . '<p>Verifikasi OTP hanya tersedia melalui koneksi aman (HTTPS) pada lingkungan produksi.</p>'
                . '</body></html>';
            exit;
        }
    }
}
