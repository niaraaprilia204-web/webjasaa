<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/AiNonceStore.php';

/**
 * HMAC token service for the PHP <-> Node.js AI bridge.
 *
 * Tokens are short-lived, audience-bound, and carry only the authenticated
 * user id + role. They are never rendered to the browser and never stored in
 * the database.
 */
class AiInternalAuthService extends BaseService
{
    private const VERSION = 1;
    private const ISSUER_PHP = 'php-web';
    private const ISSUER_NODE = 'chatbot-node';
    private const AUDIENCE_NODE = 'node-chatbot';
    private const AUDIENCE_PHP = 'php-internal';
    private const MAX_TOKEN_LENGTH = 8192;
    private const CLOCK_SKEW_SECONDS = 15;
    private const MIN_SECRET_LENGTH = 32;

    private $secret;
    private $nonceStore;

    public function __construct()
    {
        $this->secret = defined('AI_INTERNAL_SECRET') ? (string) AI_INTERNAL_SECRET : '';
        $this->nonceStore = new AiNonceStore();
    }

    public function isConfigured()
    {
        return strlen($this->secret) >= self::MIN_SECRET_LENGTH;
    }

    /**
     * Create the short-lived token PHP sends to Node.js after a browser request
     * has already passed PHP session + role validation.
     */
    public function issueHandoffToken(array $user)
    {
        return $this->issueToken(
            self::ISSUER_PHP,
            self::AUDIENCE_NODE,
            $user,
            $this->ttl()
        );
    }

    /**
     * Create a service token for Node -> PHP calls. Node does this in
     * production; exposing it in PHP keeps the contract testable.
     */
    public function issueServiceToken(array $user, $ttl = 60)
    {
        return $this->issueToken(
            self::ISSUER_NODE,
            self::AUDIENCE_PHP,
            $user,
            $ttl
        );
    }

    /**
     * Verify a token minted by PHP for Node.js.
     */
    public function verifyHandoffToken($token, $consumeNonce = false)
    {
        return $this->verifyToken(
            $token,
            self::ISSUER_PHP,
            self::AUDIENCE_NODE,
            (bool) $consumeNonce
        );
    }

    /**
     * Verify a Node.js service token on a PHP internal endpoint.
     */
    public function verifyServiceToken($token, $consumeNonce = true)
    {
        return $this->verifyToken(
            $token,
            self::ISSUER_NODE,
            self::AUDIENCE_PHP,
            (bool) $consumeNonce
        );
    }

