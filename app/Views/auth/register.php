<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="bg-gradient-hero text-white p-4 text-center">
                    <div class="badge bg-primary rounded-circle p-3 mb-2">
                        <i class="bi bi-person-plus fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Registrasi Akun Customer</h4>
                    <p class="text-secondary-light small mb-0">Daftarkan diri Anda untuk mengajukan layanan IT pada unit bisnis TKJ</p>
                </div>

                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/auth/registerProcess" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Contoh: Ahmad Wijaya" required autofocus>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" placeholder="contoh: ahmad.wijaya" minlength="3" maxlength="30" pattern="[A-Za-z0-9._-]{3,30}" required>
                                <div class="form-text">3-30 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Alamat Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="nama@email.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">No. WhatsApp / HP</label>
                                <input type="text" name="phone" class="form-control" placeholder="081234567890">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Instansi / Kategori</label>
                            <input type="text" name="institution" class="form-control" placeholder="Masyarakat Umum / Nama Sekolah / Kantoran">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Alamat Lengkap</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Jl. Merdeka No. 123..."></textarea>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" placeholder="Min. 6 karakter" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Konfirmasi Password <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirm" class="form-control" placeholder="Ulangi password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-pill fw-bold mb-3 shadow">
                            <i class="bi bi-check-circle me-1"></i> Mendaftar Sekarang
                        </button>

                        <div class="text-center small text-muted">
                            Sudah memiliki akun? <a href="<?= BASEURL; ?>/auth/login" class="fw-semibold text-primary">Login di sini</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
