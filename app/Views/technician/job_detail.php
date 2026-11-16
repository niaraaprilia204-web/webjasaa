<?php
$workOrder = $data['workOrder'];
$files = $data['files'] ?? [];
$csrfToken = $data['csrfToken'] ?? '';
$statusLabels = ['assigned' => 'Ditugaskan', 'accepted' => 'Diterima', 'in_progress' => 'Sedang Dikerjakan', 'done' => 'Selesai'];
$orderLabels = [
    'submitted' => 'Submitted', 'verified' => 'Verified', 'scheduled' => 'Scheduled', 'assigned' => 'Assigned',
    'in_progress' => 'In Progress', 'waiting_confirmation' => 'Waiting Confirmation', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
];
?>

<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-success border-3">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1 small">
                        <li class="breadcrumb-item"><a href="<?= BASEURL; ?>/technician/dashboard" class="text-secondary text-decoration-none">Dashboard Teknisi</a></li>
                        <li class="breadcrumb-item active text-white"><?= htmlspecialchars((string) $workOrder['order_code']); ?></li>
                    </ol>
                </nav>
                <h2 class="fw-bold mb-0">Pekerjaan <?= htmlspecialchars((string) $workOrder['order_code']); ?></h2>
                <div class="small text-white-50 mt-1"><?= htmlspecialchars((string) $workOrder['service_name']); ?></div>
            </div>
            <div class="d-flex gap-2">
                <span class="badge bg-success px-3 py-2 align-self-center text-uppercase"><?= htmlspecialchars($statusLabels[$workOrder['status']] ?? (string) $workOrder['status']); ?></span>
                <a href="<?= BASEURL; ?>/technician/dashboard" class="btn btn-outline-light rounded-pill btn-sm"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
            </div>
        </div>
    </div>
</div>