    /**
     * Authenticate the current PHP request using a Node.js service token.
     * Returns normalized claims or throws RuntimeException with an HTTP-like
     * code (401/403/503).
     */
    public function authenticateRequest()
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Konfigurasi autentikasi internal AI belum lengkap.', 503);
        }

        $token = $this->requestToken();
        if ($token === '') {
            throw new RuntimeException('Token autentikasi internal tidak ditemukan.', 401);
        }

        return $this->verifyServiceToken($token, true);
    }

    private function issueToken($issuer, $audience, array $user, $ttl)
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('AI_INTERNAL_SECRET belum dikonfigurasi minimal 32 karakter.', 503);
        }

        $userId = (int) ($user['id'] ?? 0);
        $role = strtolower(trim((string) ($user['role'] ?? '')));
        if ($userId < 1 || !in_array($role, ['customer', 'admin'], true)) {
            throw new RuntimeException('Hanya customer dan admin yang dapat memakai token AI internal.', 403);
        }

        $now = time();
        $ttl = (int) $ttl;
        if ($ttl < 30) {
            $ttl = 30;
        }
        if ($ttl > 300) {
            $ttl = 300;
        }

        $payload = [
            'v' => self::VERSION,
            'iss' => $issuer,
            'aud' => $audience,
            'sub' => (string) $userId,
            'role' => $role,
            'scope' => $role,
            // Identitas diambil dari session autentikasi PHP yang sudah ada.
            // Ditandatangani HMAC, jadi Node.js tidak pernah harus menebak
            // identitas pengguna dari isi pesan percakapan.
            'name' => $this->safeIdentityClaim($user['name'] ?? ''),
            'email' => $this->safeIdentityClaim($user['email'] ?? ''),
            'username' => $this->safeIdentityClaim($user['username'] ?? ''),
            'iat' => $now,
            'exp' => $now + $ttl,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new RuntimeException('Gagal membuat payload token internal.', 500);
        }

        $encodedPayload = $this->base64UrlEncode($json);
        $signature = hash_hmac('sha256', $encodedPayload, $this->secret, true);

        return $encodedPayload . '.' . $this->base64UrlEncode($signature);
    }

    private function verifyToken($token, $expectedIssuer, $expectedAudience, $consumeNonce)
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Konfigurasi autentikasi internal AI belum lengkap.', 503);
        }

        $token = trim((string) $token);
        if ($token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            throw new RuntimeException('Token internal tidak valid.', 401);
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new RuntimeException('Format token internal tidak valid.', 401);
        }

        $encodedPayload = $parts[0];
        $providedSignature = $this->base64UrlDecode($parts[1]);
        if ($providedSignature === false || strlen($providedSignature) !== 32) {
            throw new RuntimeException('Tanda tangan token internal tidak valid.', 401);
        }

        $expectedSignature = hash_hmac('sha256', $encodedPayload, $this->secret, true);
        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new RuntimeException('Tanda tangan token internal tidak cocok.', 401);
        }

        $payloadJson = $this->base64UrlDecode($encodedPayload);
        if ($payloadJson === false) {
            throw new RuntimeException('Payload token internal tidak valid.', 401);
        }

        try {
            $claims = json_decode($payloadJson, true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new RuntimeException('Payload token internal bukan JSON yang valid.', 401);
        }

        if (!is_array($claims)) {
            throw new RuntimeException('Payload token internal tidak valid.', 401);
        }

        if ((int) ($claims['v'] ?? 0) !== self::VERSION
            || ($claims['iss'] ?? '') !== $expectedIssuer
            || ($claims['aud'] ?? '') !== $expectedAudience
        ) {
            throw new RuntimeException('Penerbit atau tujuan token internal tidak sesuai.', 401);
        }

        $role = strtolower((string) ($claims['role'] ?? ''));
        $scope = strtolower((string) ($claims['scope'] ?? ''));
        $subject = (string) ($claims['sub'] ?? '');
        $nonce = (string) ($claims['nonce'] ?? '');
        $issuedAt = (int) ($claims['iat'] ?? 0);
        $expiresAt = (int) ($claims['exp'] ?? 0);
        $now = time();

        if (!preg_match('/^[1-9][0-9]{0,19}$/', $subject)) {
            throw new RuntimeException('Subjek token internal tidak valid.', 401);
        }
        if (!in_array($role, ['customer', 'admin'], true) || $scope !== $role) {
            throw new RuntimeException('Role token internal tidak diizinkan.', 403);
        }
        if (!preg_match('/^[a-f0-9]{32,128}$/', $nonce)) {
            throw new RuntimeException('Nonce token internal tidak valid.', 401);
        }
        if ($issuedAt < 1 || $expiresAt <= $issuedAt) {
            throw new RuntimeException('Masa berlaku token internal tidak valid.', 401);
        }
        if ($issuedAt > $now + self::CLOCK_SKEW_SECONDS) {
            throw new RuntimeException('Token internal diterbitkan pada waktu yang tidak valid.', 401);
        }
        if ($expiresAt < $now - self::CLOCK_SKEW_SECONDS) {
            throw new RuntimeException('Token internal sudah kedaluwarsa.', 401);
        }

        if ($consumeNonce && !$this->nonceStore->consume($expectedIssuer, $nonce, $expiresAt)) {
            throw new RuntimeException('Token internal sudah pernah digunakan.', 401);
        }

        return [
            'user_id' => (int) $subject,
            'role' => $role,
            'scope' => $scope,
            'name' => $this->safeIdentityClaim($claims['name'] ?? ''),
            'email' => $this->safeIdentityClaim($claims['email'] ?? ''),
            'username' => $this->safeIdentityClaim($claims['username'] ?? ''),
            'issuer' => $expectedIssuer,
            'audience' => $expectedAudience,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Normalize an identity claim so it is safe to embed in a signed token
     * and to display in a prompt: printable characters only, limited length.
     */
    private function safeIdentityClaim($value)
    {
        if (!is_string($value)) {
            return '';
        }

        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        if (!is_string($clean)) {
            return '';
        }

        $clean = trim($clean);

        return mb_substr($clean, 0, 160);
    }

    private function requestToken()
    {
        $authorization = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (is_string($authorization) && preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            return trim($matches[1]);
        }

        $headerToken = $_SERVER['HTTP_X_AI_TOKEN'] ?? '';
        if (is_string($headerToken) && trim($headerToken) !== '') {
            return trim($headerToken);
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strcasecmp((string) $name, 'Authorization') === 0
                        && preg_match('/^Bearer\s+(.+)$/i', trim((string) $value), $matches)
                    ) {
                        return trim($matches[1]);
                    }
                    if (strcasecmp((string) $name, 'X-AI-Token') === 0) {
                        return trim((string) $value);
                    }
                }
            }
        }

        return '';
    }

    private function ttl()
    {
        $ttl = defined('AI_INTERNAL_TOKEN_TTL') ? (int) AI_INTERNAL_TOKEN_TTL : 90;
        if ($ttl < 30) {
            $ttl = 30;
        }
        if ($ttl > 300) {
            $ttl = 300;
        }
        return $ttl;
    }

    private function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode($value)
    {
        $value = (string) $value;
        if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            return false;
        }

        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
