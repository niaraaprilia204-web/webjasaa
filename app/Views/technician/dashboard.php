<?php
$activeOrders = $data['activeOrders'] ?? [];
$history = $data['history'] ?? [];
$stats = $data['stats'] ?? ['total' => 0, 'active' => 0, 'done' => 0];
$statusBadge = [
    'assigned' => 'bg-secondary', 'accepted' => 'bg-info text-dark', 'in_progress' => 'bg-primary', 'done' => 'bg-success',
];
$orderStatusBadge = [
    'submitted' => 'bg-secondary', 'verified' => 'bg-info text-dark', 'scheduled' => 'bg-primary',
    'assigned' => 'bg-warning text-dark', 'in_progress' => 'bg-primary', 'waiting_confirmation' => 'bg-warning text-dark',
    'completed' => 'bg-success', 'cancelled' => 'bg-danger',
];
?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-success border-3">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <span class="badge bg-success px-3 py-2 rounded-pill mb-2"><i class="bi bi-tools me-1"></i> Portal Teknisi TKJ</span>
                <h2 class="fw-bold mb-0">Selamat Datang, <?= htmlspecialchars($data['user']['name']); ?></h2>
                <div class="small text-white-50 mt-1">Kelola pekerjaan yang ditugaskan dan unggah dokumentasi pengerjaan.</div>
            </div>
            <a href="<?= BASEURL; ?>/auth/logout" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
</div>

<div class="container py-3 mb-5">
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle"><i class="bi bi-inbox fs-3"></i></div>
                    <div><div class="small text-muted">Total Tugas</div><div class="fs-3 fw-bold"><?= (int) $stats['total']; ?></div></div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle"><i class="bi bi-wrench-adjustable fs-3"></i></div>
                    <div><div class="small text-muted">Sedang Dikerjakan</div><div class="fs-3 fw-bold"><?= (int) $stats['active']; ?></div></div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle"><i class="bi bi-check2-circle fs-3"></i></div>
                    <div><div class="small text-muted">Selesai</div><div class="fs-3 fw-bold"><?= (int) $stats['done']; ?></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Info Card -->
    <?php if ($data['profile']): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle">
                        <i class="bi bi-person-badge fs-2"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($data['user']['name']); ?></h5>
                        <div class="small text-muted">
                            <span class="me-3"><i class="bi bi-card-text me-1"></i> NISN: <code><?= htmlspecialchars((string) ($data['profile']['nisn'] ?: '-')); ?></code></span>
                            <span><i class="bi bi-mortarboard me-1"></i> Kelas: <strong><?= htmlspecialchars((string) ($data['profile']['class_grade'] ?: '-')); ?></strong></span>
                        </div>
                    </div>
                </div>
                <span class="badge bg-success bg-opacity-25 text-success px-3 py-2 rounded-pill">
                    <i class="bi bi-activity me-1"></i> Status: <?= strtoupper((string) $data['profile']['status']); ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" id="tugas-aktif">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clipboard-check text-success me-2"></i> Tugas Aktif</h5>
            <span class="badge bg-primary"><?= count($activeOrders); ?> tugas</span>
        </div>

        <?php if (!empty($activeOrders)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Kode Order</th><th>Layanan</th><th>Customer</th><th>Jadwal</th><th>Status Tugas</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeOrders as $wo): ?>
                            <tr>
                                <td><code><?= htmlspecialchars((string) $wo['order_code']); ?></code></td>
                                <td class="fw-semibold"><?= htmlspecialchars((string) $wo['service_name']); ?></td>
                                <td>
                                    <div class="small fw-semibold"><?= htmlspecialchars((string) $wo['customer_name']); ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars((string) ($wo['customer_phone'] ?: '-')); ?></div>
                                </td>
                                <td class="small text-muted"><?= !empty($wo['scheduled_date']) ? htmlspecialchars(date('d M Y H:i', strtotime((string) $wo['scheduled_date']))) : '-'; ?></td>
                                <td><span class="badge <?= $statusBadge[$wo['status']] ?? 'bg-secondary'; ?> text-uppercase"><?= htmlspecialchars((string) $wo['status']); ?></span></td>
                                <td class="text-end">
                                    <a href="<?= BASEURL; ?>/technician/jobs/detail/<?= (int) $wo['id']; ?>" class="btn btn-sm btn-success rounded-pill">
                                        <i class="bi bi-eye me-1"></i> Detail & Kerjakan
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill ai-btn-checklist"
                                            data-service="<?= htmlspecialchars((string) $wo['service_name']); ?>"
                                            data-problem="<?= htmlspecialchars((string) $wo['problem_description']); ?>">
                                        <i class="bi bi-robot me-1"></i> AI
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-4 bg-light rounded-3 text-center text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                Belum ada tugas aktif untuk Anda.
            </div>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4" id="riwayat">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-secondary me-2"></i> Riwayat Pekerjaan Selesai</h5>
            <span class="badge bg-success"><?= count($history); ?> selesai</span>
        </div>

        <?php if (!empty($history)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Kode Order</th><th>Layanan</th><th>Customer</th><th>Selesai</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $wo): ?>
                            <tr>
                                <td><code><?= htmlspecialchars((string) $wo['order_code']); ?></code></td>
                                <td class="fw-semibold"><?= htmlspecialchars((string) $wo['service_name']); ?></td>
                                <td class="small"><?= htmlspecialchars((string) $wo['customer_name']); ?></td>
                                <td class="small text-muted"><?= !empty($wo['finished_at']) ? htmlspecialchars(date('d M Y H:i', strtotime((string) $wo['finished_at']))) : '-'; ?></td>
                                <td class="text-end">
                                    <a href="<?= BASEURL; ?>/technician/jobs/detail/<?= (int) $wo['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
                                        <i class="bi bi-eye me-1"></i> Lihat
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-4 bg-light rounded-3 text-center text-muted">
                <i class="bi bi-clock-history fs-2 d-block mb-2 text-secondary"></i>
                Belum ada riwayat pekerjaan selesai.
            </div>
        <?php endif; ?>
    </div>
</div>
