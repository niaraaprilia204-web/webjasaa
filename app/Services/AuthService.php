<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Models/Customer.php';

/**
 * Authentication & Authorization Service
 */
class AuthService extends BaseService
{
    private $userModel;
    private $customerModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->customerModel = new Customer();
    }

    /**
     * Verify credentials and log user in
     */
    public function login($login, $password)
    {
        $login = trim((string) $login);

        if (empty($login) || empty($password)) {
            return $this->response(false, 'Email/username dan password wajib diisi.');
        }

        $users = $this->userModel->findByLogin($login);
        if (empty($users)) {
            return $this->response(false, 'Email/username atau password yang Anda masukkan salah.');
        }

        $user = $users[0];

        if (empty($user['is_active'])) {
            return $this->response(false, 'Akun Anda sedang dinonaktifkan. Silakan hubungi administrator.');
        }

        if (!password_verify($password, $user['password'])) {
            return $this->response(false, 'Email/username atau password yang Anda masukkan salah.');
        }

        // Initialize Session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user'] = [
            'id'       => $user['id'],
            'name'     => $user['name'],
            'email'    => $user['email'],
            'username' => $user['username'] ?? '',
            'role'     => $user['role'],
            'phone'    => $user['phone']
        ];

        $targetUrl = $this->getDashboardUrlByRole($user['role']);

        return $this->response(true, 'Selamat datang kembali, ' . htmlspecialchars($user['name']) . '!', [
            'role' => $user['role'],
            'targetUrl' => $targetUrl
        ]);
    }

    /**
     * Register new customer safely (Enforces role = customer on server)
     */
    public function registerCustomer($data)
    {
        $name = trim(htmlspecialchars($data['name'] ?? ''));
        $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $username = strtolower(trim((string) ($data['username'] ?? '')));
        $phone = trim(htmlspecialchars($data['phone'] ?? ''));
        $address = trim(htmlspecialchars($data['address'] ?? ''));
        $institution = trim(htmlspecialchars($data['institution'] ?? 'Masyarakat Umum'));
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';

        // Input Validation
        if (empty($name) || strlen($name) < 3) {
            return $this->response(false, 'Nama lengkap minimal 3 karakter.');
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response(false, 'Format alamat email tidak valid.');
        }

        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
            return $this->response(false, 'Username 3-30 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.');
        }

        $existing = $this->userModel->findByEmail($email);
        if (!empty($existing)) {
            return $this->response(false, 'Alamat email tersebut sudah terdaftar.');
        }

        $existingUsername = $this->userModel->findByUsername($username);
        if (!empty($existingUsername)) {
            return $this->response(false, 'Username tersebut sudah digunakan.');
        }

        if (empty($password) || strlen($password) < 6) {
            return $this->response(false, 'Password minimal 6 karakter.');
        }

        if ($password !== $passwordConfirm) {
            return $this->response(false, 'Konfirmasi password tidak cocok.');
        }

        // Secure password hashing
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Create User with enforced role = customer
        $userData = [
            'role'      => 'customer', // HARDCODED FOR SECURITY
            'name'      => $name,
            'email'     => $email,
            'username'  => $username,
            'password'  => $hashedPassword,
            'phone'     => $phone,
            'is_active' => 1
        ];

        $created = $this->userModel->create($userData);
        if (!$created) {
            return $this->response(false, 'Gagal meregistrasi akun. Silakan coba lagi.');
        }

        // Get newly inserted User ID
        $newUsers = $this->userModel->findByEmail($email);
        if (!empty($newUsers)) {
            $newUserId = $newUsers[0]['id'];
            $customerCreated = $this->customerModel->create([
                'user_id'     => $newUserId,
                'address'     => $address,
                'institution' => $institution
            ]);

            // Notifikasi WhatsApp ke Admin HANYA setelah baris customer
            // benar-benar tersimpan. Kegagalan notifikasi tidak pernah
            // membatalkan pendaftaran yang sudah berhasil.
            if ($customerCreated) {
                $customerRow = $this->customerModel->findByUserId((int) $newUserId);
                if (!empty($customerRow['id'])) {
                    $this->notifyAdminCustomerRegistered((int) $customerRow['id']);
                }
            }
        }

        return $this->response(true, 'Registrasi akun customer berhasil! Silakan login.');
    }

    /**
     * Kirim notifikasi WhatsApp ke Admin tentang customer baru.
     * Semua error ditangkap: notifikasi tidak boleh menggagalkan registrasi.
     */
    private function notifyAdminCustomerRegistered($customerId)
    {
        try {
            require_once __DIR__ . '/WhatsappNotificationService.php';
            $service = WhatsappNotificationService::create();
            $service->notifyCustomerRegistered((int) $customerId);
        } catch (\Throwable $e) {
            error_log('[WhatsApp Notify] Notifikasi customer baru gagal diproses.');
        }
    }

    /**
     * Get redirect URL by role
     */
    public function getDashboardUrlByRole($role)
    {
        switch ($role) {
            case 'customer':
                return BASEURL . '/customer/dashboard';
            case 'technician':
                return BASEURL . '/technician/dashboard';
            case 'operator':
                return BASEURL . '/operator/dashboard';
            case 'principal':
                return BASEURL . '/principal/dashboard';
            case 'admin':
                return BASEURL . '/admin/dashboard';
            default:
                return BASEURL;
        }
    }
}
