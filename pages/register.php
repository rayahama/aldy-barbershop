<?php
require_once '../config/database.php';
require_once '../config/functions.php';

// Jika sudah login, redirect
if (isset($_SESSION['user'])) {
    redirect('customer-dashboard.php');
}

// Handle POST registrasi (FR-01)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($csrf)) {
        setFlash('error', 'Permintaan tidak valid.');
        redirect('register.php');
    }

    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validasi
    $errors = [];
    if (strlen($name) < 3) $errors[] = 'Nama minimal 3 karakter.';
    if (!preg_match('/^[0-9+\-\s]{10,20}$/', $phone)) $errors[] = 'Nomor HP tidak valid (10-20 digit).';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';
    if ($password !== $confirm) $errors[] = 'Konfirmasi password tidak cocok.';

    // Cek duplikat phone
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id_customers FROM customers WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) $errors[] = 'Nomor HP sudah terdaftar.';
    }

    // Cek duplikat email
    if (empty($errors) && !empty($email)) {
        $stmt = $conn->prepare("SELECT id_customers FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = 'Email sudah terdaftar.';
    }

    if (!empty($errors)) {
        setFlash('error', implode('<br>', $errors));
        redirect('register.php');
    }

    // Simpan ke database
    $hashed = hashPassword($password);
    $stmt = $conn->prepare("INSERT INTO customers (name, phone, email, password) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $email ?: null, $hashed]);

    setFlash('success', 'Registrasi berhasil! Silakan login.');
    redirect('login.php');
}

$pageTitle = 'Registrasi - Aldy Barbershop';
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
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <h2 class="h4 fw-bold text-light">Daftar Akun</h2>
                        <p class="text-muted small">Buat akun untuk booking layanan</p>
                    </div>

                    <form method="POST" action="" id="registerForm" novalidate>
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="name" class="form-label small fw-medium">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-dark-subtle border-secondary text-light" id="name" name="name"
                                   placeholder="Masukkan nama" required minlength="3">
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label small fw-medium">Nomor HP <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control bg-dark-subtle border-secondary text-light" id="phone" name="phone"
                                   placeholder="08" required>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label small fw-medium">Tanggal Lahir <span class="text-danger">*</span></label>
                           <input type="date" class="form-control bg-dark-subtle border-secondary text-light" id="birth" name="birth_date" max="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label small fw-medium">Email <span class="text-muted">(opsional)</span></label>
                            <input type="email" class="form-control bg-dark-subtle border-secondary text-light" id="email" name="email"
                                   placeholder="nama@gmail.com">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label small fw-medium">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control bg-dark-subtle border-secondary text-light" id="password" name="password"
                                   placeholder="Minimal 6 karakter" required minlength="6">
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label small fw-medium">Konfirmasi Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control bg-dark-subtle border-secondary text-light" id="confirm_password" name="confirm_password"
                                   placeholder="Ulangi password" required>
                        </div>

                        <button type="submit" class="btn btn-accent w-100 py-2 fw-semibold mb-3">
                            <i class="bi bi-check-circle me-2"></i>Daftar Sekarang
                        </button>

                        <div class="text-center">
                            <span class="text-muted small">Sudah punya akun?</span>
                            <a href="login.php" class="text-accent small fw-medium text-decoration-none ms-1">Login di sini</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>