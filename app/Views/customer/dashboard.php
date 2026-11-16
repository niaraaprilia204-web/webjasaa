<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-primary border-3">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span class="badge bg-primary px-3 py-2 rounded-pill mb-2"><i class="bi bi-person-heart me-1"></i> Portal Customer</span>
                <h2 class="fw-bold mb-0">Selamat Datang, <?= htmlspecialchars($data['user']['name']); ?></h2>
            </div>
            <a href="<?= BASEURL; ?>/auth/logout" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
</div>

<div class="container py-3 mb-5">
    
    <!-- Quick Actions Card -->
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white card-hover border-start border-4 border-primary">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-primary bg-opacity-10 text-primary mb-2"><i class="bi bi-cart-plus me-1"></i> Pengajuan Jasa</span>
                        <h4 class="fw-bold text-dark mb-1">Buat Pesanan Layanan Baru</h4>
                        <p class="text-muted small mb-0">Pilih dari 10+ jasa teknis (CCTV, PC/Laptop, Wi-Fi, LAN, Printer).</p>
                    </div>
                    <a href="<?= BASEURL; ?>/customer/orders/create" class="btn btn-primary rounded-circle p-3 shadow-sm text-white ms-3">
                        <i class="bi bi-arrow-right fs-4"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white card-hover border-start border-4 border-info">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-info bg-opacity-10 text-info mb-2"><i class="bi bi-clock-history me-1"></i> Lacak Status</span>
                        <h4 class="fw-bold text-dark mb-1">Riwayat Pesanan Saya</h4>
                        <p class="text-muted small mb-0">Pantau progres pengerjaan teknis melalui 7-step progress bar workflow.</p>
                    </div>
                    <a href="<?= BASEURL; ?>/customer/orders" class="btn btn-info rounded-circle p-3 shadow-sm text-white ms-3">
                        <i class="bi bi-arrow-right fs-4"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php
    $stats = $data['stats'] ?? ['total' => 0, 'active' => 0, 'completed' => 0, 'cancelled' => 0];
    $statusBadge = [
        'submitted' => 'bg-secondary', 'verified' => 'bg-info text-dark', 'scheduled' => 'bg-primary',
        'assigned' => 'bg-warning text-dark', 'in_progress' => 'bg-primary', 'waiting_confirmation' => 'bg-warning text-dark',
        'completed' => 'bg-success', 'cancelled' => 'bg-danger',
    ];
    ?>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="small text-muted">Total Pesanan</div><div class="fs-2 fw-bold text-primary"><?= (int) $stats['total']; ?></div></div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="small text-muted">Sedang Diproses</div><div class="fs-2 fw-bold text-warning"><?= (int) $stats['active']; ?></div></div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="small text-muted">Selesai</div><div class="fs-2 fw-bold text-success"><?= (int) $stats['completed']; ?></div></div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="small text-muted">Dibatalkan</div><div class="fs-2 fw-bold text-danger"><?= (int) $stats['cancelled']; ?></div></div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-primary me-2"></i> Pesanan Terbaru</h5>
            <a href="<?= BASEURL; ?>/customer/orders" class="btn btn-sm btn-outline-primary rounded-pill">Lihat Semua</a>
        </div>
        <?php if (!empty($data['orders'])): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Kode</th><th>Layanan</th><th>Jadwal</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['orders'] as $order): ?>
                            <tr>
                                <td><code><?= htmlspecialchars((string) $order['order_code']); ?></code></td>
                                <td class="small fw-semibold"><?= htmlspecialchars((string) $order['service_name']); ?></td>
                                <td class="small text-muted"><?= !empty($order['scheduled_date']) ? htmlspecialchars(date('d M Y H:i', strtotime((string) $order['scheduled_date']))) : '-'; ?></td>
                                <td><span class="badge <?= $statusBadge[$order['status']] ?? 'bg-secondary'; ?> text-uppercase"><?= htmlspecialchars((string) $order['status']); ?></span></td>
                                <td class="text-end"><a href="<?= BASEURL; ?>/customer/orders/detail/<?= htmlspecialchars((string) $order['order_code']); ?>" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-eye me-1"></i> Detail</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center text-muted py-4"><i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>Belum ada pesanan. Mulai dengan membuat pesanan baru.</div>
        <?php endif; ?>
    </div>

    <!-- Protected Role Banner -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-light text-center">
        <h5 class="fw-bold text-dark mb-2"><i class="bi bi-shield-check text-primary me-2"></i> Akses Terisolasi - Role Customer</h5>
        <p class="text-muted mb-0">Halaman ini dilindungi oleh <code>RoleMiddleware</code>. Anda hanya dapat melihat data dan pesanan yang terkait dengan akun Customer Anda.</p>
    </div>
</div>
