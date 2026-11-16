<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * OTP Verification persistence.
 *
 * Stores only a password_hash() of the OTP, never the plaintext code.
 * All timestamps use the database server clock to avoid PHP/MySQL timezone drift.
 */
class OtpVerification extends BaseModel
{
    protected $table = 'otp_verifications';

    /**
     * Insert a new OTP row and return its ID (0 on failure).
     */
    public function createOtp($data)
    {
        if (!$this->db->isConnected()) {
            return 0;
        }

        $expiryMinutes = max(1, min(5, (int) ($data['expiry_minutes'] ?? OTP_EXPIRY_MINUTES)));
        $maxAttempts = max(1, min(10, (int) ($data['max_attempts'] ?? OTP_MAX_VERIFY_ATTEMPTS)));
        $purpose = $data['purpose'] ?? 'phone_verification';

        $sql = "INSERT INTO `otp_verifications`
                    (`user_id`, `phone`, `purpose`, `otp_hash`, `attempts`, `max_attempts`, `request_ip`, `expires_at`, `created_at`)
                VALUES
                    (:user_id, :phone, :purpose, :otp_hash, 0, :max_attempts, :request_ip,
                     DATE_ADD(NOW(), INTERVAL {$expiryMinutes} MINUTE), NOW())";

        $this->db->query($sql);
        $this->db->bind(':user_id', $data['user_id'] ?? null);
        $this->db->bind(':phone', $data['phone']);
        $this->db->bind(':purpose', $purpose);
        $this->db->bind(':otp_hash', $data['otp_hash']);
        $this->db->bind(':max_attempts', $maxAttempts);
        $this->db->bind(':request_ip', $data['request_ip'] ?? null);

        if (!$this->db->execute()) {
            return 0;
        }

        return $this->db->lastInsertId();
    }

    /**
     * Number of OTP requests from one phone within the rolling window.
     */
    public function countRecentByPhone($phone, $minutes)
    {
        if (!$this->db->isConnected()) {
            return 0;
        }

        $minutes = max(1, (int) $minutes);
        $sql = "SELECT COUNT(*) AS total
                FROM `otp_verifications`
                WHERE `phone` = :phone
                  AND `created_at` >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)";
        $this->db->query($sql);
        $this->db->bind(':phone', $phone);
        $row = $this->db->single();

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Number of OTP requests from one IP within the rolling window.
     */
    public function countRecentByIp($ip, $minutes)
    {
        if (!$this->db->isConnected() || empty($ip)) {
            return 0;
        }

        $minutes = max(1, (int) $minutes);
        $sql = "SELECT COUNT(*) AS total
                FROM `otp_verifications`
                WHERE `request_ip` = :ip
                  AND `created_at` >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)";
        $this->db->query($sql);
        $this->db->bind(':ip', $ip);
        $row = $this->db->single();

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Seconds until the oldest request in the window expires (for Retry-After messaging).
     */
    public function retryAfterForPhone($phone, $minutes)
    {
        if (!$this->db->isConnected()) {
            return max(1, (int) $minutes * 60);
        }

        $minutes = max(1, (int) $minutes);
        $sql = "SELECT TIMESTAMPDIFF(
                        SECOND,
                        NOW(),
                        DATE_ADD(MIN(`created_at`), INTERVAL {$minutes} MINUTE)
                    ) AS retry_after
                FROM `otp_verifications`
                WHERE `phone` = :phone
                  AND `created_at` >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)";
        $this->db->query($sql);
        $this->db->bind(':phone', $phone);
        $row = $this->db->single();

        $seconds = (int) ($row['retry_after'] ?? 0);

        return max(1, min($minutes * 60, $seconds));
    }

    /**
     * Latest unused, unexpired OTP for a phone number.
     */
    public function latestActiveByPhone($phone)
    {
        if (!$this->db->isConnected()) {
            return null;
        }

        $sql = "SELECT *
                FROM `otp_verifications`
                WHERE `phone` = :phone
                  AND `consumed_at` IS NULL
                  AND `expires_at` > NOW()
                ORDER BY `id` DESC
                LIMIT 1";
        $this->db->query($sql);
        $this->db->bind(':phone', $phone);

        return $this->db->single();
    }

    /**
     * Invalidate any previous active code before a new one is issued.
     */
    public function invalidateActiveByPhone($phone)
    {
        if (!$this->db->isConnected()) {
            return false;
        }

        $sql = "UPDATE `otp_verifications`
                SET `consumed_at` = NOW()
                WHERE `phone` = :phone
                  AND `consumed_at` IS NULL
                  AND `expires_at` > NOW()";
        $this->db->query($sql);
        $this->db->bind(':phone', $phone);

        return $this->db->execute();
    }

    public function incrementAttempts($id)
    {
        if (!$this->db->isConnected()) {
            return false;
        }

        $this->db->query("UPDATE `otp_verifications` SET `attempts` = `attempts` + 1 WHERE `id` = :id");
        $this->db->bind(':id', (int) $id);

        return $this->db->execute();
    }

    /**
     * Mark an OTP unusable (used, failed delivery, or locked after too many attempts).
     */
    public function consume($id)
    {
        if (!$this->db->isConnected()) {
            return false;
        }

        $this->db->query("UPDATE `otp_verifications` SET `consumed_at` = NOW() WHERE `id` = :id AND `consumed_at` IS NULL");
        $this->db->bind(':id', (int) $id);
        $this->db->execute();

        return $this->db->rowCount() > 0;
    }

    public function markProviderMessage($id, $messageId)
    {
        if (!$this->db->isConnected()) {
            return false;
        }

        $this->db->query("UPDATE `otp_verifications` SET `provider_message_id` = :message_id WHERE `id` = :id");
        $this->db->bind(':message_id', substr((string) $messageId, 0, 100));
        $this->db->bind(':id', (int) $id);

        return $this->db->execute();
    }

    /**
     * Atomically consume the OTP and, when logged in, mark the user's phone verified.
     * Both writes share this model's PDO connection, so the transaction is real.
     */
    public function consumeAndLinkUser($id, $userId, $phone)
    {
        if (!$this->db->isConnected()) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $this->db->query("UPDATE `otp_verifications`
                              SET `consumed_at` = NOW()
                              WHERE `id` = :id AND `consumed_at` IS NULL");
            $this->db->bind(':id', (int) $id);
            $this->db->execute();

            if ($this->db->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }

            if ($userId !== null && (int) $userId > 0) {
                $this->db->query("SELECT `id` FROM `users` WHERE `id` = :uid LIMIT 1");
                $this->db->bind(':uid', (int) $userId);

                if (!$this->db->single()) {
                    $this->db->rollBack();
                    return false;
                }

                $this->db->query("UPDATE `users`
                                  SET `phone` = :phone,
                                      `phone_verified_at` = NOW(),
                                      `updated_at` = NOW()
                                  WHERE `id` = :uid");
                $this->db->bind(':phone', $phone);
                $this->db->bind(':uid', (int) $userId);

                if (!$this->db->execute()) {
                    $this->db->rollBack();
                    return false;
                }
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            try {
                $this->db->rollBack();
            } catch (\Throwable $ignored) {
                // No active transaction to roll back.
            }
            error_log('[OTP] Failed to consume verification row.');
            return false;
        }
    }

    /**
     * Housekeeping: remove rows older than two days.
     */
    public function cleanupOld()
    {
        if (!$this->db->isConnected()) {
            return false;
        }

        $this->db->query("DELETE FROM `otp_verifications` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 2 DAY)");

        return $this->db->execute();
    }
}
