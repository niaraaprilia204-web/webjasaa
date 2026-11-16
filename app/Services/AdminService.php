<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/ReportService.php';
require_once __DIR__ . '/../Models/ChatLog.php';

/**
 * Administrator account, user, report, and catalog helpers.
 */
class AdminService extends BaseService
{
    private $reportService;
    private $chatLogModel;

    private $allowedRoles = ['customer', 'technician', 'operator', 'principal', 'admin'];

    public function __construct()
    {
        $this->reportService = new ReportService();
        $this->chatLogModel = new ChatLog();
    }

    public function getDashboardMetrics()
    {
        $orderReport = $this->reportService->getOrderReport();
        $userReport = $this->reportService->getUserReport();
        $chatReport = $this->reportService->getChatReport();

        return [
            'totalUsers'      => $userReport['totalUsers'],
            'activeUsers'     => $userReport['activeUsers'],
            'newUsers30d'     => $userReport['newUsers30d'],
            'roleCounts'      => $userReport['roleCounts'],
            'totalOrders'     => $orderReport['totalOrders'],
            'completedOrders' => $orderReport['completedOrders'],
            'activeOrders'    => $orderReport['activeOrders'],
            'cancelledOrders' => $orderReport['cancelledOrders'],
            'statusCounts'    => $orderReport['statusCounts'],
            'dailyTrend'      => $orderReport['dailyTrend'],
            'topServices'     => $orderReport['topServices'],
            'revenue'         => $orderReport['revenue'],
            'totalServices'   => $this->getServiceCount(),
            'totalChats'      => $chatReport['totalChats'],
            'todayChats'      => $chatReport['todayChats'],
        ];
    }

