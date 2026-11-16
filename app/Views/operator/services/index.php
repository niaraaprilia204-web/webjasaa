<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-warning border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill mb-2"><i class="bi bi-tools me-1"></i> Master Data Operasional</span>
                <h2 class="fw-bold mb-0">Kelola Katalog Layanan IT</h2>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASEURL; ?>/operator/categories" class="btn btn-outline-light rounded-pill btn-sm">
                    <i class="bi bi-tag me-1"></i> Kelola Kategori
                </a>
                <button type="button" class="btn btn-warning rounded-pill btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Layanan
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    
    <!-- Search & Filter Controls Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form action="<?= BASEURL; ?>/operator/services" method="GET" class="row g-3 align-items-center">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">Pencarian Layanan</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama atau deskripsi..." value="<?= htmlspecialchars($data['filters']['search']); ?>">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Filter Kategori</label>
                <select name="category_id" class="form-select">
                    <option value="">-- Semua Kategori --</option>
                    <?php foreach ($data['categories'] as $cat): ?>
                        <option value="<?= $cat['id']; ?>" <?= ($data['filters']['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Filter Status</label>
                <select name="status" class="form-select">
                    <option value="">-- Semua Status --</option>
                    <option value="1" <?= ($data['filters']['status'] === 1) ? 'selected' : ''; ?>>Aktif</option>
                    <option value="0" <?= ($data['filters']['status'] === 0) ? 'selected' : ''; ?>>Nonaktif</option>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2 mt-auto">
                <button type="submit" class="btn btn-primary w-100 rounded-pill fw-semibold">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= BASEURL; ?>/operator/services" class="btn btn-outline-secondary rounded-pill" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Services Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-box-seam text-warning me-2"></i> Daftar Layanan Jasa TKJ</h5>
            <span class="badge bg-secondary rounded-pill">Total: <?= $data['pagination']['total']; ?> Layanan</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Nama Layanan</th>
                            <th>Kategori</th>
                            <th>Estimasi Waktu</th>
                            <th>Kisaran Harga</th>
                            <th>Status</th>
                            <th class="text-end pe-4" style="width: 220px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data['services'])): ?>
                            <?php foreach ($data['services'] as $svc): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($svc['title']); ?></div>
                                        <div class="small text-muted text-truncate" style="max-width: 300px;"><?= htmlspecialchars($svc['description']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary">
                                            <i class="bi <?= htmlspecialchars($svc['category_icon']); ?> me-1"></i> <?= htmlspecialchars($svc['category_name']); ?>
                                        </span>
                                    </td>
                                    <td class="small fw-semibold text-secondary">
                                        <i class="bi bi-clock me-1"></i> <?= htmlspecialchars($svc['estimated_duration'] ?: '-'); ?>
                                    </td>
                                    <td class="small fw-bold text-dark">
                                        <i class="bi bi-cash me-1 text-success"></i> <?= htmlspecialchars($svc['price_range'] ?: '-'); ?>
                                    </td>
                                    <td>
                                        <?php if ($svc['is_active']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i> Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="<?= BASEURL; ?>/operator/services/toggle/<?= $svc['id']; ?>" 
                                           class="btn btn-sm <?= $svc['is_active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?> rounded-pill me-1"
                                           title="Ubah Status Aktif/Nonaktif">
                                            <i class="bi <?= $svc['is_active'] ? 'bi-eye-slash' : 'bi-eye'; ?>"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editServiceModal<?= $svc['id']; ?>">
                                            <i class="bi bi-pencil me-1"></i> Edit
                                        </button>
                                        <a href="<?= BASEURL; ?>/operator/services/delete/<?= $svc['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger rounded-pill"
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus layanan ini?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editServiceModal<?= $svc['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Data Layanan</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="<?= BASEURL; ?>/operator/services/update/<?= $svc['id']; ?>" method="POST">
                                                <div class="modal-body p-4">
                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-8">
                                                            <label class="form-label fw-semibold">Nama Layanan <span class="text-danger">*</span></label>
                                                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($svc['title']); ?>" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
                                                            <select name="category_id" class="form-select" required>
                                                                <?php foreach ($data['categories'] as $cat): ?>
                                                                    <option value="<?= $cat['id']; ?>" <?= ($svc['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                                        <?= htmlspecialchars($cat['name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">Estimasi Waktu Pengerjaan</label>
                                                            <input type="text" name="estimated_duration" class="form-control" value="<?= htmlspecialchars($svc['estimated_duration']); ?>" placeholder="Contoh: 1-2 hari">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">Kisaran Harga Dasar</label>
                                                            <input type="text" name="price_range" class="form-control" value="<?= htmlspecialchars($svc['price_range']); ?>" placeholder="Contoh: Rp 50.000 - Rp 150.000">
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Deskripsi Layanan</label>
                                                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($svc['description']); ?></textarea>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="is_active" id="editActiveStatus<?= $svc['id']; ?>" <?= $svc['is_active'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label fw-semibold" for="editActiveStatus<?= $svc['id']; ?>">Status Aktif (Tampil di Katalog Customer)</label>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light border-top p-3">
                                                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Data layanan tidak ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <?php if ($data['pagination']['pages'] > 1): ?>
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
                <span class="small text-muted">Halaman <?= $data['pagination']['page']; ?> dari <?= $data['pagination']['pages']; ?></span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $data['pagination']['pages']; $i++): ?>
                            <li class="page-item <?= ($data['pagination']['page'] == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= BASEURL; ?>/operator/services?page=<?= $i; ?>&search=<?= urlencode($data['filters']['search']); ?>&category_id=<?= $data['filters']['category_id']; ?>&status=<?= $data['filters']['status']; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah Layanan Baru -->
<div class="modal fade" id="addServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold text-warning"><i class="bi bi-plus-circle me-2"></i> Tambah Layanan IT Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASEURL; ?>/operator/services/store" method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Nama Layanan <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="Contoh: Instalasi Mikrotik Routerboard" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($data['categories'] as $cat): ?>
                                    <option value="<?= $cat['id']; ?>"><?= htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Estimasi Waktu Pengerjaan</label>
                            <input type="text" name="estimated_duration" class="form-control" placeholder="Contoh: 2-3 jam">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kisaran Harga Dasar</label>
                            <input type="text" name="price_range" class="form-control" placeholder="Contoh: Rp 50.000 - Rp 150.000">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi Layanan</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Penjelasan mengenai ruang lingkup pengerjaan..."></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="addActiveStatus" checked>
                        <label class="form-check-label fw-semibold" for="addActiveStatus">Langsung Aktifkan di Katalog Customer</label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold">Simpan Layanan Baru</button>
                </div>
            </form>
        </div>
    </div>
</div>