<div class="container py-3 mb-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-clipboard-data text-success me-2"></i> Detail Keluhan & Permintaan</h5></div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <span class="text-muted small d-block">Layanan</span>
                        <div class="fs-5 fw-bold text-success"><?= htmlspecialchars((string) $workOrder['service_name']); ?></div>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted small d-block">Deskripsi Masalah</span>
                        <div class="p-3 bg-light rounded-3"><?= nl2br(htmlspecialchars((string) $workOrder['problem_description'])); ?></div>
                    </div>
                    <?php if (!empty($workOrder['device_info'])): ?>
                        <div class="mb-3"><span class="text-muted small d-block">Perangkat</span><strong><?= htmlspecialchars((string) $workOrder['device_info']); ?></strong></div>
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Lokasi Pengerjaan</span>
                            <div class="fw-semibold"><i class="bi bi-geo-alt text-danger me-1"></i> <?= htmlspecialchars((string) $workOrder['location_address']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small d-block">Jadwal</span>
                            <div class="fw-semibold"><i class="bi bi-calendar-event text-primary me-1"></i> <?= !empty($workOrder['scheduled_date']) ? htmlspecialchars(date('d M Y H:i', strtotime((string) $workOrder['scheduled_date']))) : '-'; ?></div>
                        </div>
                    </div>
                    <?php if (!empty($workOrder['operator_notes'])): ?>
                        <div class="p-3 rounded-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 mt-3">
                            <strong class="small"><i class="bi bi-chat-left-text me-1"></i> Instruksi Operator:</strong>
                            <div class="small mt-1"><?= nl2br(htmlspecialchars((string) $workOrder['operator_notes'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-journal-text text-primary me-2"></i> Catatan Hasil Pemeriksaan & Pengerjaan</h5></div>
                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/technician/jobs/update" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="work_order_id" value="<?= (int) $workOrder['id']; ?>">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Status Pekerjaan <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="accepted" <?= $workOrder['status'] === 'accepted' ? 'selected' : ''; ?>>Diterima / Siap Dikerjakan</option>
                                <option value="in_progress" <?= $workOrder['status'] === 'in_progress' ? 'selected' : ''; ?>>Sedang Dikerjakan</option>
                                <option value="done" <?= $workOrder['status'] === 'done' ? 'selected' : ''; ?>>Selesai</option>
                            </select>
                            <div class="form-text">Memilih "Selesai" akan menandai pesanan selesai dan membuka form ulasan customer.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Diagnosa / Hasil Pemeriksaan</label>
                            <textarea name="diagnosis_notes" class="form-control" rows="3" maxlength="2000" placeholder="Contoh: Kabel LAN putus pada konektor, IP address konflik."><?= htmlspecialchars((string) ($workOrder['diagnosis_notes'] ?? '')); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Tindakan / Pengerjaan</label>
                            <textarea name="action_taken" class="form-control" rows="3" maxlength="2000" placeholder="Contoh: Mengganti konektor RJ45, konfigurasi ulang router."><?= htmlspecialchars((string) ($workOrder['action_taken'] ?? '')); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Sparepart / Bahan yang Digunakan</label>
                            <textarea name="parts_used" class="form-control" rows="2" maxlength="1000" placeholder="Contoh: Konektor RJ45 2 pcs, kabel UTP 3 meter."><?= htmlspecialchars((string) ($workOrder['parts_used'] ?? '')); ?></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold"><i class="bi bi-save me-1"></i> Simpan Pekerjaan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-camera text-primary me-2"></i> Dokumentasi Sebelum & Sesudah</h5></div>
                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/technician/jobs/upload" method="POST" enctype="multipart/form-data" class="row g-3 align-items-end mb-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="work_order_id" value="<?= (int) $workOrder['id']; ?>">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Jenis Foto</label>
                            <select name="file_type" class="form-select" required>
                                <option value="before">Sebelum</option>
                                <option value="after">Sesudah</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">File Foto</label>
                            <input type="file" name="documentation" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Keterangan</label>
                            <input type="text" name="caption" class="form-control" maxlength="255" placeholder="Opsional">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary rounded-pill w-100 fw-bold"><i class="bi bi-upload me-1"></i> Unggah</button>
                        </div>
                    </form>

                    <?php if (!empty($files)): ?>
                        <div class="row g-3">
                            <?php foreach ($files as $file): ?>
                                <?php
                                $fileUrl = BASEURL . '/' . ltrim((string) $file['file_path'], '/');
                                $typeLabel = ['damage' => 'Kerusakan', 'before' => 'Sebelum', 'after' => 'Sesudah'][$file['file_type']] ?? ucfirst((string) $file['file_type']);
                                ?>
                                <div class="col-sm-6 col-lg-4">
                                    <a href="<?= htmlspecialchars($fileUrl); ?>" target="_blank" class="text-decoration-none">
                                        <img src="<?= htmlspecialchars($fileUrl); ?>" alt="<?= htmlspecialchars($typeLabel); ?>" class="img-fluid rounded-3 border" style="height: 150px; width: 100%; object-fit: cover;">
                                        <div class="small text-muted mt-1"><span class="badge bg-light text-dark border"><?= htmlspecialchars($typeLabel); ?></span> <?= htmlspecialchars((string) ($file['caption'] ?: '')); ?></div>
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
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-person-vcard text-primary me-2"></i> Informasi Customer</h5></div>
                <div class="card-body p-4 small">
                    <div class="mb-2"><span class="text-muted d-block">Nama</span><strong><?= htmlspecialchars((string) $workOrder['customer_name']); ?></strong></div>
                    <div class="mb-2"><span class="text-muted d-block">Telepon / WhatsApp</span><a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', (string) ($workOrder['customer_phone'] ?? '')); ?>" target="_blank" class="text-decoration-none"><?= htmlspecialchars((string) ($workOrder['customer_phone'] ?? '-')); ?></a></div>
                    <div class="mb-2"><span class="text-muted d-block">Email</span><?= htmlspecialchars((string) ($workOrder['customer_email'] ?? '-')); ?></div>
                    <div class="mb-2"><span class="text-muted d-block">Alamat</span><?= htmlspecialchars((string) ($workOrder['customer_address'] ?: '-')); ?></div>
                    <div><span class="text-muted d-block">Institusi</span><?= htmlspecialchars((string) ($workOrder['institution'] ?: '-')); ?></div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 p-4 pb-0"><h5 class="fw-bold mb-0"><i class="bi bi-info-circle text-success me-2"></i> Ringkasan Tugas</h5></div>
                <div class="card-body p-4 small">
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Status pesanan</span><span class="badge bg-primary text-uppercase"><?= htmlspecialchars($orderLabels[$workOrder['order_status']] ?? (string) $workOrder['order_status']); ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Pembayaran</span><span class="badge bg-light text-dark border text-uppercase"><?= htmlspecialchars((string) ($workOrder['payment_status'] ?? 'unpaid')); ?></span></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Ditugaskan</span><span><?= !empty($workOrder['assigned_at']) ? htmlspecialchars(date('d M Y H:i', strtotime((string) $workOrder['assigned_at']))) : '-'; ?></span></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Selesai</span><span><?= !empty($workOrder['finished_at']) ? htmlspecialchars(date('d M Y H:i', strtotime((string) $workOrder['finished_at']))) : '-'; ?></span></div>
                </div>
            </div>
        </div>
    </div>
</div>
