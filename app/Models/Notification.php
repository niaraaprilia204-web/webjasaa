<?php

require_once __DIR__ . '/BaseModel.php';

class Notification extends BaseModel
{
    protected $table = 'notifications';

    public function forUser($userId, $limit = 8)
    {
        if (!$this->db->isConnected()) return [];

        $limit = max(1, min(30, (int) $limit));
        $this->db->query(
            "SELECT * FROM notifications
              WHERE user_id = :uid
              ORDER BY created_at DESC, id DESC
              LIMIT :limit"
        );
        $this->db->bind(':uid', (int) $userId);
        $this->db->bind(':limit', $limit, PDO::PARAM_INT);
        return $this->db->resultSet();
    }

    public function unreadCount($userId)
    {
        if (!$this->db->isConnected()) return 0;

        $this->db->query("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = :uid AND is_read = 0");
        $this->db->bind(':uid', (int) $userId);
        $row = $this->db->single();
        return (int) ($row['cnt'] ?? 0);
    }

    public function markAllRead($userId)
    {
        if (!$this->db->isConnected()) return false;

        $this->db->query("UPDATE notifications SET is_read = 1 WHERE user_id = :uid");
        $this->db->bind(':uid', (int) $userId);
        return $this->db->execute();
    }

    public function markRead($id, $userId)
    {
        if (!$this->db->isConnected()) return false;

        $this->db->query("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
        $this->db->bind(':id', (int) $id);
        $this->db->bind(':uid', (int) $userId);
        return $this->db->execute();
    }
}
