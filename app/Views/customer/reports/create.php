<?php
$categories = $data['categories'] ?? ['umum'];
?>
<div class="bg-primary text-white py-4 mb-4 shadow-sm border-bottom border-info border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-info text-dark px-3 py-2 rounded-pill mb-2"><i class="bi bi-chat-square-text me-1"></i> Laporan</span>
                <h2 class="fw-bold mb-0">Kirim Laporan</h2>
                <div class="small text-white-50 mt-1">Sampaikan keluhan atau masukan Anda kepada Admin TKJ Service Hub.</div>
            </div>
            <a href="<?= BASEURL; ?>/customer/reports" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-list-ul me-1"></i> Laporan Saya
            </a>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/customer/reports/store" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($data['csrfToken'] ?? ''); ?>">

                        <div class="mb-3">
                            <label for="subject" class="form-label fw-semibold">Judul Laporan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" maxlength="150" required
                                   placeholder="Contoh: Instalasi CCTV tidak berfungsi">
                            <div class="form-text">Minimal 5 karakter, maksimal 150 karakter.</div>
                        </div>

                        <div class="mb-3">
                            <label for="category" class="form-label fw-semibold">Kategori</label>
                            <select class="form-select" id="category" name="category">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars((string) $category); ?>"><?= htmlspecialchars(ucfirst((string) $category)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold">Isi Laporan <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="6" required
                                      placeholder="Jelaskan kronologi, lokasi, dan harapan Anda."></textarea>
                            <div class="form-text">Minimal 10 karakter. Jangan sertakan password atau kode OTP.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="bi bi-send me-1"></i> Kirim Laporan
                            </button>
                            <a href="<?= BASEURL; ?>/customer/reports" class="btn btn-outline-secondary rounded-pill">Batal</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="alert alert-info rounded-4 shadow-sm mt-4 small mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Setelah laporan berhasil disimpan, Admin menerima notifikasi WhatsApp dan Anda menerima konfirmasi
                di nomor WhatsApp yang terdaftar pada akun Anda.
            </div>
        </div>
    </div>
</div>
