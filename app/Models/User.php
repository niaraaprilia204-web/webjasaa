<?php

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel
{
    protected $table = 'users';

    public function findByEmail($email)
    {
        return $this->where('email', $email);
    }

    public function findByLogin($login)
    {
        if (!$this->db->isConnected()) return [];

        try {
            $this->db->query(
                "SELECT * FROM users WHERE email = :login OR username = :login LIMIT 1"
            );
            $this->db->bind(':login', (string) $login);
            $row = $this->db->single();
            return $row ? [$row] : [];
        } catch (\Throwable $e) {
            // Older databases without the username column can still log in.
            return $this->findByEmail($login);
        }
    }

    public function findByUsername($username)
    {
        return $this->where('username', $username);
    }

    public function customerProfile($userId)
    {
        if (!$this->db->isConnected()) return null;
        $this->db->query("SELECT u.id, u.name, u.email, u.username, u.phone, u.role, u.is_active, c.address, c.institution, c.notes FROM users u JOIN customers c ON c.user_id = u.id WHERE u.id = :uid");
        $this->db->bind(':uid', $userId);
        return $this->db->single();
    }

    public function technicianProfile($userId)
    {
        if (!$this->db->isConnected()) return null;
        $this->db->query("SELECT * FROM technicians WHERE user_id = :uid");
        $this->db->bind(':uid', $userId);
        return $this->db->single();
    }
}
