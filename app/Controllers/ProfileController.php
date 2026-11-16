<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

/**
 * Self-service profile and password management for every authenticated role.
 */
class ProfileController extends BaseController
{
    public function index()
    {
        $auth = new AuthMiddleware();
        $auth->handle();

        $db = new Database();
        $user = null;
        if ($db->isConnected()) {
            $db->query("SELECT id, role, name, email, username, phone, is_active, created_at FROM users WHERE id = :id LIMIT 1");
            $db->bind(':id', (int) $_SESSION['user']['id']);
            $user = $db->single();
        }

        $this->renderLayout('profile/index', [
            'title'      => 'Profil Saya - TKJ Service Hub',
            'activeMenu' => 'profile',
            'user'       => $user ?: $_SESSION['user'],
            'csrfToken'  => Csrf::token('profile'),
        ]);
    }

    public function update()
    {
        $this->requirePost();
        if (!Csrf::validate('profile')) {
            $this->flash('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', false);
            $this->redirect('/profile');
        }

        $userId = (int) $_SESSION['user']['id'];
        $name = trim((string) ($_POST['name'] ?? ''));
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            $this->flash('Nama harus 3-100 karakter.', false);
            $this->redirect('/profile');
        }
        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
            $this->flash('Username harus 3-30 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.', false);
            $this->redirect('/profile');
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
            $this->flash('Nomor telepon tidak valid.', false);
            $this->redirect('/profile');
        }

        $db = new Database();
        if (!$db->isConnected()) {
            $this->flash('Database tidak tersedia.', false);
            $this->redirect('/profile');
        }

        $db->query("SELECT id FROM users WHERE username = :username AND id <> :id LIMIT 1");
        $db->bind(':username', $username);
        $db->bind(':id', $userId);
        if ($db->single()) {
            $this->flash('Username sudah digunakan akun lain.', false);
            $this->redirect('/profile');
        }

        $db->query("UPDATE users SET name = :name, username = :username, phone = :phone WHERE id = :id");
        $db->bind(':name', $name);
        $db->bind(':username', $username);
        $db->bind(':phone', $phone !== '' ? $phone : null);
        $db->bind(':id', $userId);

        if (!$db->execute()) {
            $this->flash('Profil gagal diperbarui.', false);
            $this->redirect('/profile');
        }

        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['username'] = $username;
        $_SESSION['user']['phone'] = $phone;
        $this->flash('Profil berhasil diperbarui.', true);
        $this->redirect('/profile');
    }

    public function password()
    {
        $this->requirePost();
        if (!Csrf::validate('profile')) {
            $this->flash('Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.', false);
            $this->redirect('/profile');
        }

        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            $this->flash('Semua kolom password wajib diisi.', false);
            $this->redirect('/profile');
        }
        if (strlen($new) < 8) {
            $this->flash('Password baru minimal 8 karakter.', false);
            $this->redirect('/profile');
        }
        if ($new !== $confirm) {
            $this->flash('Konfirmasi password baru tidak cocok.', false);
            $this->redirect('/profile');
        }

        $db = new Database();
        if (!$db->isConnected()) {
            $this->flash('Database tidak tersedia.', false);
            $this->redirect('/profile');
        }

        $db->query("SELECT password FROM users WHERE id = :id LIMIT 1");
        $db->bind(':id', (int) $_SESSION['user']['id']);
        $row = $db->single();

        if (!$row || !password_verify($current, (string) $row['password'])) {
            $this->flash('Password saat ini salah.', false);
            $this->redirect('/profile');
        }

        $db->query("UPDATE users SET password = :password WHERE id = :id");
        $db->bind(':password', password_hash($new, PASSWORD_BCRYPT));
        $db->bind(':id', (int) $_SESSION['user']['id']);

        if (!$db->execute()) {
            $this->flash('Password gagal diubah.', false);
            $this->redirect('/profile');
        }

        $this->flash('Password berhasil diubah. Gunakan password baru pada login berikutnya.', true);
        $this->redirect('/profile');
    }

    private function requirePost()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->redirect('/profile');
        }

        $auth = new AuthMiddleware();
        $auth->handle();
    }

    private function flash($message, $success)
    {
        Flasher::setMessage($message, $success ? 'Berhasil' : 'Gagal', $success ? 'success' : 'danger');
    }

    private function redirect($path)
    {
        header('Location: ' . BASEURL . $path);
        exit;
    }
}
