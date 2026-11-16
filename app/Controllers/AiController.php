<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Core/Csrf.php';
require_once __DIR__ . '/../Services/AiService.php';
require_once __DIR__ . '/../Services/AiBridgeService.php';
require_once __DIR__ . '/../Services/AiTopicGuard.php';
require_once __DIR__ . '/../Models/ChatLog.php';

/**
 * AI Assistant REST API Controller.
 *
 * Browser-facing AI endpoints require a valid PHP session, an allowed role,
 * and the AI CSRF token. Role is always read from the server-side session;
 * no role value from JavaScript is trusted.
 */
class AiController extends BaseController
{
    private $aiService;

    public function __construct()
    {
        parent::__construct();
        $this->aiService = new AiService();
    }

    /**
     * AJAX Endpoint: Customer/Admin AI Chat Assistant
     * URL: POST /ai/chat
     */
    public function chat()
    {
        $this->requireMethod('POST');
        $this->requireRoles(['customer', 'admin']);
        $this->requireCsrf();

        try {
            $this->handleChat();
        } catch (\Throwable $e) {
            error_log('[AI chat] ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->jsonError('Terjadi kesalahan pada server AI. Silakan coba lagi nanti.', 500);
        }
    }

    private function handleChat()
    {
        $input = $this->jsonInput();
        $userQuery = trim((string) ($input['query'] ?? ($_POST['query'] ?? '')));
        $history = $input['history'] ?? ($_POST['history'] ?? []);

        if ($userQuery === '') {
            $this->jsonError('Pertanyaan tidak boleh kosong.', 422);
        }

        if (mb_strlen($userQuery) > 2000) {
            $this->jsonError('Pertanyaan terlalu panjang. Maksimal 2.000 karakter.', 422);
        }

        $history = $this->sanitizeHistory($history);

        // Backend relevance guard: jangan panggil AI untuk topik di luar TKJ.
        if (!AiTopicGuard::isRelevant($userQuery)) {
            $reply = AiTopicGuard::rejectionMessage();
            $this->persistChatLog($userQuery, [
                'success' => true,
                'source' => 'topic_guard',
                'data' => ['reply' => $reply, 'category' => 'offtopic'],
            ]);

            $this->json([
                'success' => true,
                'source' => 'topic_guard',
                'data' => [
                    'reply' => $reply,
                    'category' => 'offtopic',
                    'suggestedAction' => 'Tanyakan Lagi',
                    'relatedService' => 'TKJ Service Hub',
                ],
            ]);
        }

        $result = $this->aiService->chatResponse($userQuery, ['history' => $history]);

        $this->persistChatLog($userQuery, $result);
        $this->json($result);
    }

    /**
     * AJAX Endpoint: Chat via Node.js + OpenRouter backend
     * URL: POST /ai/chat/node
     *
     * Memvalidasi session, role, dan CSRF di PHP, lalu membuat handoff token
     * dan mem-forward permintaan ke Node.js OpenRouter backend.
     */
    public function chatNode()
    {
        $this->requireMethod('POST');
        $this->requireRoles(['customer', 'admin']);
        $this->requireCsrf();

        // Tangkap SEMUA unexpected error supaya endpoint selalu menjawab JSON,
        // bukan halaman fatal HTML yang membuat frontend menampilkan
        // "Terjadi kesalahan pada server AI (HTTP 200)".
        try {
            $this->handleChatNode();
        } catch (\Throwable $e) {
            error_log('[AI chatNode] ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->jsonError('Terjadi kesalahan pada server AI. Silakan coba lagi nanti.', 500);
        }
    }

    private function handleChatNode()
    {
        $startedAt = microtime(true);

        $input = $this->jsonInput();
        $userQuery = trim((string) ($input['query'] ?? ($_POST['query'] ?? '')));
        $history = $input['history'] ?? ($_POST['history'] ?? []);

        if ($userQuery === '') {
            $this->jsonError('Pertanyaan tidak boleh kosong.', 422);
        }

        if (mb_strlen($userQuery) > 2000) {
            $this->jsonError('Pertanyaan terlalu panjang. Maksimal 2.000 karakter.', 422);
        }

        $history = $this->sanitizeHistory($history);

        // ==============================================================
        // Backend relevance guard (WAJIB).
        // Off-topic questions are rejected HERE, before Node.js / OpenRouter
        // is contacted and before any database tool can run.
        // ==============================================================
        if (!AiTopicGuard::isRelevant($userQuery)) {
            $reply = AiTopicGuard::rejectionMessage();
            $this->persistChatLog($userQuery, [
                'success' => true,
                'source' => 'topic_guard',
                'data' => ['reply' => $reply, 'category' => 'offtopic'],
            ], (int) round((microtime(true) - $startedAt) * 1000));

            $this->json([
                'success' => true,
                'source' => 'topic_guard',
                'data' => [
                    'reply' => $reply,
                    'category' => 'offtopic',
                    'suggestedAction' => 'Tanyakan Lagi',
                    'relatedService' => 'TKJ Service Hub',
                ],
            ]);
        }

        $bridge = new AiBridgeService();
        $token = $bridge->createHandoffToken($_SESSION['user']);

        $result = $bridge->forwardChat($token, $userQuery, $history);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (!$result['success']) {
            // Teruskan status HTTP asli dari Node.js (mis. 503 OpenRouter belum siap)
            // supaya frontend menampilkan pesan error yang sebenarnya.
            $nodeStatus = (int) ($result['_status'] ?? 0);
            $status = ($nodeStatus >= 400 && $nodeStatus < 600) ? $nodeStatus : 502;
            $message = $result['message'] ?? 'Backend Node.js gagal merespons.';
            $diagnostic = (string) ($result['_diagnostic'] ?? '');
            if ($diagnostic !== '') {
                $message .= ' [' . $diagnostic . ']';
            }

            // Catat error ke log chat agar dapat dianalisis di tab Chatbot Logs.
            $this->persistChatLog($userQuery, [
                'success' => false,
                'source' => 'node_openrouter_error',
                'message' => $message,
            ], $durationMs);

            $this->jsonError($message, $status);
        }

        $this->persistChatLog($userQuery, $result, $durationMs);

        $data = $result['data'] ?? [];

        // Normalisasi struktur agar konsisten dengan respons lama
        $reply = $data['reply'] ?? '';
        $suggestedAction = $data['suggestedAction'] ?? 'Tanyakan Lagi';
        $relatedService = $data['relatedService'] ?? 'TKJ Service Hub';

        $this->json([
            'success' => true,
            'source' => 'node_openrouter',
            'data' => [
                'reply' => $reply,
                'category' => $data['source'] ?? 'openrouter',
                'suggestedAction' => $suggestedAction,
                'relatedService' => $relatedService,
            ],
        ]);
    }

    /**
     * AJAX Endpoint: Order Form Problem Description Generator
     * URL: POST /ai/generateDescription
     */
    public function generateDescription()
    {
        $this->requireMethod('POST');
        $this->requireRoles(['customer']);
        $this->requireCsrf();

        $input = $this->jsonInput();
        $briefInput = trim((string) ($input['brief_input'] ?? ($_POST['brief_input'] ?? '')));
        $serviceType = trim((string) ($input['service_type'] ?? ($_POST['service_type'] ?? '')));

        if ($briefInput === '') {
            $this->jsonError('Masukkan catatan singkat mengenai masalah perangkat Anda.', 422);
        }

        if (mb_strlen($briefInput) > 1000) {
            $this->jsonError('Catatan masalah terlalu panjang. Maksimal 1.000 karakter.', 422);
        }

        if (mb_strlen($serviceType) > 150) {
            $this->jsonError('Jenis layanan tidak valid.', 422);
        }

        $result = $this->aiService->generateProblemDescription($briefInput, $serviceType);
        $this->json($result);
    }

    /**
     * AJAX Endpoint: Technician & Operator AI Tools
     * URL: POST /ai/technicianHelper
     */
    public function technicianHelper()
    {
        $this->requireMethod('POST');
        $this->requireRoles(['technician', 'operator']);
        $this->requireCsrf();

        $input = $this->jsonInput();
        $action = trim((string) ($input['action'] ?? ($_POST['action'] ?? 'checklist')));
        $serviceType = trim((string) ($input['service_type'] ?? ($_POST['service_type'] ?? '')));
        $problemDesc = trim((string) ($input['problem_description'] ?? ($_POST['problem_description'] ?? '')));

        if (!in_array($action, ['checklist', 'report'], true)) {
            $this->jsonError('Tindakan AI teknisi tidak valid.', 422);
        }

        if (mb_strlen($serviceType) > 150 || mb_strlen($problemDesc) > 3000) {
            $this->jsonError('Data AI teknisi terlalu panjang.', 422);
        }

        if ($action === 'checklist') {
            $result = $this->aiService->generateTechnicianChecklist($serviceType, $problemDesc);
            $this->json($result);
        }

        $orderCode = trim((string) ($input['order_code'] ?? ($_POST['order_code'] ?? 'SPK-000')));
        $findings = trim((string) ($input['findings'] ?? ($_POST['findings'] ?? '')));
        $actionsTaken = trim((string) ($input['actions'] ?? ($_POST['actions'] ?? '')));

        if (mb_strlen($orderCode) > 60 || mb_strlen($findings) > 3000 || mb_strlen($actionsTaken) > 3000) {
            $this->jsonError('Data laporan AI teknisi terlalu panjang.', 422);
        }

        $result = $this->aiService->generateWorkReportSummary($orderCode, $serviceType, $findings, $actionsTaken);
        $this->json($result);
    }

    /**
     * Store the conversation for Admin/Operator review. Logging is best-effort
     * and must never prevent the chat response from being returned.
     */
    private function persistChatLog($query, $result, $durationMs = null)
    {
        try {
            $reply = '';
            if (is_array($result)) {
                $reply = (string) ($result['data']['reply'] ?? ($result['message'] ?? ''));
            }

            $query = trim((string) $query);
            $reply = trim($reply);
            if ($query === '') {
                return;
            }

            $success = is_array($result) ? !empty($result['success']) : false;
            $toolsUsed = '';
            if (is_array($result) && !empty($result['data']['toolsUsed']) && is_array($result['data']['toolsUsed'])) {
                $toolNames = [];
                foreach ($result['data']['toolsUsed'] as $tool) {
                    $name = is_array($tool) ? (string) ($tool['function'] ?? '') : (string) $tool;
                    if ($name !== '') {
                        $toolNames[] = $name;
                    }
                }
                $toolsUsed = mb_substr(implode(',', array_unique($toolNames)), 0, 255);
            }

            $log = new ChatLog();
            $log->logConversation([
                'user_id'       => $_SESSION['user']['id'] ?? null,
                'session_id'    => session_id() ?: null,
                'query'         => mb_substr($query, 0, 5000),
                'reply'         => mb_substr($reply, 0, 20000),
                'source'        => is_array($result) ? ($result['source'] ?? null) : null,
                'category'      => is_array($result) ? ($result['data']['category'] ?? null) : null,
                'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
                'role'          => $_SESSION['user']['role'] ?? null,
                'status'        => $success ? 'success' : 'error',
                'duration_ms'   => $durationMs !== null ? max(0, (int) $durationMs) : null,
                'error_message' => $success ? null : mb_substr((string) ($result['message'] ?? ''), 0, 255),
                'tools_used'    => $toolsUsed !== '' ? $toolsUsed : null,
            ]);
        } catch (\Throwable $e) {
            // Ignore logging failures.
        }
    }

    private function requireMethod($method)
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== strtoupper((string) $method)) {
            if (strtoupper((string) $method) === 'POST') {
                header('Allow: POST');
            }
            $this->jsonError('Invalid HTTP Request Method', 405);
        }
    }

