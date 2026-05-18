<?php
require_once '../config/database.php';
require_once '../config/functions.php';

// Jika sudah login, redirect ke dashboard masing-masing
if (isset($_SESSION['user'])) {
    $role = $_SESSION['user']['role'];
    if ($role === 'customer') redirect('customer-dashboard.php');
    if ($role === 'admin') redirect('admin-dashboard.php');
    if ($role === 'owner') redirect('owner-dashboard.php');
}

// Handle GET messages
$getMsg = $_GET['msg'] ?? '';

// Handle POST login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        setFlash('error', 'Email dan password wajib diisi.');
        redirect('login.php');
    }

    // 1. CEK DI TABEL USERS (Admin & Owner) - Menggunakan MySQLi
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email); 
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close(); // Tutup statement setelah selesai

  if ($user && verifyPassword($password, $user['password'])) {
            $_SESSION['user'] = [
                'id'   => $user['id_users'],
                'name' => $user['name'],
                'email'=> $user['email'],
                'role' => $user['role'],
            ];
            $_SESSION['last_activity'] = time();
            if ($user['role'] === 'admin') redirect('admin-dashboard.php');
            if ($user['role'] === 'staff') redirect('staff-dashboard.php');
     }

    // 2. CEK DI TABEL CUSTOMERS (Pelanggan) - Disesuaikan ke MySQLi
    $stmt = $conn->prepare("SELECT * FROM customers WHERE email = ? OR phone = ?");
    $stmt->bind_param("ss", $email, $email); // Mengikat email untuk kedua placeholder ?
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close(); // Tutup statement

    if ($customer && verifyPassword($password, $customer['password'])) {
        $_SESSION['user'] = [
            'id'    => $customer['id_customers'],
            'name'  => $customer['name'],
            'email' => $customer['email'],
            'phone' => $customer['phone'],
            'role'  => 'customer',
        ];
        $_SESSION['last_activity'] = time();
        redirect('customer-dashboard.php');
    }

    // Jika kedua pengecekan di atas gagal
    setFlash('error', 'Email/Nomor HP atau password salah.');
    redirect('login.php');
}

$pageTitle = 'Login - Aldy Barbershop';
require_once '../includes/header.php';
?>

<div class="login-wrapper">
    <div class="login-bg-pattern"></div>
    <div class="container">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-11 col-sm-8 col-md-6 col-lg-5 col-xl-4">
                <div class="login-card">
                    <div class="text-center mb-4">
                        <div class="brand-logo mb-3">
                            <i class="bi bi-scissors"></i>
                        </div>
                        <h1 class="brand-title">ALDY<span class="text-accent">BARBERSHOP</span></h1>
                        <p class="text-muted small">Sistem Booking Online</p>
                    </div>

                    <form method="POST" action="" id="loginForm" novalidate>
                        <?= csrfField() ?>

                        <?php if ($getMsg === 'timeout'): ?>
                        <div class="alert alert-warning py-2 small">
                            <i class="bi bi-hourglass-split me-1"></i> Sesi Anda habis. Silakan login kembali.
                        </div>
                        <?php elseif ($getMsg === 'login_required'): ?>
                        <div class="alert alert-warning py-2 small">
                            <i class="bi bi-shield-lock me-1"></i> Silakan login terlebih dahulu.
                        </div>
                        <?php elseif ($getMsg === 'logged_out'): ?>
                        <div class="alert alert-info py-2 small">
                            <i class="bi bi-info-circle me-1"></i> Anda telah berhasil keluar.
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="email" class="form-label small fw-medium">Email / Nomor HP</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark-subtle border-secondary text-muted">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" class="form-control bg-dark-subtle border-secondary text-light" id="email" name="email"
                                       placeholder="Masukkan email atau nomor HP" required autocomplete="username">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label small fw-medium">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark-subtle border-secondary text-muted">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" class="form-control bg-dark-subtle border-secondary text-light" id="password" name="password"
                                       placeholder="Masukkan password" required autocomplete="current-password">
                                <button class="btn btn-outline-secondary border-secondary" type="button" id="togglePwd">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-accent w-100 py-2 fw-semibold mb-3">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
                        </button>

                        <div class="text-center">
                            <span class="text-muted small">Belum punya akun pelanggan?</span>
                            <a href="register.php" class="text-accent small fw-medium text-decoration-none ms-1">Daftar di sini</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>