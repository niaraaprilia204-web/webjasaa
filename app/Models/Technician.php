<?php

require_once __DIR__ . '/BaseModel.php';

class Technician extends BaseModel
{
    protected $table = 'technicians';

    public function user($technicianId)
    {
        if (!$this->db->isConnected()) return null;
        $this->db->query("SELECT u.* FROM users u JOIN technicians t ON t.user_id = u.id WHERE t.id = :id");
        $this->db->bind(':id', $technicianId);
        return $this->db->single();
    }

    public function workOrders($technicianId)
    {
        if (!$this->db->isConnected()) return [];
        $this->db->query("SELECT wo.*, o.order_code, o.problem_description, s.title as service_name FROM work_orders wo JOIN orders o ON wo.order_id = o.id JOIN services s ON o.service_id = s.id WHERE wo.technician_id = :tid ORDER BY wo.created_at DESC");
        $this->db->bind(':tid', $technicianId);
        return $this->db->resultSet();
    }
}
