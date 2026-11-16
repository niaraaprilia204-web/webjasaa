<?php
$reports = $data['reports'] ?? [];
$statuses = $data['statuses'] ?? [];
$statusFilter = (string) ($data['filters']['status'] ?? '');
$csrfToken = (string) ($data['csrfToken'] ?? '');
?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-info border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-info text-dark px-3 py-2 rounded-pill mb-2"><i class="bi bi-chat-square-text me-1"></i> Laporan Customer</span>
                <h2 class="fw-bold mb-0">Laporan Customer</h2>
                <div class="small text-white-50 mt-1">Kelola laporan yang dikirim customer. Perubahan status otomatis memberi tahu pemilik laporan via WhatsApp.</div>
            </div>
            <a href="<?= BASEURL; ?>/admin/dashboard" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard Admin
            </a>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form action="<?= BASEURL; ?>/admin/customer-reports" method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Status Laporan</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= htmlspecialchars((string) $status); ?>" <?= $statusFilter === $status ? 'selected' : ''; ?>>
                            <?= htmlspecialchars(CustomerReportService::statusLabel($status)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary rounded-pill w-100"><i class="bi bi-funnel me-1"></i> Terapkan</button>
            </div>
            <div class="col-md-3">
                <a href="<?= BASEURL; ?>/admin/customer-reports" class="btn btn-outline-secondary rounded-pill w-100">Reset</a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($reports)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Belum ada laporan dari customer.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Kode</th>
                                <th>Customer</th>
                                <th>Judul &amp; Deskripsi</th>
                                <th>Status</th>
                                <th class="pe-3">Ubah Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($reports as $report): ?>
                            <?php
                            $status = (string) ($report['status'] ?? 'submitted');
                            $badge = [
                                'submitted'  => 'bg-warning text-dark',
                                'processing' => 'bg-info text-dark',
                                'resolved'   => 'bg-success',
                                'rejected'   => 'bg-danger',
                            ][$status] ?? 'bg-secondary';
                            ?>
                            <tr>
                                <td class="ps-3"><code><?= htmlspecialchars((string) ($report['report_code'] ?? '')); ?></code>
                                    <div class="small text-muted"><?= htmlspecialchars((string) ($report['created_at'] ?? '')); ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($report['customer_name'] ?? '')); ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars((string) ($report['customer_email'] ?? '')); ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars((string) ($report['customer_phone'] ?? '-')); ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($report['subject'] ?? '')); ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars(mb_substr((string) ($report['description'] ?? ''), 0, 140)); ?></div>
                                    <div class="small text-muted">Kategori: <?= htmlspecialchars(ucfirst((string) ($report['category'] ?? 'umum'))); ?></div>
                                </td>
                                <td><span class="badge <?= $badge; ?>"><?= htmlspecialchars(CustomerReportService::statusLabel($status)); ?></span></td>
                                <td class="pe-3">
                                    <form action="<?= BASEURL; ?>/admin/customer-reports/status" method="POST" class="d-flex flex-column gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                        <input type="hidden" name="report_id" value="<?= (int) ($report['id'] ?? 0); ?>">
                                        <select name="status" class="form-select form-select-sm">
                                            <?php foreach ($statuses as $option): ?>
                                                <option value="<?= htmlspecialchars((string) $option); ?>" <?= $status === $option ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars(CustomerReportService::statusLabel($option)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="admin_notes" class="form-control form-control-sm" maxlength="255"
                                               placeholder="Catatan untuk customer" value="<?= htmlspecialchars((string) ($report['admin_notes'] ?? '')); ?>">
                                        <button type="submit" class="btn btn-sm btn-primary rounded-pill">
                                            <i class="bi bi-check2 me-1"></i> Simpan &amp; Notifikasi
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
