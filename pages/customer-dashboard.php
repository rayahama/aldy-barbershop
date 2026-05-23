<?php
require_once '../config/database.php';
require_once '../config/functions.php';

checkRole('customer');

 $customerId = $_SESSION['user']['id'];

// Ambil data pelanggan termasuk tanggal lahir
 $stmt = $conn->prepare("SELECT * FROM customers WHERE id_customers = ?");
 $stmt->bind_param("i", $customerId);
 $stmt->execute();
 $result = $stmt->get_result();
 $customer = $result->fetch_assoc();
 $stmt->close();

 $umur = hitungUmur($customer['birth_date']);

// Handle POST
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

        $stmt = $conn->prepare("SELECT * FROM services WHERE id_services = ?");
        $stmt->bind_param("i", $serviceId);
        $stmt->execute();
        $resSvc = $stmt->get_result();
        $service = $resSvc->fetch_assoc();
        $stmt->close();

        if (!$service) {
            setFlash('error', 'Layanan tidak ditemukan.');
            redirect('customer-dashboard.php');
        }

        if (empty($bookingDate) || $bookingDate < date('Y-m-d')) {
            setFlash('error', 'Tanggal booking tidak valid.');
            redirect('customer-dashboard.php');
        }

        if (!validateTimeSlot($bookingTime)) {
            setFlash('error', 'Jam booking harus antara 09:00-19:30 dengan slot 30 menit.');
            redirect('customer-dashboard.php');
        }

        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE customers_id = ? AND booking_date = ? AND status NOT IN ('ditolak','dibatalkan')");
        $stmt->bind_param("is", $customerId, $bookingDate);
        $stmt->execute();
        $resCount = $stmt->get_result();
        $rowCount = $resCount->fetch_assoc();
        $stmt->close();

        if ($rowCount['total'] > 0) {
            setFlash('error', 'Anda sudah memiliki booking pada tanggal ' . formatTanggal($bookingDate) . '. Maksimal 1 booking per hari.');
            redirect('customer-dashboard.php');
        }

        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE booking_date = ? AND booking_time = ? AND status NOT IN ('ditolak','dibatalkan')");
        $stmt->bind_param("ss", $bookingDate, $bookingTime);
        $stmt->execute();
        $resSlot = $stmt->get_result();
        $rowSlot = $resSlot->fetch_assoc();
        $stmt->close();

        if ($rowSlot['total'] > 0) {
            setFlash('error', 'Slot waktu ' . formatWaktu($bookingTime) . ' pada ' . formatTanggal($bookingDate) . ' sudah dipesan.');
            redirect('customer-dashboard.php');
        }

        $hargaFinal = hitungHarga($service['service_name'], $umur);

        $stmt = $conn->prepare("INSERT INTO bookings (service_name, service_price, booking_date, booking_time, status, customers_id) VALUES (?, ?, ?, ?, 'menunggu', ?)");
        $stmt->bind_param("sissi", $service['service_name'], $hargaFinal, $bookingDate, $bookingTime, $customerId);
        $stmt->execute();
        $stmt->close();

        setFlash('success', 'Booking berhasil dibuat! Harga: ' . formatRupiah($hargaFinal) . ' (' . ($umur < 15 ? 'Anak-anak' : 'Dewasa') . '). Menunggu konfirmasi admin.');
        redirect('customer-dashboard.php');
    }

    if ($_POST['action'] === 'cancel') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM bookings WHERE id_bookings = ? AND customers_id = ? AND status = 'menunggu'");
        $stmt->bind_param("ii", $bookingId, $customerId);
        $stmt->execute();
        $resBook = $stmt->get_result();
        $booking = $resBook->fetch_assoc();
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

// Ambil daftar layanan
 $servicesData = [];
 $resServices = $conn->query("SELECT * FROM services ORDER BY service_price ASC");
while ($s = $resServices->fetch_assoc()) {
    $servicesData[] = $s;
}

// Ambil riwayat booking
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
 $bookingsData = [];
while ($row = $result->fetch_assoc()) {
    $bookingsData[] = $row;
}
 $stmt->close();

 $pageTitle = 'Dashboard Pelanggan - Aldy Barbershop';
require_once '../includes/header.php';
?>

