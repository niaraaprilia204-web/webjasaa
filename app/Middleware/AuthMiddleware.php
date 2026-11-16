<?php

require_once __DIR__ . '/BaseMiddleware.php';

/**
 * Auth Middleware
 * Verifies if user has an active authenticated session.
 */
class AuthMiddleware extends BaseMiddleware
{
    public function handle()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
            Flasher::setMessage('Silakan login terlebih dahulu untuk mengakses halaman ini.', 'Akses Ditolak', 'warning');
            header('Location: ' . BASEURL . '/auth/login');
            exit;
        }
    }
}
