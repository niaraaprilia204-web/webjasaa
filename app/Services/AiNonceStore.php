<?php

/**
 * Small file-backed replay guard for internal AI requests.
 *
 * A nonce is accepted once until its signed token expires. The store is kept
 * outside the web root and contains only hashed nonce names + expiry timestamps.
 */
class AiNonceStore
{
    private $directory;

    public function __construct($directory = null)
    {
        $this->directory = $directory ?: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'webjasa_ai_nonces');
    }

    /**
     * Consume a nonce exactly once.
     *
     * Returns false when the nonce was already used, when the store is not
     * writable, or when locking fails. Callers must fail closed.
     */
    public function consume($issuer, $nonce, $expiresAt)
    {
        $issuer = (string) $issuer;
        $nonce = (string) $nonce;
        $expiresAt = max(time(), (int) $expiresAt);

        if ($issuer === '' || $nonce === '' || !$this->ensureDirectory()) {
            return false;
        }

        $path = $this->path($issuer, $nonce);
        $handle = @fopen($path, 'c+');
        if (!$handle) {
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }

            $existing = stream_get_contents($handle);
            $existingExpiry = (int) trim((string) $existing);
            if ($existingExpiry >= time()) {
                return false;
            }

            if (!ftruncate($handle, 0)) {
                return false;
            }
            rewind($handle);
            if (fwrite($handle, (string) $expiresAt) === false) {
                return false;
            }
            fflush($handle);

            if (random_int(1, 50) === 1) {
                $this->cleanupExpired();
            }

            return true;
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    private function path($issuer, $nonce)
    {
        return $this->directory . DIRECTORY_SEPARATOR
            . hash('sha256', $issuer . '|' . $nonce) . '.nonce';
    }

    private function ensureDirectory()
    {
        if (is_dir($this->directory)) {
            return is_writable($this->directory);
        }

        if (!@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            return false;
        }

        return is_writable($this->directory);
    }

    private function cleanupExpired()
    {
        $files = @glob($this->directory . DIRECTORY_SEPARATOR . '*.nonce');
        if (!is_array($files)) {
            return;
        }

        $cutoff = time() - 60;
        foreach ($files as $file) {
            $expiry = (int) trim((string) @file_get_contents($file));
            if ($expiry > 0 && $expiry < $cutoff) {
                @unlink($file);
            }
        }
    }
}
