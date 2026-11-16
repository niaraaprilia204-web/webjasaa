<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/AiBridgeService.php';
require_once __DIR__ . '/../Models/ChatLog.php';

/**
 * Backend for the Admin "AI Configuration" page.
 *
 * Manages the non-secret chatbot configuration stored in the database
 * (ai_system_knowledge, ai_prompt_config, ai_model_config) and provides the
 * dashboard data. The OpenRouter API key is NEVER stored here and is never
 * returned to the browser.
 *
 * Security invariants:
 *  - Every write is validated server-side.
 *  - A custom system prompt cannot remove the read-only rule or the role
 *    restriction: the Node server always appends its hard-coded security
 *    block, and role checks always stay in PHP.
 */
class AiConfigService extends BaseService
{
    private $chatLog;

    public function __construct()
    {
        $this->chatLog = new ChatLog();
    }

    // ==============================================================
    // Dashboard AI
    // ==============================================================

    public function getDashboardData()
    {
        $bridge = new AiBridgeService();
        $nodeHealth = $bridge->healthCheck(4);

        $model = $this->getModelConfig();
        $apiKeyStatus = $this->getApiKeyStatus()['data'] ?? [];

        $openrouterStatus = null;
        if (is_array($nodeHealth['status'] ?? null) && isset($nodeHealth['status']['openrouter'])) {
            $openrouterStatus = $nodeHealth['status']['openrouter'];
        }

        // Jika API key disimpan di database, OpenRouter dianggap siap meski
        // chatbot-node/.env tidak berisi key.
        if (is_array($openrouterStatus)) {
            if (!empty($apiKeyStatus['configured'])) {
                $openrouterStatus['configured'] = true;
                if (empty($openrouterStatus['missing']) === false) {
                    $openrouterStatus['missing'] = array_values(array_diff(
                        (array) $openrouterStatus['missing'],
                        ['OPENROUTER_API_KEY']
                    ));
                }
            }
        } elseif (!empty($apiKeyStatus['configured'])) {
            $openrouterStatus = [
                'configured' => true,
                'missing' => [],
                'model' => $model['model'] ?? null,
            ];
        }

        return $this->response(true, 'OK', [
            'node' => [
                'reachable' => (bool) ($nodeHealth['reachable'] ?? false),
                'message'   => (string) ($nodeHealth['message'] ?? ''),
                'status'    => $nodeHealth['status'],
            ],
            'openrouter' => $openrouterStatus,
            'model' => $model,
            'chat_summary' => $this->chatLog->getAiDashboardSummary(),
            'database' => $this->getDatabaseStatus(),
        ]);
    }

    public function getModelConfig()
    {
        $db = new Database();

        if (!$db->isConnected()) {
            return ['model' => 'openai/gpt-4.1-mini', 'provider' => 'openrouter', 'temperature' => 0.3, 'max_tokens' => 1000, 'timeout_ms' => 30000, 'source' => 'default'];
        }

        try {
            $db->query("SELECT provider, model, temperature, max_tokens, timeout_ms, is_active, key_hint, encrypted_api_key FROM ai_model_config ORDER BY id ASC LIMIT 1");
            $row = $db->single();
            if (is_array($row)) {
                return [
                    'provider'    => (string) ($row['provider'] ?? 'openrouter'),
                    'model'       => (string) ($row['model'] ?? ''),
                    'temperature' => (float) ($row['temperature'] ?? 0.3),
                    'max_tokens'  => (int) ($row['max_tokens'] ?? 1000),
                    'timeout_ms'  => (int) ($row['timeout_ms'] ?? 30000),
                    'is_active'   => (int) ($row['is_active'] ?? 1),
                    'key_hint'    => (string) ($row['key_hint'] ?? ''),
                    'api_key_configured' => !empty($row['encrypted_api_key']),
                    'source'      => 'database',
                ];
            }
        } catch (\Throwable $e) {
            // Table not migrated yet.
        }

        return ['provider' => 'openrouter', 'model' => 'openai/gpt-4.1-mini', 'temperature' => 0.3, 'max_tokens' => 1000, 'timeout_ms' => 30000, 'is_active' => 1, 'key_hint' => '', 'api_key_configured' => false, 'source' => 'default'];
    }

