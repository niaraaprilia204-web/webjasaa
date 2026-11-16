<?php $service = $data['service']; ?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-primary border-3">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2 small text-secondary">
                <li class="breadcrumb-item"><a href="<?= BASEURL; ?>" class="text-secondary text-decoration-none">Beranda</a></li>
                <li class="breadcrumb-item"><a href="<?= BASEURL; ?>/services" class="text-secondary text-decoration-none">Katalog Layanan</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page"><?= htmlspecialchars($service['title']); ?></li>
            </ol>
        </nav>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <span class="badge bg-primary rounded-pill px-3 py-2 mb-2"><i class="bi <?= htmlspecialchars($service['category_icon']); ?> me-1"></i> <?= htmlspecialchars($service['category_name']); ?></span>
                <h2 class="fw-extrabold text-white mb-0"><?= htmlspecialchars($service['title']); ?></h2>
            </div>
            <a href="<?= BASEURL; ?>/customer/orders/create/<?= $service['id']; ?>" class="btn btn-primary btn-lg rounded-pill fw-bold shadow">
                <i class="bi bi-calendar-check me-1"></i> Pesan Layanan Ini
            </a>
        </div>
    </div>
</div>

<div class="container py-3 mb-5">
    <div class="row gy-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h4 class="fw-bold text-dark mb-3"><i class="bi bi-card-text text-primary me-2"></i> Deskripsi & Cakupan Pengerjaan</h4>
                <p class="text-muted lead fs-6 mb-4"><?= nl2br(htmlspecialchars($service['description'])); ?></p>

                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-shield-check text-success me-2"></i> Mengapa Memilih Unit Bisnis TKJ?</h5>
                <ul class="list-unstyled vstack gap-2 small mb-0">
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>Dikerjakan langsung oleh siswa TKJ berbakat yang teruji asesmen kompetensi.</span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>Disupervisi penuh oleh instruktur / operator berpengalaman.</span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>Pemberian garansi pemeliharaan pasca-pengerjaan teknis.</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
                <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">Informasi Detail</h5>
                
                <div class="vstack gap-3 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle">
                            <i class="bi bi-clock fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Estimasi Waktu</div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($service['estimated_duration'] ?: 'Tentatif'); ?></div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle">
                            <i class="bi bi-cash-stack fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Kisaran Biaya Dasar</div>
                            <div class="fw-bold text-success"><?= htmlspecialchars($service['price_range'] ?: 'Disesuaikan'); ?></div>
                        </div>
                    </div>
                </div>

                <a href="<?= BASEURL; ?>/customer/orders/create/<?= $service['id']; ?>" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow">
                    <i class="bi bi-pencil-square me-1"></i> Ajukan Permintaan Jasa
                </a>
            </div>
        </div>
    </div>
</div>
