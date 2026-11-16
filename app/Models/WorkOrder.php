<?php

require_once __DIR__ . '/BaseModel.php';

class WorkOrder extends BaseModel
{
    protected $table = 'work_orders';

    public function order($workOrderId)
    {
        if (!$this->db->isConnected()) return null;
        $this->db->query("SELECT o.* FROM orders o JOIN work_orders wo ON wo.order_id = o.id WHERE wo.id = :id");
        $this->db->bind(':id', $workOrderId);
        return $this->db->single();
    }

    public function technician($workOrderId)
    {
        if (!$this->db->isConnected()) return null;
        $this->db->query("SELECT t.*, u.name, u.email, u.phone FROM technicians t JOIN users u ON t.user_id = u.id JOIN work_orders wo ON wo.technician_id = t.id WHERE wo.id = :id");
        $this->db->bind(':id', $workOrderId);
        return $this->db->single();
    }
}
