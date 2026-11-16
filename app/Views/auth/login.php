<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="bg-gradient-hero text-white p-4 text-center">
                    <div class="badge bg-primary rounded-circle p-3 mb-2">
                        <i class="bi <?= $data['theme']['brandIcon']; ?> fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Masuk ke Portal</h4>
                    <p class="text-secondary-light small mb-0"><?= htmlspecialchars($data['theme']['appName']); ?></p>
                </div>

                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/auth/loginProcess" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email atau Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                                <input type="text" name="login" class="form-control border-start-0 ps-0" placeholder="nama@domain.com atau username" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                                <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="••••••••" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-pill fw-bold mb-3 shadow">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
                        </button>

                        <div class="text-center small text-muted">
                            Belum memiliki akun Customer? <a href="<?= BASEURL; ?>/auth/register" class="fw-semibold text-primary">Daftar Akun Baru</a>
                        </div>
                        <div class="text-center small mt-2">
                            <a href="<?= BASEURL; ?>/otp" class="fw-semibold text-success">
                                <i class="bi bi-whatsapp me-1"></i> Verifikasi nomor WhatsApp
                            </a>
                        </div>
                    </form>
                </div>

                <div class="card-footer bg-light p-3 border-top text-center">
                    <span class="small text-muted"><i class="bi bi-shield-lock me-1"></i> Sistem Login Multi-Role Terproteksi (Bcrypt Hashing)</span>
                </div>
            </div>

            <!-- Quick Access Demo Credentials Card -->
            <div class="card border-0 shadow-sm rounded-4 mt-4 bg-white p-3">
                <div class="card-body">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-key text-warning me-1"></i> Akun Pengujian Demo (Password: <code>password123</code>):</h6>
                    <ul class="list-unstyled small vstack gap-1 mb-0">
                        <li><span class="badge bg-primary">Customer:</span> <code>customer@gmail.com</code></li>
                        <li><span class="badge bg-success">Teknisi Siswa:</span> <code>rizky.tkj@school.id</code></li>
                        <li><span class="badge bg-warning text-dark">Operator:</span> <code>operator@school.id</code></li>
                        <li><span class="badge bg-danger">Kepala Sekolah:</span> <code>kepsek@school.id</code></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
