<?php
require_once '../config/database.php';
require_once '../config/functions.php';

checkRole('customer');

$customerId = $_SESSION['user']['id'];

// Handle POST: Booking (FR-04)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($csrf)) {
        setFlash('error', 'Permintaan tidak valid.');
        redirect('customer-dashboard.php');
    }

    if ($_POST['action'] === 'book') {
        $serviceId   = (int)($_POST['service_id'] ?? 0);
        $bookingDate = $_POST['booking_date'] ?? '';
        $bookingTime = $_POST['booking_time'] ?? '';

        // Ambil data layanan menggunakan MySQLi
        $stmt = $conn->prepare("SELECT * FROM services WHERE id_services = ?");
        $stmt->bind_param("i", $serviceId);
        $stmt->execute();
        $result = $stmt->get_result();
        $service = $result->fetch_assoc();
        $stmt->close();

        if (!$service) {
            setFlash('error', 'Layanan tidak ditemukan.');
            redirect('customer-dashboard.php');
        }

        // Validasi tanggal (tidak boleh di masa lalu)
        if (empty($bookingDate) || $bookingDate < date('Y-m-d')) {
            setFlash('error', 'Tanggal booking tidak valid.');
            redirect('customer-dashboard.php');
        }

        // Validasi waktu (FR: jam operasional 09:00-20:00, slot 30 menit)
        if (!validateTimeSlot($bookingTime)) {
            setFlash('error', 'Jam booking harus antara 09:00-19:30 dengan slot 30 menit.');
            redirect('customer-dashboard.php');
        }

        // Cek max 1 booking/hari/pelanggan menggunakan MySQLi
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE customers_id = ? AND booking_date = ? AND status NOT IN ('ditolak','dibatalkan')");
        $stmt->bind_param("is", $customerId, $bookingDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row['total'] > 0) {
            setFlash('error', 'Anda sudah memiliki booking pada tanggal ' . formatTanggal($bookingDate) . '. Maksimal 1 booking per hari.');
            redirect('customer-dashboard.php');
        }

        // Cek slot waktu tersedia menggunakan MySQLi
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE booking_date = ? AND booking_time = ? AND status NOT IN ('ditolak','dibatalkan')");
        $stmt->bind_param("ss", $bookingDate, $bookingTime);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row['total'] > 0) {
            setFlash('error', 'Slot waktu ' . formatWaktu($bookingTime) . ' pada ' . formatTanggal($bookingDate) . ' sudah dipesan.');
            redirect('customer-dashboard.php');
        }

        // Simpan booking menggunakan MySQLi
        $stmt = $conn->prepare("INSERT INTO bookings (service_name, service_price, booking_date, booking_time, status, customers_id) VALUES (?, ?, ?, ?, 'menunggu', ?)");
        $stmt->bind_param("sdssi", $service['service_name'], $service['service_price'], $bookingDate, $bookingTime, $customerId);
        $stmt->execute();
        $stmt->close();

        setFlash('success', 'Booking berhasil dibuat! Menunggu konfirmasi admin.');
        redirect('customer-dashboard.php');
    }

    // Handle POST: Batal booking (FR-05)
    if ($_POST['action'] === 'cancel') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);

        $stmt = $conn->prepare("SELECT * FROM bookings WHERE id_bookings = ? AND customers_id = ? AND status = 'menunggu'");
        $stmt->bind_param("ii", $bookingId, $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $booking = $result->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            setFlash('error', 'Booking tidak ditemukan atau tidak dapat dibatalkan.');
            redirect('customer-dashboard.php');
        }

        $stmt = $conn->prepare("UPDATE bookings SET status = 'dibatalkan' WHERE id_bookings = ?");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $stmt->close();

        setFlash('success', 'Booking berhasil dibatalkan.');
        redirect('customer-dashboard.php');
    }
}

// Ambil daftar layanan (FR-03) menggunakan MySQLi
$services = [];
$queryServices = $conn->query("SELECT * FROM services ORDER BY service_price ASC");
if ($queryServices) {
    while ($row = $queryServices->fetch_assoc()) {
        $services[] = $row;
    }
}

