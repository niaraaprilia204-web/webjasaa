<?php

require_once __DIR__ . '/BaseModel.php';

class OrderFile extends BaseModel
{
    protected $table = 'order_files';

    public function byOrder($orderId)
    {
        return $this->where('order_id', $orderId);
    }
}
