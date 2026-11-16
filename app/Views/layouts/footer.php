</main>

<footer class="bg-dark text-white pt-4 pb-3 mt-auto border-top">
    <div class="container">
        <div class="row gy-3 align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <h6 class="fw-bold mb-1 text-primary"><i class="bi bi-cpu me-1"></i> TKJ Service Hub</h6>
                <p class="text-secondary small mb-0">Platform Unit Bisnis Siswa Teknik Komputer & Jaringan (TKJ)</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="text-secondary small mb-0">&copy; <?= date('Y'); ?> TKJ Service Hub. MVC Architecture Initialized.</p>
            </div>
        </div>
    </div>
</footer>

<!-- Floating AI Assistant Component (customer and admin only) -->
<?php
$aiWidgetRole = $_SESSION['user']['role'] ?? '';
$aiCsrfToken = !empty($_SESSION['user']['id']) ? Csrf::token('ai') : '';
?>
<?php if (in_array($aiWidgetRole, ['customer', 'admin'], true)): ?>
    <?php require_once __DIR__ . '/../components/ai_widget.php'; ?>
<?php endif; ?>

<!-- Global Base URL definition for JS scripts -->
<script>
    window.BASEURL = "<?= BASEURL; ?>";
    window.AI_CSRF_TOKEN = "<?= htmlspecialchars($aiCsrfToken, ENT_QUOTES, 'UTF-8'); ?>";
</script>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom Application Script -->
<script src="<?= BASEURL; ?>/js/main.js"></script>
<!-- AI Assistant & Helper Script -->
<script src="<?= BASEURL; ?>/js/ai_assistant.js"></script>
</body>
</html>

