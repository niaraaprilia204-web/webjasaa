<?php
$services = $data['services'] ?? [];
$categories = $data['categories'] ?? [];
$filters = $data['filters'] ?? [];
$csrfToken = $data['csrfToken'] ?? '';
?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-warning border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill mb-2"><i class="bi bi-box-seam me-1"></i> Master Data</span>
                <h2 class="fw-bold mb-0">Katalog Layanan</h2>
                <div class="small text-white-50 mt-1"><?= count($services); ?> layanan ditampilkan.</div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASEURL; ?>/admin/dashboard" class="btn btn-outline-light rounded-pill btn-sm"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
                <button type="button" class="btn btn-warning rounded-pill btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Layanan
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form action="<?= BASEURL; ?>/admin/services" method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-muted">Cari Layanan</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="<?= htmlspecialchars((string) ($filters['search'] ?? '')); ?>" class="form-control border-start-0" placeholder="Nama atau deskripsi layanan...">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Kategori</label>
                <select name="category_id" class="form-select">
                    <option value="">Semua kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id']; ?>" <?= ((int) ($filters['category_id'] ?? 0) === (int) $cat['id']) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="1" <?= (($filters['status'] ?? '') === 1) ? 'selected' : ''; ?>>Aktif</option>
                    <option value="0" <?= (($filters['status'] ?? '') === 0) ? 'selected' : ''; ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-warning rounded-pill w-100 fw-semibold"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= BASEURL; ?>/admin/services" class="btn btn-outline-secondary rounded-pill" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Layanan</th>
                        <th>Kategori</th>
                        <th>Durasi</th>
                        <th>Estimasi Harga</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($services)): ?>
                        <?php foreach ($services as $svc): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars((string) $svc['title']); ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 360px;"><?= htmlspecialchars((string) ($svc['description'] ?? '')); ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars((string) ($svc['category_name'] ?? '-')); ?></span></td>
                                <td class="small text-muted"><?= htmlspecialchars((string) ($svc['estimated_duration'] ?: '-')); ?></td>
                                <td class="small fw-semibold text-success"><?= htmlspecialchars((string) ($svc['price_range'] ?: '-')); ?></td>
                                <td>
                                    <?php if ((int) $svc['is_active'] === 1): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-warning rounded-pill" data-bs-toggle="modal" data-bs-target="#editService<?= (int) $svc['id']; ?>" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form action="<?= BASEURL; ?>/admin/services/toggle" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $svc['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill" title="<?= (int) $svc['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                                                <i class="bi <?= (int) $svc['is_active'] === 1 ? 'bi-toggle-on text-success' : 'bi-toggle-off'; ?>"></i>
                                            </button>
                                        </form>
                                        <form action="<?= BASEURL; ?>/admin/services/delete" method="POST" class="d-inline" onsubmit="return confirm('Hapus layanan ini? Layanan yang sudah dipakai pesanan tidak dapat dihapus.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $svc['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">Tidak ada layanan yang cocok dengan filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <form action="<?= BASEURL; ?>/admin/services/store" method="POST">
                <div class="modal-header bg-dark text-white rounded-top-4">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i> Tambah Layanan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Nama Layanan</label>
                            <input type="text" name="title" class="form-control" required minlength="3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Kategori</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Pilih kategori</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int) $cat['id']; ?>"><?= htmlspecialchars((string) $cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Estimasi Durasi</label>
                            <input type="text" name="estimated_duration" class="form-control" placeholder="Contoh: 1-2 hari">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Estimasi Harga</label>
                            <input type="text" name="price_range" class="form-control" placeholder="Contoh: Rp 100.000 - Rp 300.000">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="addServiceActive" value="1" checked>
                                <label class="form-check-label small fw-semibold" for="addServiceActive">Aktifkan layanan di katalog publik</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-semibold"><i class="bi bi-save me-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($services as $svc): ?>
    <div class="modal fade" id="editService<?= (int) $svc['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <form action="<?= BASEURL; ?>/admin/services/update" method="POST">
                    <div class="modal-header bg-dark text-white rounded-top-4">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Layanan #<?= (int) $svc['id']; ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?= (int) $svc['id']; ?>">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small fw-bold">Nama Layanan</label>
                                <input type="text" name="title" class="form-control" required minlength="3" value="<?= htmlspecialchars((string) $svc['title']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Kategori</label>
                                <select name="category_id" class="form-select" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int) $cat['id']; ?>" <?= ((int) $svc['category_id'] === (int) $cat['id']) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Deskripsi</label>
                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars((string) ($svc['description'] ?? '')); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Estimasi Durasi</label>
                                <input type="text" name="estimated_duration" class="form-control" value="<?= htmlspecialchars((string) ($svc['estimated_duration'] ?? '')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Estimasi Harga</label>
                                <input type="text" name="price_range" class="form-control" value="<?= htmlspecialchars((string) ($svc['price_range'] ?? '')); ?>">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="editServiceActive<?= (int) $svc['id']; ?>" value="1" <?= ((int) $svc['is_active'] === 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small fw-semibold" for="editServiceActive<?= (int) $svc['id']; ?>">Aktifkan layanan di katalog publik</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning rounded-pill px-4 fw-semibold"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
