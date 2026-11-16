<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm border-bottom border-primary border-3">
    <div class="container-fluid px-lg-4">
        <!-- Sidebar Toggle Button -->
        <button class="btn btn-outline-light border-0 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" title="Buka Menu Sidebar">
            <i class="bi bi-list fs-4"></i>
        </button>

        <!-- Brand Title & Icon -->
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold me-auto me-lg-4" href="<?= BASEURL; ?>">
            <span class="badge bg-primary rounded-pill p-2"><i class="bi <?= $data['theme']['brandIcon']; ?> fs-5"></i></span>
            <span><?= $data['theme']['brandLogo']; ?></span>
        </a>

        <!-- Mobile Toggler -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMainCollapse">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar Links -->
        <div class="collapse navbar-collapse" id="navbarMainCollapse">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'home') ? 'active fw-bold text-primary' : ''; ?>" href="<?= BASEURL; ?>">
                        <i class="bi bi-house-door me-1"></i> Beranda
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'services') ? 'active fw-bold text-primary' : ''; ?>" href="<?= BASEURL; ?>/services">
                        <i class="bi bi-tools me-1"></i> Layanan Jasa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'about') ? 'active fw-bold text-primary' : ''; ?>" href="<?= BASEURL; ?>/about">
                        <i class="bi bi-info-circle me-1"></i> Tentang Hub
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'contact') ? 'active fw-bold text-primary' : ''; ?>" href="<?= BASEURL; ?>/contact">
                        <i class="bi bi-envelope me-1"></i> Kontak
                    </a>
                </li>
            </ul>

            <!-- Dynamic Session / Auth Buttons -->
            <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
                <?php if (isset($_SESSION['user']) && !empty($_SESSION['user']['id'])): ?>
                    <?php
                    $role = $_SESSION['user']['role'];
                    $dashboardUrl = BASEURL . '/' . $role . '/dashboard';
                    $roleBadgeClass = 'bg-primary';
                    if ($role === 'technician') $roleBadgeClass = 'bg-success';
                    if ($role === 'operator') $roleBadgeClass = 'bg-warning text-dark';
                    if ($role === 'principal') $roleBadgeClass = 'bg-danger';
                    if ($role === 'admin') $roleBadgeClass = 'bg-info text-dark';
                    ?>
                    <div class="dropdown me-1">
                        <button class="btn btn-outline-light rounded-circle position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifikasi">
                            <i class="bi bi-bell fs-5"></i>
                            <?php if ((int) ($data['unreadNotificationCount'] ?? 0) > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= (int) $data['unreadNotificationCount']; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2 p-0" style="width: 340px; max-width: 90vw;">
                            <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                                <span class="fw-bold small"><i class="bi bi-bell me-1 text-primary"></i> Notifikasi</span>
                                <?php if (!empty($data['notifications'])): ?>
                                    <form action="<?= BASEURL; ?>/notifications/read-all" method="POST" class="m-0">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token('notification')); ?>">
                                        <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none small">Tandai dibaca</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div style="max-height: 320px; overflow-y: auto;">
                                <?php if (!empty($data['notifications'])): ?>
                                    <?php foreach ($data['notifications'] as $notification): ?>
                                        <?php
                                        $notifLink = (string) ($notification['link'] ?? '');
                                        if ($notifLink !== '' && $notifLink[0] === '/') {
                                            $notifLink = BASEURL . $notifLink;
                                        }
                                        ?>
                                        <a class="dropdown-item py-2 px-3 border-bottom <?= empty($notification['is_read']) ? 'bg-primary bg-opacity-10' : ''; ?>"
                                           href="<?= htmlspecialchars($notifLink !== '' ? $notifLink : BASEURL . '/'); ?>">
                                            <div class="fw-semibold small"><?= htmlspecialchars((string) $notification['title']); ?></div>
                                            <div class="text-muted" style="font-size: .78rem;"><?= htmlspecialchars((string) $notification['message']); ?></div>
                                            <div class="text-secondary mt-1" style="font-size: .7rem;">
                                                <?= htmlspecialchars(date('d M Y H:i', strtotime((string) $notification['created_at']))); ?>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted small py-4">
                                        <i class="bi bi-bell-slash fs-3 d-block mb-2 opacity-50"></i>
                                        Belum ada notifikasi.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle rounded-pill px-3 py-1.5 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle fs-5 text-primary"></i>
                            <span class="fw-semibold small"><?= htmlspecialchars($_SESSION['user']['name']); ?></span>
                            <span class="badge <?= $roleBadgeClass; ?> small text-capitalize"><?= $role; ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2">
                            <li><h6 class="dropdown-header text-uppercase small">Role: <?= $role; ?></h6></li>
                            <li><a class="dropdown-item" href="<?= $dashboardUrl; ?>"><i class="bi bi-speedometer2 me-2 text-primary"></i> Dashboard Saya</a></li>
                            <li><a class="dropdown-item" href="<?= BASEURL; ?>/profile"><i class="bi bi-person-gear me-2 text-secondary"></i> Profil & Password</a></li>
                            <li><a class="dropdown-item" href="<?= BASEURL; ?>/otp"><i class="bi bi-whatsapp me-2 text-success"></i> Verifikasi WhatsApp</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASEURL; ?>/auth/logout"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= BASEURL; ?>/auth/login" class="btn btn-outline-light rounded-pill px-3 py-1.5 small fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Login
                    </a>
                    <a href="<?= BASEURL; ?>/auth/register" class="btn btn-primary rounded-pill px-3 py-1.5 small fw-bold shadow-sm">
                        <i class="bi bi-person-plus me-1"></i> Register Customer
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
