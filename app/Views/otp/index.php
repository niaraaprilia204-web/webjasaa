<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-xl-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="bg-gradient-hero text-white p-4 text-center">
                    <div class="badge bg-success rounded-circle p-3 mb-2">
                        <i class="bi bi-whatsapp fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Verifikasi Nomor WhatsApp</h4>
                    <p class="text-secondary-light small mb-0">
                        Terima kode OTP melalui WhatsApp, lalu masukkan kode untuk memverifikasi nomor Anda.
                    </p>
                </div>

                <div class="card-body p-4">
                    <?php if (!empty($data['verifiedAt'])): ?>
                        <div class="alert alert-success d-flex align-items-start gap-2" role="status">
                            <i class="bi bi-patch-check-fill fs-5 mt-1"></i>
                            <div>
                                <strong>Nomor sudah terverifikasi.</strong>
                                <div class="small">
                                    <?= htmlspecialchars($data['phone'] ?: 'Nomor WhatsApp akun Anda'); ?>
                                    &middot; <?= htmlspecialchars(date('d M Y H:i', strtotime($data['verifiedAt']))); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($data['whatsappConfigured'])): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>Integrasi WhatsApp belum aktif.</strong>
                            Pengiriman OTP dinonaktifkan sampai kredensial API WhatsApp
                            diisi oleh operator pada konfigurasi server.
                        </div>
                    <?php endif; ?>

                    <div id="otpAlert" class="alert d-none" role="alert" aria-live="polite"></div>

                    <div class="alert alert-light border small mb-4" role="note">
                        <div><i class="bi bi-shield-lock text-primary me-1"></i>
                            Kode berlaku <strong><?= (int) $data['expiryMinutes']; ?> menit</strong>,
                            hanya dapat digunakan <strong>sekali</strong>, dan tidak pernah ditampilkan di halaman ini.
                        </div>
                        <div class="mt-1"><i class="bi bi-hourglass-split text-secondary me-1"></i>
                            Maksimal <?= (int) $data['maxRequests']; ?> permintaan per nomor dalam
                            <?= (int) $data['requestWindowMinutes']; ?> menit.
                        </div>
                    </div>

                    <?php if (!empty($data['isLoggedIn'])): ?>
                        <p class="small text-muted mb-3">
                            <i class="bi bi-person-check me-1"></i>
                            Halo, <strong><?= htmlspecialchars($data['userName']); ?></strong>.
                            Nomor yang berhasil diverifikasi akan ditautkan ke akun Anda.
                        </p>
                    <?php else: ?>
                        <p class="small text-muted mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Anda belum login. Verifikasi tetap dapat dilakukan, tetapi nomor
                            <strong>tidak ditautkan ke akun</strong>. <a href="<?= BASEURL; ?>/auth/login" class="fw-semibold">Login</a>
                            agar nomor tersimpan pada akun.
                        </p>
                    <?php endif; ?>

                    <form id="otpForm"
                          action="<?= BASEURL; ?>/otp/send"
                          method="POST"
                          novalidate
                          data-send-url="<?= BASEURL; ?>/otp/send"
                          data-verify-url="<?= BASEURL; ?>/otp/verify"
                          data-expiry-seconds="<?= (int) $data['expiryMinutes'] * 60; ?>"
                          data-resend-seconds="60"
                          data-configured="<?= !empty($data['whatsappConfigured']) ? '1' : '0'; ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($data['csrfToken']); ?>">

                        <!-- Honeypot: real users never fill this hidden field. -->
                        <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                            <label for="website">Website</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label fw-semibold">
                                Nomor WhatsApp <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-whatsapp text-success"></i>
                                </span>
                                <input type="tel"
                                       id="phone"
                                       name="phone"
                                       class="form-control border-start-0 ps-0"
                                       value="<?= htmlspecialchars($data['phone']); ?>"
                                       placeholder="081234567890"
                                       inputmode="tel"
                                       autocomplete="tel"
                                       maxlength="20"
                                       required
                                       <?= empty($data['whatsappConfigured']) ? 'disabled' : ''; ?>>
                            </div>
                            <div class="form-text">Gunakan nomor aktif berformat 08xxxxxxxxxx atau 628xxxxxxxxxx.</div>
                        </div>

                        <div class="d-grid mb-4">
                            <button type="submit"
                                    id="sendBtn"
                                    class="btn btn-success py-2.5 rounded-pill fw-bold shadow-sm"
                                    <?= empty($data['whatsappConfigured']) ? 'disabled' : ''; ?>>
                                <span class="spinner-border spinner-border-sm me-1 d-none" id="sendSpinner" aria-hidden="true"></span>
                                <i class="bi bi-send me-1" id="sendIcon"></i>
                                <span id="sendBtnLabel">Kirim OTP</span>
                            </button>
                        </div>

                        <hr class="my-4">

                        <div id="otpCodeSection" class="<?= !empty($data['otpSent']) ? '' : 'd-none'; ?>">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label for="code" class="form-label fw-semibold mb-0">
                                    Kode OTP <span class="text-danger">*</span>
                                </label>
                                <span id="otpCountdown" class="badge bg-secondary" aria-live="polite"><?= !empty($data['otpSent']) ? 'Aktif' : '05:00'; ?></span>
                            </div>
                            <input type="text"
                                   id="code"
                                   name="code"
                                   class="form-control form-control-lg text-center fw-bold font-monospace mb-2"
                                   placeholder="______"
                                   inputmode="numeric"
                                   autocomplete="one-time-code"
                                   pattern="[0-9]{6}"
                                   maxlength="6"
                                   aria-describedby="codeHelp">
                            <div id="codeHelp" class="form-text mb-3">
                                Masukkan 6 digit kode yang dikirim ke WhatsApp Anda. Jangan bagikan kode kepada siapa pun.
                            </div>

                            <div class="d-grid">
                                <button type="submit"
                                        id="verifyBtn"
                                        class="btn btn-primary py-2.5 rounded-pill fw-bold shadow-sm"
                                        formaction="<?= BASEURL; ?>/otp/verify">
                                    <span class="spinner-border spinner-border-sm me-1 d-none" id="verifySpinner" aria-hidden="true"></span>
                                    <i class="bi bi-patch-check me-1"></i>
                                    Verifikasi
                                </button>
                            </div>
                        </div>

                        <noscript>
                            <div class="alert alert-info mt-3 mb-0 small">
                                JavaScript nonaktif. Form tetap dapat digunakan: klik <strong>Kirim OTP</strong>,
                                lalu isi kode dan klik <strong>Verifikasi</strong>.
                            </div>
                        </noscript>
                    </form>

                    <div class="text-center small text-muted mt-4">
                        Belum punya akun?
                        <a href="<?= BASEURL; ?>/auth/register" class="fw-semibold text-primary">Daftar Customer</a>
                        &middot;
                        <a href="<?= BASEURL; ?>/auth/login" class="fw-semibold text-primary">Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var form = document.getElementById('otpForm');
    if (!form) return;

    var alertBox = document.getElementById('otpAlert');
    var phoneInput = document.getElementById('phone');
    var codeInput = document.getElementById('code');
    var codeSection = document.getElementById('otpCodeSection');
    var sendBtn = document.getElementById('sendBtn');
    var sendLabel = document.getElementById('sendBtnLabel');
    var sendSpinner = document.getElementById('sendSpinner');
    var sendIcon = document.getElementById('sendIcon');
    var verifyBtn = document.getElementById('verifyBtn');
    var verifySpinner = document.getElementById('verifySpinner');
    var countdown = document.getElementById('otpCountdown');
    var csrfToken = form.querySelector('input[name="csrf_token"]').value;
    var configured = form.getAttribute('data-configured') === '1';
    var expirySeconds = parseInt(form.getAttribute('data-expiry-seconds'), 10) || 300;
    var resendSeconds = parseInt(form.getAttribute('data-resend-seconds'), 10) || 60;
    var expiryTimer = null;
    var resendTimer = null;
    var resendLocked = false;
    var expired = false;
    var verified = false;

    function showAlert(type, message) {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideAlert() {
        alertBox.classList.add('d-none');
        alertBox.textContent = '';
    }

    function setLoading(button, spinner, loading) {
        if (spinner) spinner.classList.toggle('d-none', !loading);
        button.disabled = loading || resendLocked;
    }

    function formatTime(seconds) {
        var s = Math.max(0, parseInt(seconds, 10) || 0);
        var m = Math.floor(s / 60);
        var r = s % 60;
        return String(m).padStart(2, '0') + ':' + String(r).padStart(2, '0');
    }

    function stopExpiryTimer() {
        if (expiryTimer) {
            clearInterval(expiryTimer);
            expiryTimer = null;
        }
    }

    function startExpiryTimer(seconds) {
        stopExpiryTimer();
        expired = false;
        var remaining = Math.max(1, parseInt(seconds, 10) || expirySeconds);
        countdown.className = 'badge bg-success';
        countdown.textContent = formatTime(remaining);

        expiryTimer = setInterval(function () {
            remaining -= 1;
            countdown.textContent = formatTime(remaining);

            if (remaining <= 60 && remaining > 0) {
                countdown.className = 'badge bg-warning text-dark';
            }

            if (remaining <= 0) {
                clearInterval(expiryTimer);
                expiryTimer = null;
                expired = true;
                countdown.className = 'badge bg-danger';
                countdown.textContent = 'Kedaluwarsa';
                verifyBtn.disabled = true;
                showAlert('warning', 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru.');
            }
        }, 1000);
    }

    function startResendCooldown(seconds) {
        if (resendTimer) clearInterval(resendTimer);
        resendLocked = true;
        var remaining = Math.max(1, parseInt(seconds, 10) || resendSeconds);
        sendBtn.disabled = true;
        sendIcon.classList.add('d-none');

        function tick() {
            sendLabel.textContent = 'Kirim ulang (' + remaining + 's)';
            if (remaining <= 0) {
                clearInterval(resendTimer);
                resendTimer = null;
                resendLocked = false;
                sendBtn.disabled = verified || !configured;
                sendIcon.classList.remove('d-none');
                sendLabel.textContent = 'Kirim Ulang OTP';
                return;
            }
            remaining -= 1;
        }

        tick();
        resendTimer = setInterval(tick, 1000);
    }

    function validPhone(value) {
        var compact = String(value || '').replace(/[\s\-().]/g, '');
        return /^(\+?62|0)8[0-9]{7,12}$/.test(compact);
    }

    async function postForm(url, payload) {
        var body = new URLSearchParams(payload);
        var response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            body: body.toString()
        });

        var data;
        try {
            data = await response.json();
        } catch (e) {
            throw new Error('Respons server tidak valid. Silakan muat ulang halaman.');
        }

        return { status: response.status, data: data };
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        hideAlert();

        var action = event.submitter && event.submitter.id === 'verifyBtn' ? 'verify' : 'send';

        if (!configured) {
            showAlert('warning', 'Integrasi WhatsApp belum dikonfigurasi oleh operator.');
            return;
        }

        if (!validPhone(phoneInput.value)) {
            showAlert('danger', 'Format nomor WhatsApp tidak valid. Gunakan 08xxxxxxxxxx atau 628xxxxxxxxxx.');
            phoneInput.focus();
            return;
        }

        if (action === 'send') {
            sendBtn.disabled = true;
            setLoading(sendBtn, sendSpinner, true);
            sendIcon.classList.add('d-none');

            try {
                var sent = await postForm(form.getAttribute('data-send-url'), {
                    csrf_token: csrfToken,
                    phone: phoneInput.value
                });

                if (sent.data && sent.data.success) {
                    showAlert('success', sent.data.message);
                    codeSection.classList.remove('d-none');
                    codeInput.value = '';
                    codeInput.focus();
                    verifyBtn.disabled = false;
                    startExpiryTimer(sent.data.data && sent.data.data.expires_in ? sent.data.data.expires_in : expirySeconds);
                    startResendCooldown(sent.data.data && sent.data.data.resend_after ? sent.data.data.resend_after : resendSeconds);
                } else {
                    var message = (sent.data && sent.data.message) ? sent.data.message : 'Kode OTP gagal dikirim.';
                    showAlert('danger', message);
                    sendBtn.disabled = false;
                    sendIcon.classList.remove('d-none');
                    sendLabel.textContent = 'Kirim OTP';

                    if (sent.data && sent.data.data && sent.data.data.retry_after) {
                        startResendCooldown(sent.data.data.retry_after);
                    }
                }
            } catch (error) {
                showAlert('danger', error.message || 'Tidak dapat menghubungi server. Silakan coba lagi.');
                sendBtn.disabled = false;
                sendIcon.classList.remove('d-none');
                sendLabel.textContent = 'Kirim OTP';
            } finally {
                setLoading(sendBtn, sendSpinner, false);
                if (resendLocked) sendBtn.disabled = true;
            }
            return;
        }

        var codeValue = codeInput.value.replace(/\s+/g, '');
        if (!/^[0-9]{6}$/.test(codeValue)) {
            showAlert('danger', 'Masukkan kode OTP 6 digit.');
            codeInput.focus();
            return;
        }

        if (expired) {
            showAlert('warning', 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru.');
            return;
        }

        verifyBtn.disabled = true;
        setLoading(verifyBtn, verifySpinner, true);

        try {
            var verified = await postForm(form.getAttribute('data-verify-url'), {
                csrf_token: csrfToken,
                phone: phoneInput.value,
                code: codeValue
            });

            if (verified.data && verified.data.success) {
                stopExpiryTimer();
                verified = true;
                if (resendTimer) {
                    clearInterval(resendTimer);
                    resendTimer = null;
                }
                showAlert('success', verified.data.message);
                codeInput.value = '';
                verifyBtn.disabled = true;
                sendBtn.disabled = true;
                sendLabel.textContent = 'Terverifikasi';
                sendIcon.classList.add('d-none');
                phoneInput.disabled = true;
                codeInput.disabled = true;
                countdown.className = 'badge bg-success';
                countdown.textContent = 'Terverifikasi';
            } else {
                var failMessage = (verified.data && verified.data.message) ? verified.data.message : 'Verifikasi gagal.';
                showAlert('danger', failMessage);
                verifyBtn.disabled = verified.data && verified.data.error_code === 'too_many_attempts';
                codeInput.focus();
            }
        } catch (error) {
            showAlert('danger', error.message || 'Tidak dapat menghubungi server. Silakan coba lagi.');
            verifyBtn.disabled = false;
        } finally {
            if (verifySpinner) verifySpinner.classList.add('d-none');
        }
    });
})();
</script>
