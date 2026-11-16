<?php
$order = $data['order'];
$workOrders = $data['workOrders'] ?? [];
$files = $data['files'] ?? [];
$review = $data['review'] ?? null;
$technicians = $data['technicians'] ?? [];
$services = $data['services'] ?? [];
$csrfToken = $data['csrfToken'] ?? '';
$statusLabels = [
    'submitted' => 'Submitted', 'verified' => 'Verified', 'scheduled' => 'Scheduled',
    'assigned' => 'Assigned', 'in_progress' => 'In Progress',
    'waiting_confirmation' => 'Waiting Confirmation', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
];
$statusBadge = [
    'submitted' => 'bg-secondary', 'verified' => 'bg-info text-dark', 'scheduled' => 'bg-primary',
    'assigned' => 'bg-warning text-dark', 'in_progress' => 'bg-primary', 'waiting_confirmation' => 'bg-warning text-dark',
    'completed' => 'bg-success', 'cancelled' => 'bg-danger',
];
$paymentStatus = $order['payment_status'] ?? 'unpaid';
$paymentLabels = ['unpaid' => 'Belum Dibayar', 'pending' => 'Menunggu Pembayaran', 'paid' => 'Lunas', 'refunded' => 'Dikembalikan'];
$paymentBadge = ['unpaid' => 'bg-secondary', 'pending' => 'bg-warning text-dark', 'paid' => 'bg-success', 'refunded' => 'bg-info text-dark'];
?>