    public function getUsers($search = '', $role = '', $status = '')
    {
        $db = new Database();
        if (!$db->isConnected()) {
            return [];
        }

        $sql = "SELECT u.id, u.name, u.email, u.username, u.phone, u.role, u.is_active, u.created_at,
                       c.institution, c.address,
                       t.nisn, t.class_grade, t.status AS technician_status,
                       (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS total_orders
                  FROM users u
                  LEFT JOIN customers c ON c.user_id = u.id
                  LEFT JOIN technicians t ON t.user_id = u.id
                 WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (u.name LIKE :search OR u.email LIKE :search OR u.username LIKE :search OR u.phone LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($role !== '' && in_array($role, $this->allowedRoles, true)) {
            $sql .= " AND u.role = :role";
            $params[':role'] = $role;
        }

        if ($status !== '' && ($status === '0' || $status === '1')) {
            $sql .= " AND u.is_active = :is_active";
            $params[':is_active'] = (int) $status;
        }

        $sql .= " ORDER BY u.created_at DESC, u.id DESC";

        try {
            $db->query($sql);
            foreach ($params as $key => $value) {
                $db->bind($key, $value);
            }
            return $db->resultSet();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getRecentUsers($limit = 5)
    {
        $limit = max(1, min(20, (int) $limit));
        $db = new Database();
        if (!$db->isConnected()) {
            return [];
        }

        try {
            $db->query(
                "SELECT u.id, u.name, u.email, u.username, u.role, u.is_active, u.created_at
                   FROM users u
                  ORDER BY u.created_at DESC, u.id DESC
                  LIMIT :limit"
            );
            $db->bind(':limit', $limit, PDO::PARAM_INT);
            return $db->resultSet();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getRecentChatLogs($limit = 5)
    {
        return $this->chatLogModel->getRecent('', $limit, 0);
    }

    public function getServiceCount()
    {
        $db = new Database();
        if (!$db->isConnected()) {
            return 0;
        }

        try {
            $db->query("SELECT COUNT(*) AS cnt FROM services");
            $row = $db->single();
            return (int) ($row['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function updateUser($id, $data, $actorId)
    {
        $id = (int) $id;
        $actorId = (int) $actorId;

        if ($id <= 0) {
            return $this->response(false, 'Data pengguna tidak valid.');
        }

        $db = new Database();
        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak tersedia.');
        }

        try {
            $db->query("SELECT * FROM users WHERE id = :id LIMIT 1");
            $db->bind(':id', $id);
            $user = $db->single();
        } catch (\Throwable $e) {
            return $this->response(false, 'Data pengguna tidak dapat dibaca.');
        }

        if (!$user) {
            return $this->response(false, 'Pengguna tidak ditemukan.');
        }

        $name = trim(strip_tags((string) ($data['name'] ?? '')));
        $email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_SANITIZE_EMAIL);
        $username = strtolower(trim((string) ($data['username'] ?? ($user['username'] ?? ''))));
        $phone = trim(strip_tags((string) ($data['phone'] ?? '')));
        $role = (string) ($data['role'] ?? $user['role']);
        $isActive = isset($data['is_active']) ? (int) ((string) $data['is_active'] === '1') : (int) $user['is_active'];
        $password = (string) ($data['password'] ?? '');

        if (strlen($name) < 3) {
            return $this->response(false, 'Nama pengguna minimal 3 karakter.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response(false, 'Format email tidak valid.');
        }

        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
            return $this->response(false, 'Username 3-30 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.');
        }

        if (strlen($phone) > 20) {
            return $this->response(false, 'Nomor telepon maksimal 20 karakter.');
        }

        if (!in_array($role, $this->allowedRoles, true)) {
            return $this->response(false, 'Role pengguna tidak valid.');
        }

        // An administrator must not lock themselves out.
        if ($id === $actorId) {
            $role = $user['role'];
            $isActive = 1;
        }

        if ($password !== '' && strlen($password) < 6) {
            return $this->response(false, 'Password baru minimal 6 karakter.');
        }

        try {
            $db->query("SELECT id FROM users WHERE (email = :email OR username = :username) AND id <> :id LIMIT 1");
            $db->bind(':email', $email);
            $db->bind(':username', $username);
            $db->bind(':id', $id);
            $duplicate = $db->single();
            if ($duplicate) {
                return $this->response(false, 'Email atau username tersebut sudah digunakan pengguna lain.');
            }
        } catch (\Throwable $e) {
            return $this->response(false, 'Tidak dapat memeriksa keunikan email/username.');
        }

        $fields = [
            'name'      => $name,
            'email'     => $email,
            'username'  => $username,
            'phone'     => $phone !== '' ? $phone : null,
            'role'      => $role,
            'is_active' => $isActive,
        ];

        if ($password !== '') {
            $fields['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        try {
            $db->beginTransaction();

            $set = [];
            foreach (array_keys($fields) as $field) {
                $set[] = "{$field} = :{$field}";
            }
            $db->query("UPDATE users SET " . implode(', ', $set) . " WHERE id = :id");
            foreach ($fields as $key => $value) {
                $db->bind(':' . $key, $value);
            }
            $db->bind(':id', $id);
            $db->execute();

            // Keep role-specific profile rows consistent when a role changes.
            if ($role === 'customer') {
                $db->query("SELECT id FROM customers WHERE user_id = :uid LIMIT 1");
                $db->bind(':uid', $id);
                $profile = $db->single();
                if (!$profile) {
                    $db->query("INSERT INTO customers (user_id, institution) VALUES (:uid, :institution)");
                    $db->bind(':uid', $id);
                    $db->bind(':institution', 'Masyarakat Umum');
                    $db->execute();
                }
            }

            if ($role === 'technician') {
                $db->query("SELECT id FROM technicians WHERE user_id = :uid LIMIT 1");
                $db->bind(':uid', $id);
                $profile = $db->single();
                if (!$profile) {
                    $db->query("INSERT INTO technicians (user_id, status) VALUES (:uid, 'available')");
                    $db->bind(':uid', $id);
                    $db->execute();
                }
            }

            $db->commit();
        } catch (\Throwable $e) {
            try {
                $db->rollBack();
            } catch (\Throwable $ignored) {
            }
            return $this->response(false, 'Data pengguna gagal diperbarui.');
        }

        $message = 'Data pengguna berhasil diperbarui.';
        if ($id === $actorId) {
            $message .= ' Role dan status akun Anda sendiri tidak dapat diubah dari halaman ini.';
        }

        return $this->response(true, $message, ['user_id' => $id]);
    }

    public function createUser($data, $actorId)
    {
        $name = trim(strip_tags((string) ($data['name'] ?? '')));
        $email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_SANITIZE_EMAIL);
        $username = strtolower(trim((string) ($data['username'] ?? '')));
        $phone = trim(strip_tags((string) ($data['phone'] ?? '')));
        $role = (string) ($data['role'] ?? 'operator');
        $password = (string) ($data['password'] ?? '');
        $isActive = isset($data['is_active']) ? 1 : 0;

        if (strlen($name) < 3) {
            return $this->response(false, 'Nama pengguna minimal 3 karakter.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response(false, 'Format email tidak valid.');
        }
        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
            return $this->response(false, 'Username 3-30 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.');
        }
        if (strlen($phone) > 20) {
            return $this->response(false, 'Nomor telepon maksimal 20 karakter.');
        }
        if (!in_array($role, $this->allowedRoles, true)) {
            return $this->response(false, 'Role pengguna tidak valid.');
        }
        if (strlen($password) < 6) {
            return $this->response(false, 'Password minimal 6 karakter.');
        }

        $db = new Database();
        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak tersedia.');
        }

        try {
            $db->query("SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1");
            $db->bind(':email', $email);
            $db->bind(':username', $username);
            if ($db->single()) {
                return $this->response(false, 'Email atau username sudah digunakan.');
            }
        } catch (\Throwable $e) {
            return $this->response(false, 'Tidak dapat memeriksa keunikan akun.');
        }

        try {
            $db->beginTransaction();
            $db->query(
                "INSERT INTO users (role, name, email, username, password, phone, is_active)
                 VALUES (:role, :name, :email, :username, :password, :phone, :is_active)"
            );
            $db->bind(':role', $role);
            $db->bind(':name', $name);
            $db->bind(':email', $email);
            $db->bind(':username', $username);
            $db->bind(':password', password_hash($password, PASSWORD_BCRYPT));
            $db->bind(':phone', $phone !== '' ? $phone : null);
            $db->bind(':is_active', $isActive);
            $db->execute();
            $newId = (int) $db->lastInsertId();

            if ($role === 'customer') {
                $db->query("INSERT INTO customers (user_id, institution) VALUES (:uid, :institution)");
                $db->bind(':uid', $newId);
                $db->bind(':institution', 'Masyarakat Umum');
                $db->execute();
            }
            if ($role === 'technician') {
                $db->query("INSERT INTO technicians (user_id, status) VALUES (:uid, 'available')");
                $db->bind(':uid', $newId);
                $db->execute();
            }

            $db->commit();
        } catch (\Throwable $e) {
            try {
                $db->rollBack();
            } catch (\Throwable $ignored) {
            }
            return $this->response(false, 'Akun baru gagal dibuat.');
        }

        return $this->response(true, 'Akun ' . $role . ' berhasil dibuat.', ['user_id' => $newId]);
    }

    public function deleteUser($id, $actorId)
    {
        $id = (int) $id;
        $actorId = (int) $actorId;

        if ($id <= 0 || $id === $actorId) {
            return $this->response(false, 'Akun sendiri atau akun tidak valid tidak dapat dihapus.');
        }

        $db = new Database();
        if (!$db->isConnected()) {
            return $this->response(false, 'Database tidak tersedia.');
        }

        try {
            $db->query("SELECT id, role, name FROM users WHERE id = :id LIMIT 1");
            $db->bind(':id', $id);
            $user = $db->single();
            if (!$user) {
                return $this->response(false, 'Pengguna tidak ditemukan.');
            }

            $db->query("SELECT COUNT(*) AS cnt FROM orders o JOIN customers c ON c.id = o.customer_id WHERE c.user_id = :uid");
            $db->bind(':uid', $id);
            $orderCount = (int) (($db->single() ?: [])['cnt'] ?? 0);
            if ($orderCount > 0) {
                return $this->response(false, 'Akun memiliki riwayat pesanan dan tidak dapat dihapus. Nonaktifkan akun sebagai gantinya.');
            }

            $db->query("SELECT COUNT(*) AS cnt FROM work_orders wo JOIN technicians t ON t.id = wo.technician_id WHERE t.user_id = :uid");
            $db->bind(':uid', $id);
            $workCount = (int) (($db->single() ?: [])['cnt'] ?? 0);
            if ($workCount > 0) {
                return $this->response(false, 'Akun teknisi memiliki riwayat pekerjaan dan tidak dapat dihapus. Nonaktifkan akun sebagai gantinya.');
            }

            $db->query("DELETE FROM users WHERE id = :id");
            $db->bind(':id', $id);
            $db->execute();
        } catch (\Throwable $e) {
            return $this->response(false, 'Akun gagal dihapus karena masih terhubung dengan data lain.');
        }

        return $this->response(true, 'Akun ' . $user['name'] . ' berhasil dihapus.');
    }
}
