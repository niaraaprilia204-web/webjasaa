<!-- Hero Banner Section -->
<div class="bg-gradient-hero text-white py-5 shadow-sm position-relative overflow-hidden">
    <div class="container py-4 position-relative z-1">
        <div class="row align-items-center gy-4">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-10 border border-white border-opacity-25 px-3 py-2 rounded-pill mb-3 backdrop-blur">
                    <span class="badge bg-primary rounded-circle"><i class="bi bi-rocket-takeoff"></i></span>
                    <span class="small fw-semibold text-light"><?= htmlspecialchars($data['theme']['schoolName']); ?></span>
                </div>
                <h1 class="display-4 fw-extrabold mb-3 text-white">
                    TKJ <span class="text-primary-gradient">Service Hub</span>
                </h1>
                <p class="lead text-secondary-light mb-4 fs-5">
                    <?= htmlspecialchars($data['theme']['tagline']); ?>. Solusi cepat, tepat, dan terjangkau untuk kebutuhan instalasi CCTV, jaringan, service komputer, dan pemeliharaan IT.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="<?= BASEURL; ?>/services" class="btn btn-primary btn-lg rounded-pill px-4 fw-bold shadow">
                        <i class="bi bi-tools me-1"></i> Lihat Katalag Layanan
                    </a>
                    <a href="#roles-structure" class="btn btn-outline-light btn-lg rounded-pill px-4 fw-semibold">
                        <i class="bi bi-people me-1"></i> Rencana 4 Role User
                    </a>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card bg-dark bg-opacity-60 border border-secondary border-opacity-25 rounded-4 shadow-lg backdrop-blur text-white p-2">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-secondary border-opacity-25 pb-2">
                            <span class="fw-bold text-primary"><i class="bi bi-cpu me-1"></i> Core Architecture Check</span>
                            <span class="badge bg-success">MVC Ready</span>
                        </div>
                        <div class="vstack gap-2 small">
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">Routing & Engine:</span>
                                <span class="fw-semibold text-light">Clean URL + Router</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">Database Adapter:</span>
                                <span class="fw-semibold text-light">PDO (Prepared Stmt)</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">UI Design Engine:</span>
                                <span class="fw-semibold text-light">Bootstrap 5.3 + Theme Config</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">Services & Middleware:</span>
                                <span class="fw-semibold text-light">Decoupled Layers</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Container Content -->
<div class="container py-5">

    <!-- Status Connection Card -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-activity text-primary"></i> Status Infrastructure & Database
                    </h5>
                    <span class="badge bg-secondary rounded-pill">App Version <?= APP_VERSION; ?></span>
                </div>
                <div class="card-body p-4">
                    <div class="row gy-3">
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light d-flex align-items-center gap-3">
                                <div class="fs-1 text-primary"><i class="bi bi-database"></i></div>
                                <div>
                                    <h6 class="fw-bold mb-1">Status MySQL/MariaDB</h6>
                                    <?php if ($data['healthData']['dbConnected']): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Terhubung (Database: <?= htmlspecialchars($data['healthData']['dbName']); ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i> Database Belum Terkoneksi</span>
                                        <div class="small text-muted mt-1"><?= htmlspecialchars($data['healthData']['dbError']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border bg-light d-flex align-items-center gap-3">
                                <div class="fs-1 text-info"><i class="bi bi-layers"></i></div>
                                <div>
                                    <h6 class="fw-bold mb-1">Pemisahan Arsitektur</h6>
                                    <span class="badge bg-primary me-1">Controller</span>
                                    <span class="badge bg-success me-1">Model</span>
                                    <span class="badge bg-warning text-dark me-1">Service</span>
                                    <span class="badge bg-dark">Middleware</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4 Role Overview -->
    <div id="roles-structure" class="mb-5">
        <div class="text-center mb-4">
            <h3 class="fw-extrabold text-dark mb-1">4 Role Pengguna Sistem</h3>
            <p class="text-muted">Struktur otorisasi & hak akses pengguna pada unit bisnis TKJ</p>
        </div>
        <div class="row g-4">
            <?php foreach ($data['healthData']['roles'] as $role): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm rounded-4 p-3 card-hover">
                        <div class="card-body text-center">
                            <div class="icon-circle bg-<?= $role['color']; ?> bg-opacity-10 text-<?= $role['color']; ?> mx-auto mb-3">
                                <i class="bi <?= $role['icon']; ?> fs-3"></i>
                            </div>
                            <span class="badge bg-<?= $role['color']; ?> bg-opacity-10 text-<?= $role['color']; ?> mb-2"><?= htmlspecialchars($role['badge']); ?></span>
                            <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($role['name']); ?></h5>
                            <p class="text-muted small mb-0"><?= htmlspecialchars($role['desc']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Services Overview Scope -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-extrabold text-dark mb-1">Katalog Layanan Teknis</h3>
                <p class="text-muted mb-0">Cakupan jasa IT profesional yang dapat dilayani oleh siswa TKJ</p>
            </div>
            <a href="<?= BASEURL; ?>/services" class="btn btn-outline-primary rounded-pill fw-semibold">
                Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="row g-4">
            <?php foreach ($data['healthData']['services'] as $service): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4 p-3 card-hover border-start border-4 border-primary">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3">
                                    <i class="bi <?= $service['icon']; ?> fs-4"></i>
                                </div>
                                <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($service['title']); ?></h5>
                            </div>
                            <p class="text-muted small mb-0"><?= htmlspecialchars($service['desc']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>
