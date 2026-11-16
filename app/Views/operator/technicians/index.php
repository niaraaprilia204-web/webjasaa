<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-success border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span class="badge bg-success px-3 py-2 rounded-pill mb-2"><i class="bi bi-tools me-1"></i> Data Operasional</span>
                <h2 class="fw-bold mb-0">Kelola Siswa Teknisi TKJ</h2>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASEURL; ?>/operator/dashboard" class="btn btn-outline-light rounded-pill btn-sm">
                    <i class="bi bi-speedometer2 me-1"></i> Dashboard Operator
                </a>
                <button type="button" class="btn btn-success rounded-pill btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addTechnicianModal">
                    <i class="bi bi-person-plus me-1"></i> Tambah Teknisi Siswa
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    
    <!-- Search Control -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form action="<?= BASEURL; ?>/operator/technicians" method="GET" class="row g-3 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari nama teknisi, NISN, kelas, atau keahlian..." value="<?= htmlspecialchars($data['search']); ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold">
                    <i class="bi bi-search me-1"></i> Cari Teknisi
                </button>
                <a href="<?= BASEURL; ?>/operator/technicians" class="btn btn-outline-secondary rounded-pill" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Technician Table Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-person-badge text-success me-2"></i> Daftar Teknisi Siswa Terdaftar</h5>
            <span class="badge bg-secondary rounded-pill">Total: <?= count($data['technicians']); ?> Teknisi</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Nama Teknisi</th>
                            <th>NISN</th>
                            <th>Kelas</th>
                            <th>Ringkasan Keahlian Teknis</th>
                            <th>Beban SPK Aktif</th>
                            <th>Status Ketersediaan</th>
                            <th class="text-end pe-4" style="width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($data['technicians'])): ?>
                            <?php foreach ($data['technicians'] as $tech): ?>
                                <?php
                                $statusBadge = 'bg-success';
                                if ($tech['status'] === 'on_duty') $statusBadge = 'bg-warning text-dark';
                                if ($tech['status'] === 'off') $statusBadge = 'bg-secondary';
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark">
                                        <div class="mb-0"><?= htmlspecialchars($tech['name']); ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($tech['email']); ?></small>
                                    </td>
                                    <td><code><?= htmlspecialchars($tech['nisn'] ?: '-'); ?></code></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($tech['class_grade'] ?: '-'); ?></span></td>
                                    <td class="small text-muted text-truncate" style="max-width: 250px;"><?= htmlspecialchars($tech['skills_summary'] ?: '-'); ?></td>
                                    <td>
                                        <span class="badge bg-info text-dark rounded-pill">
                                            <?= $tech['active_workload']; ?> SPK Aktif
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $statusBadge; ?> text-uppercase small px-3 py-1">
                                            <?= htmlspecialchars($tech['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editTechnicianModal<?= $tech['technician_id']; ?>">
                                            <i class="bi bi-pencil me-1"></i> Edit
                                        </button>
                                    </td>
                                </tr>

                                <!-- Edit Technician Modal -->
                                <div class="modal fade" id="editTechnicianModal<?= $tech['technician_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Edit Profil Teknisi</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="<?= BASEURL; ?>/operator/technicians/update/<?= $tech['technician_id']; ?>" method="POST">
                                                <div class="modal-body p-4">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Nama Teknisi</label>
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($tech['name']); ?>" disabled>
                                                    </div>
                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">NISN</label>
                                                            <input type="text" name="nisn" class="form-control" value="<?= htmlspecialchars($tech['nisn']); ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">Kelas / Tingkat</label>
                                                            <input type="text" name="class_grade" class="form-control" value="<?= htmlspecialchars($tech['class_grade']); ?>" placeholder="Contoh: XII TKJ 1">
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Ringkasan Keahlian Teknis</label>
                                                        <textarea name="skills_summary" class="form-control" rows="3" placeholder="Contoh: CCTV, Mikrotik, LAN Crimping, Hardware PC..."><?= htmlspecialchars($tech['skills_summary']); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Status Ketersediaan <span class="text-danger">*</span></label>
                                                        <select name="status" class="form-select" required>
                                                            <option value="available" <?= ($tech['status'] === 'available') ? 'selected' : ''; ?>>AVAILABLE (Tersedia untuk Tugas)</option>
                                                            <option value="on_duty" <?= ($tech['status'] === 'on_duty') ? 'selected' : ''; ?>>ON_DUTY (Sedang Bertugas)</option>
                                                            <option value="off" <?= ($tech['status'] === 'off') ? 'selected' : ''; ?>>OFF (Izin / Tidak Aktif)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light border-top p-3">
                                                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Data teknisi siswa tidak ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Teknisi Siswa Baru -->
<div class="modal fade" id="addTechnicianModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold text-success"><i class="bi bi-person-plus me-2"></i> Tambah Teknisi Siswa TKJ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASEURL; ?>/operator/technicians/store" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap Siswa <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Rizky Pratama" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Sekolah <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="rizky@school.id" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">No. Telepon / WA</label>
                            <input type="text" name="phone" class="form-control" placeholder="085711223344">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">NISN</label>
                            <input type="text" name="nisn" class="form-control" placeholder="0051234567">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kelas / Tingkat</label>
                            <input type="text" name="class_grade" class="form-control" placeholder="XII TKJ 1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ringkasan Keahlian Teknis</label>
                        <textarea name="skills_summary" class="form-control" rows="3" placeholder="Contoh: CCTV IP Cam, Mikrotik Routerboard, Crimping LAN, Install OS..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Default Password</label>
                        <input type="text" name="password" class="form-control" value="password123">
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">Simpan Teknisi Siswa</button>
                </div>
            </form>
        </div>
    </div>
</div>