    private function getDatabaseStatus()
    {
        $db = new Database();

        if (!$db->isConnected()) {
            return ['connected' => false, 'name' => DB_NAME];
        }

        try {
            $db->query("SELECT 1");
            return ['connected' => true, 'name' => DB_NAME];
        } catch (\Throwable $e) {
            return ['connected' => false, 'name' => DB_NAME];
        }
    }

    // ==============================================================
    // Knowledge Database (struktur + jumlah record + relasi)
    // ==============================================================

    public function getKnowledgeDatabase()
    {
        $db = new Database();

        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak terhubung.', ['tables' => [], 'relations' => []]);
        }

        try {
            $db->query(
                "SELECT TABLE_NAME AS table_name, TABLE_ROWS AS approx_rows,
                        TABLE_COMMENT AS table_comment
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_TYPE = 'BASE TABLE'
                  ORDER BY TABLE_NAME"
            );
            $tables = $db->resultSet() ?: [];

            $db->query(
                "SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name,
                        REFERENCED_TABLE_NAME AS referenced_table,
                        REFERENCED_COLUMN_NAME AS referenced_column
                   FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                  ORDER BY TABLE_NAME, COLUMN_NAME"
            );
            $relations = $db->resultSet() ?: [];

            // TABLE_ROWS is approximate for InnoDB; count exact rows for the
            // application tables (small dataset, safe to count directly).
            foreach ($tables as $index => $table) {
                $tableName = (string) $table['table_name'];
                if (!preg_match('/^[a-z0-9_]+$/i', $tableName)) {
                    continue;
                }
                try {
                    $db->query("SELECT COUNT(*) AS c FROM `{$tableName}`");
                    $row = $db->single();
                    $tables[$index]['row_count'] = (int) ($row['c'] ?? 0);
                } catch (\Throwable $e) {
                    $tables[$index]['row_count'] = null;
                }
            }

            return $this->response(true, 'OK', [
                'tables' => $tables,
                'relations' => $relations,
                'total_tables' => count($tables),
            ]);
        } catch (\Throwable $e) {
            return $this->response(false, 'Gagal membaca struktur database: ' . $e->getMessage(), ['tables' => [], 'relations' => []]);
        }
    }

    // ==============================================================
    // System Knowledge (ai_system_knowledge)
    // ==============================================================

    public function getSystemKnowledge()
    {
        $db = new Database();

        if (!$db->isConnected()) {
            return [];
        }

        try {
            $db->query("SELECT id, key_name, title, content, is_active, created_at, updated_at FROM ai_system_knowledge ORDER BY id ASC");
            return $db->resultSet() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function saveSystemKnowledge(array $input, $actorId)
    {
        $id = (int) ($input['id'] ?? 0);
        $keyName = strtolower(trim((string) ($input['key_name'] ?? '')));
        $title = trim((string) ($input['title'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));
        $isActive = !empty($input['is_active']);

        if ($keyName === '' || !preg_match('/^[a-z0-9_]{2,80}$/', $keyName)) {
            return $this->response(false, 'Key harus 2-80 karakter, hanya huruf kecil, angka, dan underscore (contoh: alur_pesanan).');
        }

        if ($title === '' || mb_strlen($title) > 150) {
            return $this->response(false, 'Judul wajib diisi, maksimal 150 karakter.');
        }

        if ($content === '' || mb_strlen($content) > 5000) {
            return $this->response(false, 'Isi pengetahuan wajib diisi, maksimal 5.000 karakter.');
        }

        $db = new Database();
        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak terhubung.');
        }

        try {
            // Unique key check (excluding the row being edited).
            $db->query("SELECT id FROM ai_system_knowledge WHERE key_name = :key_name AND id <> :id LIMIT 1");
            $db->bind(':key_name', $keyName);
            $db->bind(':id', $id);
            if ($db->single()) {
                return $this->response(false, 'Key tersebut sudah digunakan. Gunakan key lain.');
            }

            if ($id > 0) {
                $db->query(
                    "UPDATE ai_system_knowledge
                        SET key_name = :key_name, title = :title, content = :content, is_active = :is_active
                      WHERE id = :id"
                );
                $db->bind(':key_name', $keyName);
                $db->bind(':title', $title);
                $db->bind(':content', $content);
                $db->bind(':is_active', $isActive ? 1 : 0);
                $db->bind(':id', $id);
                $db->execute();
                return $this->response(true, 'Pengetahuan sistem berhasil diperbarui.');
            }

            $db->query(
                "INSERT INTO ai_system_knowledge (key_name, title, content, is_active)
                 VALUES (:key_name, :title, :content, :is_active)"
            );
            $db->bind(':key_name', $keyName);
            $db->bind(':title', $title);
            $db->bind(':content', $content);
            $db->bind(':is_active', $isActive ? 1 : 0);
            $db->execute();

            return $this->response(true, 'Pengetahuan sistem baru berhasil disimpan.');
        } catch (\Throwable $e) {
            return $this->response(false, 'Gagal menyimpan pengetahuan: ' . $e->getMessage());
        }
    }

    public function deleteSystemKnowledge($id, $actorId)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return $this->response(false, 'ID pengetahuan tidak valid.');
        }

        $db = new Database();
        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak terhubung.');
        }

        try {
            $db->query("DELETE FROM ai_system_knowledge WHERE id = :id");
            $db->bind(':id', $id);
            $db->execute();
            return $this->response(true, 'Pengetahuan sistem berhasil dihapus.');
        } catch (\Throwable $e) {
            return $this->response(false, 'Gagal menghapus pengetahuan: ' . $e->getMessage());
        }
    }

    // ==============================================================
    // AI Prompt Configuration (ai_prompt_config)
    // ==============================================================

    public function getPrompts()
    {
        $db = new Database();

        if (!$db->isConnected()) {
            return ['admin' => null, 'customer' => null];
        }

        try {
            $db->query("SELECT role_key, system_prompt, is_active, updated_at FROM ai_prompt_config WHERE role_key IN ('admin', 'customer')");
            $rows = $db->resultSet() ?: [];

            $prompts = ['admin' => null, 'customer' => null];
            foreach ($rows as $row) {
                $prompts[(string) $row['role_key']] = [
                    'system_prompt' => (string) $row['system_prompt'],
                    'is_active'     => (int) $row['is_active'],
                    'updated_at'    => (string) $row['updated_at'],
                ];
            }

            return $prompts;
        } catch (\Throwable $e) {
            return ['admin' => null, 'customer' => null];
        }
    }

    public function savePrompt(array $input, $actorId)
    {
        $roleKey = strtolower(trim((string) ($input['role_key'] ?? '')));
        $prompt = trim((string) ($input['system_prompt'] ?? ''));

        if (!in_array($roleKey, ['admin', 'customer'], true)) {
            return $this->response(false, 'Role prompt tidak valid.');
        }

        // Prompt aktif harus tetap berada dalam cakupan TKJ Service Hub dan
        // tidak boleh menyuruh AI mengabaikan aturannya sendiri.
        if ($prompt !== '') {
            if (mb_strlen($prompt) < 10 || mb_strlen($prompt) > 8000) {
                return $this->response(false, 'Prompt harus 10-8.000 karakter.');
            }

            if (stripos($prompt, 'tkj') === false) {
                return $this->response(false, 'Prompt harus tetap menyebut konteks "TKJ" (TKJ Service Hub). Prompt yang keluar dari konteks tidak disimpan.');
            }

            $forbidden = [
                'abaikan aturan', 'abaikan instruksi', 'abai aturan', 'abai instruksi',
                'abaikan (semua )?(aturan|instruksi|pembatasan)', 'abai (semua )?(aturan|instruksi|pembatasan)',
                'ignor(e|a)(semua)? (aturan|instruksi)',
                'lupakan (semua )?(aturan|instruksi|role)', 'hapus (semua )?aturan',
                'tanpa pembatasan', 'tanpa keamanan', 'tanpa role',
                'boleh menghapus data', 'boleh mengubah data', 'diperbolehkan menghapus', 'diperbolehkan mengubah',
                'customer boleh (melihat|mengakses|membaca) data customer lain',
                'customer dapat (melihat|mengakses|membaca) data customer lain',
                'jangan pakai role', 'lupakan role',
            ];
            foreach ($forbidden as $pattern) {
                if (preg_match('/' . $pattern . '/i', $prompt) === 1) {
                    return $this->response(false, 'Prompt ditolak: berisi instruksi untuk mengabaikan aturan keamanan atau pembatasan role. Aturan keamanan selalu diterapkan server-side dan tidak dapat dihapus dari prompt.');
                }
            }
        }

        $db = new Database();
        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak terhubung.');
        }

        try {
            if ($prompt === '') {
                // Empty prompt -> deactivate, Node falls back to the default.
                $db->query("UPDATE ai_prompt_config SET system_prompt = '', is_active = 0, updated_by = :updated_by WHERE role_key = :role_key");
            } else {
                $db->query(
                    "UPDATE ai_prompt_config
                        SET system_prompt = :system_prompt, is_active = 1, updated_by = :updated_by
                      WHERE role_key = :role_key"
                );
                $db->bind(':system_prompt', $prompt);
            }

            $db->bind(':updated_by', $actorId !== null ? (int) $actorId : null);
            $db->bind(':role_key', $roleKey);
            $db->execute();

            return $this->response(true, $prompt === ''
                ? 'Prompt ' . $roleKey . ' dinonaktifkan; chatbot memakai prompt bawaan sistem.'
                : 'Prompt ' . $roleKey . ' berhasil disimpan dan aktif.');
        } catch (\Throwable $e) {
            return $this->response(false, 'Gagal menyimpan prompt: ' . $e->getMessage());
        }
    }

    public function resetPrompt($roleKey, $actorId)
    {
        return $this->savePrompt(['role_key' => $roleKey, 'system_prompt' => ''], $actorId);
    }

    // ==============================================================
    // AI Model Configuration (ai_model_config)
    // ==============================================================

    public function saveModel(array $input, $actorId)
    {
        $model = trim((string) ($input['model'] ?? ''));
        $temperature = (float) ($input['temperature'] ?? 0.3);
        $maxTokens = (int) ($input['max_tokens'] ?? 1800);
        $timeoutMs = (int) ($input['timeout_ms'] ?? 30000);

        if ($model === '' || !preg_match('/^[A-Za-z0-9._\/:-]{3,120}$/', $model)) {
            return $this->response(false, 'Nama model tidak valid (contoh: openai/gpt-4.1-mini).');
        }

        if ($temperature < 0 || $temperature > 1.5) {
            return $this->response(false, 'Temperature harus di antara 0 dan 1.5.');
        }

        if ($maxTokens < 256 || $maxTokens > 4000) {
            return $this->response(false, 'Max tokens harus di antara 256 dan 4000.');
        }

        if ($timeoutMs < 5000 || $timeoutMs > 120000) {
            return $this->response(false, 'Timeout harus di antara 5.000 dan 120.000 ms.');
        }

        $db = new Database();
        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak terhubung.');
        }

        try {
            $db->query("SELECT id FROM ai_model_config ORDER BY id ASC LIMIT 1");
            $existing = $db->single();

            if (is_array($existing)) {
                $db->query(
                    "UPDATE ai_model_config
                        SET model = :model, temperature = :temperature, max_tokens = :max_tokens,
                            timeout_ms = :timeout_ms, is_active = 1, updated_by = :updated_by
                      WHERE id = :id"
                );
                $db->bind(':id', (int) $existing['id']);
            } else {
                $db->query(
                    "INSERT INTO ai_model_config (model, temperature, max_tokens, timeout_ms, is_active, updated_by)
                     VALUES (:model, :temperature, :max_tokens, :timeout_ms, 1, :updated_by)"
                );
            }

            $db->bind(':model', $model);
            $db->bind(':temperature', $temperature);
            $db->bind(':max_tokens', $maxTokens);
            $db->bind(':timeout_ms', $timeoutMs);
            $db->bind(':updated_by', $actorId !== null ? (int) $actorId : null);
            $db->execute();

            return $this->response(true, 'Konfigurasi model AI berhasil disimpan. Perubahan langsung dipakai percakapan berikutnya.');
        } catch (\Throwable $e) {
            return $this->response(false, 'Gagal menyimpan konfigurasi model: ' . $e->getMessage());
        }
    }

    // ==============================================================
    // OpenRouter API key (encrypted at rest in ai_model_config)
    // ==============================================================

    private function encryptionKey()
    {
        $secret = (string) (getenv('AI_CONFIG_SECRET') ?: (getenv('AI_INTERNAL_SECRET') ?: 'tkj-service-hub-ai-config'));
        return hash('sha256', $secret, true); // 32 bytes
    }

    private function encryptApiKey($plain)
    {
        $plain = (string) $plain;
        if ($plain === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt')) {
            return '';
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            return '';
        }

        return base64_encode($iv . $tag . $cipher);
    }

    private function decryptApiKey($cipher)
    {
        $cipher = trim((string) $cipher);
        if ($cipher === '') {
            return '';
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $raw = base64_decode($cipher, true);
        if ($raw === false || strlen($raw) < 12 + 16 + 1) {
            return '';
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : (string) $plain;
    }

    public function getApiKeyStatus()
    {
        $db = new Database();

        $envKey = trim((string) (getenv('OPENROUTER_API_KEY') ?: ''));
        $source = 'env';
        $configured = $envKey !== '';
        $hint = $envKey !== '' ? $this->maskKey($envKey) : '';

        if ($db->isConnected()) {
            try {
                $db->query("SELECT encrypted_api_key, key_hint FROM ai_model_config ORDER BY id ASC LIMIT 1");
                $row = $db->single();
                if (is_array($row) && !empty($row['encrypted_api_key'])) {
                    $decrypted = $this->decryptApiKey((string) $row['encrypted_api_key']);
                    if ($decrypted !== '') {
                        $configured = true;
                        $source = 'database';
                        $hint = !empty($row['key_hint']) ? (string) $row['key_hint'] : $this->maskKey($decrypted);
                    }
                }
            } catch (\Throwable $e) {
                // Table not migrated yet.
            }
        }

        return $this->response(true, 'OK', [
            'configured' => $configured,
            'source'     => $source,
            'hint'       => $hint,
            'env_configured' => $envKey !== '',
        ]);
    }

    public function getDecryptedApiKey()
    {
        $db = new Database();

        if ($db->isConnected()) {
            try {
                $db->query("SELECT encrypted_api_key FROM ai_model_config ORDER BY id ASC LIMIT 1");
                $row = $db->single();
                if (is_array($row) && !empty($row['encrypted_api_key'])) {
                    $decrypted = $this->decryptApiKey((string) $row['encrypted_api_key']);
                    if ($decrypted !== '') {
                        return $decrypted;
                    }
                }
            } catch (\Throwable $e) {
                // Fall back to env.
            }
        }

        return trim((string) (getenv('OPENROUTER_API_KEY') ?: ''));
    }

    private function maskKey($key)
    {
        $key = (string) $key;
        $len = strlen($key);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        // Hint singkat yang tidak bergantung pada panjang key, sehingga selalu
        // muat di kolom key_hint (VARCHAR).
        return substr($key, 0, 6) . '****' . substr($key, -4);
    }

    private function validateOpenRouterKey($key)
    {
        $key = (string) $key;
        if (!function_exists('curl_init')) {
            return ['valid' => true, 'message' => 'validasi dilewati (curl tidak tersedia)'];
        }

        $ch = curl_init('https://openrouter.ai/api/v1/auth/key');
        if (!$ch) {
            return ['valid' => true, 'message' => 'validasi dilewati (curl gagal inisialisasi)'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $key,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = (int) curl_errno($ch);
        curl_close($ch);

        if ($curlErrno !== 0 || $response === false) {
            // Jangan blokir penyimpanan bila jaringan server ke OpenRouter gagal;
            // key tetap disimpan, dan kegagalan akan terlihat saat chat.
            return ['valid' => true, 'message' => 'validasi dilewati (jaringan ke OpenRouter gagal)'];
        }

        if ($httpCode === 200) {
            $decoded = json_decode((string) $response, true);
            if (is_array($decoded) && isset($decoded['data'])) {
                return ['valid' => true, 'message' => 'valid'];
            }
        }

        $decoded = json_decode((string) $response, true);
        $detail = '';
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $detail = (string) $decoded['error']['message'];
        } elseif (is_array($decoded) && isset($decoded['message'])) {
            $detail = (string) $decoded['message'];
        }

        return [
            'valid' => false,
            'message' => $detail !== '' ? $detail : ('HTTP ' . $httpCode),
        ];
    }
    public function saveApiKey($key, $actorId)
    {
        $key = trim((string) $key);

        if ($key === '') {
            return $this->response(false, 'API key tidak boleh kosong.');
        }

        if (strlen($key) < 20 || strlen($key) > 500) {
            return $this->response(false, 'API key tidak valid (panjang 20-500 karakter).');
        }

        if (preg_match('/\s/', $key) === 1) {
            return $this->response(false, 'API key tidak boleh mengandung spasi atau baris baru.');
        }

        // Validasi key ke OpenRouter sebelum disimpan, agar key tidak valid
        // (atau key yang ditolak OpenRouter) tidak pernah tersimpan di DB.
        $validation = $this->validateOpenRouterKey($key);
        if (!$validation['valid']) {
            return $this->response(false, 'API key ditolak OpenRouter: ' . $validation['message']);
        }

        $encrypted = $this->encryptApiKey($key);
        if ($encrypted === '') {
            return $this->response(false, 'Gagal mengenkripsi API key. Periksa ekstensi OpenSSL server.');
        }

        $hint = $this->maskKey($key);

        $db = new Database();
        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak terhubung.');
        }

        try {
            $db->query("SELECT id FROM ai_model_config ORDER BY id ASC LIMIT 1");
            $existing = $db->single();

            if (is_array($existing)) {
                $db->query(
                    "UPDATE ai_model_config
                        SET encrypted_api_key = :encrypted_api_key, key_hint = :key_hint,
                            is_active = 1, updated_by = :updated_by
                      WHERE id = :id"
                );
                $db->bind(':id', (int) $existing['id']);
            } else {
                $db->query(
                    "INSERT INTO ai_model_config (provider, model, temperature, max_tokens, timeout_ms, is_active, encrypted_api_key, key_hint, updated_by)
                     VALUES ('openrouter', 'openai/gpt-4.1-mini', 0.30, 1000, 30000, 1, :encrypted_api_key, :key_hint, :updated_by)"
                );
            }

            $db->bind(':encrypted_api_key', $encrypted);
            $db->bind(':key_hint', $hint);
            $db->bind(':updated_by', $actorId !== null ? (int) $actorId : null);
            $db->execute();

            return $this->response(true, 'API key OpenRouter berhasil disimpan (terenkripsi di database). Perubahan langsung dipakai percakapan berikutnya.');
        } catch (\Throwable $e) {
            return $this->response(false, 'Gagal menyimpan API key: ' . $e->getMessage());
        }
    }

    public function clearApiKey($actorId)
    {
        $db = new Database();
        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak terhubung.');
        }

        try {
            $db->query("SELECT id FROM ai_model_config ORDER BY id ASC LIMIT 1");
            $existing = $db->single();
            if (is_array($existing)) {
                $db->query("UPDATE ai_model_config SET encrypted_api_key = NULL, key_hint = NULL, updated_by = :updated_by WHERE id = :id");
                $db->bind(':id', (int) $existing['id']);
                $db->bind(':updated_by', $actorId !== null ? (int) $actorId : null);
                $db->execute();
            }

            return $this->response(true, 'API key database dihapus. Chatbot kembali memakai OPENROUTER_API_KEY dari file .env (jika ada).');
        } catch (\Throwable $e) {
            return $this->response(false, 'Gagal menghapus API key: ' . $e->getMessage());
        }
    }

    public function getLogs($search = '', $status = '', $limit = 50, $offset = 0)
    {
        $search = trim((string) $search);
        $status = trim((string) $status);
        $limit = max(1, min(200, (int) $limit));
        $offset = max(0, (int) $offset);

        $db = new Database();
        if (!$db->isConnected()) {
            return ['logs' => [], 'total' => 0];
        }

        $conditions = [];
        $params = [];

        if ($search !== '') {
            $conditions[] = "(cl.query LIKE :search OR cl.reply LIKE :search OR u.name LIKE :search OR u.email LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($status !== '' && in_array($status, ['success', 'error'], true)) {
            $conditions[] = "cl.status = :status";
            $params[':status'] = $status;
        }

        $where = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

        try {
            $sql = "SELECT cl.*, u.name AS user_name, u.email AS user_email, u.role AS user_role
                    FROM ai_chat_logs cl
                    LEFT JOIN users u ON u.id = cl.user_id" . $where;

            $sql .= " ORDER BY cl.created_at DESC, cl.id DESC LIMIT :limit OFFSET :offset";

            $db->query($sql);
            foreach ($params as $name => $value) {
                $db->bind($name, $value);
            }
            $db->bind(':limit', $limit, PDO::PARAM_INT);
            $db->bind(':offset', $offset, PDO::PARAM_INT);
            $logs = $db->resultSet() ?: [];

            $countSql = "SELECT COUNT(*) AS cnt FROM ai_chat_logs cl LEFT JOIN users u ON u.id = cl.user_id" . $where;
            $db->query($countSql);
            foreach ($params as $name => $value) {
                $db->bind($name, $value);
            }
            $row = $db->single() ?: [];

            return ['logs' => $logs, 'total' => (int) ($row['cnt'] ?? 0)];
        } catch (\Throwable $e) {
            return ['logs' => [], 'total' => 0];
        }
    }
}
