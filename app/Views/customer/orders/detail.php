<?php
$order = $data['order'];
$statusList = ['submitted', 'verified', 'scheduled', 'assigned', 'in_progress', 'waiting_confirmation', 'completed'];
$currentStatus = $order['status'];
$currentIndex = array_search($currentStatus, $statusList);
if ($currentIndex === false) $currentIndex = -1;
?>

<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-primary border-3">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small text-secondary">
                        <li class="breadcrumb-item"><a href="<?= BASEURL; ?>/customer/orders" class="text-secondary text-decoration-none">Riwayat Pesanan</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page"><?= htmlspecialchars($order['order_code']); ?></li>
                    </ol>
                </nav>
                <h2 class="fw-bold mb-0">Pesanan #<?= htmlspecialchars($order['order_code']); ?></h2>
            </div>
            <a href="<?= BASEURL; ?>/customer/orders" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat
            </a>
        </div>
    </div>
</div>

<div class="container py-3 mb-5">
    
    <!-- Workflow Progress Bar Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-4 bg-white">
        <h5 class="fw-bold text-dark mb-4"><i class="bi bi-diagram-2 text-primary me-2"></i> Progress Alur Workflow Pesanan</h5>

        <?php if ($currentStatus === 'cancelled'): ?>
            <div class="alert alert-danger mb-0 rounded-3">
                <i class="bi bi-x-circle-fill me-2"></i> Pesanan ini telah <strong>DIBATALKAN</strong> oleh operator/sistem.
            </div>
        <?php else: ?>
            <div class="position-relative m-4 d-none d-md-block">
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= ($currentIndex / (count($statusList) - 1)) * 100; ?>%;"></div>
                </div>
                <div class="d-flex justify-content-between position-absolute top-50 start-0 end-0 translate-middle-y">
                    <?php 
                    $labels = [
                        'submitted' => 'Diajukan',
                        'verified'  => 'Verifikasi',
                        'scheduled' => 'Jadwal',
                        'assigned'  => 'Ditugaskan',
                        'in_progress' => 'Pengerjaan',
                        'waiting_confirmation' => 'Konfirmasi',
                        'completed' => 'Selesai'
                    ];
                    foreach ($statusList as $idx => $st): 
                        $isPassed = $idx <= $currentIndex;
                        $isCurrent = $idx === $currentIndex;
                    ?>
                        <div class="text-center">
                            <button type="button" class="btn btn-sm rounded-circle p-0 d-inline-flex align-items-center justify-content-center <?= $isPassed ? 'btn-primary' : 'btn-secondary bg-opacity-25 border-0 text-muted'; ?>" style="width: 32px; height: 32px;">
                                <i class="bi <?= $isPassed ? 'bi-check-lg' : 'bi-circle'; ?> fs-6"></i>
                            </button>
                            <div class="small fw-semibold mt-2 <?= $isCurrent ? 'text-primary fw-bold' : ($isPassed ? 'text-dark' : 'text-muted'); ?>">
                                <?= $labels[$st]; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Mobile Status Badge View -->
            <div class="d-md-none p-3 rounded-3 bg-light text-center">
                <span class="text-muted small d-block mb-1">Status Pengerjaan Saat Ini:</span>
                <span class="badge bg-primary fs-6 px-3 py-2 text-uppercase"><?= htmlspecialchars($currentStatus); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Order Specification Details -->
    <div class="row gy-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">Rincian Permintaan Jasa</h5>

                <div class="mb-3">
                    <label class="text-muted small d-block">Layanan IT yang Dipesan:</label>
                    <div class="fs-5 fw-bold text-primary"><?= htmlspecialchars($order['service_name']); ?></div>
                </div>

                <div class="mb-3">
                    <label class="text-muted small d-block">Deskripsi Masalah / Kebutuhan:</label>
                    <div class="p-3 bg-light rounded-3 text-dark"><?= nl2br(htmlspecialchars($order['problem_description'])); ?></div>
                </div>

                <?php if (!empty($order['device_info'])): ?>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Informasi Perangkat:</label>
                        <div class="fw-semibold text-dark"><?= htmlspecialchars($order['device_info']); ?></div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="text-muted small d-block">Alamat Lokasi Pekerjaan:</label>
                    <div class="fw-semibold text-dark"><i class="bi bi-geo-alt text-danger me-1"></i> <?= htmlspecialchars($order['location_address']); ?></div>
                </div>

                <?php if (!empty($order['operator_notes'])): ?>
                    <div class="p-3 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 mt-3">
                        <strong class="text-dark small"><i class="bi bi-chat-left-text me-1"></i> Catatan Operator:</strong>
                        <p class="mb-0 small text-dark mt-1"><?= nl2br(htmlspecialchars($order['operator_notes'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">Informasi Kontak & Waktu</h5>
                
                <div class="mb-3">
                    <span class="text-muted small d-block">Nama Pemesan:</span>
                    <strong class="text-dark"><?= htmlspecialchars($order['customer_name']); ?></strong>
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block">No. Telepon / WhatsApp:</span>
                    <strong class="text-dark"><i class="bi bi-telephone text-success me-1"></i> <?= htmlspecialchars($order['customer_phone']); ?></strong>
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block">Jadwal Diinginkan:</span>
                    <strong class="text-dark"><i class="bi bi-calendar-event text-primary me-1"></i> <?= date('d M Y H:i', strtotime($order['scheduled_date'])); ?></strong>
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block">Tanggal Pengajuan:</span>
                    <span class="text-muted"><?= date('d M Y H:i', strtotime($order['created_at'])); ?></span>
                </div>

                <hr>
                <div class="mb-3">
                    <span class="text-muted small d-block">Status Pembayaran:</span>
                    <?php
                    $paymentStatus = $order['payment_status'] ?? 'unpaid';
                    $paymentLabels = ['unpaid' => 'Belum Dibayar', 'pending' => 'Menunggu Pembayaran', 'paid' => 'Lunas', 'refunded' => 'Dikembalikan'];
                    $paymentBadge = ['unpaid' => 'bg-secondary', 'pending' => 'bg-warning text-dark', 'paid' => 'bg-success', 'refunded' => 'bg-info text-dark'];
                    ?>
                    <span class="badge <?= $paymentBadge[$paymentStatus] ?? 'bg-secondary'; ?>"><?= htmlspecialchars($paymentLabels[$paymentStatus] ?? $paymentStatus); ?></span>
                    <?php if (!empty($order['payment_amount'])): ?>
                        <div class="small text-muted mt-1">Nominal: Rp <?= number_format((float) $order['payment_amount'], 0, ',', '.'); ?></div>
                    <?php endif; ?>
                </div>

                <hr>
                <div>
                    <span class="text-muted small d-block mb-2">Teknisi yang Ditugaskan:</span>
                    <?php if (!empty($data['workOrders'])): ?>
                        <?php foreach ($data['workOrders'] as $wo): ?>
                            <div class="p-2 rounded-3 bg-success bg-opacity-10 mb-2">
                                <div class="fw-semibold text-dark"><i class="bi bi-person-badge text-success me-1"></i> <?= htmlspecialchars((string) $wo['technician_name']); ?></div>
                                <div class="small text-muted">Status tugas: <span class="badge bg-success text-uppercase"><?= htmlspecialchars((string) $wo['status']); ?></span></div>
                                <?php if (!empty($wo['diagnosis_notes'])): ?>
                                    <div class="small text-muted mt-1">Diagnosa: <?= nl2br(htmlspecialchars((string) $wo['diagnosis_notes'])); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($wo['action_taken'])): ?>
                                    <div class="small text-muted mt-1">Pengerjaan: <?= nl2br(htmlspecialchars((string) $wo['action_taken'])); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="small text-muted"><i class="bi bi-hourglass-split me-1"></i> Teknisi akan ditampilkan setelah operator menugaskan.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row gy-4 mt-1">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <h5 class="fw-bold text-dark mb-3 border-bottom pb-2"><i class="bi bi-images text-primary me-2"></i> Dokumentasi Pengerjaan</h5>
                <?php if (!empty($data['files'])): ?>
                    <div class="row g-3">
                        <?php foreach ($data['files'] as $file): ?>
                            <?php
                            $fileUrl = BASEURL . '/' . ltrim((string) $file['file_path'], '/');
                            $typeLabel = ['damage' => 'Foto Kerusakan', 'before' => 'Sebelum', 'after' => 'Sesudah'][$file['file_type']] ?? ucfirst((string) $file['file_type']);
                            ?>
                            <div class="col-sm-6">
                                <a href="<?= htmlspecialchars($fileUrl); ?>" target="_blank" class="text-decoration-none">
                                    <img src="<?= htmlspecialchars($fileUrl); ?>" alt="<?= htmlspecialchars($typeLabel); ?>" class="img-fluid rounded-3 border" style="height: 190px; width: 100%; object-fit: cover;">
                                    <div class="small text-muted mt-1"><span class="badge bg-light text-dark border"><?= htmlspecialchars($typeLabel); ?></span> <?= htmlspecialchars((string) ($file['caption'] ?: '')); ?></div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted small">Belum ada foto dokumentasi untuk pesanan ini.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <h5 class="fw-bold text-dark mb-3 border-bottom pb-2"><i class="bi bi-star text-warning me-2"></i> Ulasan & Rating</h5>
                <?php if (!empty($data['review'])): ?>
                    <div class="text-warning fs-4 mb-1">
                        <?= str_repeat('★', (int) $data['review']['rating']); ?><span class="text-secondary"><?= str_repeat('☆', max(0, 5 - (int) $data['review']['rating'])); ?></span>
                    </div>
                    <div class="small text-secondary"><?= nl2br(htmlspecialchars((string) ($data['review']['comment'] ?: 'Tanpa komentar.'))); ?></div>
                    <div class="small text-muted mt-2"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) $data['review']['created_at']))); ?></div>
                <?php elseif ($order['status'] === 'completed'): ?>
                    <form action="<?= BASEURL; ?>/customer/orders/review" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($data['csrfToken'] ?? ''); ?>">
                        <input type="hidden" name="order_code" value="<?= htmlspecialchars((string) $order['order_code']); ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Rating <span class="text-danger">*</span></label>
                            <select name="rating" class="form-select" required>
                                <option value="">Pilih rating</option>
                                <option value="5">★★★★★ (Sangat Baik)</option>
                                <option value="4">★★★★☆ (Baik)</option>
                                <option value="3">★★★☆☆ (Cukup)</option>
                                <option value="2">★★☆☆☆ (Kurang)</option>
                                <option value="1">★☆☆☆☆ (Sangat Kurang)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Komentar</label>
                            <textarea name="comment" class="form-control" rows="4" maxlength="1000" placeholder="Ceritakan pengalaman pengerjaan..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill w-100 fw-bold"><i class="bi bi-send me-1"></i> Kirim Ulasan</button>
                    </form>
                <?php else: ?>
                    <div class="text-muted small"><i class="bi bi-info-circle me-1"></i> Form ulasan tersedia setelah pekerjaan berstatus selesai.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
