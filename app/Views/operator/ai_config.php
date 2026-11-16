<?php
$settings = $data['settings'] ?? [];
$models = $data['models'] ?? [];
$apiKeyConfigured = !empty($data['apiKeyConfigured']);
$csrfToken = $data['csrfToken'] ?? '';
?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-info border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-info text-dark px-3 py-2 rounded-pill mb-2"><i class="bi bi-robot me-1"></i> Pengaturan Chatbot</span>
                <h2 class="fw-bold mb-0">Konfigurasi AI</h2>
                <div class="small text-white-50 mt-1">Atur model, temperature, dan instruksi tambahan tanpa menyimpan API key di database.</div>
            </div>
            <a href="<?= BASEURL; ?>/operator/chat-history" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-chat-square-text me-1"></i> Riwayat Chat
            </a>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 p-4 pb-0">
                    <h5 class="fw-bold mb-0"><i class="bi bi-sliders text-info me-2"></i> Parameter AI</h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?= BASEURL; ?>/operator/ai-config/update" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">

                        <div class="p-3 rounded-3 bg-light mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="api_enabled" id="apiEnabled" value="1" <?= !empty($settings['api_enabled']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="apiEnabled">Gunakan Gemini API untuk jawaban AI</label>
                            </div>
                            <div class="small text-muted mt-1">Jika dimatikan, chatbot tetap berjalan memakai basis pengetahuan lokal TKJ Service Hub.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Model Gemini</label>
                            <input type="text" name="model" class="form-control" list="modelOptions" required value="<?= htmlspecialchars((string) ($settings['model'] ?? '')); ?>" placeholder="gemini-1.5-flash">
                            <datalist id="modelOptions">
                                <?php foreach ($models as $model): ?>
                                    <option value="<?= htmlspecialchars((string) $model); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">Gunakan nama model yang tersedia pada akun Google AI Studio Anda.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Temperature (0.0 - 1.0)</label>
                            <input type="number" name="temperature" class="form-control" min="0" max="1" step="0.1" required value="<?= htmlspecialchars((string) ($settings['temperature'] ?? '0.4')); ?>">
                            <div class="form-text">Nilai rendah lebih konsisten; nilai tinggi lebih kreatif. Rekomendasi: 0.3 - 0.5.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Instruksi Tambahan untuk Operator</label>
                            <textarea name="extra_instruction" class="form-control" rows="5" maxlength="1000" placeholder="Contoh: Selalu arahkan customer menghubungi WhatsApp operator untuk jadwal survei."><?= htmlspecialchars((string) ($settings['extra_instruction'] ?? '')); ?></textarea>
                            <div class="form-text">Maksimal 1000 karakter. Instruksi ini ditambahkan ke system prompt chatbot.</div>
                        </div>

                        <button type="submit" class="btn btn-info text-dark rounded-pill px-4 fw-bold">
                            <i class="bi bi-save me-1"></i> Simpan Konfigurasi
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-shield-check text-success me-2"></i> Status Integrasi</h5>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small text-muted">API Key Gemini</span>
                        <?php if ($apiKeyConfigured): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Terkonfigurasi</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> Belum diatur</span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small text-muted">Penyedia</span>
                        <span class="badge bg-light text-dark border">Google Gemini</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small text-muted">Mode API</span>
                        <?php if (!empty($settings['api_enabled'])): ?>
                            <span class="badge bg-info text-dark">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Nonaktif</span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Model Aktif</span>
                        <code class="small"><?= htmlspecialchars((string) ($settings['model'] ?? '-')); ?></code>
                    </div>
                    <hr>
                    <div class="small text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        API key hanya dibaca dari <code>.env</code> di server. Halaman ini tidak pernah menampilkan atau menyimpan token.
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb text-warning me-2"></i> Catatan Keamanan</h6>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>Jangan menaruh API key di form atau database.</li>
                        <li>Uji perubahan model dengan satu percakapan sebelum dipakai luas.</li>
                        <li>Riwayat chatbot dapat berisi data pribadi; akses hanya untuk Admin/Operator.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
