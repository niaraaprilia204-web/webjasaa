<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-danger border-3">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span class="badge bg-danger px-3 py-2 rounded-pill mb-2"><i class="bi bi-briefcase me-1"></i> Dashboard Monitoring Kepala Sekolah</span>
                <h2 class="fw-bold mb-0">Selamat Datang, <?= htmlspecialchars($data['user']['name']); ?></h2>
            </div>
            <a href="<?= BASEURL; ?>/auth/logout" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
</div>

<div class="container py-3 mb-5">
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger">
                <div class="card-body">
                    <div class="text-muted small">Total Omset & Pendapatan Unit Bisnis</div>
                    <div class="display-6 fw-bold text-dark my-1">Rp 0</div>
                    <div class="small text-secondary"><i class="bi bi-graph-up-arrow text-success me-1"></i> Akumulasi jasa teknis</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small">Total Pekerjaan Diselesaikan</div>
                    <div class="display-6 fw-bold text-primary my-1">0</div>
                    <div class="small text-secondary"><i class="bi bi-check-all me-1"></i> Kepuasan pelanggan</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small">Siswa Teknisi Aktif Terlibat</div>
                    <div class="display-6 fw-bold text-success my-1">1</div>
                    <div class="small text-secondary"><i class="bi bi-people me-1"></i> Pengalaman kerja praktik</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Executive Report Notice -->
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart text-danger me-2"></i> Laporan Eksekutif & Evaluasi Kinerja</h5>
        <p class="text-muted mb-0">Halaman ini difokuskan khusus untuk pemantauan performa unit bisnis TKJ, rekap jam kerja siswa teknisi, statistik kepuasan pelanggan, dan transparansi omset jasa sekolah.</p>
    </div>
</div>
