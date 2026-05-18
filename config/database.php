<?php
 $host = 'localhost';
 $user = 'root';
 $pass = '';
 $dbname = 'aldy_barbershop';


 $conn = new mysqli($host, $user, $pass, $dbname,);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Mulai session
session_start();

// ============================================
// Session Timeout 30 menit 
// ============================================
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    
    // Hapus semua data session
    session_unset();
    session_destroy();
    
    // Cek role terakhir sebelum timeout untuk redirect ke halaman yang benar
    function checkRole($allowedRoles) {
    checkLogin();
    if (!in_array($_SESSION['user']['role'], (array)$allowedRoles)) {
        setFlash('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        // Redirect sesuai role masing-masing
        $role = $_SESSION['user']['role'];
        if ($role === 'customer') redirect('customer-dashboard.php');
        if ($role === 'staff') redirect('staff-dashboard.php');
        if ($role === 'admin') redirect('admin-dashboard.php');
        redirect('login.php');
    }
}
}

// Update waktu aktivitas terakhir jika user sedang login
if (isset($_SESSION['customer_id']) || isset($_SESSION['admin_id'])) {
    $_SESSION['last_activity'] = time();
}