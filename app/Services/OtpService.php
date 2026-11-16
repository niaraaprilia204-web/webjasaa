<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../Models/OtpVerification.php';
require_once __DIR__ . '/FonnteService.php';
require_once __DIR__ . '/WhatsAppService.php';

/**
 * OTP business rules:
 * - 6 random digits, 5-minute expiry, single use
 * - only a bcrypt hash is stored
 * - max 3 requests per phone per 15 minutes + per-IP cap
 * - max 5 verification attempts per code
 * - delivery is reported as successful only when the provider accepts the message
 */
class OtpService extends BaseService
{
    private $model;
    private $sender;

    /**
     * @param object|null $sender Injectable transport (tests use a fake sender;
     *                             production uses FonnteService by default).
     */
    public function __construct($sender = null, $model = null)
    {
        $this->sender = $sender ?: $this->createSender();
        $this->model = $model ?: new OtpVerification();
    }

    private function createSender()
    {
        $provider = defined('WHATSAPP_PROVIDER') ? WHATSAPP_PROVIDER : 'fonnte';

        return $provider === 'meta_cloud' ? new WhatsAppService() : new FonnteService();
    }

    public function isWhatsAppConfigured()
    {
        return method_exists($this->sender, 'isConfigured') ? (bool) $this->sender->isConfigured() : true;
    }

