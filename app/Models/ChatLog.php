<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Persisted AI chatbot conversation history.
 */
class ChatLog extends BaseModel
{
    protected $table = 'ai_chat_logs';

    public function logConversation($data)
    {
        if (!$this->db->isConnected()) {
            return false;
        }

        try {
            return $this->create([
                'user_id'       => $data['user_id'] ?? null,
                'session_id'    => $data['session_id'] ?? null,
                'query'         => $data['query'] ?? '',
                'reply'         => $data['reply'] ?? '',
                'source'        => $data['source'] ?? null,
                'category'      => $data['category'] ?? null,
                'ip_address'    => $data['ip_address'] ?? null,
                'role'          => $data['role'] ?? null,
                'status'        => $data['status'] ?? 'success',
                'duration_ms'   => isset($data['duration_ms']) ? (int) $data['duration_ms'] : null,
                'error_message' => $data['error_message'] ?? null,
                'tools_used'    => $data['tools_used'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Chat logging must never break the chat endpoint.
            return false;
        }
    }

    /**
     * Rangkuman untuk tab "Dashboard AI": total, hari ini, error, dan rata-rata
     * durasi respons (semua nilainya dihitung server-side dari ai_chat_logs).
     */
    public function getAiDashboardSummary()
    {
        if (!$this->db->isConnected()) {
            return ['total' => 0, 'today' => 0, 'errors' => 0, 'avg_duration_ms' => null, 'tool_usage' => 0];
        }

        try {
            $this->db->query(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
                        SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) AS errors,
                        AVG(duration_ms) AS avg_duration_ms,
                        SUM(CASE WHEN tools_used IS NOT NULL AND tools_used <> '' THEN 1 ELSE 0 END) AS tool_usage
                   FROM ai_chat_logs"
            );
            $row = $this->db->single() ?: [];
            return [
                'total'           => (int) ($row['total'] ?? 0),
                'today'           => (int) ($row['today'] ?? 0),
                'errors'          => (int) ($row['errors'] ?? 0),
                'avg_duration_ms' => $row['avg_duration_ms'] !== null ? (int) round((float) $row['avg_duration_ms']) : null,
                'tool_usage'      => (int) ($row['tool_usage'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['total' => 0, 'today' => 0, 'errors' => 0, 'avg_duration_ms' => null, 'tool_usage' => 0];
        }
    }

    public function getRecent($search = '', $limit = 50, $offset = 0)
    {
        if (!$this->db->isConnected()) {
            return [];
        }

        $limit = max(1, min(200, (int) $limit));
        $offset = max(0, (int) $offset);
        $sql = "SELECT cl.*, u.name AS user_name, u.email AS user_email
                FROM ai_chat_logs cl
                LEFT JOIN users u ON u.id = cl.user_id";
        if ($search !== '') {
            $sql .= " WHERE cl.query LIKE :search OR cl.reply LIKE :search OR u.name LIKE :search OR u.email LIKE :search";
        }
        $sql .= " ORDER BY cl.created_at DESC, cl.id DESC LIMIT :limit OFFSET :offset";

        try {
            $this->db->query($sql);
            if ($search !== '') {
                $this->db->bind(':search', '%' . $search . '%');
            }
            $this->db->bind(':limit', $limit, PDO::PARAM_INT);
            $this->db->bind(':offset', $offset, PDO::PARAM_INT);
            return $this->db->resultSet();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function countAll($search = '')
    {
        if (!$this->db->isConnected()) {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS cnt FROM ai_chat_logs cl LEFT JOIN users u ON u.id = cl.user_id";
        if ($search !== '') {
            $sql .= " WHERE cl.query LIKE :search OR cl.reply LIKE :search OR u.name LIKE :search OR u.email LIKE :search";
        }

        try {
            $this->db->query($sql);
            if ($search !== '') {
                $this->db->bind(':search', '%' . $search . '%');
            }
            $row = $this->db->single();
            return (int) ($row['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function getSummary()
    {
        if (!$this->db->isConnected()) {
            return ['total' => 0, 'today' => 0, 'uniqueUsers' => 0];
        }

        try {
            $this->db->query("SELECT COUNT(*) AS total,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
                COUNT(DISTINCT user_id) AS unique_users
                FROM ai_chat_logs");
            $row = $this->db->single() ?: [];
            return [
                'total'       => (int) ($row['total'] ?? 0),
                'today'       => (int) ($row['today'] ?? 0),
                'uniqueUsers' => (int) ($row['unique_users'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['total' => 0, 'today' => 0, 'uniqueUsers' => 0];
        }
    }
}
