<?php
$node = $data['node'] ?? [];
$openrouter = $data['openrouter'] ?? null;
$modelConfig = $data['modelConfig'] ?? [];
$chatSummary = $data['chatSummary'] ?? [];
$databaseStatus = $data['databaseStatus'] ?? [];
$knowledgeTables = $data['knowledgeTables'] ?? [];
$knowledgeRelations = $data['knowledgeRelations'] ?? [];
$totalTables = $data['totalTables'] ?? 0;
$systemKnowledge = $data['systemKnowledge'] ?? [];
$prompts = $data['prompts'] ?? ['admin' => null, 'customer' => null];
$logs = $data['logs'] ?? [];
$logsTotal = $data['logsTotal'] ?? 0;
$logsPage = $data['logsPage'] ?? 1;
$logsStatus = $data['logsStatus'] ?? '';
$logsSearch = $data['logsSearch'] ?? '';
$activeTab = $data['activeTab'] ?? 'dashboard';
$csrfToken = $data['csrfToken'] ?? '';

/**
 * Badge komponen status (aktif/mati).
 */
function aiStatusBadge($ok, $okText, $downText)
{
    return $ok
        ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>' . $okText . '</span>'
        : '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>' . $downText . '</span>';
}

function aiDuration($ms)
{
    if ($ms === null || $ms === '') {
        return '-';
    }
    return number_format((int) $ms) . ' ms';
}
?>
<div class="bg-dark text-white py-4 mb-4 shadow-sm border-bottom border-info border-3">
    <div class="container-fluid px-lg-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-info text-dark px-3 py-2 rounded-pill mb-2"><i class="bi bi-robot me-1"></i> Menu Khusus Admin</span>
                <h2 class="fw-bold mb-0">AI Configuration</h2>
                <div class="small text-white-50 mt-1">Kelola pengetahuan, prompt, dan model chatbot AI. Semua perubahan tersimpan di database dan langsung dipakai percakapan berikutnya.</div>
            </div>
            <div>
                <?= aiStatusBadge(!empty($node['reachable']), 'Node.js Aktif', 'Node.js Mati'); ?>
                <?= aiStatusBadge(!empty($openrouter['configured']), 'OpenRouter Siap', 'OpenRouter Belum Siap'); ?>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-lg-4 py-2 mb-5">
    <ul class="nav nav-tabs mb-4" id="aiConfigTabs" role="tablist">
        <?php
        $tabs = [
            'dashboard'         => ['bi-speedometer2', 'Dashboard AI'],
            'knowledge-database'=> ['bi-diagram-3', 'Knowledge Database'],
            'system-knowledge'  => ['bi-journal-bookmark', 'System Knowledge'],
            'prompt'            => ['bi-chat-left-text', 'AI Prompt Configuration'],
            'model'             => ['bi-cpu', 'AI Model Configuration'],
            'logs'              => ['bi-clock-history', 'Chatbot Logs'],
            'test'              => ['bi-lightning', 'Test AI'],
        ];
        foreach ($tabs as $tabKey => [$icon, $label]): ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $activeTab === $tabKey ? 'active fw-bold' : ''; ?>"
                   href="<?= BASEURL; ?>/admin/ai-config?tab=<?= $tabKey; ?>"
                   aria-selected="<?= $activeTab === $tabKey ? 'true' : 'false'; ?>">
                    <i class="bi <?= $icon; ?> me-1"></i> <?= $label; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- ======================================================
         TAB 1: DASHBOARD AI
    ====================================================== -->
    <?php if ($activeTab === 'dashboard'): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-secondary small fw-bold">Node.js Bridge</div>
                                <div class="fs-4 fw-bold <?= !empty($node['reachable']) ? 'text-success' : 'text-danger'; ?>">
                                    <?= !empty($node['reachable']) ? 'Aktif' : 'Tidak Aktif'; ?>
                                </div>
                            </div>
                            <i class="bi bi-hdd-network fs-2 <?= !empty($node['reachable']) ? 'text-success' : 'text-danger'; ?>"></i>
                        </div>
                        <div class="small text-secondary mt-2"><?= htmlspecialchars((string) ($node['message'] ?? '')); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-secondary small fw-bold">OpenRouter</div>
                                <div class="fs-4 fw-bold <?= !empty($openrouter['configured']) ? 'text-success' : 'text-danger'; ?>">
                                    <?= !empty($openrouter['configured']) ? 'Siap' : 'Belum Siap'; ?>
                                </div>
                            </div>
                            <i class="bi bi-lightning-charge fs-2 <?= !empty($openrouter['configured']) ? 'text-success' : 'text-danger'; ?>"></i>
                        </div>
                        <div class="small text-secondary mt-2">
                            <?= $openrouter && !empty($openrouter['missing'])
                                ? 'Konfigurasi kurang: ' . htmlspecialchars(implode(', ', (array) $openrouter['missing']))
                                : 'API key hanya tersimpan di .env server (tidak pernah dikirim ke browser).'; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-secondary small fw-bold">Model AI Aktif</div>
                                <div class="fs-6 fw-bold text-truncate" title="<?= htmlspecialchars((string) ($modelConfig['model'] ?? '')); ?>">
                                    <?= htmlspecialchars((string) ($modelConfig['model'] ?? '-')); ?>
                                </div>
                            </div>
                            <i class="bi bi-cpu fs-2 text-primary"></i>
                        </div>
                        <div class="small text-secondary mt-2">
                            Temperature <?= htmlspecialchars((string) ($modelConfig['temperature'] ?? '-')); ?> ·
                            Max tokens <?= htmlspecialchars((string) ($modelConfig['max_tokens'] ?? '-')); ?> ·
                            Sumber: <?= htmlspecialchars((string) ($modelConfig['source'] ?? '-')); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-secondary small fw-bold">Database</div>
                                <div class="fs-4 fw-bold <?= !empty($databaseStatus['connected']) ? 'text-success' : 'text-danger'; ?>">
                                    <?= !empty($databaseStatus['connected']) ? 'Terhubung' : 'Terputus'; ?>
                                </div>
                            </div>
                            <i class="bi bi-database fs-2 <?= !empty($databaseStatus['connected']) ? 'text-success' : 'text-danger'; ?>"></i>
                        </div>
                        <div class="small text-secondary mt-2"><?= htmlspecialchars((string) ($databaseStatus['name'] ?? '')); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="fs-3 fw-bold text-primary"><?= number_format((int) ($chatSummary['total'] ?? 0)); ?></div>
                        <div class="text-secondary small">Total Percakapan</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="fs-3 fw-bold text-success"><?= number_format((int) ($chatSummary['today'] ?? 0)); ?></div>
                        <div class="text-secondary small">Percakapan Hari Ini</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="fs-3 fw-bold <?= (int) ($chatSummary['errors'] ?? 0) > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?= number_format((int) ($chatSummary['errors'] ?? 0)); ?>
                        </div>
                        <div class="text-secondary small">Error Tercatat</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="fs-3 fw-bold text-info">
                            <?= isset($chatSummary['avg_duration_ms']) && $chatSummary['avg_duration_ms'] !== null ? number_format((int) $chatSummary['avg_duration_ms']) . ' ms' : '-'; ?>
                        </div>
                        <div class="text-secondary small">Rata-rata Durasi Respons</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-shield-check text-success me-1"></i> Status Keamanan</h6>
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i>Chatbot read-only: INSERT/UPDATE/DELETE/DROP/TRUNCATE/ALTER dilarang di PHP dan Node.js.</li>
                    <li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i>Pembatasan role Admin/Customer divalidasi backend (token bertanda tangan, bukan dari browser).</li>
                    <li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i>Relevansi topik divalidasi sebelum tool database dijalankan.</li>
                    <li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i>API key OpenRouter hanya di file .env server, tidak pernah tampil di browser.</li>
                </ul>
                <button type="button" class="btn btn-outline-primary btn-sm mt-3" id="refreshHealthBtn">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh Status
                </button>
                <div id="refreshHealthResult" class="small mt-2"></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ======================================================
         TAB 2: KNOWLEDGE DATABASE
    ====================================================== -->
    <?php if ($activeTab === 'knowledge-database'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold"><i class="bi bi-diagram-3 text-primary me-2"></i>Struktur Tabel Database (<?= number_format((int) $totalTables); ?> tabel)</div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Tabel</th>
                            <th>Jumlah Record</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($knowledgeTables === []): ?>
                            <tr><td colspan="4" class="text-center text-secondary py-4">Tidak ada data tabel.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($knowledgeTables as $i => $table): ?>
                            <tr>
                                <td><?= $i + 1; ?></td>
                                <td class="fw-bold"><?= htmlspecialchars((string) $table['table_name']); ?></td>
                                <td>
                                    <?= $table['row_count'] !== null
                                        ? number_format((int) $table['row_count']) . ' <span class="text-secondary small">(±' . number_format((int) ($table['approx_rows'] ?? 0)) . ')</span>'
                                        : '-'; ?>
                                </td>
                                <td class="small text-secondary"><?= htmlspecialchars((string) ($table['table_comment'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-link-45deg text-primary me-2"></i>Relasi Antar Tabel (Foreign Keys)</div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tabel</th>
                            <th>Kolom</th>
                            <th>Referensi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($knowledgeRelations === []): ?>
                            <tr><td colspan="3" class="text-center text-secondary py-4">Tidak ada relasi foreign key.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($knowledgeRelations as $rel): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars((string) $rel['table_name']); ?></td>
                                <td><?= htmlspecialchars((string) $rel['column_name']); ?></td>
                                <td><?= htmlspecialchars((string) $rel['referenced_table']) . '.' . htmlspecialchars((string) $rel['referenced_column']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ======================================================
         TAB 3: SYSTEM KNOWLEDGE
    ====================================================== -->
    <?php if ($activeTab === 'system-knowledge'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold"><i class="bi bi-plus-circle text-success me-2"></i>Tambah Pengetahuan Baru</div>
            <div class="card-body">
                <form method="POST" action="<?= BASEURL; ?>/admin/ai-config/system-knowledge/save" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Key <span class="text-danger">*</span></label>
                        <input type="text" name="key_name" class="form-control" placeholder="alur_pesanan" maxlength="80" required>
                        <div class="form-text">huruf kecil/angka/underscore</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Judul <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Alur Pemesanan" maxlength="150" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold">Isi Pengetahuan <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control" rows="3" maxlength="5000" required></textarea>
                    </div>
                    <div class="col-12 d-flex align-items-center gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="newKnowledgeActive" value="1" checked>
                            <label class="form-check-label small" for="newKnowledgeActive">Aktif</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm ms-auto"><i class="bi bi-save me-1"></i> Simpan Pengetahuan</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($systemKnowledge === []): ?>
            <div class="alert alert-info">Belum ada pengetahuan sistem. Chatbot memakai pengetahuan bawaan Node.js.</div>
        <?php endif; ?>

        <?php foreach ($systemKnowledge as $entry): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <form method="POST" action="<?= BASEURL; ?>/admin/ai-config/system-knowledge/save">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                        <input type="hidden" name="id" value="<?= (int) $entry['id']; ?>">
                        <div class="row g-2">
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Key</label>
                                <input type="text" name="key_name" class="form-control form-control-sm" value="<?= htmlspecialchars((string) $entry['key_name']); ?>" maxlength="80" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Judul</label>
                                <input type="text" name="title" class="form-control form-control-sm" value="<?= htmlspecialchars((string) $entry['title']); ?>" maxlength="150" required>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label small fw-bold">Isi</label>
                                <textarea name="content" class="form-control form-control-sm" rows="2" maxlength="5000" required><?= htmlspecialchars((string) $entry['content']); ?></textarea>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3 mt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="active<?= (int) $entry['id']; ?>" value="1" <?= !empty($entry['is_active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="active<?= (int) $entry['id']; ?>">Aktif</label>
                            </div>
                            <span class="small text-secondary">Diperbarui: <?= htmlspecialchars((string) $entry['updated_at']); ?></span>
                            <button type="submit" class="btn btn-primary btn-sm ms-auto"><i class="bi bi-save me-1"></i> Simpan</button>
                        </div>
                    </form>
                    <form method="POST" action="<?= BASEURL; ?>/admin/ai-config/system-knowledge/delete" class="d-inline"
                          onsubmit="return confirm('Hapus pengetahuan ini?');">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                        <input type="hidden" name="id" value="<?= (int) $entry['id']; ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm mt-2"><i class="bi bi-trash me-1"></i> Hapus</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ======================================================
         TAB 4: AI PROMPT CONFIGURATION
    ====================================================== -->
    <?php if ($activeTab === 'prompt'): ?>
        <div class="alert alert-info small">
            <i class="bi bi-shield-lock me-1"></i>
            Prompt kustom dipakai chatbot bila "Aktif". Blok keamanan read-only, pembatasan role, dan identitas session <strong>selalu ditambahkan server-side</strong> dan tidak dapat dihapus dari prompt ini.
            Kosongkan isi lalu simpan untuk kembali ke prompt bawaan sistem.
        </div>

        <?php foreach (['admin' => 'Admin', 'customer' => 'Customer'] as $roleKey => $roleLabel): ?>
            <?php $promptData = $prompts[$roleKey] ?? null; ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-badge text-primary me-2"></i>Prompt Role <?= $roleLabel; ?></span>
                    <?= aiStatusBadge($promptData !== null && !empty($promptData['is_active']), 'Aktif (Database)', 'Prompt Bawaan'); ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= BASEURL; ?>/admin/ai-config/prompt/save">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                        <input type="hidden" name="role_key" value="<?= $roleKey; ?>">
                        <textarea name="system_prompt" class="form-control font-monospace" rows="8"
                                  placeholder="Kosongkan untuk memakai prompt bawaan"><?= $promptData !== null ? htmlspecialchars((string) $promptData['system_prompt']) : ''; ?></textarea>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <?php if ($promptData !== null && $promptData['updated_at'] !== ''): ?>
                                <span class="small text-secondary">Terakhir diubah: <?= htmlspecialchars((string) $promptData['updated_at']); ?></span>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary btn-sm ms-auto"><i class="bi bi-save me-1"></i> Simpan Prompt <?= $roleLabel; ?></button>
                        </div>
                    </form>
                    <form method="POST" action="<?= BASEURL; ?>/admin/ai-config/prompt/reset" class="d-inline mt-2">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                        <input type="hidden" name="role_key" value="<?= $roleKey; ?>">
                        <button type="submit" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Kembalikan prompt <?= $roleLabel; ?> ke bawaan sistem?');">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset ke Bawaan
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- ======================================================
         TAB 5: AI MODEL CONFIGURATION
    ====================================================== -->
    <?php if ($activeTab === 'model'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold"><i class="bi bi-cpu text-primary me-2"></i>Parameter Model OpenRouter</div>
            <div class="card-body">
                <form method="POST" action="<?= BASEURL; ?>/admin/ai-config/model/save" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nama Model</label>
                        <input type="text" name="model" class="form-control" value="<?= htmlspecialchars((string) ($modelConfig['model'] ?? '')); ?>" maxlength="120" required>
                        <div class="form-text">Contoh: openai/gpt-4.1-mini</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Temperature</label>
                        <input type="number" name="temperature" class="form-control" min="0" max="1.5" step="0.05" value="<?= htmlspecialchars((string) ($modelConfig['temperature'] ?? 0.3)); ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Max Tokens</label>
                        <input type="number" name="max_tokens" class="form-control" min="256" max="4000" step="1" value="<?= (int) ($modelConfig['max_tokens'] ?? 1800); ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold">Timeout (ms)</label>
                        <input type="number" name="timeout_ms" class="form-control" min="5000" max="120000" step="1000" value="<?= (int) ($modelConfig['timeout_ms'] ?? 30000); ?>" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i> Simpan Konfigurasi Model</button>
                        <span class="small text-secondary ms-2">Sumber saat ini: <?= htmlspecialchars((string) ($modelConfig['source'] ?? '-')); ?></span>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-key text-warning me-2"></i>API Key OpenRouter</span>
                <?php $apiKeyStatus = $data['apiKeyStatus'] ?? []; ?>
                <?= aiStatusBadge(!empty($apiKeyStatus['configured']), 'Tersimpan (' . htmlspecialchars((string) ($apiKeyStatus['source'] ?? '')) . ')', 'Belum Tersimpan'); ?>
            </div>
            <div class="card-body">
                <?php if (!empty($apiKeyStatus['hint'])): ?>
                    <div class="mb-3">
                        <div class="small text-secondary">Key aktif (disamarkan):</div>
                        <code><?= htmlspecialchars((string) $apiKeyStatus['hint']); ?></code>
                        <div class="small text-secondary mt-1">
                            Sumber: <?= htmlspecialchars((string) ($apiKeyStatus['source'] ?? '-')); ?>
                            <?php if (!empty($apiKeyStatus['env_configured'])): ?>
                                · .env juga berisi key (cadangan)
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning small mb-3">
                        Belum ada API key di database. Chatbot memakai <code>OPENROUTER_API_KEY</code> dari <code>chatbot-node/.env</code> (jika ada).
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASEURL; ?>/admin/ai-config/api-key/save" class="row g-2">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                    <div class="col-md-9">
                        <label class="form-label small fw-bold">API Key Baru</label>
                        <input type="password" name="api_key" class="form-control" placeholder="sk-or-v1-..." autocomplete="off" required>
                        <div class="form-text">Key disimpan terenkripsi (AES-256-GCM) di database. Key lama tidak ditampilkan.</div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i> Simpan Key</button>
                    </div>
                </form>

                <?php if (!empty($apiKeyStatus['configured']) && ($apiKeyStatus['source'] ?? '') === 'database'): ?>
                    <form method="POST" action="<?= BASEURL; ?>/admin/ai-config/api-key/clear" class="mt-2"
                          onsubmit="return confirm('Hapus API key dari database? Chatbot akan kembali memakai key di .env (jika ada).');">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i> Hapus Key dari Database</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="alert alert-warning small">
            <i class="bi bi-key me-1"></i>
            <strong>API key OpenRouter</strong> kini dapat diganti lewat form di atas dan tersimpan terenkripsi di database.
            Key tidak pernah ditampilkan utuh di browser. <code>chatbot-node/.env</code> tetap dipakai sebagai cadangan bila database kosong.
        </div>
    <?php endif; ?>

    <!-- ======================================================
         TAB 6: CHATBOT LOGS
    ====================================================== -->
    <?php if ($activeTab === 'logs'): ?>
        <form method="GET" action="<?= BASEURL; ?>/admin/ai-config" class="row g-2 mb-3">
            <input type="hidden" name="tab" value="logs">
            <div class="col-md-5">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari pertanyaan/jawaban/nama..." value="<?= htmlspecialchars($logsSearch); ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua status</option>
                    <option value="success" <?= $logsStatus === 'success' ? 'selected' : ''; ?>>Success</option>
                    <option value="error" <?= $logsStatus === 'error' ? 'selected' : ''; ?>>Error</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i> Filter</button>
            </div>
        </form>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Riwayat Percakapan (<?= number_format((int) $logsTotal); ?> total)</div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Role</th>
                            <th>Pengguna</th>
                            <th>Pertanyaan</th>
                            <th>Status</th>
                            <th>Sumber Data</th>
                            <th>Tool</th>
                            <th>Durasi</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs === []): ?>
                            <tr><td colspan="9" class="text-center text-secondary py-4">Belum ada percakapan.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="small text-nowrap"><?= htmlspecialchars((string) $log['created_at']); ?></td>
                                <td class="small"><?= htmlspecialchars((string) ($log['role'] ?? ($log['user_role'] ?? '-'))); ?></td>
                                <td class="small"><?= htmlspecialchars((string) ($log['user_name'] ?? '-')); ?></td>
                                <td class="small" title="<?= htmlspecialchars((string) $log['query']); ?>">
                                    <?= htmlspecialchars(mb_strimwidth((string) $log['query'], 0, 60, '...')); ?>
                                </td>
                                <td>
                                    <?= ($log['status'] ?? 'success') === 'error'
                                        ? '<span class="badge bg-danger">Error</span>'
                                        : '<span class="badge bg-success">Success</span>'; ?>
                                </td>
                                <td class="small"><?= htmlspecialchars((string) ($log['source'] ?? '-')); ?></td>
                                <td class="small"><?= htmlspecialchars((string) ($log['tools_used'] ?? '-')); ?></td>
                                <td class="small text-nowrap"><?= aiDuration($log['duration_ms'] ?? null); ?></td>
                                <td class="small text-danger" title="<?= htmlspecialchars((string) ($log['error_message'] ?? '')); ?>">
                                    <?= $log['error_message'] !== null ? htmlspecialchars(mb_strimwidth((string) $log['error_message'], 0, 40, '...')) : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($logsTotal > 30): ?>
                <div class="card-body border-top d-flex justify-content-between align-items-center">
                    <span class="small text-secondary">Halaman <?= (int) $logsPage; ?> dari <?= (int) ceil($logsTotal / 30); ?></span>
                    <div class="d-flex gap-2">
                        <?php if ($logsPage > 1): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= BASEURL; ?>/admin/ai-config?tab=logs&page=<?= $logsPage - 1; ?>&status=<?= urlencode($logsStatus); ?>&q=<?= urlencode($logsSearch); ?>">&laquo; Sebelumnya</a>
                        <?php endif; ?>
                        <?php if ($logsPage * 30 < $logsTotal): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= BASEURL; ?>/admin/ai-config?tab=logs&page=<?= $logsPage + 1; ?>&status=<?= urlencode($logsStatus); ?>&q=<?= urlencode($logsSearch); ?>">Berikutnya &raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ======================================================
         TAB 7: TEST AI
    ====================================================== -->
    <?php if ($activeTab === 'test'): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-bold"><i class="bi bi-lightning text-warning me-2"></i>Uji Chatbot Langsung (session admin aktif)</div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-9">
                        <input type="text" id="aiTestQuery" class="form-control" placeholder="Contoh: Siapa saya? Berapa total user?" maxlength="2000">
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-primary w-100" id="aiTestBtn"><i class="bi bi-send me-1"></i> Kirim ke Chatbot</button>
                    </div>
                </div>
                <div class="mb-2 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm ai-test-sample" data-query="Siapa saya?">Siapa saya?</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ai-test-sample" data-query="Berapa jumlah total user?">Total user</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ai-test-sample" data-query="Siapa saja user yang terdaftar?">Daftar user</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ai-test-sample" data-query="Berapa jumlah laporan dan siapa yang membuatnya?">Laporan</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ai-test-sample" data-query="Bagaimana sistem WhatsApp bekerja?">WhatsApp</button>
                    <button type="button" class="btn btn-outline-danger btn-sm ai-test-sample" data-query="Buatkan kode Python untuk saya.">Topik luar (harus ditolak)</button>
                </div>
                <div id="aiTestResult" class="border rounded p-3 bg-light" style="min-height: 120px; display: none;"></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($activeTab === 'dashboard'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('refreshHealthBtn');
            const result = document.getElementById('refreshHealthResult');
            if (!btn) return;
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                result.innerHTML = '<span class="text-secondary">Memeriksa...</span>';
                try {
                    const res = await fetch('<?= BASEURL; ?>/admin/ai-config/health');
                    const json = await res.json();
                    const node = json.node || {};
                    const openrouter = (node.status && node.status.openrouter) || null;
                    result.innerHTML =
                        '<div class="text-success fw-bold">Node.js: ' + (node.reachable ? 'AKTIF' : 'TIDAK AKTIF') + '</div>' +
                        (openrouter
                            ? '<div class="text-secondary">OpenRouter: ' + (openrouter.configured ? 'siap, model ' + openrouter.model : 'belum siap (' + (openrouter.missing || []).join(', ') + ')') + '</div>'
                            : '');
                } catch (e) {
                    result.innerHTML = '<span class="text-danger">Gagal memeriksa status: ' + e.message + '</span>';
                } finally {
                    btn.disabled = false;
                }
            });
        });
    </script>
<?php endif; ?>

<?php if ($activeTab === 'test'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('aiTestBtn');
            const query = document.getElementById('aiTestQuery');
            const result = document.getElementById('aiTestResult');

            async function runTest(question) {
                query.value = question;
                btn.disabled = true;
                result.style.display = 'block';
                result.innerHTML = '<span class="text-secondary">Mengirim ke chatbot (Node.js + OpenRouter)...</span>';
                try {
                    const body = new URLSearchParams();
                    body.set('query', question);
                    body.set('csrf_token', '<?= $csrfToken; ?>');
                    const res = await fetch('<?= BASEURL; ?>/admin/ai-config/test', {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': '<?= $csrfToken; ?>' },
                        body
                    });
                    const json = await res.json();
                    const meta = [];
                    if (json.source) meta.push('sumber: ' + json.source);
                    if (json.duration_ms !== undefined) meta.push(json.duration_ms + ' ms');
                    if (json.toolsUsed && json.toolsUsed.length) {
                        meta.push('tool: ' + json.toolsUsed.map((t) => t.function || t).join(', '));
                    }
                    result.innerHTML =
                        '<div class="mb-2">' + (meta.length ? '<span class="badge bg-secondary">' + meta.join('</span><span class="badge bg-secondary">') + '</span>' : '') + '</div>' +
                        '<div>' + (json.reply || json.message || '(tidak ada jawaban)') + '</div>';
                    result.classList.toggle('border-danger', json.success === false);
                } catch (e) {
                    result.innerHTML = '<span class="text-danger">Gagal: ' + e.message + '</span>';
                } finally {
                    btn.disabled = false;
                }
            }

            btn.addEventListener('click', () => runTest(query.value.trim()));
            query.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    runTest(query.value.trim());
                }
            });
            document.querySelectorAll('.ai-test-sample').forEach((el) => {
                el.addEventListener('click', () => runTest(el.dataset.query));
            });
        });
    </script>
<?php endif; ?>
