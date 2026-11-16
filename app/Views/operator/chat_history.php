<?php
$summary = $data['summary'] ?? [];
$complaints = $data['complaints'] ?? [];
$search = $data['search'] ?? '';
$pagination = $data['pagination'] ?? ['page' => 1, 'pages' => 1, 'total' => 0];
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
?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-primary border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-primary px-3 py-2 rounded-pill mb-2"><i class="bi bi-chat-square-text me-1"></i> Pusat Komunikasi</span>
                <h2 class="fw-bold mb-0">Riwayat Chat & Keluhan</h2>
                <div class="small text-white-50 mt-1">Pantau percakapan chatbot dan keluhan dari pesanan yang belum selesai.</div>
            </div>
            <a href="<?= BASEURL; ?>/operator/dashboard" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard Operator
            </a>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-info">
                <div class="card-body">
                    <div class="text-muted small">Total Chat</div>
                    <div class="fs-2 fw-bold text-info"><?= (int) ($summary['total'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small">Chat Hari Ini</div>
                    <div class="fs-2 fw-bold text-primary"><?= (int) ($summary['today'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small">Pengguna Unik</div>
                    <div class="fs-2 fw-bold text-success"><?= (int) ($summary['uniqueUsers'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-warning">
                <div class="card-body">
                    <div class="text-muted small">Keluhan Aktif</div>
                    <div class="fs-2 fw-bold text-warning"><?= count($complaints); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form action="<?= BASEURL; ?>/operator/chat-history" method="GET" class="row g-3 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="<?= htmlspecialchars((string) $search); ?>" class="form-control border-start-0" placeholder="Cari chat, kode order, customer, atau layanan...">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill w-100 fw-semibold"><i class="bi bi-funnel me-1"></i> Cari</button>
                <a href="<?= BASEURL; ?>/operator/chat-history" class="btn btn-outline-secondary rounded-pill" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="bi bi-robot text-info me-2"></i> Log Chatbot</h5>
            <span class="badge bg-secondary"><?= (int) ($pagination['total'] ?? 0); ?> data</span>
        </div>
        <div class="card-body p-0 mt-3">
            <?php require __DIR__ . '/../components/chat_logs_table.php'; ?>
        </div>
        <?php if ((int) ($pagination['pages'] ?? 1) > 1): ?>
            <div class="card-footer bg-white border-0 p-4">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for ($i = 1; $i <= (int) $pagination['pages']; $i++): ?>
                            <li class="page-item <?= $i === (int) $pagination['page'] ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= BASEURL; ?>/operator/chat-history?<?= htmlspecialchars(http_build_query(['search' => $search, 'page' => $i])); ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-0 p-4 pb-0">
            <h5 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle text-warning me-2"></i> Keluhan dari Pesanan Aktif</h5>
            <div class="small text-muted mt-1">Pesanan berstatus belum selesai; deskripsi masalah customer ditampilkan sebagai antrean keluhan.</div>
        </div>
        <div class="card-body p-0 mt-3">
            <?php if (!empty($complaints)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th class="ps-4">Order</th><th>Customer</th><th>Layanan</th><th>Keluhan</th><th>Status</th><th>Tanggal</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $item): ?>
                                <tr>
                                    <td class="ps-4"><code>#<?= htmlspecialchars((string) $item['order_code']); ?></code></td>
                                    <td>
                                        <div class="small fw-semibold"><?= htmlspecialchars((string) $item['customer_name']); ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars((string) ($item['customer_phone'] ?: '-')); ?></div>
                                    </td>
                                    <td class="small"><?= htmlspecialchars((string) $item['service_name']); ?></td>
                                    <td class="small text-secondary" style="max-width: 340px;">
                                        <?= nl2br(htmlspecialchars((string) $item['problem_description'])); ?>
                                    </td>
                                    <td><span class="badge bg-warning text-dark text-capitalize"><?= htmlspecialchars($statusLabels[$item['status']] ?? (string) $item['status']); ?></span></td>
                                    <td class="small text-muted text-nowrap"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) $item['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-check-circle fs-1 d-block mb-2 text-success opacity-50"></i>
                    Tidak ada keluhan aktif saat ini.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
