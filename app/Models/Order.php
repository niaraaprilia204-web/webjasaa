<?php

require_once __DIR__ . '/BaseModel.php';

class Order extends BaseModel
{
    protected $table = 'orders';

    public function findByCode($code)
    {
        if (!$this->db->isConnected()) return null;
        $this->db->query("SELECT o.*, s.title as service_name, c.address as customer_address, u.name as customer_name, u.phone as customer_phone FROM orders o JOIN services s ON o.service_id = s.id JOIN customers c ON o.customer_id = c.id JOIN users u ON c.user_id = u.id WHERE o.order_code = :code");
        $this->db->bind(':code', $code);
        return $this->db->single();
    }

    public function customer($orderId)
    {
        if (!$this->db->isConnected()) return null;
        $this->db->query("SELECT c.*, u.name, u.email, u.phone FROM customers c JOIN users u ON c.user_id = u.id JOIN orders o ON o.customer_id = c.id WHERE o.id = :id");
        $this->db->bind(':id', $orderId);
        return $this->db->single();
    }

    public function workOrders($orderId)
    {
        if (!$this->db->isConnected()) return [];
        $this->db->query("SELECT wo.*, u.name as technician_name, t.nisn, t.class_grade FROM work_orders wo JOIN technicians t ON wo.technician_id = t.id JOIN users u ON t.user_id = u.id WHERE wo.order_id = :id");
        $this->db->bind(':id', $orderId);
        return $this->db->resultSet();
    }

    public function files($orderId)
    {
        if (!$this->db->isConnected()) return [];
        $this->db->query("SELECT * FROM order_files WHERE order_id = :id ORDER BY uploaded_at ASC");
        $this->db->bind(':id', $orderId);
        return $this->db->resultSet();
    }

    public function review($orderId)
    {
        if (!$this->db->isConnected()) return null;
        $this->db->query("SELECT * FROM reviews WHERE order_id = :id");
        $this->db->bind(':id', $orderId);
        return $this->db->single();
    }

    public function updateStatus($orderId, $status, $operatorNotes = null)
    {
        if (!$this->db->isConnected()) return false;
        $sql = "UPDATE orders SET status = :status";
        if ($operatorNotes !== null) {
            $sql .= ", operator_notes = :notes";
        }
        if ($status === 'completed') {
            $sql .= ", completed_at = NOW()";
        }
        $sql .= " WHERE id = :id";

        $this->db->query($sql);
        $this->db->bind(':status', $status);
        $this->db->bind(':id', $orderId);
        if ($operatorNotes !== null) {
            $this->db->bind(':notes', $operatorNotes);
        }
        return $this->db->execute();
    }
}
