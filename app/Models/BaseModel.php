<?php

/**
 * Base Model Class
 * Abstraction layer for database operations providing reusable CRUD methods.
 */
class BaseModel
{
    protected $db;
    protected $table;
    protected $primaryKey = 'id';

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Fetch all records from table
     */
    public function all()
    {
        if (!$this->db->isConnected()) {
            return [];
        }
        $this->db->query("SELECT * FROM {$this->table}");
        return $this->db->resultSet();
    }

    /**
     * Find single record by Primary Key
     */
    public function find($id)
    {
        if (!$this->db->isConnected()) {
            return null;
        }
        $this->db->query("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id");
        $this->db->bind(':id', $id);
        return $this->db->single();
    }

    /**
     * Find records by column matching value
     */
    public function where($column, $value)
    {
        if (!$this->db->isConnected()) {
            return [];
        }
        $this->db->query("SELECT * FROM {$this->table} WHERE {$column} = :val");
        $this->db->bind(':val', $value);
        return $this->db->resultSet();
    }

    /**
     * Insert record
     */
    public function create($data)
    {
        if (!$this->db->isConnected()) {
            return false;
        }
        $fields = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$this->table} ({$fields}) VALUES ({$placeholders})";
        $this->db->query($sql);

        foreach ($data as $key => $value) {
            $this->db->bind(':' . $key, $value);
        }

        return $this->db->execute();
    }

    /**
     * Update record
     */
    public function update($id, $data)
    {
        if (!$this->db->isConnected()) {
            return false;
        }
        $updateFields = [];
        foreach ($data as $key => $value) {
            $updateFields[] = "{$key} = :{$key}";
        }
        $fieldsStr = implode(', ', $updateFields);

        $sql = "UPDATE {$this->table} SET {$fieldsStr} WHERE {$this->primaryKey} = :id";
        $this->db->query($sql);

        $this->db->bind(':id', $id);
        foreach ($data as $key => $value) {
            $this->db->bind(':' . $key, $value);
        }

        return $this->db->execute();
    }

    /**
     * Delete record
     */
    public function delete($id)
    {
        if (!$this->db->isConnected()) {
            return false;
        }
        $this->db->query("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }
}
