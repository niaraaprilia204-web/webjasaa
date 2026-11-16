<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-primary border-3">
    <div class="container">
        <span class="badge bg-primary px-3 py-2 rounded-pill mb-2"><i class="bi bi-calendar-plus me-1"></i> Permintaan Jasa Baru</span>
        <h2 class="fw-bold mb-0">Formulir Pemesanan Layanan IT</h2>
    </div>
</div>

<div class="container py-3 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom border-light">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-card-checklist text-primary me-2"></i> Isi Detail Permintaan Jasa</h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/customer/orders/store" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($data['csrfToken'] ?? ''); ?>">
                        
                        <!-- Choice of Service -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Pilih Layanan Teknis <span class="text-danger">*</span></label>
                            <select name="service_id" class="form-select form-select-lg" required>
                                <option value="">-- Pilih Layanan IT --</option>
                                <?php foreach ($data['services'] as $svc): ?>
                                    <option value="<?= $svc['id']; ?>" <?= (isset($data['selectedService']) && $data['selectedService']['id'] == $svc['id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($svc['title']); ?> (<?= htmlspecialchars($svc['category_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Problem Description -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <label class="form-label fw-bold mb-0">Deskripsi Masalah / Kebutuhan <span class="text-danger">*</span></label>
                                <button type="button" id="ai-generate-desc-btn" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-sm font-semibold">
                                    <i class="bi bi-magic me-1 text-warning"></i> Bantu Tulis Deskripsi dengan AI
                                </button>
                            </div>
                            <textarea name="problem_description" class="form-control" rows="4" placeholder="Jelaskan kendala perangkat atau detail pekerjaan yang Anda butuhkan (minimal 10 karakter)..." required></textarea>
                            <div class="form-text">Contoh: Komputer mati total setelah mati lampu, atau butuh instalasi 4 titik CCTV di ruang kelas. Tekan tombol AI untuk perumusan otomatis.</div>
                        </div>


                        <!-- Device Info -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Informasi Perangkat / Perangkat Tambahan (Opsional)</label>
                            <input type="text" name="device_info" class="form-control" placeholder="Contoh: Laptop Asus X441S / DVR Hikvision 8 Channel">
                        </div>

                        <!-- Location & Contact -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Alamat / Lokasi Pekerjaan <span class="text-danger">*</span></label>
                                <input type="text" name="location_address" class="form-control" placeholder="Alamat lengkap lokasi pengerjaan..." required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">No. Telepon / WhatsApp <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($data['user']['phone'] ?? ''); ?>" placeholder="081234567890" required>
                            </div>
                        </div>

                        <!-- Scheduled Date -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-7">
                                <label class="form-label fw-bold">Tanggal & Jam Pengerjaan yang Diinginkan <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="scheduled_date" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime('+1 day 09:00')); ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold">Foto Kerusakan (Opsional)</label>
                                <input type="file" name="damage_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">JPG/PNG/WEBP, maksimal 5 MB.</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <a href="<?= BASEURL; ?>/services" class="btn btn-outline-secondary rounded-pill px-4">
                                <i class="bi bi-arrow-left me-1"></i> Batal
                            </a>
                            <button type="submit" class="btn btn-primary rounded-pill px-5 py-2.5 fw-bold shadow">
                                <i class="bi bi-send me-1"></i> Kirim Permintaan Jasa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
