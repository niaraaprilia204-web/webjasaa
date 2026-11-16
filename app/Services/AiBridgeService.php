<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/AiInternalAuthService.php';

/**
 * Boundary service for the PHP -> Node.js chatbot bridge.
 *
 * Mints signed handoff tokens and forwards chat requests to the Node.js
 * OpenRouter backend. The existing PHP chatbot (AiService) remains available
 * as a fallback during the staged migration.
 */
class AiBridgeService extends BaseService
{
    private $auth;

    public function __construct()
    {
        $this->auth = new AiInternalAuthService();
    }

    public function isConfigured()
    {
        return $this->auth->isConfigured()
            && defined('AI_BRIDGE_URL')
            && AI_BRIDGE_URL !== '';
    }

    public function bridgeUrl()
    {
        return defined('AI_BRIDGE_URL') ? AI_BRIDGE_URL : '';
    }

    public function createHandoffToken(array $user)
    {
        return $this->auth->issueHandoffToken($user);
    }

    /**
     * Forward a chat request to the Node.js OpenRouter backend.
     *
     * @param string $handoffToken Short-lived PHP-issued token for Node.js.
     * @param string $query User query.
     * @param array $history Conversation history.
     * @return array Associative array with success/message/data or success:false/message.
     */
    public function forwardChat(string $handoffToken, string $query, array $history = [])
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'AI bridge (Node.js) belum dikonfigurasi.',
            ];
        }

        $url = rtrim(AI_BRIDGE_URL, '/') . '/api/chat';

        $payload = json_encode([
            'query' => $query,
            'history' => $history,
        ]);

        if ($payload === false) {
            return [
                'success' => false,
                'message' => 'Gagal mengenkripsi permintaan chat.',
            ];
        }

        $ch = curl_init($url);
        if (!$ch) {
            return [
                'success' => false,
                'message' => 'Gagal menginisialisasi koneksi ke Node.js.',
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $handoffToken,
                'X-AI-Token: ' . $handoffToken,
            ],
            // Internal loopback traffic only. The bridge never leaves the server.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = (int) curl_errno($ch);
        $curlError = trim((string) curl_error($ch));
        curl_close($ch);

        if ($curlErrno !== 0 || $response === false) {
            // Never include the token or any secret. Report the real transport
            // failure so the browser can show an actionable message instead of a
            // generic "cannot reach backend" string.
            $diagnostic = $this->describeCurlFailure($curlErrno, $curlError, $url);

            return [
                'success' => false,
                'message' => $diagnostic['message'],
                '_diagnostic' => $diagnostic['code'],
            ];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [
                'success' => false,
                'message' => 'Backend Node.js mengembalikan respons tidak valid.',
                '_diagnostic' => 'INVALID_JSON',
            ];
        }

        // Sertakan status HTTP Node.js agar proxy PHP dapat meneruskan
        // kode error yang sebenarnya (mis. 503 OpenRouter belum siap).
        $decoded['_status'] = $httpCode;

        return $decoded;
    }

    /**
     * Lightweight, unauthenticated health probe used by the Admin AI
     * Configuration dashboard. It never sends the HMAC token.
     */
    public function healthCheck(int $timeoutSeconds = 4)
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'reachable' => false,
                'message' => 'AI_BRIDGE_URL belum dikonfigurasi.',
                'status' => null,
            ];
        }

        $url = rtrim(AI_BRIDGE_URL, '/') . '/health';

        $ch = curl_init($url);
        if (!$ch) {
            return [
                'success' => false,
                'reachable' => false,
                'message' => 'Gagal menginisialisasi probe kesehatan Node.js.',
                'status' => null,
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(2, $timeoutSeconds),
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = (int) curl_errno($ch);
        curl_close($ch);

        if ($curlErrno !== 0 || $response === false) {
            return [
                'success' => false,
                'reachable' => false,
                'message' => 'Node.js tidak merespons pada ' . $url,
                'status' => null,
            ];
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'reachable' => true,
                'message' => 'Node.js merespons, tetapi bukan JSON yang valid.',
                'status' => null,
            ];
        }

        return [
            'success' => true,
            'reachable' => $httpCode >= 200 && $httpCode < 300,
            'message' => $httpCode >= 200 && $httpCode < 300
                ? 'Node.js aktif.'
                : 'Node.js merespons HTTP ' . $httpCode . '.',
            'status' => $decoded,
        ];
    }

    /**
     * Map a curl error to a safe, user-facing Indonesian message.
     */
    private function describeCurlFailure(int $errno, string $curlError, string $url)
    {
        $safeDetail = preg_replace('/[\r\n]+/', ' ', $curlError);
        $safeDetail = mb_substr((string) $safeDetail, 0, 160);

        switch ($errno) {
            case CURLE_COULDNT_CONNECT:
            case CURLE_COULDNT_RESOLVE_HOST:
            case CURLE_COULDNT_RESOLVE_PROXY:
                return [
                    'code' => 'NODE_CONNECTION_REFUSED',
                    'message' => 'Tidak dapat terhubung ke backend Node.js di ' . $url
                        . '. Pastikan server chatbot Node.js sedang berjalan (npm start).',
                ];
            case CURLE_OPERATION_TIMEDOUT:
            case CURLE_TIMEOUT:
                return [
                    'code' => 'NODE_TIMEOUT',
                    'message' => 'Backend Node.js tidak merespons dalam batas waktu. '
                        . 'Periksa koneksi OpenRouter atau coba lagi.',
                ];
            default:
                return [
                    'code' => 'NODE_CURL_ERROR_' . $errno,
                    'message' => 'Gagal menghubungi backend Node.js chatbot.'
                        . ($safeDetail !== '' ? ' Detail: ' . $safeDetail : ''),
                ];
        }
    }
}
