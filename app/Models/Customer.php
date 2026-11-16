<?php

require_once __DIR__ . '/BaseModel.php';

class Customer extends BaseModel
{
    protected $table = 'customers';

    public function user($customerId)
    {
        if (!$this->db->isConnected()) return null;
        $this->db->query("SELECT u.* FROM users u JOIN customers c ON c.user_id = u.id WHERE c.id = :id");
        $this->db->bind(':id', $customerId);
        return $this->db->single();
    }

    public function orders($customerId)
    {
        if (!$this->db->isConnected()) return [];
        $this->db->query(
            "SELECT o.*, s.title AS service_name
               FROM orders o
               JOIN services s ON s.id = o.service_id
              WHERE o.customer_id = :cid
              ORDER BY o.created_at DESC, o.id DESC"
        );
        $this->db->bind(':cid', $customerId);
        return $this->db->resultSet();
    }

    /**
     * Find the customer profile row that belongs to a user id.
     */
    public function findByUserId($userId)
    {
        if (!$this->db->isConnected()) return null;
        $this->db->query("SELECT * FROM customers WHERE user_id = :uid LIMIT 1");
        $this->db->bind(':uid', (int) $userId);
        return $this->db->single();
    }
}