    /**
     * Normalize Indonesian mobile numbers to WhatsApp/E.164 digits (628xxxxxxxxx).
     * Returns null when the number is not a valid Indonesian mobile number.
     */
    public static function normalizePhone($rawPhone)
    {
        $rawPhone = trim((string) $rawPhone);

        if ($rawPhone === '' || strlen($rawPhone) > 25) {
            return null;
        }

        // Only allow common phone formatting characters.
        if (!preg_match('/^[0-9+\-\s().]+$/', $rawPhone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $rawPhone);

        if (strpos($digits, '0') === 0) {
            $digits = '62' . substr($digits, 1);
        } elseif (strpos($digits, '8') === 0) {
            $digits = '62' . $digits;
        }

        // Indonesian mobile: 628 + 7..12 digits (10..15 digits total).
        if (!preg_match('/^628[0-9]{7,12}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    public static function maskPhone($phone)
    {
        $phone = (string) $phone;

        if (strlen($phone) < 8) {
            return '****';
        }

        return '+' . substr($phone, 0, 4) . '-****-' . substr($phone, -3);
    }

    public function requestOtp($rawPhone, $userId = null, $ip = '')
    {
        try {
            $phone = self::normalizePhone($rawPhone);
            if ($phone === null) {
                return $this->fail(
                    'invalid_phone',
                    'Format nomor WhatsApp tidak valid. Gunakan format 08xxxxxxxxxx atau 628xxxxxxxxxx.'
                );
            }

            if (!$this->isWhatsAppConfigured()) {
                return $this->fail(
                    'not_configured',
                    'Layanan pengiriman WhatsApp belum dikonfigurasi. Silakan hubungi operator TKJ Service Hub.'
                );
            }

            $window = (int) OTP_REQUEST_WINDOW_MINUTES;
            $maxRequests = (int) OTP_MAX_REQUESTS;

            if ($this->model->countRecentByPhone($phone, $window) >= $maxRequests) {
                $retryAfter = $this->model->retryAfterForPhone($phone, $window);
                return $this->fail(
                    'rate_limited',
                    'Terlalu banyak permintaan OTP untuk nomor ini. Coba lagi dalam '
                        . $this->humanDuration($retryAfter) . '.',
                    ['retry_after' => $retryAfter]
                );
            }

            if (!empty($ip) && $this->model->countRecentByIp($ip, $window) >= (int) OTP_MAX_REQUESTS_PER_IP) {
                return $this->fail(
                    'rate_limited',
                    'Terlalu banyak permintaan OTP dari jaringan ini. Coba lagi nanti.',
                    ['retry_after' => $window * 60]
                );
            }

            $this->model->cleanupOld();
            $this->model->invalidateActiveByPhone($phone);

            $otp = (string) random_int(100000, 999999);
            $otpHash = password_hash($otp, PASSWORD_BCRYPT);

            $otpId = $this->model->createOtp([
                'user_id'        => $userId ? (int) $userId : null,
                'phone'          => $phone,
                'purpose'        => 'phone_verification',
                'otp_hash'       => $otpHash,
                'max_attempts'   => (int) OTP_MAX_VERIFY_ATTEMPTS,
                'request_ip'     => substr((string) $ip, 0, 45) ?: null,
                'expiry_minutes' => (int) OTP_EXPIRY_MINUTES,
            ]);

            if (!$otpId) {
                error_log('[OTP] Failed to persist a new OTP row.');
                return $this->fail('server_error', 'Terjadi kesalahan pada server. Silakan coba lagi.');
            }

            try {
                $send = $this->sender->sendOtp($phone, $otp);
            } catch (\Throwable $e) {
                // Never log the exception message: it may contain provider details.
                $send = ['success' => false, 'error_code' => 'network_error'];
            }

            if (empty($send['success'])) {
                // The code must not remain usable when delivery failed.
                $this->model->consume($otpId);

                error_log('[OTP] WhatsApp delivery failed; verification code invalidated. error_code='
                    . substr((string) ($send['error_code'] ?? 'unknown'), 0, 40));

                return $this->fail(
                    'send_failed',
                    'Kode OTP tidak dapat dikirim melalui WhatsApp saat ini. '
                        . 'Silakan coba lagi nanti atau hubungi operator.',
                    ['retry_after' => (int) OTP_RESEND_COOLDOWN_SECONDS]
                );
            }

            if (!empty($send['message_id'])) {
                $this->model->markProviderMessage($otpId, $send['message_id']);
            }

            return $this->response(true, 'Kode OTP telah dikirim ke WhatsApp ' . self::maskPhone($phone) . '.', [
                'masked_phone' => self::maskPhone($phone),
                'expires_in'   => (int) OTP_EXPIRY_MINUTES * 60,
                'resend_after' => (int) OTP_RESEND_COOLDOWN_SECONDS,
            ]);
        } catch (\Throwable $e) {
            error_log('[OTP] Request failed due to an unexpected server/database error.');
            return $this->fail('server_error', 'Terjadi kesalahan pada server. Silakan coba lagi.');
        }
    }

    public function verifyOtp($rawPhone, $code, $userId = null)
    {
        try {
            $phone = self::normalizePhone($rawPhone);
            if ($phone === null) {
                return $this->fail(
                    'invalid_phone',
                    'Format nomor WhatsApp tidak valid. Gunakan format 08xxxxxxxxxx atau 628xxxxxxxxxx.'
                );
            }

            $code = preg_replace('/\s+/', '', trim((string) $code));
            if (!preg_match('/^\d{6}$/', $code)) {
                return $this->fail('invalid_code', 'Masukkan kode OTP 6 digit.');
            }

            $row = $this->model->latestActiveByPhone($phone);
            if (!$row) {
                return $this->fail(
                    'invalid_code',
                    'Kode OTP tidak valid atau sudah kedaluwarsa. Silakan minta kode baru.'
                );
            }

            // A code requested for one account may not be consumed by another account.
            if (!empty($row['user_id']) && (int) $row['user_id'] !== (int) $userId) {
                return $this->fail(
                    'invalid_code',
                    'Kode OTP tidak valid atau sudah kedaluwarsa. Silakan minta kode baru.'
                );
            }

            $attempts = (int) ($row['attempts'] ?? 0);
            $maxAttempts = max(1, (int) ($row['max_attempts'] ?? OTP_MAX_VERIFY_ATTEMPTS));

            if ($attempts >= $maxAttempts) {
                $this->model->consume($row['id']);
                return $this->fail(
                    'too_many_attempts',
                    'Batas percobaan verifikasi telah tercapai. Silakan minta kode OTP baru.'
                );
            }

            if (!password_verify($code, $row['otp_hash'])) {
                $this->model->incrementAttempts($row['id']);
                $remaining = max(0, $maxAttempts - ($attempts + 1));

                if ($remaining <= 0) {
                    $this->model->consume($row['id']);
                    return $this->fail(
                        'too_many_attempts',
                        'Batas percobaan verifikasi telah tercapai. Silakan minta kode OTP baru.',
                        ['remaining_attempts' => 0]
                    );
                }

                return $this->fail(
                    'invalid_code',
                    'Kode OTP salah. Sisa percobaan: ' . $remaining . '.',
                    ['remaining_attempts' => $remaining]
                );
            }

            $linked = $userId !== null && (int) $userId > 0;
            $consumed = $this->model->consumeAndLinkUser($row['id'], $linked ? (int) $userId : null, $phone);

            if (!$consumed) {
                return $this->fail(
                    'invalid_code',
                    'Kode OTP tidak valid atau sudah digunakan. Silakan minta kode baru.'
                );
            }

            return $this->response(
                true,
                'Nomor WhatsApp ' . self::maskPhone($phone) . ' berhasil diverifikasi.',
                [
                    'masked_phone' => self::maskPhone($phone),
                    'linked'       => $linked,
                ]
            );
        } catch (\Throwable $e) {
            error_log('[OTP] Verification failed due to an unexpected server/database error.');
            return $this->fail('server_error', 'Terjadi kesalahan pada server. Silakan coba lagi.');
        }
    }

    private function fail($errorCode, $message, $data = [])
    {
        return [
            'success'    => false,
            'error_code' => $errorCode,
            'message'    => $message,
            'data'       => $data,
        ];
    }

    private function humanDuration($seconds)
    {
        $seconds = max(1, (int) $seconds);

        if ($seconds < 60) {
            return $seconds . ' detik';
        }

        return (int) ceil($seconds / 60) . ' menit';
    }
}
