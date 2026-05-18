<?php
require_once '../config/database.php';
require_once '../config/functions.php';

checkRole('admin');

 $adminId = $_SESSION['user']['id'];

// ============================================
// HANDLE POST ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($csrf)) {
        setFlash('error', 'Permintaan tidak valid.');
        redirect('admin-dashboard.php');
    }

    $action = $_POST['action'] ?? '';

    // Konfirmasi booking
    if ($action === 'confirm') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE bookings SET status = 'dikonfirmasi', users_id = ? WHERE id_bookings = ? AND status = 'menunggu'");
        $stmt->bind_param("ii", $adminId, $bookingId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            setFlash('success', 'Booking berhasil dikonfirmasi.');
        } else {
            setFlash('error', 'Booking tidak ditemukan atau status tidak valid.');
        }
        $stmt->close();
        redirect('admin-dashboard.php');
    }

    // Tolak booking
    if ($action === 'reject') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE bookings SET status = 'ditolak', users_id = ? WHERE id_bookings = ? AND status = 'menunggu'");
        $stmt->bind_param("ii", $adminId, $bookingId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            setFlash('success', 'Booking berhasil ditolak.');
        } else {
            setFlash('error', 'Booking tidak ditemukan atau status tidak valid.');
        }
        $stmt->close();
        redirect('admin-dashboard.php');
    }

    // Catat pembayaran (CASH ONLY + HARGA TERKUNCI SESUAI UMUR)
    if ($action === 'pay') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $metode    = 'cash';
        $total     = (int)($_POST['total'] ?? 0);

        if ($total <= 0) {
            setFlash('error', 'Total bayar harus lebih dari 0.');
            redirect('admin-dashboard.php');
        }

        // Keamanan: pastikan harga cocok dengan database
        $stmtCheck = $conn->prepare("SELECT service_price FROM bookings WHERE id_bookings = ?");
        $stmtCheck->bind_param("i", $bookingId);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        $priceCheck = $resCheck->fetch_assoc();
        $stmtCheck->close();

        if (!$priceCheck || $total !== (int)$priceCheck['service_price']) {
            setFlash('error', 'Total bayar tidak sesuai dengan harga booking.');
            redirect('admin-dashboard.php');
        }

        // Cek booking dikonfirmasi
        $stmt = $conn->prepare("SELECT id_bookings FROM bookings WHERE id_bookings = ? AND status = 'dikonfirmasi'");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            setFlash('error', 'Booking tidak ditemukan atau belum dikonfirmasi.');
            redirect('admin-dashboard.php');
        }

        // Cek apakah sudah ada pembayaran
        $stmt = $conn->prepare("SELECT id_payments FROM payments WHERE booking_id = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $result = $stmt->get_result();
        $paymentExists = $result->fetch_assoc();
        $stmt->close();

        if ($paymentExists) {
            setFlash('error', 'Pembayaran untuk booking ini sudah dicatat.');
            redirect('admin-dashboard.php');
        }

        // Insert pembayaran
        $stmt = $conn->prepare("INSERT INTO payments (total, metode_pembayaran, status, booking_id) VALUES (?, ?, 'sudah_bayar', ?)");
        $stmt->bind_param("isi", $total, $metode, $bookingId);
        $stmt->execute();
        $stmt->close();

        setFlash('success', 'Pembayaran cash berhasil dicatat.');
        redirect('admin-dashboard.php');
    }

    // Tambah layanan
    if ($action === 'add_service') {
        $name  = trim($_POST['service_name'] ?? '');
        $price = (int)($_POST['service_price'] ?? 0);
        if (empty($name) || $price <= 0) {
            setFlash('error', 'Nama layanan dan harga wajib diisi.');
            redirect('admin-dashboard.php');
        }
        $stmt = $conn->prepare("INSERT INTO services (service_name, service_price) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $price);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Layanan berhasil ditambahkan.');
        redirect('admin-dashboard.php');
    }

    // Edit layanan
    if ($action === 'edit_service') {
        $id    = (int)($_POST['service_id'] ?? 0);
        $name  = trim($_POST['service_name'] ?? '');
        $price = (int)($_POST['service_price'] ?? 0);
        if (empty($name) || $price <= 0) {
            setFlash('error', 'Nama layanan dan harga wajib diisi.');
            redirect('admin-dashboard.php');
        }
        $stmt = $conn->prepare("UPDATE services SET service_name = ?, service_price = ? WHERE id_services = ?");
        $stmt->bind_param("sii", $name, $price, $id);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Layanan berhasil diperbarui.');
        redirect('admin-dashboard.php');
    }

    // Hapus layanan
    if ($action === 'delete_service') {
        $id = (int)($_POST['service_id'] ?? 0);
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE service_name = (SELECT service_name FROM services WHERE id_services = ?) AND status NOT IN ('ditolak','dibatalkan')");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if ($row['total'] > 0) {
            setFlash('error', 'Layanan tidak bisa dihapus karena sedang digunakan dalam booking aktif.');
            redirect('admin-dashboard.php');
        }
        $stmt = $conn->prepare("DELETE FROM services WHERE id_services = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Layanan berhasil dihapus.');
        redirect('admin-dashboard.php');
    }
}

