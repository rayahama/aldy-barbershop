<?php
// ============================================
// Helper Functions
// ============================================

// Sanitasi output (XSS protection)
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Format harga Rupiah
function formatRupiah($angka) {
    return 'Rp' . number_format($angka, 0, ',', '.');
}

// Format tanggal Indonesia
function formatTanggal($tgl) {
    $bulan = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];
    $d = date('d', strtotime($tgl));
    $m = (int)date('m', strtotime($tgl));
    $y = date('Y', strtotime($tgl));
    return "$d $bulan[$m] $y";
}

// Format waktu
function formatWaktu($waktu) {
    return date('H:i', strtotime($waktu));
}

// Flash message
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// CSRF Token
function generateCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . e(generateCSRF()) . '">';
}

// Redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// Cek login
function checkLogin() {
    if (!isset($_SESSION['user'])) {
        redirect('login.php?msg=login_required');
    }
}

// Cek role
function checkRole($allowedRoles) {
    checkLogin();
    if (!in_array($_SESSION['user']['role'], (array)$allowedRoles)) {
        setFlash('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        redirect('login.php');
    }
}

// Badge status
function statusBadge($status) {
    $map = [
        'menunggu'     => 'warning',
        'dikonfirmasi' => 'info',
        'selesai'      => 'success',
        'ditolak'      => 'danger',
        'dibatalkan'   => 'secondary',
    ];
    $color = $map[$status] ?? 'secondary';
    $label = ucfirst($status);
    $pulse = ($status === 'menunggu') ? 'pulse-badge' : '';
    return "<span class=\"badge bg-$color $pulse\">$label</span>";
}

// Hash password dengan bcrypt
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verifikasi password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Validasi jam operasional (09:00-20:00, slot 30 menit)
function validateTimeSlot($time) {
    $hour = (int)date('H', strtotime($time));
    $min  = (int)date('i', strtotime($time));
    if ($hour < 9 || $hour >= 20) return false;
    if ($min % 30 !== 0) return false;
    return true;
}