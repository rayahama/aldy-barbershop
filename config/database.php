<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'aldy_barbershop';
$port = 3307;

// 1. Koneksi database (Koma berlebih di ujung sudah dihapus agar tidak error)
$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Mulai session
session_start();

// ============================================
// 2. SESSION TIMEOUT 30 MENIT
// ============================================
if (isset($_SESSION['last_activity'])) {
    // Jika waktu diam user lebih dari 1800 detik (30 menit)
    if ((time() - $_SESSION['last_activity']) > 1800) {
        session_unset();
        session_destroy();
        
        // Lempar langsung ke halaman login dengan pesan timeout
        header("Location: login.php?timeout=1");
        exit();
    }
}

// 3. Update waktu aktivitas terakhir jika user sudah login
if (isset($_SESSION['user'])) {
    $_SESSION['last_activity'] = time();
}


