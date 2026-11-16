<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TKJ Service Hub - Platform Layanan Jasa IT, Service Komputer, CCTV & Jaringan Unit Bisnis Siswa TKJ">
    <title><?= isset($data['title']) ? $data['title'] : 'TKJ Service Hub'; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?= BASEURL; ?>/css/style.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<!-- Navbar Header -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm border-bottom border-primary border-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= BASEURL; ?>">
            <span class="badge bg-primary rounded-pill p-2"><i class="bi bi-cpu fs-5"></i></span>
            <span>TKJ <span class="text-primary">Service Hub</span></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-2 mt-3 mt-lg-0">
                <li class="nav-item">
                    <a class="nav-link active fw-medium" href="<?= BASEURL; ?>"><i class="bi bi-house-door me-1"></i> Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-light-50" href="#services"><i class="bi bi-tools me-1"></i> Layanan</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-light-50" href="#roles"><i class="bi bi-people me-1"></i> Akses Portal</a>
                </li>
                <li class="nav-item ms-lg-2">
                    <span class="badge text-bg-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Dev Environment Ready</span>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="flex-grow-1">
