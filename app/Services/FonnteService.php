<?php

/**
 * Fonnte WhatsApp API sender.
 *
 * Official documentation:
 *   POST https://api.fonnte.com/send
 *   Authorization: <FONNTE_API_TOKEN>   (no "Bearer" prefix)
 *
 * Success is only reported when Fonnte returns a 2xx HTTP status with
 * `status: true` and a message id. The token is read from the environment and
 * is never returned to controllers, views, JSON responses, or logs.
 */
class FonnteService
{
    private $token;
    private $endpoint;
    private $transport;

    /**
     * @param string|null $token      Fonnte token. Null = use FONNTE_API_TOKEN.
     * @param string|null $endpoint   Fonnte endpoint. Null = use FONNTE_API_URL.
     * @param callable|null $transport Optional transport used by offline tests.
     *        Receives ($endpoint, $headers, $payload) and must return
     *        ['http_code' => int, 'body' => string].
     */
    public function __construct($token = null, $endpoint = null, callable $transport = null)
    {
        $this->token = trim((string) ($token ?? (defined('FONNTE_API_TOKEN') ? FONNTE_API_TOKEN : '')));

        $endpoint = $endpoint ?? (defined('FONNTE_API_URL') ? FONNTE_API_URL : 'https://api.fonnte.com/send');
        $endpoint = rtrim(trim((string) $endpoint), '/');

        // Never send a token to a non-HTTPS or unexpected endpoint.
        if (!preg_match('#^https://api\.fonnte\.com/send$#i', $endpoint)) {
            $endpoint = 'https://api.fonnte.com/send';
        }

        $this->endpoint = $endpoint;
        $this->transport = $transport;
    }

    public function isConfigured()
    {
        // A token containing CR/LF could inject HTTP headers.
        return $this->token !== '' && preg_match('/[\r\n]/', $this->token) !== 1;
    }

    public function getEndpoint()
    {
        return $this->endpoint;
    }

    public function buildMessage($code)
    {
        $expiry = defined('OTP_EXPIRY_MINUTES') ? (int) OTP_EXPIRY_MINUTES : 5;

        return "Kode OTP TKJ Service Hub Anda: {$code}\n"
            . "Berlaku {$expiry} menit dan hanya dapat digunakan sekali.\n"
            . 'JANGAN bagikan kode ini kepada siapa pun.';
    }

    /**
     * Build the documented Fonnte payload. Public so tests can inspect it.
     *
     * `connectOnly` is true so Fonnte rejects the request when the WhatsApp
     * device is disconnected instead of silently queueing the message.
     */
    public function buildPayload($to, $code)
    {
        return [
            'target'      => (string) $to,
            'message'     => $this->buildMessage($code),
            'countryCode' => '62',
            'connectOnly' => true,
            'preview'     => false,
        ];
    }

    /**
     * Interpret a Fonnte HTTP response without contacting the network.
     * Used by sendOtp() and by offline tests.
     */
    public function interpretResponse($httpCode, $response)
    {
        $httpCode = (int) $httpCode;
        $decoded = $this->decode($response);

        if ($decoded === null) {
            return [
                'success'    => false,
                'error_code' => 'invalid_response',
                'http_code'  => $httpCode,
            ];
        }

        $status = $decoded['status'] ?? ($decoded['Status'] ?? false);
        $accepted = filter_var($status, FILTER_VALIDATE_BOOLEAN);
        $messageId = $this->extractMessageId($decoded);

        if ($httpCode >= 200 && $httpCode < 300 && $accepted && $messageId !== '') {
            return [
                'success'    => true,
                'message_id' => $messageId,
                'http_code'  => $httpCode,
            ];
        }

        return [
            'success'    => false,
            'error_code' => 'provider_error',
            'http_code'  => $httpCode,
        ];
    }

    /**
     * Send an OTP through Fonnte.
     *
     * Only an accepted/queued message is reported as success. Delivery to the
     * recipient still depends on the Fonnte device staying connected.
     */
    public function sendOtp($to, $code)
    {
        if (!$this->isConfigured()) {
            return [
                'success'    => false,
                'error_code' => 'not_configured',
                'http_code'  => 0,
            ];
        }

        if (!function_exists('curl_init') && $this->transport === null) {
            error_log('[Fonnte OTP] cURL extension is not available.');
            return [
                'success'    => false,
                'error_code' => 'network_error',
                'http_code'  => 0,
            ];
        }

        $payload = $this->buildPayload($to, $code);

        $request = $this->performRequest($payload);

        if (!empty($request['error'])) {
            error_log('[Fonnte OTP] Network error (cURL errno ' . $request['error'] . ').');
            return [
                'success'    => false,
                'error_code' => 'network_error',
                'http_code'  => $request['http_code'],
            ];
        }

        $result = $this->interpretResponse($request['http_code'], $request['body']);

        if (empty($result['success'])) {
            // Log only Fonnte's short reason, never the token or message body.
            $decoded = $this->decode($request['body']);
            $reason = is_array($decoded) ? (string) ($decoded['reason'] ?? 'unknown') : 'invalid json';
            error_log('[Fonnte OTP] Provider rejected message: HTTP ' . $request['http_code']
                . ' reason=' . substr($reason, 0, 80));
        }

        return $result;
    }

