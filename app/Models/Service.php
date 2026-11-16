<?php

require_once __DIR__ . '/BaseModel.php';

class Service extends BaseModel
{
    protected $table = 'services';

    public function category($serviceId)
    {
        if (!$this->db->isConnected()) return null;
        $this->db->query("SELECT sc.* FROM service_categories sc JOIN services s ON s.category_id = sc.id WHERE s.id = :id");
        $this->db->bind(':id', $serviceId);
        return $this->db->single();
    }

    public function allWithCategory()
    {
        if (!$this->db->isConnected()) return [];
        $this->db->query("SELECT s.*, sc.name as category_name, sc.icon as category_icon FROM services s JOIN service_categories sc ON s.category_id = sc.id WHERE s.is_active = 1 ORDER BY sc.id ASC, s.id ASC");
        return $this->db->resultSet();
    }
}
