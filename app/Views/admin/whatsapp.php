<?php
/**
 * Status integrasi WhatsApp (Fonnte) + riwayat notifikasi.
 * Token Fonnte TIDAK PERNAH ditampilkan di halaman ini.
 */
$config = $data['config'] ?? [];
$summary = $data['summary'] ?? [];
$logs = $data['logs'] ?? [];
$maskedNumber = (string) ($config['masked_number'] ?? '-');
$isConfigured = !empty($config['configured']);
$csrfToken = (string) ($data['csrfToken'] ?? '');

$activityLabels = [
    'registration' => 'Pendaftaran',
    'order'        => 'Pesanan',
    'report'       => 'Laporan',
    'status_update' => 'Perubahan Status',
];

$eventLabels = [
    'customer_registered'      => 'Customer Baru Terdaftar',
    'order_created'            => 'Pesanan Dibuat',
    'report_created'           => 'Laporan Dibuat',
    'order_status_updated'     => 'Status Pesanan Diubah',
    'report_status_updated'    => 'Status Laporan Diubah',
    'order_failed'             => 'Pesanan Gagal Dibuat',
    'report_failed'            => 'Laporan Gagal Dikirim',
];
?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-success border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-success px-3 py-2 rounded-pill mb-2"><i class="bi bi-whatsapp me-1"></i> Integrasi WhatsApp</span>
                <h2 class="fw-bold mb-0">Notifikasi WhatsApp</h2>
                <div class="small text-white-50 mt-1">Peringatan otomatis ke nomor Admin dan konfirmasi ke customer.</div>
            </div>
            <a href="<?= BASEURL; ?>/admin/dashboard" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard Admin
            </a>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">

    <?php if (!$isConfigured): ?>
        <div class="alert alert-warning rounded-4 shadow-sm d-flex align-items-start gap-3">
            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
            <div>
                <div class="fw-bold">Integrasi WhatsApp belum dikonfigurasi.</div>
                <div class="small">
                    Isi <code>FONNTE_TOKEN</code> dan <code>ADMIN_WHATSAPP_NUMBER</code> pada file <code>.env</code> di root project,
                    lalu muat ulang halaman ini. Pendaftaran, laporan, dan pemesanan tetap berjalan normal
                    meskipun notifikasi belum aktif.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small">Status Koneksi Fonnte</div>
                    <div class="h5 fw-bold text-success mb-0">
                        <?= $isConfigured ? 'Terhubung' : 'Belum siap'; ?>
                    </div>
                    <div class="small text-muted mt-1">
                        Token: <span class="badge bg-secondary"><?= $isConfigured ? 'Tersedia' : 'Kosong'; ?></span>
                        (disembunyikan)
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small">Nomor WhatsApp Admin</div>
                    <div class="h5 fw-bold text-primary mb-0"><code><?= htmlspecialchars($maskedNumber); ?></code></div>
                    <div class="small text-muted mt-1">Hanya dari konfigurasi <code>.env</code></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-info">
                <div class="card-body">
                    <div class="text-muted small">Berhasil Dikirim</div>
                    <div class="display-6 fw-bold text-info"><?= (int) ($summary['sent'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-danger">
                <div class="card-body">
                    <div class="text-muted small">Gagal Dikirim</div>
                    <div class="display-6 fw-bold text-danger"><?= (int) ($summary['failed'] ?? 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($summary['by_activity'])): ?>
        <div class="row g-3 mb-4">
            <?php foreach ($summary['by_activity'] as $group): ?>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body py-3">
                            <div class="text-muted small"><?= htmlspecialchars($activityLabels[$group['label'] ?? ''] ?? ucfirst((string) ($group['label'] ?? '-'))); ?></div>
                            <div class="fw-bold"><?= (int) ($group['total'] ?? 0); ?> notifikasi</div>
                            <div class="small text-success"><?= (int) ($group['sent'] ?? 0); ?> terkirim</div>
                            <div class="small text-danger"><?= (int) ($group['failed'] ?? 0); ?> gagal</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-3 pb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-clock-history me-2 text-secondary"></i>Riwayat Notifikasi</h5>
                <div class="small text-muted">
                    Total <?= (int) ($summary['total'] ?? 0); ?> percobaan pengiriman
                    <?php if (!empty($summary['last_sent_at'])): ?>
                        · terakhir berhasil: <?= htmlspecialchars((string) $summary['last_sent_at']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <span class="badge bg-light text-dark border">Status proses bisnis &amp; status pengiriman ditampilkan terpisah</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Belum ada notifikasi yang dikirim.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Waktu</th>
                                <th>Jenis Aktivitas</th>
                                <th>Penerima</th>
                                <th>Referensi</th>
                                <th>Proses Bisnis</th>
                                <th>Pengiriman</th>
                                <th>Dikirim</th>
                                <th>Keterangan</th>
                                <th class="pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $status = (string) ($log['status'] ?? 'pending');
                            $business = (string) ($log['business_status'] ?? 'success');
                            $badgeClass = $status === 'sent' ? 'bg-success' : ($status === 'failed' ? 'bg-danger' : 'bg-warning text-dark');
                            $businessBadge = $business === 'success' ? 'bg-success' : 'bg-danger';
                            $activity = $activityLabels[$log['activity_type'] ?? ''] ?? ucfirst((string) ($log['activity_type'] ?? '-'));
                            $eventLabel = $eventLabels[$log['event'] ?? ''] ?? (string) ($log['event'] ?? '-');
                            ?>
                            <tr>
                                <td class="ps-3 text-nowrap small"><?= htmlspecialchars((string) ($log['created_at'] ?? '')); ?></td>
                                <td>
                                    <div class="small fw-semibold"><?= htmlspecialchars($activity); ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($eventLabel); ?></div>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= htmlspecialchars((string) ($log['recipient_name'] ?? '-')); ?></div>
                                    <div class="small text-muted"><code><?= htmlspecialchars((string) ($log['recipient'] ?? '-')); ?></code></div>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars((string) ($log['reference_id'] ?? '')); ?></td>
                                <td>
                                    <span class="badge <?= $businessBadge; ?>"><?= $business === 'success' ? 'Berhasil' : 'Gagal'; ?></span>
                                </td>
                                <td><span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars(ucfirst($status)); ?></span></td>
                                <td class="small text-muted text-nowrap"><?= htmlspecialchars((string) ($log['sent_at'] ?? '-')); ?></td>
                                <td class="small text-muted">
                                    <?php if (!empty($log['error_message'])): ?>
                                        <span class="text-danger"><?= htmlspecialchars((string) $log['error_message']); ?></span>
                                    <?php else: ?>
                                        <?= htmlspecialchars((string) ($log['message_preview'] ?? '-')); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3">
                                    <?php if ($status === 'failed'): ?>
                                        <form action="<?= BASEURL; ?>/admin/whatsapp/resend" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                                            <input type="hidden" name="notification_id" value="<?= (int) ($log['id'] ?? 0); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill" title="Kirim ulang notifikasi yang gagal">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                    <?php elseif ($status === 'sent'): ?>
                                        <span class="small text-success"><i class="bi bi-check-circle"></i> Terkirim</span>
                                    <?php else: ?>
                                        <span class="small text-muted">Menunggu</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body small text-muted">
            <div class="fw-bold text-dark mb-2">Informasi Keamanan</div>
            <ul class="mb-0 ps-3">
                <li>Token Fonnte hanya dibaca di server dari <code>.env</code> dan tidak pernah ditampilkan di halaman ini, log, atau JavaScript.</li>
                <li>Nomor tujuan Admin hanya bisa diubah melalui variabel <code>ADMIN_WHATSAPP_NUMBER</code> di <code>.env</code>.</li>
                <li>Nomor customer diambil dari kolom <code>users.phone</code> milik akun tersebut, bukan dari input frontend.</li>
                <li>Notifikasi hanya dikirim setelah data berhasil disimpan di database.</li>
                <li>Satu event tidak pernah menghasilkan notifikasi ganda (kunci unik <code>idempotency_key</code>).</li>
                <li>Kegagalan Fonnte tidak membatalkan pendaftaran, laporan, atau pesanan yang sudah berhasil.</li>
                <li>Kirim ulang hanya tersedia untuk notifikasi berstatus gagal, sehingga pesan yang sudah terkirim tidak diduplikasi.</li>
            </ul>
        </div>
    </div>
</div>
