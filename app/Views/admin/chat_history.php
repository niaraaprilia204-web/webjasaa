<?php
$summary = $data['summary'] ?? [];
$search = $data['search'] ?? '';
$pagination = $data['pagination'] ?? ['page' => 1, 'pages' => 1, 'total' => 0];
?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-info border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-info text-dark px-3 py-2 rounded-pill mb-2"><i class="bi bi-chat-square-text me-1"></i> Audit Chatbot</span>
                <h2 class="fw-bold mb-0">Riwayat Chatbot</h2>
                <div class="small text-white-50 mt-1">Percakapan customer dengan TKJ AI Assistant tersimpan di server.</div>
            </div>
            <a href="<?= BASEURL; ?>/admin/dashboard" class="btn btn-outline-light rounded-pill btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard Admin
            </a>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-info">
                <div class="card-body">
                    <div class="text-muted small">Total Percakapan</div>
                    <div class="display-6 fw-bold text-info"><?= (int) ($summary['total'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-muted small">Hari Ini</div>
                    <div class="display-6 fw-bold text-primary"><?= (int) ($summary['today'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-muted small">Pengguna Unik</div>
                    <div class="display-6 fw-bold text-success"><?= (int) ($summary['uniqueUsers'] ?? 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white">
        <form action="<?= BASEURL; ?>/admin/chat-history" method="GET" class="row g-3 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="<?= htmlspecialchars((string) $search); ?>" class="form-control border-start-0" placeholder="Cari pertanyaan, jawaban, nama, atau email...">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-info text-dark rounded-pill w-100 fw-semibold"><i class="bi bi-funnel me-1"></i> Cari</button>
                <a href="<?= BASEURL; ?>/admin/chat-history" class="btn btn-outline-secondary rounded-pill" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-0 p-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Daftar Percakapan</h5>
            <span class="badge bg-secondary"><?= (int) ($pagination['total'] ?? 0); ?> data</span>
        </div>
        <div class="card-body p-0 mt-3">
            <?php require __DIR__ . '/../components/chat_logs_table.php'; ?>
        </div>
        <?php if ((int) ($pagination['pages'] ?? 1) > 1): ?>
            <div class="card-footer bg-white border-0 p-4">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for ($i = 1; $i <= (int) $pagination['pages']; $i++): ?>
                            <li class="page-item <?= $i === (int) $pagination['page'] ? 'active' : ''; ?>">
                                <a class="page-link" href="<?= BASEURL; ?>/admin/chat-history?<?= htmlspecialchars(http_build_query(['search' => $search, 'page' => $i])); ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>