// Ambil riwayat booking (FR-06) menggunakan MySQLi
$bookings = [];
$stmt = $conn->prepare("
    SELECT b.*, p.status AS payment_status, p.metode_pembayaran
    FROM bookings b
    LEFT JOIN payments p ON p.booking_id = b.id_bookings
    WHERE b.customers_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();

$pageTitle = 'Dashboard Pelanggan - Aldy Barbershop';
require_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="welcome-section mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="h4 fw-bold text-light mb-1">Selamat Datang, <?= e($_SESSION['user']['name']) ?></h2>
                <p class="text-muted small mb-0"><i class="bi bi-scissors me-1"></i>Booking layanan barbershop dengan mudah</p>
            </div>
            <div class="col-auto">
                <span class="badge bg-accent fs-6 px-3 py-2 rounded-pill">
                    <i class="bi bi-star me-1"></i>Pelanggan
                </span>
            </div>
        </div>
    </div>

    <div class="section-card mb-4">
        <div class="section-header">
            <h3 class="h5 fw-bold mb-0"><i class="bi bi-scissors me-2 text-accent"></i>Daftar Layanan</h3>
        </div>
        <div class="row g-3">
            <?php foreach ($services as $svc): ?>
            <div class="col-12 col-sm-6">
                <div class="service-card h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="fw-bold mb-1"><?= e($svc['service_name']) ?></h5>
                            <p class="text-muted small mb-0">Layanan profesional Aldy Barbershop</p>
                        </div>
                        <span class="price-tag"><?= formatRupiah($svc['service_price']) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="section-card mb-4">
        <div class="section-header">
            <h3 class="h5 fw-bold mb-0"><i class="bi bi-calendar-plus me-2 text-accent"></i>Booking Layanan</h3>
        </div>
        <form method="POST" action="" id="bookingForm" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="book">

            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label for="service_id" class="form-label small fw-medium">Pilih Layanan</label>
                    <select class="form-select bg-dark-subtle border-secondary text-light" id="service_id" name="service_id" required>
                        <option value="">-- Pilih Layanan --</option>
                        <?php foreach ($services as $svc): ?>
                        <option value="<?= $svc['id_services'] ?>" data-price="<?= $svc['service_price'] ?>">
                            <?= e($svc['service_name']) ?> - <?= formatRupiah($svc['service_price']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label for="booking_date" class="form-label small fw-medium">Tanggal</label>
                    <input type="date" class="form-control bg-dark-subtle border-secondary text-light" id="booking_date" name="booking_date"
                           min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                </div>
                <div class="col-6 col-md-4">
                    <label for="booking_time" class="form-label small fw-medium">Waktu</label>
                    <input type="time" class="form-control bg-dark-subtle border-secondary text-light" id="booking_time" name="booking_time"
                           min="09:00" max="19:30" step="1800" required>
                    <small class="text-muted">Slot 30 menit (09:00 - 19:30)</small>
                </div>
                <div class="col-12">
                    <div class="alert alert-info py-2 small mb-3" id="bookingInfo" style="display:none">
                        <i class="bi bi-info-circle me-1"></i>
                        <span id="bookingInfoText"></span>
                    </div>
                    <button type="submit" class="btn btn-accent px-4 py-2 fw-semibold">
                        <i class="bi bi-check2-circle me-2"></i>Booking Sekarang
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="section-card">
        <div class="section-header">
            <h3 class="h5 fw-bold mb-0"><i class="bi bi-clock-history me-2 text-accent"></i>Riwayat Booking</h3>
            <span class="badge bg-dark-subtle text-light"><?= count($bookings) ?> booking</span>
        </div>

        <?php if (empty($bookings)): ?>
        <div class="text-center py-5">
            <i class="bi bi-calendar-x display-4 text-muted"></i>
            <p class="text-muted mt-2">Belum ada riwayat booking.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0" id="historyTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Layanan</th>
                        <th>Tanggal</th>
                        <th>Waktu</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Pembayaran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($bookings as $b): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="fw-medium"><?= e($b['service_name']) ?></td>
                        <td><?= formatTanggal($b['booking_date']) ?></td>
                        <td><?= formatWaktu($b['booking_time']) ?></td>
                        <td><?= formatRupiah($b['service_price']) ?></td>
                        <td><?= statusBadge($b['status']) ?></td>
                        <td>
                            <?php if ($b['payment_status'] === 'sudah_bayar'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i><?= ucfirst($b['metode_pembayaran']) ?></span>
                            <?php elseif ($b['status'] === 'dikonfirmasi'): ?>
                                <span class="badge bg-warning text-dark">Menunggu bayar</span>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['status'] === 'menunggu'): ?>
                            <form method="POST" action="" onsubmit="return confirm('Yakin ingin membatalkan booking ini?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="booking_id" value="<?= $b['id_bookings'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-x-circle me-1"></i>Batal
                                </button>
                            </form>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>