<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../Models/ChatLog.php';

/**
 * Shared reporting queries for Admin and Operator dashboards.
 */
class ReportService extends BaseService
{
    private $chatLogModel;

    public function __construct()
    {
        $this->chatLogModel = new ChatLog();
    }

    public function getOrderReport($startDate = '', $endDate = '')
    {
        $startDate = $this->normalizeDate($startDate);
        $endDate = $this->normalizeDate($endDate);
        $where = [];
        $params = [];

        if ($startDate !== '') {
            $where[] = 'DATE(o.created_at) >= :start_date';
            $params[':start_date'] = $startDate;
        }
        if ($endDate !== '') {
            $where[] = 'DATE(o.created_at) <= :end_date';
            $params[':end_date'] = $endDate;
        }

        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
        $statusRows = $this->fetchAll(
            "SELECT o.status, COUNT(*) AS cnt FROM orders o{$whereSql} GROUP BY o.status",
            $params
        );

        $statuses = [
            'submitted', 'verified', 'scheduled', 'assigned', 'in_progress',
            'waiting_confirmation', 'completed', 'cancelled',
        ];
        $statusCounts = array_fill_keys($statuses, 0);
        foreach ($statusRows as $row) {
            if (isset($statusCounts[$row['status']])) {
                $statusCounts[$row['status']] = (int) $row['cnt'];
            }
        }

        $totalOrders = array_sum($statusCounts);
        $completedOrders = $statusCounts['completed'];
        $cancelledOrders = $statusCounts['cancelled'];
        $activeOrders = $totalOrders - $completedOrders - $cancelledOrders;
        $revenueRow = $this->fetchOne("SELECT COALESCE(SUM(payment_amount), 0) AS revenue FROM orders WHERE payment_status = 'paid'");
        $revenueRaw = (float) ($revenueRow['revenue'] ?? 0);

        $reviewRow = $this->fetchOne(
            "SELECT COUNT(*) AS total, COALESCE(ROUND(AVG(rating), 1), 0) AS average_rating FROM reviews"
        );

        $joinDate = '';
        if ($startDate !== '') {
            $joinDate .= ' AND DATE(o.created_at) >= :start_date';
        }
        if ($endDate !== '') {
            $joinDate .= ' AND DATE(o.created_at) <= :end_date';
        }

        $topServices = $this->fetchAll(
            "SELECT s.title, sc.name AS category_name, COUNT(o.id) AS order_count,
                    SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) AS completed_count
               FROM services s
               LEFT JOIN service_categories sc ON sc.id = s.category_id
               LEFT JOIN orders o ON o.service_id = s.id{$joinDate}
              GROUP BY s.id, s.title, sc.name
              ORDER BY order_count DESC, s.title ASC
              LIMIT 5",
            $params
        );

        $technicianPerformance = $this->fetchAll(
            "SELECT u.name, t.class_grade, t.status,
                    COUNT(wo.id) AS total_work,
                    SUM(CASE WHEN wo.status = 'done' THEN 1 ELSE 0 END) AS done_work
               FROM technicians t
               JOIN users u ON u.id = t.user_id
               LEFT JOIN work_orders wo ON wo.technician_id = t.id
              GROUP BY t.id, u.name, t.class_grade, t.status
              ORDER BY done_work DESC, total_work DESC
              LIMIT 8"
        );

        $dailyTrend = $this->fetchAll(
            "SELECT DATE(created_at) AS day, COUNT(*) AS total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
               FROM orders
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
              GROUP BY DATE(created_at)
              ORDER BY day ASC"
        );

        return [
            'totalOrders'          => $totalOrders,
            'completedOrders'      => $completedOrders,
            'activeOrders'         => max(0, $activeOrders),
            'cancelledOrders'      => $cancelledOrders,
            'revenueRaw'           => $revenueRaw,
            'revenue'              => 'Rp ' . number_format($revenueRaw, 0, ',', '.'),
            'statusCounts'         => $statusCounts,
            'topServices'          => $topServices,
            'technicianPerformance'=> $technicianPerformance,
            'dailyTrend'           => $dailyTrend,
            'totalReviews'         => (int) ($reviewRow['total'] ?? 0),
            'averageRating'        => (float) ($reviewRow['average_rating'] ?? 0),
            'startDate'            => $startDate,
            'endDate'              => $endDate,
        ];
    }

    public function getUserReport()
    {
        $roleRows = $this->fetchAll("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role");
        $roles = ['customer', 'technician', 'operator', 'principal', 'admin'];
        $roleCounts = array_fill_keys($roles, 0);
        foreach ($roleRows as $row) {
            $roleCounts[$row['role']] = (int) $row['cnt'];
        }

        $activeRow = $this->fetchOne("SELECT COUNT(*) AS cnt FROM users WHERE is_active = 1");
        $newRow = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        return [
            'totalUsers'   => array_sum($roleCounts),
            'activeUsers'  => (int) ($activeRow['cnt'] ?? 0),
            'newUsers30d'  => (int) ($newRow['cnt'] ?? 0),
            'roleCounts'   => $roleCounts,
        ];
    }

    public function getChatReport()
    {
        $summary = $this->chatLogModel->getSummary();
        return [
            'totalChats' => (int) ($summary['total'] ?? 0),
            'todayChats' => (int) ($summary['today'] ?? 0),
            'chatUsers'  => (int) ($summary['uniqueUsers'] ?? 0),
        ];
    }

    public function getRecentOrders($limit = 5)
    {
        $limit = max(1, min(50, (int) $limit));
        return $this->fetchAll(
            "SELECT o.*, s.title AS service_name, u.name AS customer_name
               FROM orders o
               JOIN services s ON s.id = o.service_id
               JOIN customers c ON c.id = o.customer_id
               JOIN users u ON u.id = c.user_id
              ORDER BY o.created_at DESC, o.id DESC
              LIMIT :limit",
            [],
            [':limit' => $limit]
        );
    }

    private function normalizeDate($value)
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return '';
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        return ($date && $date->format('Y-m-d') === $value) ? $value : '';
    }

    private function fetchAll($sql, $params = [], $typedParams = [])
    {
        $db = new Database();
        if (!$db->isConnected()) {
            return [];
        }

        try {
            $db->query($sql);
            foreach ($params as $key => $value) {
                $db->bind($key, $value);
            }
            foreach ($typedParams as $key => $value) {
                $db->bind($key, $value, PDO::PARAM_INT);
            }
            return $db->resultSet();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fetchOne($sql, $params = [])
    {
        $db = new Database();
        if (!$db->isConnected()) {
            return [];
        }

        try {
            $db->query($sql);
            foreach ($params as $key => $value) {
                $db->bind($key, $value);
            }
            return $db->single() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
