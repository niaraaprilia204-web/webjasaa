<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-primary border-3">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span class="badge bg-primary px-3 py-2 rounded-pill mb-2"><i class="bi bi-clock-history me-1"></i> Portal Customer</span>
                <h2 class="fw-bold mb-0">Riwayat Pesanan Saya</h2>
            </div>
            <a href="<?= BASEURL; ?>/customer/orders/create" class="btn btn-primary rounded-pill fw-bold btn-sm shadow">
                <i class="bi bi-plus-circle me-1"></i> Buat Pesanan Baru
            </a>
        </div>
    </div>
</div>

<div class="container py-3 mb-5">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom border-light">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-journal-text text-primary me-2"></i> Daftar Pesanan Jasa Teknis</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Kode Order</th>
                            <th>Layanan IT</th>
                            <th>Deskripsi Masalah</th>
                            <th>Status Workflow</th>
                            <th>Tanggal Diajukan</th>
                            <th class="text-end pe-4">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data['orders'])): ?>
                            <?php foreach ($data['orders'] as $ord): ?>
                                <?php
                                $statusBadge = 'bg-secondary';
                                if ($ord['status'] === 'submitted') $statusBadge = 'bg-info text-dark';
                                if ($ord['status'] === 'verified') $statusBadge = 'bg-primary';
                                if ($ord['status'] === 'scheduled') $statusBadge = 'bg-warning text-dark';
                                if ($ord['status'] === 'assigned') $statusBadge = 'bg-dark';
                                if ($ord['status'] === 'in_progress') $statusBadge = 'bg-primary bg-gradient';
                                if ($ord['status'] === 'waiting_confirmation') $statusBadge = 'bg-warning text-dark';
                                if ($ord['status'] === 'completed') $statusBadge = 'bg-success';
                                if ($ord['status'] === 'cancelled') $statusBadge = 'bg-danger';
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><code><?= htmlspecialchars($ord['order_code']); ?></code></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars((string) ($ord['service_name'] ?? '-')); ?></td>
                                    <td class="small text-muted text-truncate" style="max-width: 250px;"><?= htmlspecialchars($ord['problem_description']); ?></td>
                                    <td>
                                        <span class="badge <?= $statusBadge; ?> text-uppercase small px-3 py-1">
                                            <?= htmlspecialchars($ord['status']); ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= date('d M Y H:i', strtotime($ord['created_at'])); ?></td>
                                    <td class="text-end pe-4">
                                        <a href="<?= BASEURL; ?>/customer/orders/detail/<?= $ord['order_code']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            Lacak <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 text-secondary d-block mb-2"></i>
                                    Anda belum memiliki riwayat pesanan jasa.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
