<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Log pengiriman notifikasi WhatsApp (Fonnte).
 *
 * Tabel ini hanya menyimpan metadata pengiriman. Token Fonnte, password,
 * OTP, dan token autentikasi TIDAK PERNAH disimpan di sini.
 */
class WhatsappNotificationLog extends BaseModel
{
    protected $table = 'whatsapp_notification_logs';

    /**
     * Insert a log row only when (event, reference_id) is not present yet.
     *
     * Uses INSERT IGNORE so the unique key performs the idempotency check:
     * a duplicate event returns null and no notification is sent again.
     *
     * @return int|null New log id, or null when the event already exists.
     */
    public function createIfAbsent(array $data)
    {
        if (!$this->db->isConnected()) {
            return null;
        }

        $allowed = [
            'event', 'activity_type', 'reference_id', 'idempotency_key',
            'recipient', 'recipient_name', 'recipient_user_id', 'provider', 'status',
            'business_status', 'message_preview', 'message_body', 'error_message',
            'http_code', 'provider_message_id', 'attempts', 'sent_at',
        ];

        $fields = [];
        $placeholders = [];
        $values = [];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $fields[] = $field;
            $placeholders[] = ':' . $field;
            $values[$field] = $data[$field];
        }

        if (empty($fields)) {
            return null;
        }

        $sql = "INSERT IGNORE INTO {$this->table} (" . implode(', ', $fields) . ') VALUES ('
            . implode(', ', $placeholders) . ')';

        $this->db->query($sql);
        foreach ($values as $field => $value) {
            $this->db->bind(':' . $field, $value);
        }

        if (!$this->db->execute()) {
            return null;
        }

        // INSERT IGNORE silently skips the row when the unique key matches.
        if ($this->db->rowCount() === 0) {
            return null;
        }

        return (int) $this->db->lastInsertId();
    }

    /**
     * Mark a notification as successfully accepted by Fonnte.
     */
    public function markSent($id, $httpCode = null, $providerMessageId = null, $attempts = null)
    {
        return $this->updateStatus($id, 'sent', null, $httpCode, $providerMessageId, $attempts);
    }

    /**
     * Mark a notification as failed. Only a short, safe reason is stored.
     */
    public function markFailed($id, $errorMessage = null, $httpCode = null, $attempts = null)
    {
        return $this->updateStatus($id, 'failed', $errorMessage, $httpCode, null, $attempts);
    }

    private function updateStatus($id, $status, $errorMessage = null, $httpCode = null, $providerMessageId = null, $attempts = null)
    {
        if (!$this->db->isConnected()) {
            return false;
        }

        $data = ['status' => $status];

        if ($status === 'sent') {
            $data['sent_at'] = date('Y-m-d H:i:s');
        }

        if ($errorMessage !== null) {
            $data['error_message'] = mb_substr((string) $errorMessage, 0, 255);
        }

        if ($httpCode !== null && (int) $httpCode > 0) {
            $data['http_code'] = (int) $httpCode;
        }

        if ($providerMessageId !== null && (string) $providerMessageId !== '') {
            $data['provider_message_id'] = mb_substr((string) $providerMessageId, 0, 64);
        }

        if ($attempts !== null && (int) $attempts > 0) {
            $data['attempts'] = (int) $attempts;
        }

        $sql = "UPDATE {$this->table} SET";
        $parts = [];
        $params = [];

        foreach ($data as $field => $value) {
            $parts[] = "{$field} = :{$field}";
            $params[':' . $field] = $value;
        }

        $sql .= ' ' . implode(', ', $parts) . ' WHERE id = :id';
        $params[':id'] = (int) $id;

        $this->db->query($sql);
        foreach ($params as $name => $value) {
            $this->db->bind($name, $value);
        }

        return $this->db->execute();
    }

    /**
     * Most recent notification logs for the admin dashboard.
     */
    public function recent($limit = 50)
    {
        if (!$this->db->isConnected()) {
            return [];
        }

        $limit = (int) $limit;
        if ($limit < 1) {
            $limit = 50;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $this->db->query(
            "SELECT id, event, activity_type, reference_id, idempotency_key,
                    recipient, recipient_name, recipient_user_id, provider, status,
                    business_status, message_preview, message_body, error_message,
                    http_code, provider_message_id, attempts, sent_at, created_at
               FROM {$this->table}
              ORDER BY id DESC
              LIMIT :row_limit"
        );
        $this->db->bind(':row_limit', $limit);

        $rows = $this->db->resultSet();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Aggregated counters for the admin dashboard.
     */
    public function summary()
    {
        if (!$this->db->isConnected()) {
            return [
                'total' => 0, 'sent' => 0, 'failed' => 0, 'pending' => 0,
                'last_sent_at' => null, 'by_activity' => [], 'by_business_status' => [],
            ];
        }

        $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    MAX(CASE WHEN status = 'sent' THEN sent_at END) AS last_sent_at
               FROM {$this->table}"
        );
        $row = $this->db->single();
        $row = is_array($row) ? $row : [];

        $this->db->query(
            "SELECT activity_type AS label, COUNT(*) AS total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed
               FROM {$this->table}
              GROUP BY activity_type
              ORDER BY total DESC"
        );
        $byActivity = $this->db->resultSet();

        $this->db->query(
            "SELECT business_status AS label, COUNT(*) AS total
               FROM {$this->table}
              GROUP BY business_status
              ORDER BY total DESC"
        );
        $byBusiness = $this->db->resultSet();

        return [
            'total'              => (int) ($row['total'] ?? 0),
            'sent'               => (int) ($row['sent'] ?? 0),
            'failed'             => (int) ($row['failed'] ?? 0),
            'pending'            => (int) ($row['pending'] ?? 0),
            'last_sent_at'       => $row['last_sent_at'] ?? null,
            'by_activity'        => is_array($byActivity) ? $byActivity : [],
            'by_business_status' => is_array($byBusiness) ? $byBusiness : [],
        ];
    }
}
