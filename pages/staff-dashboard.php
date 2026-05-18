<?php
require_once '../config/database.php';
require_once '../config/functions.php';

checkRole('staff');

 $staffId = $_SESSION['user']['id'];
 $today = date('Y-m-d');

// Handle POST: Tandai selesai
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($csrf)) {
        setFlash('error', 'Permintaan tidak valid.');
        redirect('staff-dashboard.php');
    }

    if ($_POST['action'] === 'done') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE bookings SET status = 'selesai', users_id = ? WHERE id_bookings = ? AND status = 'dikonfirmasi'");
        $stmt->bind_param("ii", $staffId, $bookingId);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            setFlash('success', 'Booking ditandai selesai.');
        } else {
            setFlash('error', 'Booking tidak ditemukan atau status tidak valid.');
        }
        $stmt->close();
        redirect('staff-dashboard.php');
    }
}

// Ambil booking hari ini yang dikonfirmasi
 $stmt = $conn->prepare("
    SELECT b.*, c.name AS customer_name, c.phone AS customer_phone, c.birth_date
    FROM bookings b
    LEFT JOIN customers c ON c.id_customers = b.customers_id
    WHERE b.booking_date = ? AND b.status = 'dikonfirmasi'
    ORDER BY b.booking_time ASC
");
 $stmt->bind_param("s", $today);
 $stmt->execute();
 $todayBookings = [];
while ($row = $stmt->get_result()->fetch_assoc()) {
    $todayBookings[] = $row;
}
 $stmt->close();

// Ambil booking yang sudah selesai hari ini
 $stmt = $conn->prepare("
    SELECT b.*, c.name AS customer_name
    FROM bookings b
    LEFT JOIN customers c ON c.id_customers = b.customers_id
    WHERE b.booking_date = ? AND b.status = 'selesai'
    ORDER BY b.booking_time ASC
");
 $stmt->bind_param("s", $today);
 $stmt->execute();
 $doneBookings = [];
while ($row = $stmt->get_result()->fetch_assoc()) {
    $doneBookings[] = $row;
}
 $stmt->close();

// Statistik hari ini
 $totalToday = count($todayBookings) + count($doneBookings);
 $doneToday = count($doneBookings);
 $waitingToday = count($todayBookings);

 $pageTitle = 'Dashboard Staff - Aldy Barbershop';
require_once '../includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="welcome-section mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="h4 fw-bold text-light mb-1">Dashboard Staff</h2>
                <p class="text-muted small mb-0"><i class="bi bi-scissors me-1"></i>Jadwal booking hari ini: <?= formatTanggal($today) ?></p>
            </div>
            <div class="col-auto text-end">
                <span class="badge bg-accent fs-6 px-3 py-2 rounded-pill">
                    <i class="bi bi-person-workspace me-1"></i>Staff
                </span>
            </div>
        </div>
    </div>

    <!-- Statistik -->
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon bg-info bg-opacity-25 text-info"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-value"><?= $totalToday ?></div>
                <div class="stat-label">Total Hari Ini</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon bg-warning bg-opacity-25 text-warning"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-value"><?= $waitingToday ?></div>
                <div class="stat-label">Menunggu Dipotong</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon bg-success bg-opacity-25 text-success"><i class="bi bi-check2-all"></i></div>
                <div class="stat-value"><?= $doneToday ?></div>
                <div class="stat-label">Selesai</div>
            </div>
        </div>
    </div>

    <!-- Booking Menunggu Dipotong -->
    <div class="section-card mb-4">
        <div class="section-header">
            <h3 class="h5 fw-bold mb-0">
                <i class="bi bi-scissors me-2 text-accent"></i>Antrian Hari Ini
            </h3>
            <span class="badge bg-warning text-dark"><?= $waitingToday ?> antrian</span>
        </div>

        <?php if (empty($todayBookings)): ?>
        <div class="text-center py-5">
            <i class="bi bi-emoji-smile display-4 text-muted"></i>
            <p class="text-muted mt-2">Tidak ada antrian saat ini. Istirahat dulu! ☕</p>
        </div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($todayBookings as $i => $b):
                $custAge = ($b['birth_date']) ? hitungUmur($b['birth_date']) : 0;
                $kategori = ($b['service_price'] <= 10000) ? 'Anak' : 'Dewasa';
                $katColor = ($b['service_price'] <= 10000) ? 'info' : 'accent';
                $now = date('H:i');
                $isNext = ($i === 0) ? true : false;
            ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="service-card h-100 <?= $isNext ? 'border-accent' : '' ?>" 
                     style="<?= $isNext ? 'border-color:var(--accent);box-shadow:0 0 15px rgba(201,169,110,0.2);' : '' ?>">
                    <?php if ($isNext): ?>
                    <div class="mb-2">
                        <span class="badge bg-accent px-2 py-1"><i class="bi bi-arrow-right-circle me-1"></i>SELANJUTNYA</span>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="fw-bold mb-0"><?= e($b['customer_name']) ?></h5>
                            <small class="text-muted"><?= e($b['customer_phone']) ?></small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-accent fs-5"><?= formatWaktu($b['booking_time']) ?></div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-medium"><?= e($b['service_name']) ?></span>
                        <div>
                            <span class="badge bg-<?= $katColor ?> me-1"><?= $kategori ?></span>
                            <span class="text-accent fw-semibold"><?= formatRupiah($b['service_price']) ?></span>
                        </div>
                    </div>
                    <?php if ($custAge > 0): ?>
                    <div class="small text-muted mb-3"><i class="bi bi-person me-1"></i>Umur: <?= $custAge ?> tahun</div>
                    <?php endif; ?>
                    <?php if ($isNext): ?>
                    <form method="POST" action="" onsubmit="return confirm('Tandai booking ini sebagai SELESAAI dipotong?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="done">
                        <input type="hidden" name="booking_id" value="<?= $b['id_bookings'] ?>">
                        <button type="submit" class="btn btn-success w-100 py-2 fw-semibold">
                            <i class="bi bi-check2-circle me-2"></i>Selesai Dipotong
                        </button>
                    </form>
                    <?php else: ?>
                    <button class="btn btn-outline-secondary w-100 py-2" disabled>
                        <i class="bi bi-hourglass-split me-1"></i>Menunggu giliran
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sudah Selesai -->
    <div class="section-card">
        <div class="section-header">
            <h3 class="h5 fw-bold mb-0">
                <i class="bi bi-check2-all me-2 text-success"></i>Sudah Selesai
            </h3>
            <span class="badge bg-success"><?= $doneToday ?> selesai</span>
        </div>

        <?php if (empty($doneBookings)): ?>
        <div class="text-center py-4">
            <p class="text-muted">Belum ada yang selesai hari ini.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Pelanggan</th>
                        <th>Layanan</th>
                        <th>Waktu</th>
                        <th>Harga</th>
                        <th>Kategori</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($doneBookings as $b):
                        $kategori = ($b['service_price'] <= 10000) ? 'Anak' : 'Dewasa';
                        $katColor = ($b['service_price'] <= 10000) ? 'info' : 'accent';
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="fw-medium"><?= e($b['customer_name']) ?></td>
                        <td><?= e($b['service_name']) ?></td>
                        <td><?= formatWaktu($b['booking_time']) ?></td>
                        <td class="text-accent fw-semibold"><?= formatRupiah($b['service_price']) ?></td>
                        <td><span class="badge bg-<?= $katColor ?>"><?= $kategori ?></span></td>
                        <td><span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Selesai</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>