// ============================================
// AMBIL DATA UNTUK TAMPILAN
// ============================================

// Semua booking
 $allBookings = $conn->query("
    SELECT b.*, c.name AS customer_name, c.phone AS customer_phone, c.birth_date, u.name AS admin_name,
           p.id_payments, p.status AS payment_status, p.metode_pembayaran
    FROM bookings b
    LEFT JOIN customers c ON c.id_customers = b.customers_id
    LEFT JOIN users u ON u.id_users = b.users_id
    LEFT JOIN payments p ON p.booking_id = b.id_bookings
    ORDER BY b.created_at DESC
");

// Semua pembayaran
 $allPayments = $conn->query("
    SELECT p.*, b.service_name, b.booking_date, b.booking_time, c.name AS customer_name
    FROM payments p
    LEFT JOIN bookings b ON b.id_bookings = p.booking_id
    LEFT JOIN customers c ON c.id_customers = b.customers_id
    ORDER BY p.created_at DESC
");

// Semua layanan
 $allServices = $conn->query("SELECT * FROM services ORDER BY service_price ASC");

// Statistik
 $stats = ['menunggu' => 0, 'dikonfirmasi' => 0, 'selesai' => 0, 'ditolak' => 0, 'dibatalkan' => 0, 'total' => 0]; $bookingsData = [];
while ($b = $allBookings->fetch_assoc()) {
    $bookingsData[] = $b;
    $stats['total']++;
    if (isset($stats[$b['status']])) $stats[$b['status']]++;
}

 $paymentsData = [];
while ($p = $allPayments->fetch_assoc()) {
    $paymentsData[] = $p;
}

 $servicesData = [];
while ($s = $allServices->fetch_assoc()) {
    $servicesData[] = $s;
}

 $pageTitle = 'Dashboard Admin - Aldy Barbershop';
require_once '../includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="welcome-section mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="h4 fw-bold text-light mb-1">Dashboard Admin</h2>
                <p class="text-muted small mb-0"><i class="bi bi-shield-check me-1"></i>Kelola booking, pembayaran, dan layanan</p>
            </div>
        </div>
    </div>

    <!-- Statistik Ringkas -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-warning bg-opacity-25 text-warning"><i class="bi bi-hourglass"></i></div>
                <div class="stat-value"><?= $stats['menunggu'] ?></div>
                <div class="stat-label">Menunggu</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-info bg-opacity-25 text-info"><i class="bi bi-check-circle"></i></div>
                <div class="stat-value"><?= $stats['dikonfirmasi'] ?></div>
                <div class="stat-label">Dikonfirmasi</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="col-6 col-lg-3">
            <div class="stat-card">
               <div class="stat-icon bg-success bg-opacity-25 text-success"><i class="bi bi-check2-all"></i></div>
               <div class="stat-value"><?= $stats['selesai'] ?></div>
               <div class="stat-label">Selesai</div>
            </div>
        </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-secondary bg-opacity-25 text-secondary"><i class="bi bi-dash-circle"></i></div>
                <div class="stat-value"><?= $stats['dibatalkan'] ?></div>
                <div class="stat-label">Dibatalkan</div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs nav-custom mb-4" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookingsPane" type="button" role="tab">
                <i class="bi bi-calendar-check me-1"></i>Booking
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#paymentsPane" type="button" role="tab">
                <i class="bi bi-cash me-1"></i>Pembayaran
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="services-tab" data-bs-toggle="tab" data-bs-target="#servicesPane" type="button" role="tab">
                <i class="bi bi-scissors me-1"></i>Layanan
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabContent">

        <!-- ==================== TAB 1: BOOKING ==================== -->
        <div class="tab-pane fade show active" id="bookingsPane" role="tabpanel">
            <div class="section-card">
                <div class="section-header">
                    <h3 class="h5 fw-bold mb-0">Semua Booking</h3>
                    <span class="badge bg-dark-subtle text-light"><?= $stats['total'] ?> total</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0" id="bookingsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pelanggan</th>
                                <th>Umur</th>
                                <th>Layanan</th>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>Harga</th>
                                <th>Kategori</th>
                                <th>Status</th>
                                <th>Bayar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($bookingsData as $b):
                                $custAge = 0;
                                if ($b['customers_id'] && $b['birth_date']) {
                                    $custAge = hitungUmur($b['birth_date']);
                                }
                                $kategori = ($b['service_price'] <= 10000) ? 'Anak-anak' : 'Dewasa';
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <div class="fw-medium"><?= e($b['customer_name'] ?? 'Unknown') ?></div>
                                    <small class="text-muted"><?= e($b['customer_phone'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <?= $custAge ?> thn
                                    <?php if ($custAge > 0): ?>
                                        <?= $custAge < 15 ? '<span class="badge bg-info ms-1">Anak</span>' : '<span class="badge bg-accent ms-1">Dewasa</span>' ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($b['service_name']) ?></td>
                                <td><?= formatTanggal($b['booking_date']) ?></td>
                                <td><?= formatWaktu($b['booking_time']) ?></td>
                                <td class="text-accent fw-semibold"><?= formatRupiah($b['service_price']) ?></td>
                                <td>
                                    <?php if ($kategori === 'Anak-anak'): ?>
                                        <span class="badge bg-info">Anak</span>
                                    <?php else: ?>
                                        <span class="badge bg-accent">Dewasa</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= statusBadge($b['status']) ?></td>
                                <td>
                                    <?php if ($b['payment_status'] === 'sudah_bayar'): ?>
                                        <span class="badge bg-success"><i class="bi bi-cash-stack me-1"></i>Cash</span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($b['status'] === 'menunggu'): ?>
                                    <div class="btn-group btn-group-sm">
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="confirm">
                                            <input type="hidden" name="booking_id" value="<?= $b['id_bookings'] ?>">
                                            <button type="submit" class="btn btn-outline-success" title="Konfirmasi">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Yakin menolak booking ini?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="booking_id" value="<?= $b['id_bookings'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Tolak">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <?php elseif ($b['status'] === 'dikonfirmasi' && !$b['id_payments']): ?>
                                    <?php
                                        $katLabel = ($b['service_price'] <= 10000) ? 'Anak-anak (< 15 thn)' : 'Dewasa (>= 15 thn)';
                                        $katBadge = ($b['service_price'] <= 10000) ? 'info' : 'accent';
                                    ?>
                                    <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal"
                                            data-bs-target="#paymentModal"
                                            data-booking-id="<?= $b['id_bookings'] ?>"
                                            data-customer="<?= e($b['customer_name']) ?>"
                                            data-service="<?= e($b['service_name']) ?>"
                                            data-price="<?= $b['service_price'] ?>"
                                            data-age="<?= $custAge ?>"
                                            data-kategori="<?= $katLabel ?>"
                                            data-katbadge="<?= $katBadge ?>"
                                            title="Catat Pembayaran">
                                        <i class="bi bi-cash-coin me-1"></i>Bayar
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 2: PEMBAYARAN ==================== -->
        <div class="tab-pane fade" id="paymentsPane" role="tabpanel">
            <div class="section-card mb-4">
                <div class="section-header">
                    <h3 class="h5 fw-bold mb-0">Riwayat Pembayaran Cash</h3>
                    <span class="badge bg-success"><?= count($paymentsData) ?> transaksi</span>
                </div>
                <?php if (empty($paymentsData)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-cash-coin display-4 text-muted"></i>
                    <p class="text-muted mt-2">Belum ada pembayaran tercatat.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0" id="paymentsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pelanggan</th>
                                <th>Layanan</th>
                                <th>Tanggal Booking</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Tgl Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($paymentsData as $p): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= e($p['customer_name'] ?? '-') ?></td>
                                <td><?= e($p['service_name']) ?></td>
                                <td><?= formatTanggal($p['booking_date']) ?></td>
                                <td class="fw-semibold text-accent"><?= formatRupiah($p['total']) ?></td>
                                <td><span class="badge bg-success"><?= ucfirst($p['status']) ?></span></td>
                                <td><?= $p['created_at'] ? formatTanggal($p['created_at']) . ' ' . date('H:i', strtotime($p['created_at'])) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==================== TAB 3: LAYANAN ==================== -->
        <div class="tab-pane fade" id="servicesPane" role="tabpanel">
            <div class="section-card">
                <div class="section-header">
                    <h3 class="h5 fw-bold mb-0">Kelola Layanan</h3>
                    <button type="button" class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#serviceModal" data-mode="add">
                        <i class="bi bi-plus-lg me-1"></i>Tambah Layanan
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0" id="servicesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama Layanan</th>
                                <th>Harga Dasar</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($servicesData as $s): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-medium"><?= e($s['service_name']) ?></td>
                                <td class="text-accent fw-semibold"><?= formatRupiah($s['service_price']) ?></td>
                                <td>
                                    <span class="badge bg-info me-1">Anak</span><?= formatRupiah(10000) ?>
                                    <span class="mx-1 text-muted">|</span>
                                    <span class="badge bg-accent me-1">Dewasa</span><?= formatRupiah(15000) ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#serviceModal"
                                                data-mode="edit"
                                                data-id="<?= $s['id_services'] ?>"
                                                data-name="<?= e($s['service_name']) ?>"
                                                data-price="<?= $s['service_price'] ?>"
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Yakin menghapus layanan ini?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_service">
                                            <input type="hidden" name="service_id" value="<?= $s['id_services'] ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- end tab-content -->
</div>

<!-- ==================== MODAL: CATAT PEMBAYARAN ==================== -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="bi bi-cash-coin me-2 text-accent"></i>Catat Pembayaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="pay">
                <input type="hidden" name="booking_id" id="payBookingId">
                <input type="hidden" name="metode_pembayaran" value="cash">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Pelanggan</label>
                        <div class="fw-medium" id="payCustomer">-</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Layanan</label>
                        <div class="fw-medium" id="payService">-</div>
                    </div>
                    <div class="mb-3 p-3 rounded" style="background:var(--bg-input);border:1px solid var(--border-color);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="small text-muted">Umur Pelanggan</div>
                                <div class="fw-bold text-light fs-5" id="payAge">-</div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-info fs-6 px-3 py-2" id="payKategori">-</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="payTotal" class="form-label small fw-medium">Total Bayar</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark-subtle border-secondary text-accent fw-bold">Rp</span>
                            <input type="text" class="form-control bg-dark-subtle border-secondary text-light fw-bold fs-5" id="payTotal" readonly
                                   style="cursor:not-allowed;background:rgba(201,169,110,0.08);">
                            <input type="hidden" id="payTotalHidden" name="total">
                        </div>
                        <small class="text-muted"><i class="bi bi-lock me-1"></i>Harga otomatis sesuai umur, tidak bisa diubah</small>
                    </div>
                    <div class="p-3 rounded d-flex align-items-center gap-2" style="background:rgba(46,204,113,0.1);border:1px solid rgba(46,204,113,0.3);">
                        <i class="bi bi-cash-stack text-success" style="font-size:1.5rem;"></i>
                        <div>
                            <div class="fw-semibold text-success">Pembayaran Tunai (Cash)</div>
                            <small class="text-muted">Metode pembayaran yang digunakan</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-accent"><i class="bi bi-check-circle me-1"></i>Konfirmasi Cash</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== MODAL: TAMBAH/EDIT LAYANAN ==================== -->
<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="serviceModalTitle"><i class="bi bi-scissors me-2 text-accent"></i>Tambah Layanan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add_service" id="serviceAction">
                <input type="hidden" name="service_id" id="serviceEditId" value="0">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="svcName" class="form-label small fw-medium">Nama Layanan</label>
                        <input type="text" class="form-control bg-dark-subtle border-secondary text-light" id="svcName" name="service_name" required>
                    </div>
                    <div class="mb-0">
                        <label for="svcPrice" class="form-label small fw-medium">Harga Dasar (Rp)</label>
                        <input type="number" class="form-control bg-dark-subtle border-secondary text-light" id="svcPrice" name="service_price" required min="0">
                        <small class="text-muted">Harga aktual akan disesuaikan otomatis: Anak Rp10.000, Dewasa Rp15.000</small>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-accent" id="serviceSubmitBtn"><i class="bi bi-check-circle me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== JAVASCRIPT KHUSUS ADMIN ==================== -->
<script>
// Payment Modal: isi data termasuk umur & harga terkunci
const paymentModal = document.getElementById('paymentModal');
if (paymentModal) {
    paymentModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        if (!button) return;

        document.getElementById('payBookingId').value = button.dataset.bookingId;
        document.getElementById('payCustomer').textContent = button.dataset.customer;
        document.getElementById('payService').textContent = button.dataset.service;

        // Umur
        const age = button.dataset.age;
        document.getElementById('payAge').textContent = age + ' tahun';

        // Badge kategori
        const katBadge = document.getElementById('payKategori');
        katBadge.textContent = button.dataset.kategori;
        if (button.dataset.katbadge === 'info') {
            katBadge.className = 'badge bg-info fs-6 px-3 py-2';
        } else {
            katBadge.className = 'badge bg-accent fs-6 px-3 py-2';
        }

        // Harga terkunci
        const price = parseInt(button.dataset.price);
        const formatted = new Intl.NumberFormat('id-ID').format(price);
        document.getElementById('payTotal').value = formatted;
        document.getElementById('payTotalHidden').value = price;
    });
}

// Service Modal: mode tambah / edit
const serviceModal = document.getElementById('serviceModal');
if (serviceModal) {
    serviceModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const mode = button ? button.dataset.mode : 'add';
        const title = document.getElementById('serviceModalTitle');
        const action = document.getElementById('serviceAction');
        const editId = document.getElementById('serviceEditId');
        const nameInput = document.getElementById('svcName');
        const priceInput = document.getElementById('svcPrice');

        if (mode === 'edit') {
            title.innerHTML = '<i class="bi bi-pencil me-2 text-accent"></i>Edit Layanan';
            action.value = 'edit_service';
            editId.value = button.dataset.id;
            nameInput.value = button.dataset.name;
            priceInput.value = button.dataset.price;
        } else {
            title.innerHTML = '<i class="bi bi-scissors me-2 text-accent"></i>Tambah Layanan';
            action.value = 'add_service';
            editId.value = '0';
            nameInput.value = '';
            priceInput.value = '';
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>