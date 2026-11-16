<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../Models/Notification.php';

/**
 * Database-backed notifications for order events.
 * All writes are best-effort so a notification failure never blocks a workflow.
 */
class NotificationService extends BaseService
{
    private $notificationModel;

    public function __construct()
    {
        $this->notificationModel = new Notification();
    }

    public function notifyUser($userId, $title, $message, $link = null)
    {
        $userId = (int) $userId;
        if ($userId <= 0) return false;

        $db = new Database();
        if (!$db->isConnected()) return false;

        try {
            $db->query(
                "INSERT INTO notifications (user_id, title, message, link, is_read)
                 VALUES (:uid, :title, :message, :link, 0)"
            );
            $db->bind(':uid', $userId);
            $db->bind(':title', mb_substr(trim((string) $title), 0, 150));
            $db->bind(':message', mb_substr(trim((string) $message), 0, 1000));
            $db->bind(':link', $link !== null ? mb_substr((string) $link, 0, 255) : null);
            return $db->execute();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function notifyRole($role, $title, $message, $link = null)
    {
        $db = new Database();
        if (!$db->isConnected()) return 0;

        try {
            $db->query("SELECT id FROM users WHERE role = :role AND is_active = 1");
            $db->bind(':role', (string) $role);
            $users = $db->resultSet();
            $sent = 0;
            foreach ($users as $user) {
                if ($this->notifyUser((int) $user['id'], $title, $message, $link)) {
                    $sent++;
                }
            }
            return $sent;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function getForUser($userId, $limit = 8)
    {
        return $this->notificationModel->forUser($userId, $limit);
    }

    public function getUnreadCount($userId)
    {
        return $this->notificationModel->unreadCount($userId);
    }

    public function markAllRead($userId)
    {
        return $this->notificationModel->markAllRead($userId);
    }
}
