<?php

/**
 * Meta WhatsApp Cloud API sender.
 *
 * Official endpoint:
 *   POST https://graph.facebook.com/{version}/{phone-number-id}/messages
 * Authentication:
 *   Authorization: Bearer <WHATSAPP_API_TOKEN>
 *
 * The access token is read from the environment and is never returned to
 * controllers, views, JSON responses, or logs.
 */
class WhatsAppService
{
    private $token;
    private $phoneNumberId;
    private $apiVersion;
    private $templateName;
    private $templateLanguage;
    private $templateButton;

    public function __construct(
        $token = null,
        $phoneNumberId = null,
        $apiVersion = null,
        $templateName = null,
        $templateLanguage = null,
        $templateButton = null
    ) {
        $this->token = trim((string) ($token ?? (defined('WHATSAPP_API_TOKEN') ? WHATSAPP_API_TOKEN : '')));
        $this->phoneNumberId = trim((string) ($phoneNumberId ?? (defined('WHATSAPP_PHONE_NUMBER_ID') ? WHATSAPP_PHONE_NUMBER_ID : '')));

        $version = (string) ($apiVersion ?? (defined('WHATSAPP_API_VERSION') ? WHATSAPP_API_VERSION : 'v26.0'));
        $this->apiVersion = preg_match('/^v\d+\.\d+$/', $version) ? $version : 'v26.0';

        $this->templateName = trim((string) ($templateName ?? (defined('WHATSAPP_OTP_TEMPLATE_NAME') ? WHATSAPP_OTP_TEMPLATE_NAME : '')));

        $language = (string) ($templateLanguage ?? (defined('WHATSAPP_OTP_TEMPLATE_LANGUAGE') ? WHATSAPP_OTP_TEMPLATE_LANGUAGE : 'id'));
        $this->templateLanguage = preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', $language) ? $language : 'id';

        if ($templateButton === null) {
            $this->templateButton = defined('WHATSAPP_OTP_TEMPLATE_BUTTON') ? (bool) WHATSAPP_OTP_TEMPLATE_BUTTON : false;
        } else {
            $this->templateButton = (bool) $templateButton;
        }
    }

    public function isConfigured()
    {
        return $this->token !== '' && $this->phoneNumberId !== '';
    }

    public function getEndpoint()
    {
        return 'https://graph.facebook.com/' . $this->apiVersion . '/'
            . rawurlencode($this->phoneNumberId) . '/messages';
    }

    public function usesAuthenticationTemplate()
    {
        return $this->templateName !== '';
    }

    /**
     * Build the exact Cloud API payload. Public so it can be verified in tests.
     */
    public function buildPayload($to, $code)
    {
        $base = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
        ];

        if (!$this->usesAuthenticationTemplate()) {
            $expiry = defined('OTP_EXPIRY_MINUTES') ? (int) OTP_EXPIRY_MINUTES : 5;
            $base['type'] = 'text';
            $base['text'] = [
                'preview_url' => false,
                'body'        => "Kode OTP TKJ Service Hub Anda: {$code}. "
                    . "Berlaku {$expiry} menit dan hanya dapat digunakan sekali. "
                    . "JANGAN bagikan kode ini kepada siapa pun.",
            ];

            return $base;
        }

        $components = [
            [
                'type'       => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => (string) $code],
                ],
            ],
        ];

        if ($this->templateButton) {
            $components[] = [
                'type'       => 'button',
                'sub_type'   => 'url',
                'index'      => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => (string) $code],
                ],
            ];
        }

        $base['type'] = 'template';
        $base['template'] = [
            'name'       => $this->templateName,
            'language'   => ['code' => $this->templateLanguage],
            'components' => $components,
        ];

        return $base;
    }

    /**
     * Send an OTP through WhatsApp Cloud API.
     *
     * Success is only reported when Meta returns HTTP 200 with a message ID.
     * Everything else is a failure; no fake success is ever returned.
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

        if (!function_exists('curl_init')) {
            error_log('[WhatsApp OTP] cURL extension is not available.');
            return [
                'success'    => false,
                'error_code' => 'network_error',
                'http_code'  => 0,
            ];
        }

        $payload = $this->buildPayload($to, $code);
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonPayload === false) {
            error_log('[WhatsApp OTP] Failed to encode provider payload.');
            return [
                'success'    => false,
                'error_code' => 'invalid_response',
                'http_code'  => 0,
            ];
        }

        $ch = curl_init($this->getEndpoint());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlErrno !== 0 || $response === false) {
            error_log('[WhatsApp OTP] Network error (cURL errno ' . $curlErrno . ').');
            return [
                'success'    => false,
                'error_code' => 'network_error',
                'http_code'  => $httpCode,
            ];
        }

        $decoded = json_decode($response, true);
        $messageId = $decoded['messages'][0]['id'] ?? '';

        if ($httpCode === 200 && is_string($messageId) && $messageId !== '') {
            return [
                'success'    => true,
                'message_id' => $messageId,
                'http_code'  => $httpCode,
            ];
        }

        // Log only Meta error identifiers, never the token or message body.
        $errType = is_array($decoded) ? ($decoded['error']['type'] ?? 'unknown') : 'unknown';
        $errCode = is_array($decoded) ? ($decoded['error']['code'] ?? 'unknown') : 'unknown';
        $errSubcode = is_array($decoded) ? ($decoded['error']['error_subcode'] ?? '') : '';
        error_log('[WhatsApp OTP] Provider rejected message: HTTP ' . $httpCode
            . ' type=' . substr((string) $errType, 0, 60)
            . ' code=' . substr((string) $errCode, 0, 30)
            . ($errSubcode !== '' ? ' subcode=' . substr((string) $errSubcode, 0, 30) : ''));

        return [
            'success'    => false,
            'error_code' => 'provider_error',
            'http_code'  => $httpCode,
        ];
    }
}
