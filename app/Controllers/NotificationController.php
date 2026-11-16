<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Notification.php';

class NotificationController extends BaseController
{
    public function markAllRead()
    {
        if (empty($_SESSION['user']['id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/auth/login');
            exit;
        }

        if (!Csrf::validate('notification')) {
            Flasher::setMessage('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 'Gagal', 'danger');
            $this->redirectBack();
            return;
        }

        $model = new Notification();
        $model->markAllRead((int) $_SESSION['user']['id']);
        Flasher::setMessage('Semua notifikasi ditandai sudah dibaca.', 'Berhasil', 'success');
        $this->redirectBack();
    }

    private function redirectBack()
    {
        $target = $_SERVER['HTTP_REFERER'] ?? '';
        if ($target === '') {
            $role = $_SESSION['user']['role'] ?? '';
            $dashboards = [
                'admin' => '/admin/dashboard',
                'operator' => '/operator/dashboard',
                'technician' => '/technician/dashboard',
                'customer' => '/customer/dashboard',
                'principal' => '/principal/dashboard',
            ];
            $target = BASEURL . ($dashboards[$role] ?? '/');
        }
        header('Location: ' . $target);
        exit;
    }
}
