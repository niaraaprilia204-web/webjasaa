<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Services/AiConfigService.php';
require_once __DIR__ . '/../Services/AiBridgeService.php';
require_once __DIR__ . '/../Services/AiTopicGuard.php';

/**
 * Admin "AI Configuration" page: dashboard, knowledge database, system
 * knowledge, prompt configuration, model configuration, chatbot logs, and
 * a live chatbot test. Strictly admin-only.
 *
 * The OpenRouter API key never appears here; it lives only in the Node.js
 * environment (.env on the server).
 */
class AiConfigController extends BaseController
{
    private $aiConfigService;

    public function __construct()
    {
        parent::__construct();
        $roleGuard = new RoleMiddleware(['admin']);
        $roleGuard->handle();

        $this->aiConfigService = new AiConfigService();
    }

    /**
     * GET /admin/ai-config (opsional ?tab= untuk membuka tab tertentu).
     * Semua data tab disiapkan server-side dalam satu render.
     */
    public function index()
    {
        $activeTab = (string) ($_GET['tab'] ?? 'dashboard');
        $allowedTabs = ['dashboard', 'knowledge-database', 'system-knowledge', 'prompt', 'model', 'logs', 'test'];
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'dashboard';
        }

        $dashboard = $this->aiConfigService->getDashboardData();
        $knowledgeDb = $this->aiConfigService->getKnowledgeDatabase();

        $logsPage = max(1, (int) ($_GET['page'] ?? 1));
        $logsStatus = (string) ($_GET['status'] ?? '');
        $logsSearch = trim((string) ($_GET['q'] ?? ''));
        $logs = $this->aiConfigService->getLogs($logsSearch, $logsStatus, 30, ($logsPage - 1) * 30);

        $data = [
            'title'        => 'AI Configuration - TKJ Service Hub',
            'activeMenu'   => 'admin_ai_config',
            'user'         => $_SESSION['user'],
            'activeTab'    => $activeTab,
            'csrfToken'    => $this->csrfToken(),

            'node'         => $dashboard['data']['node'] ?? [],
            'openrouter'   => $dashboard['data']['openrouter'] ?? null,
            'modelConfig'  => $dashboard['data']['model'] ?? [],
            'chatSummary'  => $dashboard['data']['chat_summary'] ?? [],
            'databaseStatus' => $dashboard['data']['database'] ?? [],

            'knowledgeTables'    => $knowledgeDb['data']['tables'] ?? [],
            'knowledgeRelations' => $knowledgeDb['data']['relations'] ?? [],
            'totalTables'        => $knowledgeDb['data']['total_tables'] ?? 0,

            'systemKnowledge' => $this->aiConfigService->getSystemKnowledge(),
            'prompts'         => $this->aiConfigService->getPrompts(),

            'apiKeyStatus' => $this->aiConfigService->getApiKeyStatus()['data'] ?? [],

            'logs'        => $logs['logs'],
            'logsTotal'   => $logs['total'],
            'logsPage'    => $logsPage,
            'logsStatus'  => $logsStatus,
            'logsSearch'  => $logsSearch,
        ];

