/**
 * TKJ Service Hub - AI Assistant & Helper Frontend Engine
 */

document.addEventListener('DOMContentLoaded', () => {

    // BASEURL helper detection
    const getBaseUrl = () => {
        if (typeof window.BASEURL !== 'undefined') return window.BASEURL;
        const metaBase = document.querySelector('meta[name="base-url"]');
        if (metaBase) return metaBase.getAttribute('content');
        return window.location.origin + '/webjasa';
    };

    const baseUrl = getBaseUrl();
    const aiCsrfToken = typeof window.AI_CSRF_TOKEN !== 'undefined' ? window.AI_CSRF_TOKEN : '';

    // ======================================================
    // 1. FLOATING AI ASSISTANT WIDGET LOGIC
    // ======================================================
    const widgetToggle = document.getElementById('ai-widget-toggle');
    const widgetWindow = document.getElementById('ai-widget-window');
    const widgetCloseBtn = document.getElementById('ai-widget-close-btn');
    const chatForm = document.getElementById('ai-chat-form');
    const chatInput = document.getElementById('ai-chat-input');
    const chatBody = document.getElementById('ai-chat-body');
    const messagesList = document.getElementById('ai-messages-list');
    const typingIndicator = document.getElementById('ai-typing-indicator');

    // Maintain multi-turn chat history
    let chatHistory = [];

    if (widgetToggle && widgetWindow) {
        
        // Toggle Chat Window Open/Close
        const toggleWidget = () => {
            const isHidden = widgetWindow.classList.contains('d-none');
            const iconOpen = widgetToggle.querySelector('.ai-icon-open');
            const iconClose = widgetToggle.querySelector('.ai-icon-close');

            if (isHidden) {
                widgetWindow.classList.remove('d-none');
                iconOpen.classList.add('d-none');
                iconClose.classList.remove('d-none');
                chatInput.focus();
                scrollToBottom();
            } else {
                widgetWindow.classList.add('d-none');
                iconOpen.classList.remove('d-none');
                iconClose.classList.add('d-none');
            }
        };

        widgetToggle.addEventListener('click', toggleWidget);
        if (widgetCloseBtn) widgetCloseBtn.addEventListener('click', toggleWidget);

        // Quick Suggestion Chips Click Handler
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('ai-chip') || e.target.closest('.ai-chip')) {
                const chip = e.target.classList.contains('ai-chip') ? e.target : e.target.closest('.ai-chip');
                const query = chip.getAttribute('data-query');
                if (query) {
                    chatInput.value = query;
                    chatForm.dispatchEvent(new Event('submit'));
                }
            }
        });

        // Handle Enter Keypress explicitly
        if (chatInput && chatForm) {
            chatInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    chatForm.dispatchEvent(new Event('submit'));
                }
            });
        }

        // Chat Form Submit Handler
        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const query = chatInput.value.trim();
            if (!query) return;

            // 1. Render User Message
            appendMessage('user', query);
            chatInput.value = '';
            
            // 2. Show Typing Indicator
            showTyping(true);
            scrollToBottom();

            try {
                // 3. Request AI Endpoint with history context (Node.js + OpenRouter)
                const response = await fetch(`${baseUrl}/ai/chat/node`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': aiCsrfToken
                    },
                    body: JSON.stringify({ 
                        query: query,
                        history: chatHistory
                    })
                });

                // Baca isi respons selalu (juga saat error) agar pesan asli
                // dari server tidak hilang, lalu cek keberhasilannya.
                let data = null;
                try {
                    data = await response.json();
                } catch (parseError) {
                    data = null;
                }
                showTyping(false);

                if (response.ok && data && data.success && data.data) {
                    appendMessage('ai', data.data.reply, data.data.suggestedAction, data.data.relatedService);

                    // Add turn to history
                    chatHistory.push({ role: 'user', content: query });
                    chatHistory.push({ role: 'assistant', content: data.data.reply });

                    // Keep last 10 turns max
                    if (chatHistory.length > 20) {
                        chatHistory = chatHistory.slice(-20);
                    }
                } else if (data && typeof data.message === 'string' && data.message !== '') {
                    // Tampilkan pesan error asli dari server (jangan palsukan)
                    appendMessage('ai', data.message);
                } else {
                    appendMessage('ai', `Terjadi kesalahan pada server AI (HTTP ${response.status}). Silakan coba lagi nanti.`);
                }

            } catch (error) {
                console.error('AI Request Error:', error);
                showTyping(false);
                appendMessage('ai', 'Tidak dapat menghubungi server AI. Pastikan backend Node.js chatbot sedang berjalan, lalu coba lagi.');
            }

            scrollToBottom();
        });
    }

    // Helper: Append Chat Bubble
    function appendMessage(sender, text, actionText = null, serviceName = null) {
        const isUser = sender === 'user';
        const container = document.createElement('div');
        container.className = `d-flex align-items-start gap-2 mb-3 ${isUser ? 'justify-content-end' : ''}`;

        const formattedText = formatMarkdownText(text);

        let actionButtonHtml = '';
        if (!isUser && actionText) {
            actionButtonHtml = `
                <div class="mt-2.5 pt-2 border-top border-light">
                    <a href="${baseUrl}/customer/orders/create" class="btn btn-sm btn-primary rounded-pill px-3 py-1 shadow-sm font-semibold" style="font-size: 0.78rem;">
                        <i class="bi bi-calendar-plus me-1"></i> ${actionText}
                    </a>
                </div>
            `;
        }

        if (isUser) {
            container.innerHTML = `
                <div class="chat-bubble user-bubble bg-primary text-white p-3 rounded-4 shadow-sm" style="max-width: 85%;">
                    <p class="mb-0">${escapeHtml(text)}</p>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="avatar bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 32px; height: 32px;">
                    <i class="bi bi-robot small"></i>
                </div>
                <div class="chat-bubble ai-bubble bg-white p-3 rounded-4 shadow-sm border border-light text-dark" style="max-width: 85%;">
                    ${formattedText}
                    ${actionButtonHtml}
                </div>
            `;
        }

        messagesList.appendChild(container);
    }

    function showTyping(show) {
        if (typingIndicator) {
            if (show) typingIndicator.classList.remove('d-none');
            else typingIndicator.classList.add('d-none');
        }
    }

    function scrollToBottom() {
        if (chatBody) {
            chatBody.scrollTop = chatBody.scrollHeight;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.innerText = text;
        return div.innerHTML;
    }

    function formatMarkdownText(text) {
        let html = text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
        
        // Bold formatting **text**
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italic formatting *text*
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
        // New lines to br
        html = html.replace(/\n/g, '<br>');

        return html;
    }

    // ======================================================
    // 2. ORDER FORM AI PROBLEM DESCRIPTION HELPER
    // ======================================================
    const aiGenerateDescBtn = document.getElementById('ai-generate-desc-btn');
    const problemDescInput = document.querySelector('textarea[name="problem_description"]');
    const serviceSelect = document.querySelector('select[name="service_id"]');

    if (aiGenerateDescBtn && problemDescInput) {
        aiGenerateDescBtn.addEventListener('click', async () => {
            const briefInput = prompt("💡 Tuliskan singkat kendala perangkat Anda (misal: 'Laptop mati total kipas bunyi' atau 'CCTV no signal sehabis petir'):");
            if (!briefInput || !briefInput.trim()) return;

            const selectedOption = serviceSelect ? serviceSelect.options[serviceSelect.selectedIndex].text : '';

            // Set loading state on button
            const origHtml = aiGenerateDescBtn.innerHTML;
            aiGenerateDescBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> AI Merumuskan...`;
            aiGenerateDescBtn.disabled = true;

            try {
                const response = await fetch(`${baseUrl}/ai/generateDescription`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': aiCsrfToken
                    },
                    body: JSON.stringify({
                        brief_input: briefInput,
                        service_type: selectedOption
                    })
                });

                const data = await response.json();
                if (data.success && data.description) {
                    problemDescInput.value = data.description;
                    problemDescInput.focus();
                } else {
                    alert('Gagal merumuskan deskripsi AI: ' + (data.message || 'Terjadi kesalahan'));
                }
            } catch (err) {
                console.error(err);
                alert('Gagal terhubung ke AI Service.');
            } finally {
                aiGenerateDescBtn.innerHTML = origHtml;
                aiGenerateDescBtn.disabled = false;
            }
        });
    }

    // ======================================================
    // 3. TECHNICIAN & OPERATOR AI HELPER TOOLS
    // ======================================================
    document.addEventListener('click', async (e) => {
        // AI Technician Checklist Generator Button
        if (e.target.classList.contains('ai-btn-checklist') || e.target.closest('.ai-btn-checklist')) {
            const btn = e.target.classList.contains('ai-btn-checklist') ? e.target : e.target.closest('.ai-btn-checklist');
            const serviceName = btn.getAttribute('data-service') || '';
            const problemDesc = btn.getAttribute('data-problem') || '';

            const origHtml = btn.innerHTML;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Processing...`;
            btn.disabled = true;

            try {
                const response = await fetch(`${baseUrl}/ai/technicianHelper`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': aiCsrfToken
                    },
                    body: JSON.stringify({
                        action: 'checklist',
                        service_type: serviceName,
                        problem_description: problemDesc
                    })
                });

                const data = await response.json();
                if (data.success && data.checklist) {
                    showAiModal('Checklist Troubleshooting AI (K3 & Standar Teknis)', data.checklist);
                } else {
                    alert('Gagal menghasilkan checklist AI.');
                }
            } catch (err) {
                console.error(err);
                alert('Koneksi AI gagal.');
            } finally {
                btn.innerHTML = origHtml;
                btn.disabled = false;
            }
        }
    });

    // Modal Helper for Technician Checklist
    function showAiModal(title, content) {
        let modalEl = document.getElementById('ai-dynamic-modal');
        if (!modalEl) {
            modalEl = document.createElement('div');
            modalEl.id = 'ai-dynamic-modal';
            modalEl.className = 'modal fade';
            modalEl.tabIndex = -1;
            modalEl.innerHTML = `
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header bg-dark text-white border-bottom border-primary border-3">
                            <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
                                <i class="bi bi-robot text-primary"></i> <span id="ai-modal-title"></span>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4 bg-light">
                            <div id="ai-modal-body" class="p-3 bg-white rounded-3 border font-monospace text-dark" style="white-space: pre-wrap; font-size: 0.9rem;"></div>
                        </div>
                        <div class="modal-footer bg-white border-0">
                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modalEl);
        }

        document.getElementById('ai-modal-title').innerText = title;
        document.getElementById('ai-modal-body').innerHTML = formatMarkdownText(content);
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();
    }

});
