<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-warning border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill mb-2"><i class="bi bi-tag me-1"></i> Master Data Operasional</span>
                <h2 class="fw-bold mb-0">Kelola Kategori Layanan</h2>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASEURL; ?>/operator/services" class="btn btn-outline-light rounded-pill btn-sm">
                    <i class="bi bi-tools me-1"></i> Ke Katalog Layanan
                </a>
                <button type="button" class="btn btn-warning rounded-pill btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Kategori
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom border-light">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-list-nested text-warning me-2"></i> Daftar Kategori Layanan IT</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 60px;">ID</th>
                            <th style="width: 80px;">Ikon</th>
                            <th>Nama Kategori</th>
                            <th>Slug URL</th>
                            <th>Deskripsi</th>
                            <th class="text-end pe-4" style="width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data['categories'])): ?>
                            <?php foreach ($data['categories'] as $cat): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-muted">#<?= $cat['id']; ?></td>
                                    <td>
                                        <span class="p-2 bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex">
                                            <i class="bi <?= htmlspecialchars($cat['icon']); ?> fs-5"></i>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($cat['name']); ?></td>
                                    <td><code><?= htmlspecialchars($cat['slug']); ?></code></td>
                                    <td class="small text-muted"><?= htmlspecialchars($cat['description']); ?></td>
                                    <td class="text-end pe-4">
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editCategoryModal<?= $cat['id']; ?>">
                                            <i class="bi bi-pencil me-1"></i> Edit
                                        </button>
                                        <a href="<?= BASEURL; ?>/operator/categories/delete/<?= $cat['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger rounded-pill"
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editCategoryModal<?= $cat['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Kategori</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="<?= BASEURL; ?>/operator/categories/update/<?= $cat['id']; ?>" method="POST">
                                                <div class="modal-body p-4">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cat['name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Icon Bootstrap (Class)</label>
                                                        <input type="text" name="icon" class="form-control" value="<?= htmlspecialchars($cat['icon']); ?>" placeholder="bi-tools">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Deskripsi</label>
                                                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($cat['description']); ?></textarea>
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
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada kategori layanan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Kategori -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold text-warning"><i class="bi bi-plus-circle me-2"></i> Tambah Kategori Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASEURL; ?>/operator/categories/store" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Instalasi & Perangkat" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Icon Bootstrap (Class)</label>
                        <input type="text" name="icon" class="form-control" placeholder="bi-camera-video" value="bi-tools">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Deskripsi Singkat</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Penjelasan mengenai kategori ini..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold">Simpan Kategori</button>
                </div>
            </form>
        </div>
    </div>
</div>
