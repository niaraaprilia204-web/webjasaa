<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Key/value application settings.
 *
 * This model intentionally degrades gracefully when the app_settings table has
 * not been migrated yet, so existing pages keep working.
 */
class Setting extends BaseModel
{
    protected $table = 'app_settings';

    public function get($key, $default = null)
    {
        if (!$this->db->isConnected()) {
            return $default;
        }

        try {
            $this->db->query("SELECT setting_value FROM app_settings WHERE setting_key = :key LIMIT 1");
            $this->db->bind(':key', (string) $key);
            $row = $this->db->single();
            return $row ? (string) $row['setting_value'] : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function set($key, $value)
    {
        if (!$this->db->isConnected()) {
            return false;
        }

        try {
            $this->db->query(
                "INSERT INTO app_settings (setting_key, setting_value)
                 VALUES (:key, :value)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP"
            );
            $this->db->bind(':key', (string) $key);
            $this->db->bind(':value', (string) $value);
            return $this->db->execute();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getAllKeyed()
    {
        if (!$this->db->isConnected()) {
            return [];
        }

        try {
            $this->db->query("SELECT setting_key, setting_value FROM app_settings");
            $rows = $this->db->resultSet();
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            return $settings;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
