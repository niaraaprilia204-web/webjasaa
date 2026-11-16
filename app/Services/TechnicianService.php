<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/OrderService.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/../Models/Order.php';
require_once __DIR__ . '/../Models/Technician.php';
require_once __DIR__ . '/../Models/WorkOrder.php';

/**
 * Technician field-work service with strict ownership checks.
 */
class TechnicianService extends BaseService
{
    private $technicianModel;
    private $workOrderModel;
    private $orderModel;
    private $orderService;
    private $notificationService;

    public function __construct()
    {
        $this->technicianModel = new Technician();
        $this->workOrderModel = new WorkOrder();
        $this->orderModel = new Order();
        $this->orderService = new OrderService();
        $this->notificationService = new NotificationService();
    }

    public function getProfileByUserId($userId)
    {
        $rows = $this->technicianModel->where('user_id', (int) $userId);
        return $rows[0] ?? null;
    }

    public function getWorkOrders($technicianId)
    {
        $db = new Database();
        if (!$db->isConnected()) return [];

        $db->query(
            "SELECT wo.*, o.order_code, o.problem_description, o.device_info, o.location_address,
                    o.scheduled_date, o.status AS order_status,
                    s.title AS service_name,
                    u.name AS customer_name, u.phone AS customer_phone
               FROM work_orders wo
               JOIN orders o ON o.id = wo.order_id
               JOIN services s ON s.id = o.service_id
               JOIN customers c ON c.id = o.customer_id
               JOIN users u ON u.id = c.user_id
              WHERE wo.technician_id = :tid
              ORDER BY FIELD(wo.status, 'in_progress', 'accepted', 'assigned', 'done'), wo.created_at DESC"
        );
        $db->bind(':tid', (int) $technicianId);
        return $db->resultSet();
    }

    public function getStats($technicianId)
    {
        $orders = $this->getWorkOrders($technicianId);
        $stats = ['total' => count($orders), 'active' => 0, 'done' => 0];
        foreach ($orders as $order) {
            if ($order['status'] === 'done') {
                $stats['done']++;
            } else {
                $stats['active']++;
            }
        }
        return $stats;
    }

    public function getWorkOrderDetail($workOrderId, $technicianId)
    {
        $db = new Database();
        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak tersedia.');
        }

        $sql = "SELECT wo.*, o.order_code, o.problem_description, o.device_info, o.location_address,
                       o.scheduled_date, o.operator_notes, o.status AS order_status,
                       o.payment_status, o.payment_amount,
                       s.title AS service_name,
                       c.address AS customer_address, c.institution,
                       u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email
                  FROM work_orders wo
                  JOIN orders o ON o.id = wo.order_id
                  JOIN services s ON s.id = o.service_id
                  JOIN customers c ON c.id = o.customer_id
                  JOIN users u ON u.id = c.user_id
                 WHERE wo.id = :wid AND wo.technician_id = :tid
                 LIMIT 1";
        try {
            $db->query($sql);
            $db->bind(':wid', (int) $workOrderId);
            $db->bind(':tid', (int) $technicianId);
            $workOrder = $db->single();
        } catch (\Throwable $e) {
            // Fall back for databases where the payment migration has not run yet.
            $db->query(str_replace(', o.payment_status, o.payment_amount', '', $sql));
            $db->bind(':wid', (int) $workOrderId);
            $db->bind(':tid', (int) $technicianId);
            $workOrder = $db->single();
        }

        if (!$workOrder) {
            return $this->response(false, 'Pekerjaan tidak ditemukan atau bukan tugas Anda.');
        }

        return $this->response(true, 'Detail pekerjaan ditemukan.', [
            'workOrder' => $workOrder,
            'files'     => $this->orderModel->files((int) $workOrder['order_id']),
        ]);
    }

    public function updateJob($workOrderId, $technicianId, $data)
    {
        $detail = $this->getWorkOrderDetail($workOrderId, $technicianId);
        if (!$detail['success']) {
            return $detail;
        }

        $workOrder = $detail['data']['workOrder'];
        $status = (string) ($data['status'] ?? $workOrder['status']);
        if (!in_array($status, ['accepted', 'in_progress', 'done'], true)) {
            return $this->response(false, 'Status pekerjaan tidak valid.');
        }

        $diagnosis = trim(strip_tags((string) ($data['diagnosis_notes'] ?? '')));
        $action = trim(strip_tags((string) ($data['action_taken'] ?? '')));
        $parts = trim(strip_tags((string) ($data['parts_used'] ?? '')));

        if (mb_strlen($diagnosis) > 2000 || mb_strlen($action) > 2000 || mb_strlen($parts) > 1000) {
            return $this->response(false, 'Catatan pekerjaan terlalu panjang.');
        }

        $update = [
            'status'          => $status,
            'diagnosis_notes' => $diagnosis,
            'action_taken'    => $action,
            'parts_used'      => $parts,
        ];
        if ($status === 'done') {
            $update['finished_at'] = date('Y-m-d H:i:s');
        }

        try {
            $this->workOrderModel->update((int) $workOrder['id'], $update);

            $orderStatus = $workOrder['order_status'];
            if ($status === 'accepted') {
                $orderStatus = 'assigned';
            } elseif ($status === 'in_progress') {
                $orderStatus = 'in_progress';
            } elseif ($status === 'done') {
                $orderStatus = 'completed';
            }

            if ($orderStatus !== $workOrder['order_status']) {
                $this->orderModel->updateStatus((int) $workOrder['order_id'], $orderStatus, null);
            }
        } catch (\Throwable $e) {
            return $this->response(false, 'Perubahan pekerjaan gagal disimpan.');
        }

        $userId = $this->customerUserIdForOrder((int) $workOrder['order_id']);
        $this->notificationService->notifyUser(
            $userId,
            'Status pekerjaan diperbarui',
            'Pekerjaan pada pesanan ' . $workOrder['order_code'] . ' kini berstatus ' . strtoupper($status) . '.',
            '/customer/orders/detail/' . $workOrder['order_code']
        );

        if ($status === 'done') {
            $this->notificationService->notifyRole(
                'operator',
                'Pekerjaan teknisi selesai',
                'Teknisi menyelesaikan pekerjaan pesanan ' . $workOrder['order_code'] . '.',
                '/operator/orders/detail/' . $workOrder['order_code']
            );
        }

        return $this->response(true, 'Status dan catatan pekerjaan berhasil diperbarui.');
    }

    public function uploadDocumentation($workOrderId, $technicianId, $file, $type, $caption = '')
    {
        $detail = $this->getWorkOrderDetail($workOrderId, $technicianId);
        if (!$detail['success']) {
            return $detail;
        }

        if (!in_array($type, ['before', 'after'], true)) {
            return $this->response(false, 'Jenis dokumentasi tidak valid.');
        }

        $workOrder = $detail['data']['workOrder'];
        return $this->orderService->storeOrderFile((int) $workOrder['order_id'], $file, $type, $caption);
    }

    private function customerUserIdForOrder($orderId)
    {
        $db = new Database();
        if (!$db->isConnected()) return 0;

        $db->query("SELECT u.id FROM users u JOIN customers c ON c.user_id = u.id JOIN orders o ON o.customer_id = c.id WHERE o.id = :id");
        $db->bind(':id', (int) $orderId);
        $row = $db->single();
        return (int) ($row['id'] ?? 0);
    }
}