    /**
     * Build the payload for a plain text message.
     *
     * `connectOnly` is false so a notification is queued by Fonnte when the
     * device is temporarily disconnected, instead of being rejected.
     */
    public function buildTextPayload($to, $message)
    {
        return [
            'target'      => (string) $to,
            'message'     => (string) $message,
            'countryCode' => '62',
            'connectOnly' => false,
            'preview'     => false,
        ];
    }

    /**
     * Send a plain text message through Fonnte (e.g. admin notifications).
     *
     * Uses exactly the documented send endpoint and payload fields:
     *   POST https://api.fonnte.com/send
     *   Authorization: <TOKEN>
     *   target, message, countryCode, connectOnly, preview
     *
     * @return array { success, message_id, http_code, error_code }
     */
    public function sendText($to, $message)
    {
        if (!$this->isConfigured()) {
            return [
                'success'    => false,
                'error_code' => 'not_configured',
                'http_code'  => 0,
            ];
        }

        $target = trim((string) $to);
        if ($target === '') {
            return [
                'success'    => false,
                'error_code' => 'invalid_target',
                'http_code'  => 0,
            ];
        }

        if (!function_exists('curl_init') && $this->transport === null) {
            return [
                'success'    => false,
                'error_code' => 'network_error',
                'http_code'  => 0,
            ];
        }

        $payload = $this->buildTextPayload($target, $message);

        $request = $this->performRequest($payload);

        if (!empty($request['error'])) {
            // Never log the message body or the token.
            error_log('[Fonnte Notify] Network error (cURL errno ' . $request['error'] . ').');
            return [
                'success'    => false,
                'error_code' => 'network_error',
                'http_code'  => $request['http_code'],
            ];
        }

        $result = $this->interpretResponse($request['http_code'], $request['body']);

        if (empty($result['success'])) {
            $decoded = $this->decode($request['body']);
            $reason = is_array($decoded) ? (string) ($decoded['reason'] ?? 'unknown') : 'invalid json';
            error_log('[Fonnte Notify] Provider rejected message: HTTP ' . $request['http_code']
                . ' reason=' . substr($reason, 0, 80));
            $result['error_code'] = 'provider_error';
            $result['reason'] = substr($reason, 0, 120);
        }

        return $result;
    }

    /**
     * Perform the HTTP request against the Fonnte send endpoint.
     *
     * @return array { http_code, body, error }
     */
    private function performRequest(array $payload)
    {
        $headers = [
            'Authorization: ' . $this->token,
            'Accept: application/json',
        ];

        // Offline test transport: no network call, same interpretation path.
        if ($this->transport !== null) {
            $result = call_user_func($this->transport, $this->endpoint, $headers, $payload);
            if (!is_array($result)) {
                return ['http_code' => 0, 'body' => '', 'error' => 7];
            }
            return [
                'http_code' => (int) ($result['http_code'] ?? 0),
                'body'      => (string) ($result['body'] ?? ''),
                'error'     => (int) ($result['error'] ?? 0),
            ];
        }

        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, defined('FONNTE_TIMEOUT_SECONDS') ? (int) FONNTE_TIMEOUT_SECONDS : 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlErrno !== 0 || $response === false) {
            return ['http_code' => $httpCode, 'body' => '', 'error' => $curlErrno];
        }

        return ['http_code' => $httpCode, 'body' => (string) $response, 'error' => 0];
    }

    private function decode($response)
    {
        $decoded = json_decode((string) $response, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function extractMessageId(array $decoded)
    {
        $ids = $decoded['id'] ?? [];

        if (is_string($ids) || is_int($ids)) {
            $ids = [$ids];
        }

        if (!is_array($ids)) {
            return '';
        }

        foreach ($ids as $id) {
            if (is_scalar($id) && (string) $id !== '') {
                return (string) $id;
            }
        }

        return '';
    }
}
