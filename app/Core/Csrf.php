<?php

/**
 * Small CSRF helper used by customer and technician forms.
 * Existing admin/operator screens keep their own per-area tokens.
 */
class Csrf
{
    public static function token(string $key = 'app'): string
    {
        $sessionKey = $key . '_csrf';
        if (empty($_SESSION[$sessionKey]) || !is_string($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = bin2hex(random_bytes(32));
        }
        return $_SESSION[$sessionKey];
    }

    public static function validate(string $key = 'app', ?string $submitted = null): bool
    {
        $sessionKey = $key . '_csrf';
        $expected = $_SESSION[$sessionKey] ?? '';
        if (!is_string($expected) || $expected === '') {
            return false;
        }

        if ($submitted === null) {
            $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        }

        return is_string($submitted) && $submitted !== '' && hash_equals($expected, $submitted);
    }
}
