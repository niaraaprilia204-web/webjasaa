<footer class="bg-dark text-white pt-5 pb-3 border-top border-secondary border-opacity-25 mt-auto">
    <div class="container">
        <div class="row gy-4 mb-4">
            <div class="col-lg-5">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge bg-primary rounded-circle p-2"><i class="bi <?= $data['theme']['brandIcon']; ?> fs-5"></i></span>
                    <h5 class="fw-bold mb-0 text-white"><?= $data['theme']['appName']; ?></h5>
                </div>
                <p class="text-secondary small mb-3">
                    <?= $data['theme']['tagline']; ?>. Menghadirkan pengerjaan teknis berkualitas tinggi, terpercaya, dan profesional oleh siswa TKJ berbakat.
                </p>
                <div class="d-flex gap-3 text-secondary">
                    <a href="#" class="text-secondary hover-text-primary"><i class="bi bi-facebook fs-5"></i></a>
                    <a href="#" class="text-secondary hover-text-primary"><i class="bi bi-instagram fs-5"></i></a>
                    <a href="#" class="text-secondary hover-text-primary"><i class="bi bi-youtube fs-5"></i></a>
                    <a href="#" class="text-secondary hover-text-primary"><i class="bi bi-github fs-5"></i></a>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <h6 class="fw-bold text-white mb-3"><i class="bi bi-link-45deg me-1"></i> Tautan Cepat</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li><a href="<?= BASEURL; ?>" class="text-secondary text-decoration-none hover-text-white">Beranda Hub</a></li>
                    <li><a href="<?= BASEURL; ?>/services" class="text-secondary text-decoration-none hover-text-white">Katalog Jasa Teknis</a></li>
                    <li><a href="<?= BASEURL; ?>/about" class="text-secondary text-decoration-none hover-text-white">Struktur Organization</a></li>
                    <li><a href="<?= BASEURL; ?>/contact" class="text-secondary text-decoration-none hover-text-white">Kontak & Lokasi</a></li>
                    <li><a href="<?= BASEURL; ?>/otp" class="text-secondary text-decoration-none hover-text-white">Verifikasi WhatsApp</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-4">
                <h6 class="fw-bold text-white mb-3"><i class="bi bi-geo-alt me-1"></i> Sekretariat Unit Bisnis</h6>
                <p class="text-secondary small mb-1"><i class="bi bi-building me-1"></i> <?= $data['theme']['schoolName']; ?></p>
                <p class="text-secondary small mb-1"><i class="bi bi-map me-1"></i> <?= $data['theme']['address']; ?></p>
                <p class="text-secondary small mb-1"><i class="bi bi-envelope me-1"></i> <?= $data['theme']['email']; ?></p>
                <p class="text-secondary small mb-0"><i class="bi bi-telephone me-1"></i> <?= $data['theme']['phone']; ?></p>
            </div>
        </div>

        <div class="border-top border-secondary border-opacity-25 pt-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <p class="text-secondary small mb-0">&copy; <?= date('Y'); ?> <?= $data['theme']['appName']; ?>. Developed with Clean MVC Architecture.</p>
            <span class="badge bg-dark border border-secondary text-secondary font-monospace small">Bootstrap 5.3 &bull; PHP <?= PHP_VERSION; ?></span>
        </div>
    </div>
</footer>
