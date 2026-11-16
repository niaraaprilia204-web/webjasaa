<?php
$report = $data['report'] ?? [];
$userReport = $data['userReport'] ?? [];
$chatReport = $data['chatReport'] ?? [];
$filters = $data['filters'] ?? [];
$statusCounts = $report['statusCounts'] ?? [];
$statusLabels = [
    'submitted' => 'Submitted',
    'verified' => 'Verified',
    'scheduled' => 'Scheduled',
    'assigned' => 'Assigned',
    'in_progress' => 'In Progress',
    'waiting_confirmation' => 'Waiting Confirmation',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
$roleLabels = [
    'customer' => 'Customer',
    'technician' => 'Teknisi',
    'operator' => 'Operator',
    'principal' => 'Kepala Sekolah',
    'admin' => 'Administrator',
];
?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-danger border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-danger px-3 py-2 rounded-pill mb-2"><i class="bi bi-graph-up-arrow me-1"></i> Laporan Administrator</span>
                <h2 class="fw-bold mb-0">Laporan & Analitik</h2>
            </div>
            <a href="<?= BASEURL; ?>/admin/dashboard" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard Admin
            </a>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form action="<?= BASEURL; ?>/admin/reports" method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">Dari Tanggal</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars((string) ($filters['start_date'] ?? '')); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars((string) ($filters['end_date'] ?? '')); ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill w-100"><i class="bi bi-funnel me-1"></i> Terapkan</button>
                <a href="<?= BASEURL; ?>/admin/reports" class="btn btn-outline-secondary rounded-pill" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small">Total Pesanan</div>
                    <div class="display-6 fw-bold text-primary"><?= (int) ($report['totalOrders'] ?? 0); ?></div>
                    <div class="small text-secondary"><?= (int) ($report['activeOrders'] ?? 0); ?> aktif</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small">Selesai</div>
                    <div class="display-6 fw-bold text-success"><?= (int) ($report['completedOrders'] ?? 0); ?></div>
                    <div class="small text-secondary"><?= htmlspecialchars((string) ($report['revenue'] ?? 'Rp 0')); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-danger">
                <div class="card-body">
                    <div class="text-muted small">Dibatalkan</div>
                    <div class="display-6 fw-bold text-danger"><?= (int) ($report['cancelledOrders'] ?? 0); ?></div>
                    <div class="small text-secondary"><?= (int) ($report['totalReviews'] ?? 0); ?> ulasan</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="text-muted small">Rating Rata-rata</div>
                    <div class="display-6 fw-bold text-warning"><?= htmlspecialchars((string) ($report['averageRating'] ?? 0)); ?>/5</div>
                    <div class="small text-secondary"><?= (int) ($chatReport['totalChats'] ?? 0); ?> chat tersimpan</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-people text-primary me-2"></i> Ringkasan Pengguna</h5></div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-6"><div class="p-3 rounded-3 bg-light"><div class="small text-muted">Total</div><div class="fs-4 fw-bold"><?= (int) ($userReport['totalUsers'] ?? 0); ?></div></div></div>
                        <div class="col-6"><div class="p-3 rounded-3 bg-light"><div class="small text-muted">Aktif</div><div class="fs-4 fw-bold text-success"><?= (int) ($userReport['activeUsers'] ?? 0); ?></div></div></div>
                        <div class="col-6"><div class="p-3 rounded-3 bg-light"><div class="small text-muted">Baru 30 hari</div><div class="fs-4 fw-bold text-primary"><?= (int) ($userReport['newUsers30d'] ?? 0); ?></div></div></div>
                        <div class="col-6"><div class="p-3 rounded-3 bg-light"><div class="small text-muted">Percakapan hari ini</div><div class="fs-4 fw-bold text-info"><?= (int) ($chatReport['todayChats'] ?? 0); ?></div></div></div>
                    </div>
                    <div class="vstack gap-2">
                        <?php foreach ($roleLabels as $roleKey => $roleLabel): ?>
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted"><?= htmlspecialchars($roleLabel); ?></span>
                                <span class="fw-bold"><?= (int) ($userReport['roleCounts'][$roleKey] ?? 0); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-list-check text-warning me-2"></i> Distribusi Status Pesanan</h5></div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Status</th><th class="text-end">Jumlah</th><th class="text-end">Persentase</th></tr></thead>
                            <tbody>
                                <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
                                    <?php
                                    $count = (int) ($statusCounts[$statusKey] ?? 0);
                                    $total = max(1, (int) ($report['totalOrders'] ?? 0));
                                    $percent = round(($count / $total) * 100, 1);
                                    ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border text-capitalize"><?= htmlspecialchars($statusLabel); ?></span></td>
                                        <td class="text-end fw-bold"><?= $count; ?></td>
                                        <td class="text-end text-muted"><?= $percent; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-trophy text-warning me-2"></i> Layanan Terpopuler</h5></div>
                <div class="card-body p-4">
                    <?php if (!empty($report['topServices'])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Layanan</th><th>Kategori</th><th class="text-end">Pesanan</th></tr></thead>
                                <tbody>
                                    <?php foreach ($report['topServices'] as $svc): ?>
                                        <tr>
                                            <td class="small fw-semibold"><?= htmlspecialchars((string) $svc['title']); ?></td>
                                            <td class="small text-muted"><?= htmlspecialchars((string) ($svc['category_name'] ?? '-')); ?></td>
                                            <td class="text-end"><span class="badge bg-primary"><?= (int) $svc['order_count']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small text-center py-4">Belum ada data layanan.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-person-badge text-success me-2"></i> Kinerja Teknisi</h5></div>
                <div class="card-body p-4">
                    <?php if (!empty($report['technicianPerformance'])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Teknisi</th><th>Kelas</th><th class="text-end">Tugas</th><th class="text-end">Selesai</th></tr></thead>
                                <tbody>
                                    <?php foreach ($report['technicianPerformance'] as $tech): ?>
                                        <tr>
                                            <td class="small fw-semibold"><?= htmlspecialchars((string) $tech['name']); ?></td>
                                            <td class="small text-muted"><?= htmlspecialchars((string) ($tech['class_grade'] ?? '-')); ?></td>
                                            <td class="text-end"><?= (int) $tech['total_work']; ?></td>
                                            <td class="text-end"><span class="badge bg-success"><?= (int) $tech['done_work']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small text-center py-4">Belum ada data teknisi.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($report['dailyTrend'])): ?>
        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-calendar3 text-info me-2"></i> Tren Pesanan 14 Hari Terakhir</h5></div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Tanggal</th><th class="text-end">Total Pesanan</th><th class="text-end">Selesai</th></tr></thead>
                        <tbody>
                            <?php foreach ($report['dailyTrend'] as $day): ?>
                                <tr>
                                    <td class="small"><?= htmlspecialchars(date('d M Y', strtotime((string) $day['day']))); ?></td>
                                    <td class="text-end fw-semibold"><?= (int) $day['total']; ?></td>
                                    <td class="text-end"><span class="badge bg-success"><?= (int) $day['completed']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
