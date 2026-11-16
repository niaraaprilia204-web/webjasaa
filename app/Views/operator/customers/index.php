<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-primary border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span class="badge bg-primary px-3 py-2 rounded-pill mb-2"><i class="bi bi-people me-1"></i> Data Operasional</span>
                <h2 class="fw-bold mb-0">Kelola Data Customer</h2>
            </div>
            <a href="<?= BASEURL; ?>/operator/dashboard" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard Operator
            </a>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    
    <!-- Search Control -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form action="<?= BASEURL; ?>/operator/customers" method="GET" class="row g-3 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama pelanggan, email, atau instansi..." value="<?= htmlspecialchars($data['search']); ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">
                    <i class="bi bi-search me-1"></i> Cari Customer
                </button>
                <a href="<?= BASEURL; ?>/operator/customers" class="btn btn-outline-secondary rounded-pill" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Customer Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-person-check text-primary me-2"></i> Daftar Pelanggan Terdaftar</h5>
            <span class="badge bg-secondary rounded-pill">Total: <?= count($data['customers']); ?> Pelanggan</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Nama Pelanggan</th>
                            <th>Email</th>
                            <th>No. Telepon / WA</th>
                            <th>Instansi / Kategori</th>
                            <th>Alamat Lengkap</th>
                            <th>Total Pesanan</th>
                            <th class="pe-4">Tanggal Daftar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data['customers'])): ?>
                            <?php foreach ($data['customers'] as $cust): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($cust['name']); ?></td>
                                    <td><code><?= htmlspecialchars($cust['email']); ?></code></td>
                                    <td><i class="bi bi-telephone text-success me-1"></i> <?= htmlspecialchars($cust['phone'] ?: '-'); ?></td>
                                    <td><span class="badge bg-info bg-opacity-10 text-dark"><?= htmlspecialchars($cust['institution'] ?: 'Masyarakat Umum'); ?></span></td>
                                    <td class="small text-muted text-truncate" style="max-width: 250px;"><?= htmlspecialchars($cust['address'] ?: '-'); ?></td>
                                    <td><span class="badge bg-primary rounded-pill"><?= $cust['total_orders']; ?> Order</span></td>
                                    <td class="pe-4 small text-muted"><?= date('d M Y', strtotime($cust['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Data customer tidak ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
