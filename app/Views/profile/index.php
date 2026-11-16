<?php
$user = $data['user'];
$roleLabels = [
    'admin' => 'Administrator', 'operator' => 'Operator', 'technician' => 'Teknisi',
    'customer' => 'Customer', 'principal' => 'Kepala Sekolah',
];
?>

<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-primary border-3">
    <div class="container">
        <span class="badge bg-primary px-3 py-2 rounded-pill mb-2"><i class="bi bi-person-gear me-1"></i> Pengaturan Akun</span>
        <h2 class="fw-bold mb-0">Profil Saya</h2>
        <div class="small text-white-50 mt-1">Perbarui identitas akun dan password Anda.</div>
    </div>
</div>

<div class="container py-3 mb-5">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <div class="text-center mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 86px; height: 86px;">
                        <i class="bi bi-person-circle fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars((string) $user['name']); ?></h5>
                    <span class="badge bg-primary"><?= htmlspecialchars($roleLabels[$user['role']] ?? (string) $user['role']); ?></span>
                </div>
                <div class="small">
                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Email</span><span class="fw-semibold text-end"><?= htmlspecialchars((string) $user['email']); ?></span></div>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Username</span><span class="fw-semibold">@<?= htmlspecialchars((string) ($user['username'] ?? '-')); ?></span></div>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Telepon</span><span class="fw-semibold"><?= htmlspecialchars((string) ($user['phone'] ?: '-')); ?></span></div>
                    <div class="d-flex justify-content-between py-2"><span class="text-muted">Terdaftar</span><span class="fw-semibold"><?= !empty($user['created_at']) ? htmlspecialchars(date('d M Y', strtotime((string) $user['created_at']))) : '-'; ?></span></div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0"><i class="bi bi-person-vcard text-primary me-2"></i> Informasi Profil</h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/profile/update" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($data['csrfToken'] ?? ''); ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nama Lengkap</label>
                                <input type="text" name="name" class="form-control" minlength="3" maxlength="100" required value="<?= htmlspecialchars((string) $user['name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Username</label>
                                <input type="text" name="username" class="form-control" minlength="3" maxlength="30" pattern="[A-Za-z0-9._-]{3,30}" required value="<?= htmlspecialchars((string) ($user['username'] ?? '')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars((string) $user['email']); ?>" disabled>
                                <div class="form-text">Email tidak dapat diubah dari halaman ini.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nomor Telepon / WhatsApp</label>
                                <input type="text" name="phone" class="form-control" maxlength="20" value="<?= htmlspecialchars((string) ($user['phone'] ?? '')); ?>">
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="bi bi-save me-1"></i> Simpan Profil</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0"><i class="bi bi-shield-lock text-danger me-2"></i> Ubah Password</h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/profile/password" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($data['csrfToken'] ?? ''); ?>">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold">Password Saat Ini</label>
                                <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Password Baru</label>
                                <input type="password" name="new_password" class="form-control" minlength="8" autocomplete="new-password" required>
                                <div class="form-text">Minimal 8 karakter.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Konfirmasi Password Baru</label>
                                <input type="password" name="confirm_password" class="form-control" minlength="8" autocomplete="new-password" required>
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold"><i class="bi bi-key me-1"></i> Ubah Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
