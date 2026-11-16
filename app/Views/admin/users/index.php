<?php
$users = $data['users'] ?? [];
$filters = $data['filters'] ?? [];
$csrfToken = $data['csrfToken'] ?? '';
$currentUserId = (int) ($data['currentUserId'] ?? 0);

$roleBadges = [
    'customer' => 'bg-primary',
    'technician' => 'bg-success',
    'operator' => 'bg-warning text-dark',
    'principal' => 'bg-danger',
    'admin' => 'bg-info text-dark',
];
$roleLabels = [
    'customer' => 'Customer',
    'technician' => 'Teknisi',
    'operator' => 'Operator',
    'principal' => 'Kepala Sekolah',
    'admin' => 'Administrator',
];
?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-danger border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-danger px-3 py-2 rounded-pill mb-2"><i class="bi bi-people me-1"></i> Manajemen Akun</span>
                <h2 class="fw-bold mb-0">Kelola Data Pengguna</h2>
                <div class="small text-white-50 mt-1"><?= count($users); ?> akun ditampilkan. Admin tidak dapat menonaktifkan akunnya sendiri.</div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASEURL; ?>/admin/dashboard" class="btn btn-outline-light rounded-pill btn-sm">
                    <i class="bi bi-speedometer2 me-1"></i> Dashboard Admin
                </a>
                <button type="button" class="btn btn-danger rounded-pill btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="bi bi-person-plus me-1"></i> Tambah Akun
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form action="<?= BASEURL; ?>/admin/users" method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-muted">Cari Nama / Email / Telepon</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="<?= htmlspecialchars((string) ($filters['search'] ?? '')); ?>" class="form-control border-start-0" placeholder="Kata kunci pengguna...">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Role</label>
                <select name="role" class="form-select">
                    <option value="">Semua role</option>
                    <?php foreach ($roleLabels as $roleKey => $roleLabel): ?>
                        <option value="<?= htmlspecialchars($roleKey); ?>" <?= (($filters['role'] ?? '') === $roleKey) ? 'selected' : ''; ?>><?= htmlspecialchars($roleLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua status</option>
                    <option value="1" <?= (($filters['status'] ?? '') === '1') ? 'selected' : ''; ?>>Aktif</option>
                    <option value="0" <?= (($filters['status'] ?? '') === '0') ? 'selected' : ''; ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= BASEURL; ?>/admin/users" class="btn btn-outline-secondary rounded-pill" title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Pengguna</th>
                        <th>Kontak</th>
                        <th>Role</th>
                        <th>Profil</th>
                        <th>Pesanan</th>
                        <th>Status</th>
                        <th>Terdaftar</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $u): ?>
                            <?php
                            $role = (string) $u['role'];
                            $isSelf = ((int) $u['id'] === $currentUserId);
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars((string) $u['name']); ?></div>
                                    <div class="small text-muted">ID #<?= (int) $u['id']; ?><?= $isSelf ? ' · Akun Anda' : ''; ?></div>
                                </td>
                                <td>
                                    <div class="small"><?= htmlspecialchars((string) $u['email']); ?></div>
                                    <div class="small text-muted">@<?= htmlspecialchars((string) ($u['username'] ?? '-')); ?> · <?= htmlspecialchars((string) ($u['phone'] ?: '-')); ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $roleBadges[$role] ?? 'bg-secondary'; ?> text-capitalize"><?= htmlspecialchars($roleLabels[$role] ?? $role); ?></span>
                                </td>
                                <td class="small text-muted">
                                    <?php if (!empty($u['institution'])): ?>
                                        <div><i class="bi bi-building me-1"></i><?= htmlspecialchars((string) $u['institution']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($u['nisn'])): ?>
                                        <div><i class="bi bi-person-vcard me-1"></i><?= htmlspecialchars((string) $u['nisn']); ?></div>
                                    <?php endif; ?>
                                    <?php if (empty($u['institution']) && empty($u['nisn'])): ?>-<?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= (int) ($u['total_orders'] ?? 0); ?></span></td>
                                <td>
                                    <?php if ((int) $u['is_active'] === 1): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted text-nowrap"><?= htmlspecialchars(date('d M Y', strtotime((string) $u['created_at']))); ?></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#editUser<?= (int) $u['id']; ?>">
                                            <i class="bi bi-pencil-square me-1"></i> Edit
                                        </button>
                                        <?php if (!$isSelf): ?>
                                            <form action="<?= BASEURL; ?>/admin/users/delete" method="POST" class="d-inline" onsubmit="return confirm('Hapus akun ini? Akun dengan riwayat pesanan/pekerjaan akan ditolak.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                                <input type="hidden" name="user_id" value="<?= (int) $u['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Hapus akun"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">Tidak ada pengguna yang cocok dengan filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <form action="<?= BASEURL; ?>/admin/users/create" method="POST">
                <div class="modal-header bg-dark text-white rounded-top-4">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i> Tambah Akun Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nama Lengkap</label>
                            <input type="text" name="name" class="form-control" required minlength="3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="operator">Operator</option>
                                <option value="technician">Teknisi</option>
                                <option value="customer">Customer</option>
                                <option value="admin">Administrator</option>
                                <option value="principal">Kepala Sekolah</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Username</label>
                            <input type="text" name="username" class="form-control" required minlength="3" maxlength="30" pattern="[A-Za-z0-9._-]{3,30}" placeholder="contoh: operator1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nomor Telepon</label>
                            <input type="text" name="phone" class="form-control" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Password Awal</label>
                            <input type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="createUserActive" value="1" checked>
                                <label class="form-check-label small fw-semibold" for="createUserActive">Aktifkan akun setelah dibuat</label>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info small rounded-3 mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i> Akun Operator/Teknisi/Admin yang dibuat di sini langsung dapat login. Password awal wajib diganti pengguna setelah login.
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold"><i class="bi bi-save me-1"></i> Buat Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($users as $u): ?>
    <?php
    $isSelf = ((int) $u['id'] === $currentUserId);
    $role = (string) $u['role'];
    ?>
    <div class="modal fade" id="editUser<?= (int) $u['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <form action="<?= BASEURL; ?>/admin/users/update" method="POST">
                    <div class="modal-header bg-dark text-white rounded-top-4">
                        <h5 class="modal-title"><i class="bi bi-person-gear me-2"></i> Edit Pengguna #<?= (int) $u['id']; ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="user_id" value="<?= (int) $u['id']; ?>">

                        <?php if ($isSelf): ?>
                            <div class="alert alert-info small rounded-3">
                                <i class="bi bi-info-circle me-1"></i> Ini akun Anda. Role dan status tidak dapat diubah di sini agar Anda tidak terkunci dari dashboard admin.
                            </div>
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nama Lengkap</label>
                                <input type="text" name="name" class="form-control" required minlength="3" value="<?= htmlspecialchars((string) $u['name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email</label>
                                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars((string) $u['email']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Username</label>
                                <input type="text" name="username" class="form-control" minlength="3" maxlength="30" pattern="[A-Za-z0-9._-]{3,30}" required value="<?= htmlspecialchars((string) ($u['username'] ?? '')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nomor Telepon</label>
                                <input type="text" name="phone" class="form-control" maxlength="20" value="<?= htmlspecialchars((string) ($u['phone'] ?? '')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Role</label>
                                <select name="role" class="form-select" <?= $isSelf ? 'disabled' : ''; ?>>
                                    <?php foreach ($roleLabels as $roleKey => $roleLabel): ?>
                                        <option value="<?= htmlspecialchars($roleKey); ?>" <?= $role === $roleKey ? 'selected' : ''; ?>><?= htmlspecialchars($roleLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($isSelf): ?><input type="hidden" name="role" value="<?= htmlspecialchars($role); ?>"><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Status Akun</label>
                                <select name="is_active" class="form-select" <?= $isSelf ? 'disabled' : ''; ?>>
                                    <option value="1" <?= ((int) $u['is_active'] === 1) ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="0" <?= ((int) $u['is_active'] === 0) ? 'selected' : ''; ?>>Nonaktif</option>
                                </select>
                                <?php if ($isSelf): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Password Baru <span class="text-muted fw-normal">(opsional)</span></label>
                                <input type="password" name="password" class="form-control" minlength="6" placeholder="Kosongkan jika tidak diubah">
                                <div class="form-text">Minimal 6 karakter. Password disimpan sebagai hash Bcrypt.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