        $this->renderLayout('admin/ai_config', $data);
    }

    /**
     * POST /admin/ai-config/system-knowledge/save
     */
    public function saveSystemKnowledge()
    {
        $this->requirePostAndCsrf('/admin/ai-config?tab=system-knowledge');

        $result = $this->aiConfigService->saveSystemKnowledge($_POST, $_SESSION['user']['id'] ?? null);
        $this->flashResult($result);
        $this->redirect('/admin/ai-config?tab=system-knowledge');
    }

    /**
     * POST /admin/ai-config/system-knowledge/delete
     */
    public function deleteSystemKnowledge()
    {
        $this->requirePostAndCsrf('/admin/ai-config?tab=system-knowledge');

        $result = $this->aiConfigService->deleteSystemKnowledge($_POST['id'] ?? 0, $_SESSION['user']['id'] ?? null);
        $this->flashResult($result);
        $this->redirect('/admin/ai-config?tab=system-knowledge');
    }

    /**
     * POST /admin/ai-config/prompt/save
     */
    public function savePrompt()
    {
        $this->requirePostAndCsrf('/admin/ai-config?tab=prompt');

        $result = $this->aiConfigService->savePrompt($_POST, $_SESSION['user']['id'] ?? null);
        $this->flashResult($result);
        $this->redirect('/admin/ai-config?tab=prompt');
    }

    /**
     * POST /admin/ai-config/prompt/reset
     */
    public function resetPrompt()
    {
        $this->requirePostAndCsrf('/admin/ai-config?tab=prompt');

        $result = $this->aiConfigService->resetPrompt($_POST['role_key'] ?? '', $_SESSION['user']['id'] ?? null);
        $this->flashResult($result);
        $this->redirect('/admin/ai-config?tab=prompt');
    }

    /**
     * POST /admin/ai-config/model/save
     */
    public function saveModel()
    {
        $this->requirePostAndCsrf('/admin/ai-config?tab=model');

        $result = $this->aiConfigService->saveModel($_POST, $_SESSION['user']['id'] ?? null);
        $this->flashResult($result);
        $this->redirect('/admin/ai-config?tab=model');
    }

    /**
     * POST /admin/ai-config/test (AJAX JSON).
     * Menjalankan satu percakapan nyata: session admin + guard topik +
     * Node.js + OpenRouter, tanpa menyimpan apa pun kecuali log chat.
     */
    public function testAi()
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        $this->validateCsrf();

        try {
            $this->handleTestAi();
        } catch (\Throwable $e) {
            error_log('[AI testAi] ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server AI. Silakan coba lagi nanti.',
            ], 500);
        }
    }

    private function handleTestAi()
    {
        $query = trim((string) ($_POST['query'] ?? ''));

        if ($query === '') {
            $this->json([
                'success' => false,
                'message' => 'Pertanyaan uji tidak boleh kosong.',
            ], 422);
        }

        if (mb_strlen($query) > 2000) {
            $this->json([
                'success' => false,
                'message' => 'Pertanyaan uji terlalu panjang.',
            ], 422);
        }

        $startedAt = microtime(true);
        $bridge = new AiBridgeService();

        // Guard topik dijalankan juga pada halaman Test AI sehingga perilaku
        // persis sama dengan chatbot di website.
        if (!AiTopicGuard::isRelevant($query)) {
            $this->json([
                'success' => true,
                'source' => 'topic_guard',
                'reply' => AiTopicGuard::rejectionMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        }

        $token = $bridge->createHandoffToken($_SESSION['user']);
        $result = $bridge->forwardChat($token, $query);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (empty($result['success'])) {
            $message = (string) ($result['message'] ?? 'Backend Node.js gagal merespons.');
            $diagnostic = (string) ($result['_diagnostic'] ?? '');
            $this->json([
                'success' => false,
                'message' => $message,
                'diagnostic' => $diagnostic,
                'duration_ms' => $durationMs,
            ], 502);
        }

        $this->json([
            'success' => true,
            'source' => $result['source'] ?? 'openrouter',
            'reply' => (string) ($result['data']['reply'] ?? ''),
            'toolsUsed' => $result['data']['toolsUsed'] ?? [],
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * POST /admin/ai-config/api-key/save
     */
    public function saveApiKey()
    {
        $this->requirePostAndCsrf('/admin/ai-config?tab=model');

        $submittedKey = (string) ($_POST['api_key'] ?? '');

        $result = $this->aiConfigService->saveApiKey($submittedKey, $_SESSION['user']['id'] ?? null);
        $this->flashResult($result);
        $this->redirect('/admin/ai-config?tab=model');
    }

    /**
     * POST /admin/ai-config/api-key/clear
     */
    public function clearApiKey()
    {
        $this->requirePostAndCsrf('/admin/ai-config?tab=model');

        $result = $this->aiConfigService->clearApiKey($_SESSION['user']['id'] ?? null);
        $this->flashResult($result);
        $this->redirect('/admin/ai-config?tab=model');
    }
    public function health()
    {
        $bridge = new AiBridgeService();
        $nodeHealth = $bridge->healthCheck(4);

        $this->json([
            'success' => (bool) ($nodeHealth['success'] ?? false),
            'node' => [
                'reachable' => (bool) ($nodeHealth['reachable'] ?? false),
                'message'   => (string) ($nodeHealth['message'] ?? ''),
                'status'    => $nodeHealth['status'],
            ],
        ]);
    }

    private function requirePostAndCsrf($redirectPath)
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            Flasher::setMessage('Metode request tidak valid.', 'Gagal', 'danger');
            $this->redirect($redirectPath);
        }

        $this->validateCsrf();
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

    private function flashResult($result)
    {
        if (is_array($result) && !empty($result['success'])) {
            Flasher::setMessage($result['message'] ?? 'Berhasil.', 'Berhasil', 'success');
        } else {
            Flasher::setMessage($result['message'] ?? 'Terjadi kesalahan.', 'Gagal', 'danger');
        }
    }

    private function csrfToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['admin_csrf']) || !is_string($_SESSION['admin_csrf'])) {
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['admin_csrf'];
    }

    private function validateCsrf()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected = $_SESSION['admin_csrf'] ?? '';

        if (!is_string($submitted) || !is_string($expected) || $expected === ''
            || !hash_equals($expected, $submitted)) {
            Flasher::setMessage('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 'Gagal', 'danger');
            $this->redirect('/admin/ai-config');
        }
    }

    private function redirect($path)
    {
        header('Location: ' . BASEURL . $path);
        exit;
    }
}
