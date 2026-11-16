<!-- Floating AI Customer Assistant Widget -->
<div id="ai-chat-widget" class="ai-chat-widget">
    
    <!-- Floating Trigger Toggle Button -->
    <button id="ai-widget-toggle" class="ai-widget-toggle btn btn-primary shadow-lg rounded-circle d-flex align-items-center justify-content-center" title="Buka Asisten AI Customer Service">
        <i class="bi bi-robot fs-3 ai-icon-open"></i>
        <i class="bi bi-x-lg fs-3 ai-icon-close d-none"></i>
        <span class="position-absolute top-0 start-100 translate-middle p-2 bg-success border border-light rounded-circle">
            <span class="visually-hidden">AI Online</span>
        </span>
    </button>

    <!-- Floating Chat Container Window -->
    <div id="ai-widget-window" class="ai-widget-window card border-0 shadow-lg rounded-4 d-none">
        
        <!-- Header -->
        <div class="card-header bg-dark text-white p-3 d-flex align-items-center justify-content-between border-bottom border-primary border-3 rounded-top-4">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary bg-opacity-25 rounded-circle text-primary border border-primary border-opacity-50">
                    <i class="bi bi-robot fs-5 text-info"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
                        TKJ AI Assistant
                        <span class="badge bg-primary text-wrap rounded-pill font-monospace" style="font-size: 0.65rem;">v2.5 Hybrid</span>
                    </h6>
                    <small class="text-white-50 d-flex align-items-center gap-1" style="font-size: 0.75rem;">
                        <span class="d-inline-block bg-success rounded-circle" style="width: 6px; height: 6px;"></span>
                        Asisten Diagnosa & FAQ Unit Bisnis
                    </small>
                </div>
            </div>
            <button id="ai-widget-close-btn" class="btn btn-sm btn-link text-white-50 p-0 fs-5 text-decoration-none">
                <i class="bi bi-dash-lg"></i>
            </button>
        </div>

        <!-- Chat Messages Scrollable Body -->
        <div id="ai-chat-body" class="card-body p-3 overflow-auto flex-grow-1 bg-light bg-opacity-50" style="max-height: 420px; min-height: 320px;">
            
            <!-- Welcome Message Bubble -->
            <div class="d-flex align-items-start gap-2 mb-3">
                <div class="avatar bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px;">
                    <i class="bi bi-robot small"></i>
                </div>
                <div class="chat-bubble ai-bubble bg-white p-3 rounded-4 shadow-sm border border-light text-dark" style="max-width: 85%;">
                    <p class="mb-2 fw-medium">Halo! 👋 Saya **TKJ AI Assistant**.</p>
                    <p class="small text-muted mb-0">Ada yang bisa saya bantu terkait kendala perangkat Anda hari ini? Anda dapat menanyakan diagnosa awal, troubleshooting, atau rekomendasi layanan IT.</p>
                </div>
            </div>

            <!-- Quick Suggested Action Chips -->
            <div id="ai-quick-chips" class="mb-3">
                <div class="small fw-semibold text-muted mb-2"><i class="bi bi-stars text-warning me-1"></i> Pertanyaan Populer:</div>
                <div class="d-flex flex-wrap gap-1.5">
                    <button class="btn btn-sm btn-outline-primary rounded-pill ai-chip" data-query="CCTV saya tidak tampil di monitor, harus bagaimana?">
                        📹 CCTV Tidak Tampil
                    </button>
                    <button class="btn btn-sm btn-outline-primary rounded-pill ai-chip" data-query="Wi-Fi lemot dan sering putus koneksi">
                        🌐 Wi-Fi Lemot / Putus
                    </button>
                    <button class="btn btn-sm btn-outline-primary rounded-pill ai-chip" data-query="Laptop saya mati total dan berisik">
                        💻 Laptop Mati / Overheat
                    </button>
                    <button class="btn btn-sm btn-outline-primary rounded-pill ai-chip" data-query="Printer hasil cetak bergaris dan blinking">
                        🖨️ Printer Hasil Bergaris
                    </button>
                    <button class="btn btn-sm btn-outline-primary rounded-pill ai-chip" data-query="Berapa estimasi biaya dan garansi service?">
                        ❓ Biaya & Garansi
                    </button>
                </div>
            </div>

            <!-- Dynamic Messages will append here -->
            <div id="ai-messages-list"></div>

            <!-- Typing Indicator (Hidden by default) -->
            <div id="ai-typing-indicator" class="d-none d-flex align-items-center gap-2 mb-3">
                <div class="avatar bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px;">
                    <i class="bi bi-robot small"></i>
                </div>
                <div class="bg-white px-3 py-2 rounded-4 shadow-sm border border-light text-muted small d-flex align-items-center gap-1">
                    <span>Sedang mengaitkan diagnosa</span>
                    <span class="spinner-grow spinner-grow-sm text-primary ms-1" role="status"></span>
                </div>
            </div>

        </div>

        <!-- Chat Footer Input Form -->
        <div class="card-footer bg-white p-2.5 border-top">
            <form id="ai-chat-form" class="d-flex gap-2">
                <input type="text" id="ai-chat-input" class="form-control form-control-sm rounded-pill px-3" placeholder="Tuliskan kendala Anda..." autocomplete="off" required>
                <button type="submit" id="ai-chat-send-btn" class="btn btn-primary btn-sm rounded-circle px-3 d-flex align-items-center justify-content-center shadow-sm">
                    <i class="bi bi-send-fill"></i>
                </button>
            </form>
            <div class="text-center mt-1">
                <span class="text-muted" style="font-size: 0.68rem;"><i class="bi bi-shield-check text-success me-1"></i> TKJ Service Hub AI Powered Assistant</span>
            </div>
        </div>

    </div>
</div>
