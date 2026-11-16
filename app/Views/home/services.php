<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-primary border-3">
    <div class="container">
        <h2 class="fw-bold mb-1"><i class="bi bi-tools text-primary me-2"></i> Katalog Layanan Teknis TKJ</h2>
        <p class="text-secondary mb-0">Daftar jasa teknologi informasi & pemeliharaan teknis aktif unit bisnis siswa TKJ.</p>
    </div>
</div>

<div class="container py-2 mb-5">
    
    <!-- Filter Badges & Search Form -->
    <div class="row align-items-center gy-3 mb-4">
        <div class="col-lg-8">
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= BASEURL; ?>/services" class="btn btn-sm rounded-pill <?= empty($data['currCategory']) ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    Semua Kategori
                </a>
                <?php if (!empty($data['categories'])): ?>
                    <?php foreach ($data['categories'] as $cat): ?>
                        <a href="<?= BASEURL; ?>/services?category=<?= $cat['id']; ?>" 
                           class="btn btn-sm rounded-pill <?= ($data['currCategory'] == $cat['id']) ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                            <i class="bi <?= htmlspecialchars($cat['icon']); ?> me-1"></i> <?= htmlspecialchars($cat['name']); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-4">
            <form action="<?= BASEURL; ?>/services" method="GET">
                <input type="hidden" name="category" value="<?= htmlspecialchars($data['currCategory'] ?? ''); ?>">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari jasa teknis..." value="<?= htmlspecialchars($data['currSearch'] ?? ''); ?>">
                    <button class="btn btn-primary rounded-end" type="submit">Cari</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Services Cards Grid -->
    <div class="row g-4">
        <?php if (!empty($data['services'])): ?>
            <?php foreach ($data['services'] as $service): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4 p-3 card-hover border-top border-4 border-primary">
                        <div class="card-body vstack justify-content-between">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3">
                                        <i class="bi <?= htmlspecialchars($service['category_icon'] ?? 'bi-tools'); ?> fs-3"></i>
                                    </div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1">
                                        <?= htmlspecialchars($service['category_name'] ?? 'Layanan IT'); ?>
                                    </span>
                                </div>
                                <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($service['title']); ?></h5>
                                <p class="text-muted small mb-3"><?= htmlspecialchars($service['description']); ?></p>
                            </div>

                            <div class="pt-3 border-top mt-3 vstack gap-3">
                                <div class="d-flex justify-content-between small text-secondary">
                                    <span><i class="bi bi-clock me-1"></i> Estimasi Waktu:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($service['estimated_duration'] ?: '-'); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between small text-secondary">
                                    <span><i class="bi bi-tag me-1"></i> Kisaran Harga:</span>
                                    <strong class="text-success"><?= htmlspecialchars($service['price_range'] ?: '-'); ?></strong>
                                </div>

                                <div class="d-flex gap-2 pt-2">
                                    <a href="<?= BASEURL; ?>/services/detail/<?= $service['id']; ?>" class="btn btn-outline-primary rounded-pill btn-sm w-50 fw-semibold">
                                        <i class="bi bi-info-circle me-1"></i> Detail
                                    </a>
                                    <a href="<?= BASEURL; ?>/customer/orders/create/<?= $service['id']; ?>" class="btn btn-primary rounded-pill btn-sm w-50 fw-bold shadow-sm">
                                        <i class="bi bi-cart-plus me-1"></i> Pesan
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="p-5 bg-white rounded-4 shadow-sm">
                    <i class="bi bi-tools fs-1 text-muted d-block mb-3"></i>
                    <h5 class="fw-bold text-dark">Layanan Tidak Ditemukan</h5>
                    <p class="text-muted small mb-0">Belum ada layanan aktif yang sesuai dengan kriteria pencarian Anda.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
