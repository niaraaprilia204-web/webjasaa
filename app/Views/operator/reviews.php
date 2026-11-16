<?php
$reviews = $data['reviews'] ?? [];
$summary = $data['summary'] ?? [];
$search = $data['search'] ?? '';
$distribution = $summary['distribution'] ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$average = (float) ($summary['average_rating'] ?? 0);
$total = (int) ($summary['total'] ?? 0);
?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-success border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-success px-3 py-2 rounded-pill mb-2"><i class="bi bi-star me-1"></i> Umpan Balik Customer</span>
                <h2 class="fw-bold mb-0">Semua Ulasan</h2>
                <div class="small text-white-50 mt-1">Monitoring kualitas layanan teknisi dan unit bisnis TKJ.</div>
            </div>
            <a href="<?= BASEURL; ?>/operator/dashboard" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard Operator
            </a>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4 text-center">
                <div class="text-muted small mb-1">Rating Rata-rata</div>
                <div class="display-4 fw-bold text-warning"><?= htmlspecialchars((string) $average); ?></div>
                <div class="fs-5 text-warning mb-2">
                    <?= str_repeat('★', (int) round($average)); ?><span class="text-secondary"><?= str_repeat('☆', max(0, 5 - (int) round($average))); ?></span>
                </div>
                <div class="small text-muted">Berdasarkan <?= $total; ?> ulasan</div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
                <h6 class="fw-bold mb-3">Distribusi Rating</h6>
                <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                    <?php
                    $count = (int) ($distribution[$rating] ?? 0);
                    $percent = $total > 0 ? round(($count / $total) * 100) : 0;
                    ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="small fw-semibold text-nowrap" style="width: 46px;"><?= $rating; ?> <i class="bi bi-star-fill text-warning"></i></span>
                        <div class="progress flex-grow-1" style="height: 8px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $percent; ?>%"></div>
                        </div>
                        <span class="small text-muted text-end" style="width: 52px;"><?= $count; ?> (<?= $percent; ?>%)</span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form action="<?= BASEURL; ?>/operator/reviews" method="GET" class="row g-3 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="<?= htmlspecialchars((string) $search); ?>" class="form-control border-start-0" placeholder="Cari komentar, kode order, layanan, atau nama customer...">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-success rounded-pill w-100 fw-semibold"><i class="bi bi-funnel me-1"></i> Cari</button>
                <a href="<?= BASEURL; ?>/operator/reviews" class="btn btn-outline-secondary rounded-pill" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Customer</th>
                        <th>Order / Layanan</th>
                        <th>Rating</th>
                        <th>Komentar</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $review): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold"><?= htmlspecialchars((string) $review['customer_name']); ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars((string) $review['customer_email']); ?></div>
                                </td>
                                <td>
                                    <div class="small fw-semibold">#<?= htmlspecialchars((string) $review['order_code']); ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars((string) $review['service_name']); ?></div>
                                </td>
                                <td>
                                    <div class="text-warning text-nowrap">
                                        <?= str_repeat('★', (int) $review['rating']); ?><span class="text-secondary"><?= str_repeat('☆', max(0, 5 - (int) $review['rating'])); ?></span>
                                    </div>
                                    <div class="small text-muted"><?= (int) $review['rating']; ?>/5</div>
                                </td>
                                <td class="small text-secondary" style="max-width: 360px;">
                                    <?= $review['comment'] !== null && $review['comment'] !== '' ? nl2br(htmlspecialchars((string) $review['comment'])) : '<span class="text-muted">Tanpa komentar</span>'; ?>
                                </td>
                                <td class="small text-muted text-nowrap"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) $review['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-5">Belum ada ulasan yang cocok.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
