<?php $metrics = $data['metrics']; ?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-warning border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill mb-2"><i class="bi bi-speedometer2 me-1"></i> Dashboard Operasional</span>
                <h2 class="fw-bold mb-0">Operator Unit Bisnis TKJ</h2>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASEURL; ?>/operator/orders" class="btn btn-warning rounded-pill btn-sm fw-bold">
                    <i class="bi bi-inbox me-1"></i> Kelola Workflow Orders
                </a>
                <a href="<?= BASEURL; ?>/auth/logout" class="btn btn-outline-light rounded-pill btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    
    <!-- 8 Core Metric Cards Grid -->
    <div class="row g-3 mb-5">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Pesanan masuk</div>
                    <div class="display-6 fw-bold text-primary my-1"><?= $metrics['totalOrders']; ?></div>
                    <div class="small text-secondary"><i class="bi bi-box-seam me-1"></i> Seluruh riwayat order</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning h-100">
                <div class="card-body">
                    <div class="text-muted small">Pesanan Baru (Submitted)</div>
                    <div class="display-6 fw-bold text-warning my-1"><?= $metrics['newOrders']; ?></div>
                    <div class="small text-secondary"><i class="bi bi-exclamation-circle me-1"></i> Membutuhkan verifikasi</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-info h-100">
                <div class="card-body">
                    <div class="text-muted small">Pesanan Aktif (Dalam Progres)</div>
                    <div class="display-6 fw-bold text-info my-1"><?= $metrics['activeOrders']; ?></div>
                    <div class="small text-secondary"><i class="bi bi-arrow-repeat me-1"></i> Berjalan di lapangan</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success h-100">
                <div class="card-body">
                    <div class="text-muted small">Pesanan Selesai (Completed)</div>
                    <div class="display-6 fw-bold text-success my-1"><?= $metrics['completedOrders']; ?></div>
                    <div class="small text-secondary"><i class="bi bi-check-circle me-1"></i> Tuntas terverifikasi</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-dark h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Customer Terdaftar</div>
                    <div class="display-6 fw-bold text-dark my-1"><?= $metrics['totalCustomers']; ?></div>
                    <div class="small text-secondary"><i class="bi bi-person-heart me-1"></i> Pelanggan aktif</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-teal h-100">
                <div class="card-body">
                    <div class="text-muted small">Siswa Teknisi Aktif</div>
                    <div class="display-6 fw-bold text-teal my-1"><?= $metrics['activeTechnicians']; ?></div>
                    <div class="small text-secondary"><i class="bi bi-tools me-1"></i> Ready & On-Duty</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success h-100">
                <div class="card-body">
                    <div class="text-muted small">Estimasi Pendapatan Jasa Unit Bisnis</div>
                    <div class="display-6 fw-extrabold text-success my-1"><?= $metrics['estimatedRevenue']; ?></div>
                    <div class="small text-secondary"><i class="bi bi-graph-up-arrow text-success me-1"></i> Akumulasi dari pesanan selesai</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation & Popular Services Row -->
    <div class="row g-4 mb-4">
        <!-- Quick Actions Card -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-grid text-warning me-2"></i> Akses Cepat Operasional</h5>
                <div class="row g-3">
                    <div class="col-6">
                        <a href="<?= BASEURL; ?>/operator/orders" class="btn btn-outline-warning text-dark w-100 p-3 rounded-3 text-start h-100">
                            <i class="bi bi-inbox fs-3 text-warning d-block mb-1"></i>
                            <span class="fw-bold d-block">Kelola Pesanan</span>
                            <span class="small text-muted">Verifikasi & Workflow</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= BASEURL; ?>/operator/technicians" class="btn btn-outline-success w-100 p-3 rounded-3 text-start h-100">
                            <i class="bi bi-tools fs-3 text-success d-block mb-1"></i>
                            <span class="fw-bold d-block">Teknisi Siswa</span>
                            <span class="small text-muted">Status & Keahlian</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= BASEURL; ?>/operator/customers" class="btn btn-outline-primary w-100 p-3 rounded-3 text-start h-100">
                            <i class="bi bi-people fs-3 text-primary d-block mb-1"></i>
                            <span class="fw-bold d-block">Data Customer</span>
                            <span class="small text-muted">Riwayat Pelanggan</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= BASEURL; ?>/operator/services" class="btn btn-outline-dark w-100 p-3 rounded-3 text-start h-100">
                            <i class="bi bi-box-seam fs-3 text-dark d-block mb-1"></i>
                            <span class="fw-bold d-block">Master Layanan</span>
                            <span class="small text-muted">Katalog & Harga</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Services Ranking -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-trophy text-warning me-2"></i> Layanan Paling Populer (Berdasarkan Pesanan)</h5>
                <?php if (!empty($metrics['popularServices'])): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($metrics['popularServices'] as $idx => $pop): ?>
                            <div class="list-group-item bg-transparent px-0 border-light d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge bg-warning bg-opacity-25 text-dark rounded-circle p-2 fs-6" style="width: 32px; height: 32px; display: inline-flex; align-items-center; justify-content: center;"><?= $idx + 1; ?></span>
                                    <div>
                                        <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($pop['title']); ?></div>
                                        <span class="small text-muted"><?= htmlspecialchars($pop['category_name'] ?? 'Layanan IT'); ?></span>
                                    </div>
                                </div>
                                <span class="badge bg-primary rounded-pill px-3 py-2"><?= $pop['order_count']; ?> Pesanan</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">Belum ada statistik pemesanan layanan.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Review, AI, Communication & Report Shortcuts -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-grid-3x3-gap text-primary me-2"></i> Menu Tambahan Operator</h5>
                <div class="row g-3">
                    <div class="col-sm-6 col-xl-3">
                        <a href="<?= BASEURL; ?>/operator/reviews" class="btn btn-outline-success w-100 p-3 rounded-3 text-start h-100">
                            <i class="bi bi-star fs-3 text-success d-block mb-1"></i>
                            <span class="fw-bold d-block">Semua Ulasan</span>
                            <span class="small text-muted">Rating & komentar</span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="<?= BASEURL; ?>/operator/ai-config" class="btn btn-outline-info w-100 p-3 rounded-3 text-start h-100">
                            <i class="bi bi-robot fs-3 text-info d-block mb-1"></i>
                            <span class="fw-bold d-block">Konfigurasi AI</span>
                            <span class="small text-muted">Model & instruksi</span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="<?= BASEURL; ?>/operator/chat-history" class="btn btn-outline-primary w-100 p-3 rounded-3 text-start h-100">
                            <i class="bi bi-chat-square-text fs-3 text-primary d-block mb-1"></i>
                            <span class="fw-bold d-block">Riwayat Chat</span>
                            <span class="small text-muted">Chat & keluhan</span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <a href="<?= BASEURL; ?>/operator/reports" class="btn btn-outline-danger w-100 p-3 rounded-3 text-start h-100">
                            <i class="bi bi-graph-up-arrow fs-3 text-danger d-block mb-1"></i>
                            <span class="fw-bold d-block">Laporan</span>
                            <span class="small text-muted">Statistik operasional</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-activity text-warning me-2"></i> Ringkasan Kualitas</h5>
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-light mb-2">
                    <span class="small text-muted"><i class="bi bi-star-fill text-warning me-1"></i> Rating rata-rata</span>
                    <span class="fw-bold"><?= htmlspecialchars((string) ($metrics['averageRating'] ?? 0)); ?>/5</span>
                </div>
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-light mb-2">
                    <span class="small text-muted"><i class="bi bi-chat-dots text-primary me-1"></i> Chat hari ini</span>
                    <span class="fw-bold"><?= (int) ($metrics['todayChats'] ?? 0); ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-light">
                    <span class="small text-muted"><i class="bi bi-exclamation-triangle text-warning me-1"></i> Keluhan aktif</span>
                    <span class="fw-bold"><?= (int) ($metrics['activeComplaints'] ?? 0); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