<div class="container py-4">
    <!-- Welcome + Info Umur -->
    <div class="welcome-section mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="h4 fw-bold text-light mb-1">Selamat Datang, <?= e($customer['name']) ?></h2>
                <p class="text-light small mb-0" style="opacity:0.8;">
                    <i class="bi bi-scissors me-1"></i>Booking layanan barbershop dengan mudah
                </p>
            </div>
            <div class="col-auto text-end">
                <?= labelHarga($umur) ?>
                <div class="small text-light mt-1" style="opacity:0.8;">Umur: <strong class="text-light"><?= $umur ?> tahun</strong></div>
            </div>
        </div>
    </div>

    <!-- Info Harga Berdasarkan Umur -->
    <div class="section-card mb-4" style="border-color: var(--accent); background: linear-gradient(135deg, rgba(201,169,110,0.08), var(--bg-card));">
        <div class="d-flex align-items-start">
            <div class="me-3" style="font-size:2rem;color:var(--accent);">
                <i class="bi bi-tag"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-2 text-light" style="color:var(--accent);font-family:var(--font-display);">Harga Khusus untuk Anda</h5>
                <p class="text-light small mb-0" style="opacity:0.9;">
                    Karena Anda berusia <strong class="text-light"><?= $umur ?> tahun</strong> (<?= $umur < 15 ? 'Anak-anak' : 'Dewasa' ?>),
                    harga layanan Anda adalah:
                </p>
                <div class="row g-2 mt-2">
                    <?php foreach ($servicesData as $svc):
                        $harga = hitungHarga($svc['service_name'], $umur);
                    ?>
                    <div class="col-6 col-sm-4">
                        <div class="p-2 rounded text-center" style="background:var(--bg-input);border:1px solid var(--border-color);">
                            <div class="text-light small"><?= e($svc['service_name']) ?></div>
                            <div class="fw-bold text-accent" style="font-size:1.1rem;"><?= formatRupiah($harga) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Daftar Layanan -->
    <div class="section-card mb-4">
        <div class="section-header">
            <h3 class="h5 fw-bold mb-0 text-light"><i class="bi bi-scissors me-2 text-accent"></i>Daftar Layanan</h3>
        </div>
        <div class="row g-3">
            <?php foreach ($servicesData as $svc):
                $harga = hitungHarga($svc['service_name'], $umur);
            ?>
            <div class="col-12 col-sm-6">
                <div class="service-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="fw-bold mb-1 text-light"><?= e($svc['service_name']) ?></h5>
                            <p class="text-light small mb-0" style="opacity:0.8;"><?= e($svc['keterangan'] ?? 'Layanan profesional Aldy Barbershop') ?></p>
                        </div>
                        <div class="text-end">
                            <span class="price-tag"><?= formatRupiah($harga) ?></span>
                            <div class="small text-light mt-1" style="opacity:0.8;"><?= $umur < 15 ? 'Harga Anak' : 'Harga Dewasa' ?></div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap" style="border-top:1px solid var(--border-color);padding-top:0.5rem;">
                        <span class="badge bg-info"><i class="bi bi-person me-1"></i>Anak &lt;15thn: <?= formatRupiah(10000) ?></span>
                        <span class="badge bg-accent"><i class="bi bi-person-standing me-1"></i>Dewasa &ge;15thn: <?= formatRupiah(15000) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Form Booking -->
    <div class="section-card mb-4">
        <div class="section-header">
            <h3 class="h5 fw-bold mb-0 text-light"><i class="bi bi-calendar-plus me-2 text-accent"></i>Booking Layanan</h3>
        </div>
        <form method="POST" action="" id="bookingForm" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="book">
            <input type="hidden" id="customerAge" value="<?= $umur ?>">

            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label for="service_id" class="form-label small fw-medium text-light">Pilih Layanan</label>
                    <select class="form-select bg-dark-subtle border-secondary text-light" id="service_id" name="service_id" required>
                        <option value="">-- Pilih Layanan --</option>
                        <?php foreach ($servicesData as $svc):
                            $harga = hitungHarga($svc['service_name'], $umur);
                        ?>
                        <option value="<?= $svc['id_services'] ?>" data-price="<?= $harga ?>">
                            <?= e($svc['service_name']) ?> - <?= formatRupiah($harga) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label for="booking_date" class="form-label small fw-medium text-light">Tanggal</label>
                    <input type="date" class="form-control bg-dark-subtle border-secondary text-light" id="booking_date" name="booking_date"
                           min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                </div>
                <div class="col-6 col-md-4">
                    <label for="booking_time" class="form-label small fw-medium text-light">Waktu</label>
                    <input type="time" class="form-control bg-dark-subtle border-secondary text-light" id="booking_time" name="booking_time"
                           min="09:00" max="19:30" step="1800" required>
                    <small class="text-light" style="opacity:0.7;">Slot 30 menit (09:00 - 19:30)</small>
                </div>
                <div class="col-12">
                    <div class="alert alert-info py-2 small mb-3" id="bookingInfo" style="display:none;background:rgba(52,152,219,0.15);border-color:rgba(52,152,219,0.3);color:#ddd;">
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

    <!-- Riwayat Booking -->
    <div class="section-card">
        <div class="section-header">
            <h3 class="h5 fw-bold mb-0 text-light"><i class="bi bi-clock-history me-2 text-accent"></i>Riwayat Booking</h3>
            <span class="badge bg-dark-subtle text-light"><?= count($bookingsData) ?> booking</span>
        </div>

        <?php if (empty($bookingsData)): ?>
        <div class="text-center py-5">
            <i class="bi bi-calendar-x display-4 text-light" style="opacity:0.3;"></i>
            <p class="text-light mt-2" style="opacity:0.7;">Belum ada riwayat booking.</p>
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
                        <th>Kategori</th>
                        <th>Status</th>
                        <th>Pembayaran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($bookingsData as $b):
                        $kategori = ($b['service_price'] <= 10000) ? 'Anak-anak' : 'Dewasa';
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="fw-medium text-light"><?= e($b['service_name']) ?></td>
                        <td class="text-light"><?= formatTanggal($b['booking_date']) ?></td>
                        <td class="text-light"><?= formatWaktu($b['booking_time']) ?></td>
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
                            <?php elseif ($b['status'] === 'dikonfirmasi'): ?>
                                <span class="badge bg-warning text-dark">Menunggu bayar</span>
                            <?php else: ?>
                                <span class="text-light small" style="opacity:0.5;">-</span>
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
                                <span class="text-light small" style="opacity:0.5;">-</span>
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