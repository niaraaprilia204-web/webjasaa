<?php
$reports = $data['reports'] ?? [];
?>
<div class="bg-primary text-white py-4 mb-4 shadow-sm border-bottom border-info border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-info text-dark px-3 py-2 rounded-pill mb-2"><i class="bi bi-chat-square-text me-1"></i> Laporan</span>
                <h2 class="fw-bold mb-0">Laporan Saya</h2>
                <div class="small text-white-50 mt-1">Riwayat laporan yang Anda kirim kepada Admin TKJ Service Hub.</div>
            </div>
            <a href="<?= BASEURL; ?>/customer/reports/create" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Kirim Laporan
            </a>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($reports)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Belum ada laporan. Silakan kirim laporan bila ada kendala.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Kode</th>
                                <th>Judul</th>
                                <th>Kategori</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th class="pe-3">Catatan Admin</th>
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
                                <td class="ps-3"><code><?= htmlspecialchars((string) ($report['report_code'] ?? '')); ?></code></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($report['subject'] ?? '')); ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars(mb_substr((string) ($report['description'] ?? ''), 0, 120)); ?></div>
                                </td>
                                <td class="small"><?= htmlspecialchars(ucfirst((string) ($report['category'] ?? 'umum'))); ?></td>
                                <td><span class="badge <?= $badge; ?>"><?= htmlspecialchars(CustomerReportService::statusLabel($status)); ?></span></td>
                                <td class="small text-muted text-nowrap"><?= htmlspecialchars((string) ($report['created_at'] ?? '')); ?></td>
                                <td class="pe-3 small text-muted"><?= htmlspecialchars((string) ($report['admin_notes'] ?? '-')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
