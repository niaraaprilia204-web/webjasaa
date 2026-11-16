<?php

require_once __DIR__ . '/BaseModel.php';

class ServiceCategory extends BaseModel
{
    protected $table = 'service_categories';

    public function services($categoryId)
    {
        if (!$this->db->isConnected()) return [];
        $this->db->query("SELECT * FROM services WHERE category_id = :cid AND is_active = 1");
        $this->db->bind(':cid', $categoryId);
        return $this->db->resultSet();
    }
}
