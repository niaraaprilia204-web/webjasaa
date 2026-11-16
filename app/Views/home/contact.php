<div class="bg-dark text-white py-4 mb-4 shadow-sm">
    <div class="container">
        <h2 class="fw-bold mb-1"><i class="bi bi-envelope text-primary me-2"></i> Hubungi Kami</h2>
        <p class="text-secondary mb-0">Kontak & Lokasi Sekretariat Unit Bisnis TKJ Service Hub.</p>
    </div>
</div>

<div class="container py-4 mb-5">
    <div class="row gy-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h4 class="fw-bold text-dark mb-4">Informasi Kontak</h4>
                <div class="vstack gap-3 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle">
                            <i class="bi bi-building fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Sekolah</div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($data['theme']['schoolName']); ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle">
                            <i class="bi bi-geo-alt fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Alamat Sekretariat</div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($data['theme']['address']); ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle">
                            <i class="bi bi-envelope fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Email Resmi</div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($data['theme']['email']); ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle">
                            <i class="bi bi-telephone fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">WhatsApp / Telepon</div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($data['theme']['phone']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h4 class="fw-bold text-dark mb-3">Pesan Layanan / Pertanyaan</h4>
                <p class="text-muted small mb-4">Formulir kontak ini disiapkan untuk integrasi modul konsultasi/pemesanan pada tahap berikutnya.</p>
                
                <form onsubmit="event.preventDefault(); alert('Modul formulir pemesanan akan diaktifkan pada tahap berikutnya.');">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap</label>
                        <input type="text" class="form-control" placeholder="Masukkan nama Anda..." disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alamat Email / No. WhatsApp</label>
                        <input type="text" class="form-control" placeholder="Masukkan email / no hp..." disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kebutuhan Jasa</label>
                        <select class="form-select" disabled>
                            <option>Instalasi CCTV</option>
                            <option>Konfigurasi Wi-Fi / Mikrotik</option>
                            <option>Service PC / Laptop</option>
                            <option>Lainnya</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Pesan / Deskripsi Perangkat</label>
                        <textarea class="form-control" rows="3" placeholder="Jelaskan kebutuhan atau kendala perangkat Anda..." disabled></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold" disabled>
                        <i class="bi bi-send me-1"></i> Kirim Pesan (Modul Persiapan)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
