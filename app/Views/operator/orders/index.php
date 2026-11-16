<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-warning border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill mb-2"><i class="bi bi-sliders me-1"></i> Operasional Unit Bisnis</span>
                <h2 class="fw-bold mb-0">Kelola Order & Penugasan Teknisi</h2>
            </div>
            <a href="<?= BASEURL; ?>/operator/dashboard" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard Operator
            </a>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    
    <!-- Search & Filters Control Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form action="<?= BASEURL; ?>/operator/orders" method="GET" class="row g-3 align-items-center">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Cari Kode / Customer / Layanan</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Kode ORD-..., nama..." value="<?= htmlspecialchars($data['filters']['search']); ?>">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Filter Status</label>
                <select name="status" class="form-select">
                    <option value="">-- Semua Status --</option>
                    <?php 
                    $statuses = ['submitted', 'verified', 'scheduled', 'assigned', 'in_progress', 'waiting_confirmation', 'completed', 'cancelled'];
                    foreach ($statuses as $st): 
                    ?>
                        <option value="<?= $st; ?>" <?= ($data['filters']['status'] === $st) ? 'selected' : ''; ?>><?= strtoupper($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Mulai Tanggal</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($data['filters']['start_date']); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($data['filters']['end_date']); ?>">
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2 mt-auto">
                <button type="submit" class="btn btn-warning w-100 rounded-pill fw-bold text-dark">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="<?= BASEURL; ?>/operator/orders" class="btn btn-outline-secondary rounded-pill" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Orders Management Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-inbox text-warning me-2"></i> Daftar Permintaan Jasa Customer</h5>
            <span class="badge bg-secondary rounded-pill">Total: <?= count($data['orders']); ?> Orders</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Kode Order</th>
                            <th>Customer & Kontak</th>
                            <th>Layanan Dipesan</th>
                            <th>Deskripsi Masalah</th>
                            <th>Status Workflow Saat Ini</th>
                            <th>Jadwal Pengerjaan</th>
                            <th class="text-end pe-4" style="width: 260px;">Aksi Operasional</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data['orders'])): ?>
                            <?php foreach ($data['orders'] as $ord): ?>
                                <?php
                                $statusBadge = 'bg-secondary';
                                if ($ord['status'] === 'submitted') $statusBadge = 'bg-info text-dark';
                                if ($ord['status'] === 'verified') $statusBadge = 'bg-primary';
                                if ($ord['status'] === 'scheduled') $statusBadge = 'bg-warning text-dark';
                                if ($ord['status'] === 'assigned') $statusBadge = 'bg-dark';
                                if ($ord['status'] === 'in_progress') $statusBadge = 'bg-primary bg-gradient';
                                if ($ord['status'] === 'waiting_confirmation') $statusBadge = 'bg-warning text-dark';
                                if ($ord['status'] === 'completed') $statusBadge = 'bg-success';
                                if ($ord['status'] === 'cancelled') $statusBadge = 'bg-danger';
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><code><?= htmlspecialchars($ord['order_code']); ?></code></td>
                                    <td>
                                        <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($ord['customer_name']); ?></div>
                                        <div class="small text-muted"><i class="bi bi-telephone text-success me-1"></i> <?= htmlspecialchars($ord['customer_phone']); ?></div>
                                    </td>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($ord['service_name']); ?></td>
                                    <td class="small text-muted text-truncate" style="max-width: 220px;"><?= htmlspecialchars($ord['problem_description']); ?></td>
                                    <td>
                                        <span class="badge <?= $statusBadge; ?> text-uppercase small px-3 py-1">
                                            <?= htmlspecialchars($ord['status']); ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= date('d M Y H:i', strtotime($ord['scheduled_date'])); ?></td>
                                    <td class="text-end pe-4">
                                        <a href="<?= BASEURL; ?>/operator/orders/detail/<?= htmlspecialchars((string) $ord['order_code']); ?>" class="btn btn-sm btn-dark rounded-pill me-1" title="Lihat & edit detail pesanan">
                                            <i class="bi bi-eye"></i> Detail
                                        </a>
                                        <!-- AI Checklist Button -->
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill me-1 ai-btn-checklist"
                                                data-service="<?= htmlspecialchars($ord['service_name']); ?>" 
                                                data-problem="<?= htmlspecialchars($ord['problem_description']); ?>"
                                                title="Analisis & Checklist AI">
                                            <i class="bi bi-robot"></i> AI
                                        </button>
                                        <!-- Assignment Button -->
                                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill me-1 fw-bold"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignTechModal<?= $ord['id']; ?>"
                                                title="Tugaskan Siswa Teknisi">
                                            <i class="bi bi-person-plus me-1"></i> SPK
                                        </button>
                                        <!-- Update Status Button -->
                                        <button type="button" class="btn btn-sm btn-warning rounded-pill text-dark fw-bold"
                                                data-bs-toggle="modal"
                                                data-bs-target="#updateStatusModal<?= $ord['id']; ?>"
                                                title="Ubah Status Workflow">
                                            <i class="bi bi-arrow-repeat me-1"></i> Status
                                        </button>
                                    </td>
                                </tr>


                                <!-- 1. Technician Assignment Modal -->
                                <div class="modal fade" id="assignTechModal<?= $ord['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i> Penugasan Siswa Teknisi (SPK) - Order #<?= htmlspecialchars($ord['order_code']); ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="<?= BASEURL; ?>/operator/orders/assignTechnician" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($data['csrfToken'] ?? ''); ?>">
                                                <input type="hidden" name="order_id" value="<?= $ord['id']; ?>">
                                                <div class="modal-body p-4">
                                                    <div class="p-3 bg-light rounded-3 mb-3 border">
                                                        <div class="small text-muted mb-1">Pekerjaan: <strong><?= htmlspecialchars($ord['service_name']); ?></strong></div>
                                                        <div class="small text-dark">Deskripsi Masalah: <?= htmlspecialchars($ord['problem_description']); ?></div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Pilih Siswa Teknisi TKJ Berdasarkan Keahlian & Ketersediaan <span class="text-danger">*</span></label>
                                                        <select name="technician_id" class="form-select form-select-lg" required>
                                                            <option value="">-- Pilih Siswa Teknisi --</option>
                                                            <?php foreach ($data['technicians'] as $tech): ?>
                                                                <option value="<?= $tech['technician_id']; ?>">
                                                                    <?= htmlspecialchars($tech['name']); ?> (Kelas: <?= htmlspecialchars($tech['class_grade'] ?: '-'); ?>) - Status: [<?= strtoupper($tech['status']); ?>] - Keahlian: <?= htmlspecialchars($tech['skills_summary'] ?: 'Umum'); ?> (<?= $tech['active_workload']; ?> SPK Aktif)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <div class="form-text mt-1">Daftar menampilkan status ketersediaan (AVAILABLE / ON_DUTY / OFF) dan beban pekerjaan aktif.</div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Catatan Instuksi / Diagnosa Awal untuk Teknisi</label>
                                                        <textarea name="notes" class="form-control" rows="3" placeholder="Instruksi khusus penarikan kabel, pemeriksaan komponen, atau alat yang harus dibawa..."></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light border-top p-3">
                                                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">Terbitkan SPK & Assign Teknisi</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. Status Update Modal -->
                                <div class="modal fade" id="updateStatusModal<?= $ord['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header bg-dark text-white">
                                                <h5 class="modal-title fw-bold text-warning"><i class="bi bi-sliders me-2"></i> Update Status Pesanan #<?= htmlspecialchars($ord['order_code']); ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="<?= BASEURL; ?>/operator/orders/updateStatus" method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($data['csrfToken'] ?? ''); ?>">
                                                <input type="hidden" name="order_id" value="<?= $ord['id']; ?>">
                                                <div class="modal-body p-4">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Pilih Status Baru Workflow <span class="text-danger">*</span></label>
                                                        <select name="status" class="form-select form-select-lg" required>
                                                            <option value="submitted" <?= ($ord['status'] === 'submitted') ? 'selected' : ''; ?>>1. SUBMITTED (Diajukan Customer)</option>
                                                            <option value="verified" <?= ($ord['status'] === 'verified') ? 'selected' : ''; ?>>2. VERIFIED (Diverifikasi Operator)</option>
                                                            <option value="scheduled" <?= ($ord['status'] === 'scheduled') ? 'selected' : ''; ?>>3. SCHEDULED (Dijadwalkan Pengerjaan)</option>
                                                            <option value="assigned" <?= ($ord['status'] === 'assigned') ? 'selected' : ''; ?>>4. ASSIGNED (Ditugaskan ke Siswa Teknisi)</option>
                                                            <option value="in_progress" <?= ($ord['status'] === 'in_progress') ? 'selected' : ''; ?>>5. IN_PROGRESS (Pengerjaan Lapangan)</option>
                                                            <option value="waiting_confirmation" <?= ($ord['status'] === 'waiting_confirmation') ? 'selected' : ''; ?>>6. WAITING_CONFIRMATION (Menunggu Konfirmasi Customer)</option>
                                                            <option value="completed" <?= ($ord['status'] === 'completed') ? 'selected' : ''; ?>>7. COMPLETED (Selesai Tuntas)</option>
                                                            <option value="cancelled" <?= ($ord['status'] === 'cancelled') ? 'selected' : ''; ?>>CANCELLED (Dibatalkan)</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Catatan Operator untuk Customer (Opsional)</label>
                                                        <textarea name="operator_notes" class="form-control" rows="3" placeholder="Tambahkan catatan khusus..."><?= htmlspecialchars($ord['operator_notes'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light border-top p-3">
                                                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold">Perbarui Status</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada pesanan masuk yang sesuai dengan kriteria filter.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