<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-warning border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="<?= BASEURL; ?>/operator/orders" class="text-secondary text-decoration-none">Kelola Pesanan</a></li>
                        <li class="breadcrumb-item active text-white"><?= htmlspecialchars((string) $order['order_code']); ?></li>
                    </ol>
                </nav>
                <h2 class="fw-bold mb-0">Detail Pesanan <?= htmlspecialchars((string) $order['order_code']); ?></h2>
                <div class="small text-white-50 mt-1">Edit informasi, ubah status, tugaskan teknisi, dan catat pembayaran.</div>
            </div>
            <div class="d-flex gap-2">
                <span class="badge <?= $statusBadge[$order['status']] ?? 'bg-secondary'; ?> px-3 py-2 align-self-center"><?= htmlspecialchars($statusLabels[$order['status']] ?? (string) $order['status']); ?></span>
                <a href="<?= BASEURL; ?>/operator/orders" class="btn btn-outline-light rounded-pill btn-sm"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square text-warning me-2"></i> Edit Informasi Pesanan</h5>
                    <span class="badge bg-light text-dark border">Dibuat <?= htmlspecialchars(date('d M Y H:i', strtotime((string) $order['created_at']))); ?></span>
                </div>
                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/operator/orders/updateDetails" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small fw-bold">Layanan</label>
                                <select name="service_id" class="form-select" required>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= (int) $service['id']; ?>" <?= (int) $service['id'] === (int) $order['service_id'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars((string) $service['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Jadwal Pengerjaan</label>
                                <input type="datetime-local" name="scheduled_date" class="form-control" required value="<?= !empty($order['scheduled_date']) ? date('Y-m-d\TH:i', strtotime((string) $order['scheduled_date'])) : ''; ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Deskripsi Masalah / Kebutuhan</label>
                                <textarea name="problem_description" class="form-control" rows="3" required minlength="10"><?= htmlspecialchars((string) $order['problem_description']); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Informasi Perangkat</label>
                                <input type="text" name="device_info" class="form-control" maxlength="255" value="<?= htmlspecialchars((string) ($order['device_info'] ?? '')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Alamat Lokasi</label>
                                <input type="text" name="location_address" class="form-control" required minlength="5" value="<?= htmlspecialchars((string) $order['location_address']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Catatan Operator untuk Customer</label>
                                <textarea name="operator_notes" class="form-control" rows="2" maxlength="1000"><?= htmlspecialchars((string) ($order['operator_notes'] ?? '')); ?></textarea>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-clipboard-check text-success me-2"></i> Penugasan Teknisi</h5></div>
                <div class="card-body p-4">
                    <?php if (!empty($workOrders)): ?>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Teknisi</th><th>Status</th><th>Diagnosa</th><th>Pekerjaan</th><th>Ditugaskan</th></tr></thead>
                                <tbody>
                                    <?php foreach ($workOrders as $wo): ?>
                                        <tr>
                                            <td class="small fw-semibold"><?= htmlspecialchars((string) $wo['technician_name']); ?><div class="text-muted"><?= htmlspecialchars((string) ($wo['class_grade'] ?: '-')); ?></div></td>
                                            <td><span class="badge bg-primary text-uppercase"><?= htmlspecialchars((string) $wo['status']); ?></span></td>
                                            <td class="small text-muted"><?= nl2br(htmlspecialchars((string) ($wo['diagnosis_notes'] ?: '-'))); ?></td>
                                            <td class="small text-muted"><?= nl2br(htmlspecialchars((string) ($wo['action_taken'] ?: '-'))); ?></td>
                                            <td class="small text-muted"><?= !empty($wo['assigned_at']) ? htmlspecialchars(date('d M Y H:i', strtotime((string) $wo['assigned_at']))) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border small mb-3"><i class="bi bi-info-circle me-1"></i> Belum ada teknisi yang ditugaskan.</div>
                    <?php endif; ?>

                    <form action="<?= BASEURL; ?>/operator/orders/assignTechnician" method="POST" class="row g-3 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Tugaskan / Ganti Teknisi</label>
                            <select name="technician_id" class="form-select" required>
                                <option value="">-- Pilih Teknisi --</option>
                                <?php foreach ($technicians as $tech): ?>
                                    <option value="<?= (int) $tech['technician_id']; ?>">
                                        <?= htmlspecialchars((string) $tech['name']); ?> (<?= htmlspecialchars((string) ($tech['class_grade'] ?: 'Kelas -')); ?>) · <?= strtoupper((string) $tech['status']); ?> · <?= (int) $tech['active_workload']; ?> tugas aktif
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Instruksi / Diagnosa Awal</label>
                            <input type="text" name="notes" class="form-control" maxlength="255" placeholder="Opsional">
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold"><i class="bi bi-person-plus me-1"></i> Simpan Penugasan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-images text-primary me-2"></i> Dokumentasi Foto</h5></div>
                <div class="card-body p-4">
                    <?php if (!empty($files)): ?>
                        <div class="row g-3">
                            <?php foreach ($files as $file): ?>
                                <div class="col-sm-6 col-lg-4">
                                    <a href="<?= BASEURL . '/' . htmlspecialchars(ltrim((string) $file['file_path'], '/')); ?>" target="_blank" class="text-decoration-none">
                                        <img src="<?= BASEURL . '/' . htmlspecialchars(ltrim((string) $file['file_path'], '/')); ?>" alt="<?= htmlspecialchars((string) ($file['caption'] ?: 'Dokumentasi')); ?>" class="img-fluid rounded-3 border" style="height: 150px; width: 100%; object-fit: cover;">
                                        <div class="small text-muted mt-1 text-capitalize"><?= htmlspecialchars((string) $file['file_type']); ?> · <?= htmlspecialchars((string) ($file['caption'] ?: '-')); ?></div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small text-center py-3">Belum ada foto dokumentasi.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-person-vcard text-primary me-2"></i> Data Customer</h5></div>
                <div class="card-body p-4 small">
                    <div class="mb-2"><span class="text-muted d-block">Nama</span><strong><?= htmlspecialchars((string) $order['customer_name']); ?></strong></div>
                    <div class="mb-2"><span class="text-muted d-block">Email</span><?= htmlspecialchars((string) ($order['customer_email'] ?? '-')); ?></div>
                    <div class="mb-2"><span class="text-muted d-block">Telepon / WhatsApp</span><a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', (string) ($order['customer_phone'] ?? '')); ?>" target="_blank" class="text-decoration-none"><?= htmlspecialchars((string) ($order['customer_phone'] ?? '-')); ?></a></div>
                    <div class="mb-2"><span class="text-muted d-block">Alamat Customer</span><?= htmlspecialchars((string) ($order['customer_address'] ?: '-')); ?></div>
                    <div><span class="text-muted d-block">Institusi</span><?= htmlspecialchars((string) ($order['institution'] ?: '-')); ?></div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-arrow-repeat text-warning me-2"></i> Ubah Status</h5></div>
                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/operator/orders/updateStatus" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Status Workflow</label>
                            <select name="status" class="form-select" required>
                                <?php foreach ($statusLabels as $key => $label): ?>
                                    <option value="<?= $key; ?>" <?= $order['status'] === $key ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Catatan Operator</label>
                            <textarea name="operator_notes" class="form-control" rows="2" maxlength="1000"><?= htmlspecialchars((string) ($order['operator_notes'] ?? '')); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning rounded-pill w-100 fw-bold"><i class="bi bi-check2-circle me-1"></i> Perbarui Status</button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="bi bi-credit-card text-success me-2"></i> Pembayaran</h5>
                    <span class="badge <?= $paymentBadge[$paymentStatus] ?? 'bg-secondary'; ?>"><?= htmlspecialchars($paymentLabels[$paymentStatus] ?? $paymentStatus); ?></span>
                </div>
                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/operator/orders/updatePayment" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Status Pembayaran</label>
                            <select name="payment_status" class="form-select">
                                <?php foreach ($paymentLabels as $key => $label): ?>
                                    <option value="<?= $key; ?>" <?= $paymentStatus === $key ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Metode</label>
                            <input type="text" name="payment_method" class="form-control" maxlength="50" value="<?= htmlspecialchars((string) ($order['payment_method'] ?? '')); ?>" placeholder="Tunai / Transfer / QRIS">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nominal (Rp)</label>
                            <input type="number" name="payment_amount" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars((string) ($order['payment_amount'] ?? '')); ?>">
                        </div>
                        <button type="submit" class="btn btn-success rounded-pill w-100 fw-bold"><i class="bi bi-save me-1"></i> Simpan Pembayaran</button>
                    </form>
                    <?php if (!empty($order['paid_at'])): ?>
                        <div class="small text-muted mt-2"><i class="bi bi-check-circle text-success me-1"></i> Dibayar: <?= htmlspecialchars(date('d M Y H:i', strtotime((string) $order['paid_at']))); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-star text-warning me-2"></i> Ulasan Customer</h5></div>
                <div class="card-body p-4">
                    <?php if (!empty($review)): ?>
                        <div class="text-warning fs-5 mb-1"><?= str_repeat('★', (int) $review['rating']); ?><span class="text-secondary"><?= str_repeat('☆', max(0, 5 - (int) $review['rating'])); ?></span></div>
                        <div class="small text-secondary"><?= nl2br(htmlspecialchars((string) ($review['comment'] ?: 'Tanpa komentar.'))); ?></div>
                        <div class="small text-muted mt-2"><?= htmlspecialchars(date('d M Y H:i', strtotime((string) $review['created_at']))); ?></div>
                    <?php else: ?>
                        <div class="small text-muted">Belum ada ulasan. Customer dapat memberi rating setelah pesanan selesai.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