    private function requireRoles(array $allowedRoles)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user']['id']) || empty($_SESSION['user']['role'])) {
            $this->jsonError('Silakan login terlebih dahulu untuk memakai asisten AI.', 401);
        }

        $role = (string) $_SESSION['user']['role'];
        if (!in_array($role, $allowedRoles, true)) {
            $this->jsonError('Role Anda tidak diizinkan memakai fitur AI ini.', 403);
        }
    }

    private function requireCsrf()
    {
        if (!Csrf::validate('ai')) {
            $this->jsonError('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 403);
        }
    }

    private function jsonInput()
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (strpos($contentType, 'application/json') === false) {
            return is_array($_POST) ? $_POST : [];
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $this->jsonError('Format JSON tidak valid.', 400);
        }

        return $decoded;
    }

    private function sanitizeHistory($history)
    {
        if (!is_array($history)) {
            return [];
        }

        $clean = [];
        foreach ($history as $message) {
            if (!is_array($message)) {
                continue;
            }

            $role = strtolower(trim((string) ($message['role'] ?? '')));
            $content = trim((string) ($message['content'] ?? ''));

            if ($content === '' || !in_array($role, ['user', 'human', 'assistant', 'model'], true)) {
                continue;
            }

            $clean[] = [
                'role' => $role,
                'content' => mb_substr($content, 0, 4000),
            ];

            if (count($clean) >= 20) {
                break;
            }
        }

        return $clean;
    }

    private function jsonError($message, $status = 400)
    {
        $this->json([
            'success' => false,
            'message' => (string) $message,
        ], $status);
    }

    private function json($payload, $status = 200)
    {
        if (!headers_sent()) {
            http_response_code((int) $status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-Content-Type-Options: nosniff');
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
