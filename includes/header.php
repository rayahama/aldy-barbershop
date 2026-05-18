<?php
if (session_status() === PHP_SESSION_NONE) session_start();
 $flash = getFlash();
 $csrf  = generateCSRF();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) : 'Aldy Barbershop' ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- Flash Message Toast -->
<?php if ($flash): ?>
<div id="flashToast" class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999">
    <div class="toast align-items-center text-bg-<?= $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'success' ? 'success' : 'warning') ?> border-0 show" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-<?= $flash['type'] === 'error' ? 'exclamation-circle' : ($flash['type'] === 'success' ? 'check-circle' : 'info-circle') ?> me-2"></i>
                <?= e($flash['message']) ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<?php endif; ?>
<!-- Navbar (hanya tampil jika sudah login) -->
<?php if (isset($_SESSION['user'])): ?>
<?php
    // Tentukan URL dashboard berdasarkan role
    $dashboardUrl = 'customer-dashboard.php';
    if ($_SESSION['user']['role'] === 'admin') $dashboardUrl = 'admin-dashboard.php';
    if ($_SESSION['user']['role'] === 'staff') $dashboardUrl = 'staff-dashboard.php';
?>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" id="mainNav">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?= $dashboardUrl ?>">
            <i class="bi bi-scissors me-2 brand-icon"></i>
            <span class="brand-text">ALDY<span class="brand-accent">BARBERSHOP</span></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="../index.php">
                        <i class="bi bi-house me-1"></i>Beranda
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $dashboardUrl ?>">
                        <i class="bi bi-grid me-1"></i>Dashboard
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav align-items-center">
                <li class="nav-item me-3 d-none d-lg-block">
                    <span class="text-light opacity-75">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= e($_SESSION['user']['name']) ?>
                    </span>
                    <span class="badge bg-accent ms-1"><?= ucfirst($_SESSION['user']['role']) ?></span>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="btn btn-outline-accent btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i> Keluar
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>