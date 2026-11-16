<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $data['theme']['tagline']; ?>">
    <title><?= isset($data['title']) ? $data['title'] : $data['theme']['appName']; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Theme CSS Variables Injection -->
    <style>
        <?= $data['theme']['cssVars']; ?>
    </style>
    
    <!-- Application Custom CSS -->
    <link rel="stylesheet" href="<?= BASEURL; ?>/css/style.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

    <!-- Top Navbar Component -->
    <?php require_once __DIR__ . '/../components/navbar.php'; ?>

    <!-- Offcanvas Sidebar Component -->
    <?php require_once __DIR__ . '/../components/sidebar.php'; ?>

    <!-- Main Content Container -->
    <main class="flex-grow-1">
        <!-- Flash Alert Component Container -->
        <div class="container mt-3">
            <?php require_once __DIR__ . '/../components/flash.php'; ?>
        </div>

        <!-- Dynamic Content View Injection -->
        <?php 
        if (isset($data['contentView'])) {
            require_once __DIR__ . '/../' . $data['contentView'] . '.php';
        }
        ?>
    </main>

    <!-- Footer Component -->
    <?php require_once __DIR__ . '/../components/footer.php'; ?>

    <?php
    $aiLayoutRole = $_SESSION['user']['role'] ?? '';
    $aiLayoutCsrfToken = !empty($_SESSION['user']['id']) ? Csrf::token('ai') : '';
    ?>
    <?php if (in_array($aiLayoutRole, ['customer', 'admin'], true)): ?>
        <!-- Floating AI Assistant: customer and admin only -->
        <?php require_once __DIR__ . '/../components/ai_widget.php'; ?>
    <?php endif; ?>

    <!-- Global Base URL + AI CSRF token for JavaScript -->
    <script>
        window.BASEURL = "<?= BASEURL; ?>";
        window.AI_CSRF_TOKEN = "<?= htmlspecialchars($aiLayoutCsrfToken, ENT_QUOTES, 'UTF-8'); ?>";
    </script>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom Application JS -->
    <script src="<?= BASEURL; ?>/js/main.js"></script>
    <!-- AI Assistant & Helper Script -->
    <script src="<?= BASEURL; ?>/js/ai_assistant.js"></script>
</body>
</html>
