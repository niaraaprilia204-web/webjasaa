<?php

require_once __DIR__ . '/BaseModel.php';

class Review extends BaseModel
{
    protected $table = 'reviews';

    public function byCustomer($customerId)
    {
        return $this->where('customer_id', $customerId);
    }

    public function averageRating()
    {
        if (!$this->db->isConnected()) return 0;
        $this->db->query("SELECT AVG(rating) as avg_rating FROM reviews");
        $res = $this->db->single();
        return $res ? round($res['avg_rating'], 1) : 0;
    }
}
