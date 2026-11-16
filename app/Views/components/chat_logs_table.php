<?php
$chatLogs = $data['chatLogs'] ?? [];
?>
<?php if (!empty($chatLogs)): ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 220px;">Pertanyaan</th>
                    <th style="min-width: 260px;">Jawaban Chatbot</th>
                    <th>Pengguna / IP</th>
                    <th>Sumber</th>
                    <th>Kategori</th>
                    <th>Waktu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chatLogs as $log): ?>
                    <tr>
                        <td>
                            <div class="small fw-semibold text-dark">
                                <?= nl2br(htmlspecialchars(mb_substr((string) $log['query'], 0, 220))) ?><?= mb_strlen((string) $log['query']) > 220 ? '…' : '' ?>
                            </div>
                        </td>
                        <td>
                            <div class="small text-secondary">
                                <?= nl2br(htmlspecialchars(mb_substr((string) $log['reply'], 0, 260))) ?><?= mb_strlen((string) $log['reply']) > 260 ? '…' : '' ?>
                            </div>
                            <?php if (mb_strlen((string) $log['reply']) > 260): ?>
                                <details class="mt-1">
                                    <summary class="small text-primary">Lihat jawaban lengkap</summary>
                                    <div class="small text-secondary mt-2 p-2 bg-light rounded">
                                        <?= nl2br(htmlspecialchars((string) $log['reply'])) ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($log['user_name'])): ?>
                                <div class="small fw-semibold"><?= htmlspecialchars((string) $log['user_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars((string) ($log['user_email'] ?? '')) ?></div>
                            <?php else: ?>
                                <span class="badge bg-secondary">Tamu</span>
                            <?php endif; ?>
                            <div class="small text-muted"><code><?= htmlspecialchars((string) ($log['ip_address'] ?? '-')); ?></code></div>
                        </td>
                        <td>
                            <?php $source = (string) ($log['source'] ?? '-'); ?>
                            <span class="badge <?= $source === 'gemini_api' ? 'bg-success' : 'bg-secondary'; ?>">
                                <?= htmlspecialchars($source); ?>
                            </span>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars((string) ($log['category'] ?? '-')); ?></span></td>
                        <td class="small text-muted text-nowrap">
                            <?= htmlspecialchars(date('d M Y H:i', strtotime((string) $log['created_at']))); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-chat-square-text fs-1 d-block mb-2 opacity-50"></i>
        Belum ada riwayat chatbot yang cocok.
    </div>
<?php endif; ?>
