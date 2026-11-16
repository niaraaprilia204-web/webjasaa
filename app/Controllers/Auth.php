<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/AuthService.php';

class Auth extends BaseController
{
    private $authService;

    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
    }

    public function login()
    {
        // If already logged in, redirect to their role dashboard
        if (isset($_SESSION['user']) && !empty($_SESSION['user']['role'])) {
            $targetUrl = $this->authService->getDashboardUrlByRole($_SESSION['user']['role']);
            header('Location: ' . $targetUrl);
            exit;
        }

        $data = [
            'title'      => 'Login - TKJ Service Hub',
            'activeMenu' => 'login'
        ];

        $this->renderLayout('auth/login', $data);
    }

    public function loginProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/auth/login');
            exit;
        }

        $login = $_POST['login'] ?? ($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = $this->authService->login($login, $password);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Login Gagal', 'danger');
            header('Location: ' . BASEURL . '/auth/login');
            exit;
        }

        Flasher::setMessage($result['message'], 'Login Berhasil', 'success');
        header('Location: ' . $result['data']['targetUrl']);
        exit;
    }

    public function register()
    {
        if (isset($_SESSION['user']) && !empty($_SESSION['user']['role'])) {
            $targetUrl = $this->authService->getDashboardUrlByRole($_SESSION['user']['role']);
            header('Location: ' . $targetUrl);
            exit;
        }

        $data = [
            'title'      => 'Registrasi Akun Customer - TKJ Service Hub',
            'activeMenu' => 'register'
        ];

        $this->renderLayout('auth/register', $data);
    }

    public function registerProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/auth/register');
            exit;
        }

        $result = $this->authService->registerCustomer($_POST);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Registrasi Gagal', 'danger');
            header('Location: ' . BASEURL . '/auth/register');
            exit;
        }

        Flasher::setMessage($result['message'], 'Sukses', 'success');
        header('Location: ' . BASEURL . '/auth/login');
        exit;
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION['user']);
        session_destroy();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        Flasher::setMessage('Anda telah berhasil keluar dari akun.', 'Logout Berhasil', 'info');
        header('Location: ' . BASEURL . '/auth/login');
        exit;
    }
}
