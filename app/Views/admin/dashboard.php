<?php
$metrics = $data['metrics'] ?? [];
$recentUsers = $data['recentUsers'] ?? [];
$recentOrders = $data['recentOrders'] ?? [];
$recentChats = $data['recentChats'] ?? [];
$roleCounts = $metrics['roleCounts'] ?? [];
?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-danger border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-danger px-3 py-2 rounded-pill mb-2"><i class="bi bi-shield-lock me-1"></i> Dashboard Administrator</span>
                <h2 class="fw-bold mb-0">Administrator TKJ Service Hub</h2>
                <div class="small text-white-50 mt-1">Kelola pengguna, laporan, riwayat chatbot, dan katalog layanan.</div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASEURL; ?>/admin/users" class="btn btn-danger rounded-pill btn-sm fw-bold">
                    <i class="bi bi-people me-1"></i> Kelola Pengguna
                </a>
                <a href="<?= BASEURL; ?>/auth/logout" class="btn btn-outline-light rounded-pill btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small">Total Pengguna</div>
                    <div class="display-6 fw-bold text-primary my-1"><?= (int) ($metrics['totalUsers'] ?? 0); ?></div>
                    <div class="small text-secondary"><i class="bi bi-person-check me-1"></i> <?= (int) ($metrics['activeUsers'] ?? 0); ?> akun aktif</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="text-muted small">Total Pesanan</div>
                    <div class="display-6 fw-bold text-warning my-1"><?= (int) ($metrics['totalOrders'] ?? 0); ?></div>
                    <div class="small text-secondary"><i class="bi bi-arrow-repeat me-1"></i> <?= (int) ($metrics['activeOrders'] ?? 0); ?> pesanan aktif</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small">Pesanan Selesai</div>
                    <div class="display-6 fw-bold text-success my-1"><?= (int) ($metrics['completedOrders'] ?? 0); ?></div>
                    <div class="small text-secondary"><i class="bi bi-graph-up-arrow me-1"></i> <?= htmlspecialchars((string) ($metrics['revenue'] ?? 'Rp 0')); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-info">
                <div class="card-body">
                    <div class="text-muted small">Riwayat Chatbot</div>
                    <div class="display-6 fw-bold text-info my-1"><?= (int) ($metrics['totalChats'] ?? 0); ?></div>
                    <div class="small text-secondary"><i class="bi bi-chat-dots me-1"></i> <?= (int) ($metrics['todayChats'] ?? 0); ?> percakapan hari ini</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php
        $miniCards = [
            ['label' => 'Total Customer', 'value' => (int) ($roleCounts['customer'] ?? 0), 'icon' => 'bi-person-heart', 'class' => 'primary'],
            ['label' => 'Total Teknisi', 'value' => (int) ($roleCounts['technician'] ?? 0), 'icon' => 'bi-tools', 'class' => 'success'],
            ['label' => 'Total Operator', 'value' => (int) ($roleCounts['operator'] ?? 0), 'icon' => 'bi-person-gear', 'class' => 'warning'],
            ['label' => 'Total Anggota Staf', 'value' => (int) ($roleCounts['operator'] ?? 0) + (int) ($roleCounts['technician'] ?? 0) + (int) ($roleCounts['admin'] ?? 0), 'icon' => 'bi-people', 'class' => 'info'],
            ['label' => 'Pesanan Dibatalkan', 'value' => (int) ($metrics['cancelledOrders'] ?? 0), 'icon' => 'bi-x-circle', 'class' => 'danger'],
        ];
        ?>
        <?php foreach ($miniCards as $card): ?>
            <div class="col-sm-6 col-xl">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="p-2 rounded-3 bg-<?= $card['class']; ?> bg-opacity-10 text-<?= $card['class']; ?>">
                            <i class="bi <?= $card['icon']; ?> fs-4"></i>
                        </div>
                        <div>
                            <div class="small text-muted"><?= htmlspecialchars($card['label']); ?></div>
                            <div class="fs-4 fw-bold"><?= $card['value']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-grid text-danger me-2"></i> Akses Cepat Administrator</h5>
                    <span class="badge bg-danger bg-opacity-10 text-danger"><?= (int) ($metrics['totalServices'] ?? 0); ?> layanan</span>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6 col-xl-3">
                        <a href="<?= BASEURL; ?>/admin/users" class="btn btn-outline-primary w-100 p-3 rounded-3 text-start h-100">
                            <i class="bi bi-people fs-3 text-primary d-block mb-1"></i>
                            <span class="fw-bold d-block">Data Pengguna</span>
                            <span class="small text-muted">Lihat & edit akun</span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="<?= BASEURL; ?>/admin/reports" class="btn btn-outline-success w-100 p-3 rounded-3 text-start h-100">
                            <i class="bi bi-graph-up-arrow fs-3 text-success d-block mb-1"></i>
                            <span class="fw-bold d-block">Laporan</span>
                            <span class="small text-muted">Statistik & analitik</span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="<?= BASEURL; ?>/admin/chat-history" class="btn btn-outline-info w-100 p-3 rounded-3 text-start h-100">
                            <i class="bi bi-chat-square-text fs-3 text-info d-block mb-1"></i>
                            <span class="fw-bold d-block">Riwayat Chatbot</span>
                            <span class="small text-muted">Percakapan AI</span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="<?= BASEURL; ?>/admin/services" class="btn btn-outline-warning w-100 p-3 rounded-3 text-start h-100">
                            <i class="bi bi-box-seam fs-3 text-warning d-block mb-1"></i>
                            <span class="fw-bold d-block">Katalog Layanan</span>
                            <span class="small text-muted">Tambah & edit layanan</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-person-badge text-danger me-2"></i> Komposisi Pengguna</h5>
                <?php
                $roleLabels = [
                    'customer' => ['Customer', 'bg-primary'],
                    'technician' => ['Teknisi', 'bg-success'],
                    'operator' => ['Operator', 'bg-warning text-dark'],
                    'principal' => ['Kepala Sekolah', 'bg-danger'],
                    'admin' => ['Administrator', 'bg-info text-dark'],
                ];
                ?>
                <div class="vstack gap-2">
                    <?php foreach ($roleLabels as $roleKey => $roleMeta): ?>
                        <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light">
                            <span class="small fw-semibold"><span class="badge <?= $roleMeta[1]; ?> me-2"><?= htmlspecialchars($roleMeta[0]); ?></span></span>
                            <span class="fw-bold"><?= (int) ($roleCounts[$roleKey] ?? 0); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="small text-muted mt-3">
                    <i class="bi bi-calendar-plus me-1"></i> <?= (int) ($metrics['newUsers30d'] ?? 0); ?> pengguna baru dalam 30 hari.
                </div>
            </div>
        </div>
    </div>

    <?php
    $statusCounts = $metrics['statusCounts'] ?? [];
    $statusChartLabels = [
        'submitted' => 'Submitted', 'verified' => 'Verified', 'scheduled' => 'Scheduled',
        'assigned' => 'Assigned', 'in_progress' => 'In Progress',
        'waiting_confirmation' => 'Waiting Confirmation', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
    ];
    $trend = $metrics['dailyTrend'] ?? [];
    $maxTrend = 1;
    foreach ($trend as $day) {
        $maxTrend = max($maxTrend, (int) $day['total']);
    }
    ?>
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0"><i class="bi bi-bar-chart-line text-danger me-2"></i> Grafik Pemesanan 14 Hari</h5>
                    <div class="small text-muted mt-1">Jumlah pesanan masuk dan pesanan selesai per hari.</div>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($trend)): ?>
                        <div class="d-flex align-items-end justify-content-between gap-1" style="height: 220px;">
                            <?php foreach ($trend as $day): ?>
                                <?php
                                $total = (int) $day['total'];
                                $completed = (int) $day['completed'];
                                $totalHeight = max(4, (int) round(($total / $maxTrend) * 170));
                                $completedHeight = max(2, (int) round(($completed / $maxTrend) * 170));
                                ?>
                                <div class="d-flex flex-column align-items-center flex-grow-1" title="<?= htmlspecialchars(date('d M Y', strtotime((string) $day['day']))); ?>: <?= $total; ?> pesanan, <?= $completed; ?> selesai">
                                    <div class="d-flex align-items-end gap-1" style="height: 180px;">
                                        <div class="bg-danger bg-opacity-75 rounded-top" style="width: 12px; height: <?= $totalHeight; ?>px;"></div>
                                        <div class="bg-success rounded-top" style="width: 12px; height: <?= $completedHeight; ?>px;"></div>
                                    </div>
                                    <div class="small text-muted mt-1" style="font-size: .65rem;"><?= htmlspecialchars(date('d/m', strtotime((string) $day['day']))); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex gap-3 mt-3 small text-muted">
                            <span><span class="d-inline-block bg-danger bg-opacity-75 rounded me-1" style="width: 10px; height: 10px;"></span> Total</span>
                            <span><span class="d-inline-block bg-success rounded me-1" style="width: 10px; height: 10px;"></span> Selesai</span>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-5"><i class="bi bi-bar-chart fs-2 d-block mb-2 opacity-50"></i>Belum ada data tren pesanan.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0"><i class="bi bi-pie-chart text-primary me-2"></i> Distribusi Status Pesanan</h5>
                </div>
                <div class="card-body p-4">
                    <?php foreach ($statusChartLabels as $statusKey => $statusLabel): ?>
                        <?php
                        $count = (int) ($statusCounts[$statusKey] ?? 0);
                        $totalOrders = max(1, (int) array_sum($statusCounts));
                        $percent = round(($count / $totalOrders) * 100);
                        ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted"><?= htmlspecialchars($statusLabel); ?></span>
                                <span class="fw-semibold"><?= $count; ?> (<?= $percent; ?>%)</span>
                            </div>
                            <div class="progress" style="height: 7px;"><div class="progress-bar bg-primary" style="width: <?= $percent; ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="bi bi-person-plus text-primary me-2"></i> Pengguna Terbaru</h5>
                    <a href="<?= BASEURL; ?>/admin/users" class="small fw-semibold text-decoration-none">Lihat semua</a>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($recentUsers)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>Nama</th><th>Role</th><th>Status</th><th>Terdaftar</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsers as $u): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small"><?= htmlspecialchars((string) $u['name']); ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars((string) $u['email']); ?></div>
                                            </td>
                                            <td><span class="badge bg-secondary text-capitalize"><?= htmlspecialchars((string) $u['role']); ?></span></td>
                                            <td>
                                                <?php if ((int) $u['is_active'] === 1): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Nonaktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted text-nowrap"><?= htmlspecialchars(date('d M Y', strtotime((string) $u['created_at']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small py-4 text-center">Belum ada data pengguna.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="bi bi-receipt text-warning me-2"></i> Pesanan Terbaru</h5>
                    <a href="<?= BASEURL; ?>/admin/reports" class="small fw-semibold text-decoration-none">Lihat laporan</a>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($recentOrders)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>Kode</th><th>Customer</th><th>Layanan</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $o): ?>
                                        <tr>
                                            <td class="small fw-semibold text-nowrap">#<?= htmlspecialchars((string) $o['order_code']); ?></td>
                                            <td class="small"><?= htmlspecialchars((string) $o['customer_name']); ?></td>
                                            <td class="small text-muted"><?= htmlspecialchars((string) $o['service_name']); ?></td>
                                            <td><span class="badge bg-secondary text-capitalize"><?= htmlspecialchars((string) $o['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small py-4 text-center">Belum ada pesanan.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="bi bi-chat-square-text text-info me-2"></i> Riwayat Chatbot Terbaru</h5>
            <a href="<?= BASEURL; ?>/admin/chat-history" class="small fw-semibold text-decoration-none">Lihat semua</a>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($recentChats)): ?>
                <div class="vstack gap-3">
                    <?php foreach ($recentChats as $chat): ?>
                        <div class="p-3 rounded-3 bg-light">
                            <div class="d-flex justify-content-between gap-3">
                                <div class="fw-semibold small text-dark"><?= htmlspecialchars(mb_substr((string) $chat['query'], 0, 180)); ?></div>
                                <span class="small text-muted text-nowrap"><?= htmlspecialchars(date('d M H:i', strtotime((string) $chat['created_at']))); ?></span>
                            </div>
                            <div class="small text-secondary mt-1"><?= htmlspecialchars(mb_substr((string) $chat['reply'], 0, 240)); ?></div>
                            <div class="small text-muted mt-1">
                                <?= htmlspecialchars((string) ($chat['user_name'] ?: 'Tamu')); ?>
                                · <?= htmlspecialchars((string) ($chat['source'] ?? '-')); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-muted small py-4 text-center">Belum ada percakapan chatbot yang tersimpan.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
