<!-- Offcanvas Sidebar Component -->
<div class="offcanvas offcanvas-start bg-dark text-white border-end border-secondary border-opacity-25" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
    <div class="offcanvas-header border-bottom border-secondary border-opacity-25 py-3">
        <div class="d-flex align-items-center gap-2" id="appSidebarLabel">
            <div class="badge bg-primary p-2 rounded-circle"><i class="bi <?= $data['theme']['brandIcon']; ?> fs-5"></i></div>
            <h5 class="fw-bold mb-0 text-white"><?= $data['theme']['appName']; ?></h5>
        </div>
        <button type="button" class="btn-close btn-close-white text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column justify-content-between p-4">
        <div>
            <!-- Navigation Section -->
            <div class="text-uppercase text-secondary fs-7 fw-bold mb-3 tracking-wider">Navigasi Utama</div>
            <div class="list-group list-group-flush mb-4">
                <a href="<?= BASEURL; ?>" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'home') ? 'active bg-primary' : 'hover-bg-dark'; ?>">
                    <i class="bi bi-speedometer2 me-2 text-primary"></i> Dashboard Hub
                </a>
                <a href="<?= BASEURL; ?>/services" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'services') ? 'active bg-primary' : 'hover-bg-dark'; ?>">
                    <i class="bi bi-tools me-2 text-info"></i> Katalog Layanan
                </a>
                <a href="<?= BASEURL; ?>/about" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'about') ? 'active bg-primary' : 'hover-bg-dark'; ?>">
                    <i class="bi bi-diagram-3 me-2 text-warning"></i> Struktur Unit Bisnis
                </a>
            </div>

            <!-- Admin Quick Menu (If logged in as Admin) -->
            <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
                <div class="text-uppercase text-danger fs-7 fw-bold mb-3 tracking-wider"><i class="bi bi-shield-lock me-1"></i> Menu Administrator</div>
                <div class="list-group list-group-flush mb-4">
                    <a href="<?= BASEURL; ?>/admin/dashboard" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'admin_dashboard') ? 'active bg-danger text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-speedometer2 me-2 text-danger"></i> Dashboard Admin
                    </a>
                    <a href="<?= BASEURL; ?>/admin/users" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'admin_users') ? 'active bg-primary text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-people me-2 text-primary"></i> Data Pengguna
                    </a>
                    <a href="<?= BASEURL; ?>/admin/reports" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'admin_reports') ? 'active bg-success text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-graph-up-arrow me-2 text-success"></i> Laporan
                    </a>
                    <a href="<?= BASEURL; ?>/admin/chat-history" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'admin_chat_history') ? 'active bg-info text-dark' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-chat-square-text me-2 text-info"></i> Riwayat Chatbot
                    </a>
                    <a href="<?= BASEURL; ?>/admin/services" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'admin_services') ? 'active bg-warning text-dark' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-box-seam me-2 text-warning"></i> Katalog Layanan
                    </a>
                    <a href="<?= BASEURL; ?>/admin/whatsapp" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'admin_whatsapp') ? 'active bg-success text-dark' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-whatsapp me-2 text-success"></i> Notifikasi WhatsApp
                    </a>
                    <a href="<?= BASEURL; ?>/admin/customer-reports" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'admin_customer_reports') ? 'active bg-info text-dark' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-chat-square-text me-2 text-info"></i> Laporan Customer
                    </a>
                    <a href="<?= BASEURL; ?>/admin/ai-config" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'admin_ai_config') ? 'active bg-info text-dark' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-robot me-2 text-info"></i> AI Configuration
                    </a>
                </div>
            <?php endif; ?>

            <!-- Operator Quick Menu (If logged in as Operator) -->
            <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'operator'): ?>
                <div class="text-uppercase text-warning fs-7 fw-bold mb-3 tracking-wider"><i class="bi bi-sliders me-1"></i> Menu Operasional</div>
                <div class="list-group list-group-flush mb-4">
                    <a href="<?= BASEURL; ?>/operator/orders" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'operator_orders') ? 'active bg-warning text-dark' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-inbox me-2 text-warning"></i> Kelola Pesanan Masuk
                    </a>
                    <a href="<?= BASEURL; ?>/operator/technicians" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'operator_technicians') ? 'active bg-success text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-person-badge me-2 text-success"></i> Layanan Teknisi
                    </a>
                    <a href="<?= BASEURL; ?>/operator/customers" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'operator_customers') ? 'active bg-primary text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-people me-2 text-primary"></i> Kelola Data Customer
                    </a>
                    <a href="<?= BASEURL; ?>/operator/services" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'services_manage') ? 'active bg-info text-dark' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-box-seam me-2 text-info"></i> Master Layanan IT
                    </a>
                    <a href="<?= BASEURL; ?>/operator/reviews" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'operator_reviews') ? 'active bg-success text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-star me-2 text-success"></i> Semua Ulasan
                    </a>
                    <a href="<?= BASEURL; ?>/operator/ai-config" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'operator_ai_config') ? 'active bg-info text-dark' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-robot me-2 text-info"></i> Konfigurasi AI
                    </a>
                    <a href="<?= BASEURL; ?>/operator/chat-history" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'operator_chat_history') ? 'active bg-primary text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-chat-square-text me-2 text-primary"></i> Riwayat Chat & Keluhan
                    </a>
                    <a href="<?= BASEURL; ?>/operator/reports" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'operator_reports') ? 'active bg-danger text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-graph-up-arrow me-2 text-danger"></i> Laporan
                    </a>
                </div>
            <?php endif; ?>

            <!-- Customer Quick Menu -->
            <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'customer'): ?>
                <div class="text-uppercase text-primary fs-7 fw-bold mb-3 tracking-wider"><i class="bi bi-person-heart me-1"></i> Menu Customer</div>
                <div class="list-group list-group-flush mb-4">
                    <a href="<?= BASEURL; ?>/customer/dashboard" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'dashboard_customer') ? 'active bg-primary text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-speedometer2 me-2 text-primary"></i> Dashboard Customer
                    </a>
                    <a href="<?= BASEURL; ?>/customer/orders/create" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'create_order') ? 'active bg-primary text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-plus-circle me-2 text-info"></i> Buat Pesanan
                    </a>
                    <a href="<?= BASEURL; ?>/customer/orders" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'my_orders') ? 'active bg-primary text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-clock-history me-2 text-warning"></i> Riwayat Pesanan
                    </a>
                    <a href="<?= BASEURL; ?>/customer/reports" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'my_reports') ? 'active bg-primary text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-chat-square-text me-2 text-info"></i> Laporan Saya
                    </a>
                    <a href="<?= BASEURL; ?>/services" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 hover-bg-dark">
                        <i class="bi bi-grid me-2 text-success"></i> Katalog Layanan
                    </a>
                </div>
            <?php endif; ?>

            <!-- Technician Quick Menu -->
            <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'technician'): ?>
                <div class="text-uppercase text-success fs-7 fw-bold mb-3 tracking-wider"><i class="bi bi-tools me-1"></i> Menu Teknisi</div>
                <div class="list-group list-group-flush mb-4">
                    <a href="<?= BASEURL; ?>/technician/dashboard" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 <?= (isset($data['activeMenu']) && $data['activeMenu'] === 'dashboard_technician') ? 'active bg-success text-white' : 'hover-bg-dark'; ?>">
                        <i class="bi bi-speedometer2 me-2 text-success"></i> Dashboard Teknisi
                    </a>
                    <a href="<?= BASEURL; ?>/technician/dashboard#tugas-aktif" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 hover-bg-dark">
                        <i class="bi bi-clipboard-check me-2 text-primary"></i> Tugas Aktif
                    </a>
                    <a href="<?= BASEURL; ?>/technician/dashboard#riwayat" class="list-group-item list-group-item-action bg-transparent text-white border-0 rounded-3 py-2 px-3 mb-1 hover-bg-dark">
                        <i class="bi bi-clock-history me-2 text-warning"></i> Riwayat Pekerjaan
                    </a>
                </div>
            <?php endif; ?>

            <!-- Portal Roles Overview -->
            <div class="text-uppercase text-secondary fs-7 fw-bold mb-3 tracking-wider">Struktur Rencana Portal</div>
            <div class="d-flex flex-column gap-2 mb-4">
                <div class="p-2 rounded bg-dark border border-secondary border-opacity-25 d-flex align-items-center justify-content-between">
                    <span class="small"><i class="bi bi-person-heart text-primary me-2"></i> Portal Customer</span>
                    <span class="badge bg-primary bg-opacity-25 text-primary">Role 1</span>
                </div>
                <div class="p-2 rounded bg-dark border border-secondary border-opacity-25 d-flex align-items-center justify-content-between">
                    <span class="small"><i class="bi bi-tools text-success me-2"></i> Portal Teknisi Siswa</span>
                    <span class="badge bg-success bg-opacity-25 text-success">Role 2</span>
                </div>
                <div class="p-2 rounded bg-dark border border-secondary border-opacity-25 d-flex align-items-center justify-content-between">
                    <span class="small"><i class="bi bi-person-gear text-warning me-2"></i> Portal Operator</span>
                    <span class="badge bg-warning bg-opacity-25 text-warning">Role 3</span>
                </div>
                <div class="p-2 rounded bg-dark border border-secondary border-opacity-25 d-flex align-items-center justify-content-between">
                    <span class="small"><i class="bi bi-briefcase text-danger me-2"></i> Portal Kepala Sekolah</span>
                    <span class="badge bg-danger bg-opacity-25 text-danger">Role 4</span>
                </div>
            </div>
        </div>

        <!-- Sidebar Footer -->
        <div class="pt-3 border-top border-secondary border-opacity-25">
            <div class="small text-secondary mb-1"><i class="bi bi-building me-1"></i> <?= $data['theme']['schoolName']; ?></div>
            <div class="small text-secondary-50">App Version <?= APP_VERSION; ?></div>
        </div>
    </div>
</div>
