<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Model tabel customer_reports (laporan/komplain customer).
 */
class CustomerReport extends BaseModel
{
    protected $table = 'customer_reports';

    /**
     * Daftar laporan milik seorang customer.
     */
    public function forUser($userId)
    {
        if (!$this->db->isConnected()) {
            return [];
        }

        $this->db->query(
            "SELECT r.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
               FROM customer_reports r
               JOIN users u ON u.id = r.user_id
              WHERE r.user_id = :user_id
              ORDER BY r.created_at DESC, r.id DESC"
        );
        $this->db->bind(':user_id', (int) $userId);

        $rows = $this->db->resultSet();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Semua laporan (untuk admin/operator).
     */
    public function all($statusFilter = '')
    {
        if (!$this->db->isConnected()) {
            return [];
        }

        $sql = "SELECT r.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
                   FROM customer_reports r
                   JOIN users u ON u.id = r.user_id";
        $params = [];

        if ($statusFilter !== '' && in_array($statusFilter, ['submitted', 'processing', 'resolved', 'rejected'], true)) {
            $sql .= " WHERE r.status = :status";
            $params[':status'] = $statusFilter;
        }

        $sql .= " ORDER BY r.created_at DESC, r.id DESC";

        $this->db->query($sql);
        foreach ($params as $name => $value) {
            $this->db->bind($name, $value);
        }

        $rows = $this->db->resultSet();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Temukan laporan milik user tertentu (untuk otorisasi pemilik).
     */
    public function findForUser($reportId, $userId)
    {
        if (!$this->db->isConnected()) {
            return null;
        }

        $this->db->query(
            "SELECT r.*, u.name AS customer_name, u.phone AS customer_phone
               FROM customer_reports r
               JOIN users u ON u.id = r.user_id
              WHERE r.id = :id AND r.user_id = :user_id
              LIMIT 1"
        );
        $this->db->bind(':id', (int) $reportId);
        $this->db->bind(':user_id', (int) $userId);

        return $this->db->single() ?: null;
    }

    /**
     * Perbarui status laporan beserta catatan admin.
     */
    public function updateStatus($reportId, $status, $adminNotes = null, $handledBy = null)
    {
        if (!$this->db->isConnected()) {
            return false;
        }

        $data = ['status' => $status];

        if ($adminNotes !== null) {
            $data['admin_notes'] = $adminNotes;
        }

        if ($handledBy !== null && (int) $handledBy > 0) {
            $data['handled_by'] = (int) $handledBy;
            $data['handled_at'] = date('Y-m-d H:i:s');
        }

        $fields = [];
        $params = [];
        foreach ($data as $field => $value) {
            $fields[] = "{$field} = :{$field}";
            $params[':' . $field] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . ' WHERE id = :id';
        $params[':id'] = (int) $reportId;

        $this->db->query($sql);
        foreach ($params as $name => $value) {
            $this->db->bind($name, $value);
        }

        return $this->db->execute();
    }
}
