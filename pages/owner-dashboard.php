<?php
require_once '../config/database.php';
require_once '../config/functions.php';

checkRole('owner');

// Handle filter
 $filter = $_GET['filter'] ?? 'daily';
 $dateFrom = $_GET['from'] ?? date('Y-m-d');
 $dateTo   = $_GET['to'] ?? date('Y-m-d');

if ($filter === 'weekly') {
    $dateFrom = date('Y-m-d', strtotime('-7 days'));
    $dateTo   = date('Y-m-d');
} elseif ($filter === 'monthly') {
    $dateFrom = date('Y-m-01');
    $dateTo   = date('Y-m-d');
}

// Data Laporan Kunjungan (FR-11)
 $visitReport = $pdo->prepare("
    SELECT booking_date, COUNT(*) AS total_kunjungan,
           SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) AS menunggu,
           SUM(CASE WHEN status = 'dikonfirmasi' THEN 1 ELSE 0 END) AS dikonfirmasi,
           SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) AS ditolak,
           SUM(CASE WHEN status = 'dibatalkan' THEN 1 ELSE 0 END) AS dibatalkan
    FROM bookings
    WHERE booking_date BETWEEN ? AND ?
    GROUP BY booking_date
    ORDER BY booking_date ASC
");
 $visitReport->execute([$dateFrom, $dateTo]);
 $dailyVisits = $visitReport->fetchAll();

// Rekap Pembayaran (FR-11)
 $revenueReport = $pdo->prepare("
    SELECT p.metode_pembayaran, COUNT(*) AS jumlah, SUM(p.total) AS total
    FROM payments p
    JOIN bookings b ON b.id_bookings = p.booking_id
    WHERE b.booking_date BETWEEN ? AND ?
    GROUP BY p.metode_pembayaran
");
 $revenueReport->execute([$dateFrom, $dateTo]);
 $revenueByMethod = $revenueReport->fetchAll();

 $totalRevenue = 0;
 $totalTransactions = 0;
foreach ($revenueByMethod as $r) {
    $totalRevenue += $r['total'];
    $totalTransactions += $r['jumlah'];
}

// Total kunjungan periode
 $totalVisits = 0;
foreach ($dailyVisits as $d) $totalVisits += $d['total_kunjungan'];

// Laporan per layanan
 $serviceReport = $pdo->prepare("
    SELECT b.service_name, COUNT(*) AS jumlah_booking, SUM(b.service_price) AS potensi_pendapatan
    FROM bookings b
    WHERE b.booking_date BETWEEN ? AND ? AND b.status NOT IN ('ditolak','dibatalkan')
    GROUP BY b.service_name
");
 $serviceReport->execute([$dateFrom, $dateTo]);
 $serviceStats = $serviceReport->fetchAll();

// Data untuk chart harian (7 hari terakhir atau range filter)
 $chartDays = $pdo->prepare("
    SELECT booking_date, COUNT(*) AS total
    FROM bookings
    WHERE booking_date BETWEEN ? AND ?
    GROUP BY booking_date ORDER BY booking_date
");
 $chartDays->execute([$dateFrom, $dateTo]);
 $chartData = $chartDays->fetchAll();

// Data chart pendapatan harian
 $revenueDaily = $pdo->prepare("
    SELECT b.booking_date, SUM(p.total) AS total_revenue
    FROM payments p
    JOIN bookings b ON b.id_bookings = p.booking_id
    WHERE b.booking_date BETWEEN ? AND ?
    GROUP BY b.booking_date ORDER BY b.booking_date
");
 $revenueDaily->execute([$dateFrom, $dateTo]);
 $revenueChartData = $revenueDaily->fetchAll();

 $pageTitle = 'Dashboard Pemilik - Aldy Barbershop';
require_once '../includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="welcome-section mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="h4 fw-bold text-light mb-1">Dashboard Pemilik</h2>
                <p class="text-muted small mb-0"><i class="bi bi-graph-up me-1"></i>Laporan kunjungan dan rekap pembayaran</p>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-outline-accent btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Cetak Laporan
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Periode -->
    <div class="section-card mb-4">
        <div class="row align-items-end g-2">
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-medium">Periode</label>
                <select class="form-select form-select-sm bg-dark-subtle border-secondary text-light" id="filterPeriod" onchange="applyFilter()">
                    <option value="daily" <?= $filter === 'daily' ? 'selected' : '' ?>>Harian</option>
                    <option value="weekly" <?= $filter === 'weekly' ? 'selected' : '' ?>>Mingguan (7 Hari)</option>
                    <option value="monthly" <?= $filter === 'monthly' ? 'selected' : '' ?>>Bulanan</option>
                    <option value="custom" <?= $filter === 'custom' ? 'selected' : '' ?>>Kustom</option>
                </select>
            </div>
            <div class="col-6 col-md-3 filter-custom" style="<?= $filter === 'custom' ? '' : 'display:none' ?>">
                <label class="form-label small fw-medium">Dari</label>
                <input type="date" class="form-control form-control-sm bg-dark-subtle border-secondary text-light" id="dateFrom" value="<?= $dateFrom ?>">
            </div>
            <div class="col-6 col-md-3 filter-custom" style="<?= $filter === 'custom' ? '' : 'display:none' ?>">
                <label class="form-label small fw-medium">Sampai</label>
                <input type="date" class="form-control form-control-sm bg-dark-subtle border-secondary text-light" id="dateTo" value="<?= $dateTo ?>">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <button class="btn btn-accent btn-sm w-100" onclick="applyFilter()">
                    <i class="bi bi-funnel me-1"></i>Terapkan Filter
                </button>
            </div>
        </div>
    </div>

    <!-- Statistik Ringkas -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-accent bg-opacity-25 text-accent"><i class="bi bi-people"></i></div>
                <div class="stat-value"><?= $totalVisits ?></div>
                <div class="stat-label">Total Kunjungan</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-success bg-opacity-25 text-success"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-value"><?= formatRupiah($totalRevenue) ?></div>
                <div class="stat-label">Total Pendapatan</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-info bg-opacity-25 text-info"><i class="bi bi-receipt"></i></div>
                <div class="stat-value"><?= $totalTransactions ?></div>
                <div class="stat-label">Transaksi</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon bg-warning bg-opacity-25 text-warning"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="stat-value"><?= $totalVisits > 0 ? formatRupiah((int)($totalRevenue / $totalVisits)) : 'Rp0' ?></div>
                <div class="stat-label">Rata-rata/Kunjungan</div>
            </div>
        </div>
    </div>

    <!-- Charts (FR-11) -->
    <div class="row g-4 mb-4 print-break">
        <div class="col-12 col-lg-7">
            <div class="section-card h-100">
                <div class="section-header">
                    <h3 class="h5 fw-bold mb-0"><i class="bi bi-bar-chart me-2 text-accent"></i>Grafik Kunjungan</h3>
                </div>
                <div class="chart-container">
                    <canvas id="visitChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="section-card h-100">
                <div class="section-header">
                    <h3 class="h5 fw-bold mb-0"><i class="bi bi-pie-chart me-2 text-accent"></i>Pendapatan per Metode</h3>
                </div>
                <div class="chart-container chart-container-sm">
                    <canvas id="revenueChart"></canvas>
                </div>
                <?php if (!empty($revenueByMethod)): ?>
                <div class="mt-3">
                    <?php foreach ($revenueByMethod as $r): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small text-light"><?= ucfirst($r['metode_pembayaran']) ?> <span class="text-muted">(<?= $r['jumlah'] ?>x)</span></span>
                        <span class="small fw-semibold text-accent"><?= formatRupiah($r['total']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Laporan Kunjungan Harian -->
    <div class="section-card mb-4">
        <div class="section-header">
            <h3 class="h5 fw-bold mb-0"><i class="bi bi-calendar3 me-2 text-accent"></i>Laporan Kunjungan Harian</h3>
            <span class="small text-muted"><?= formatTanggal($dateFrom) ?> - <?= formatTanggal($dateTo) ?></span>
        </div>
        <?php if (empty($dailyVisits)): ?>
        <div class="text-center py-4">
            <i class="bi bi-calendar-x display-4 text-muted"></i>
            <p class="text-muted mt-2">Tidak ada data kunjungan pada periode ini.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0" id="visitTable">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Total Kunjungan</th>
                        <th>Menunggu</th>
                        <th>Dikonfirmasi</th>
                        <th>Ditolak</th>
                        <th>Dibatalkan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyVisits as $d): ?>
                    <tr>
                        <td class="fw-medium"><?= formatTanggal($d['booking_date']) ?></td>
                        <td><span class="badge bg-accent"><?= $d['total_kunjungan'] ?></span></td>
                        <td><?= $d['menunggu'] ?></td>
                        <td><?= $d['dikonfirmasi'] ?></td>
                        <td><?= $d['ditolak'] ?></td>
                        <td><?= $d['dibatalkan'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="border-top border-accent">
                        <td class="fw-bold">TOTAL</td>
                        <td class="fw-bold text-accent"><?= $totalVisits ?></td>
                        <td class="fw-bold"><?= array_sum(array_column($dailyVisits, 'menunggu')) ?></td>
                        <td class="fw-bold"><?= array_sum(array_column($dailyVisits, 'dikonfirmasi')) ?></td>
                        <td class="fw-bold"><?= array_sum(array_column($dailyVisits, 'ditolak')) ?></td>
                        <td class="fw-bold"><?= array_sum(array_column($dailyVisits, 'dibatalkan')) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Rekap per Layanan -->
    <div class="section-card mb-4">
        <div class="section-header">
            <h3 class="h5 fw-bold mb-0"><i class="bi bi-scissors me-2 text-accent"></i>Rekap per Layanan</h3>
        </div>
        <?php if (empty($serviceStats)): ?>
        <div class="text-center py-4">
            <p class="text-muted">Tidak ada data pada periode ini.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Layanan</th>
                        <th>Jumlah Booking</th>
                        <th>Potensi Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($serviceStats as $s): ?>
                    <tr>
                        <td class="fw-medium"><?= e($s['service_name']) ?></td>
                        <td><?= $s['jumlah_booking'] ?>x</td>
                        <td class="text-accent fw-semibold"><?= formatRupiah($s['potensi_pendapatan']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Rekap Pembayaran Detail -->
    <div class="section-card">
        <div class="section-header">
            <h3 class="h5 fw-bold mb-0"><i class="bi bi-cash-coin me-2 text-accent"></i>Rekap Pembayaran Detail</h3>
        </div>
        <?php
        $detailPayments = $pdo->prepare("
            SELECT p.*, b.service_name, b.booking_date, b.booking_time, c.name AS customer_name
            FROM payments p
            JOIN bookings b ON b.id_bookings = p.booking_id
            LEFT JOIN customers c ON c.id_customers = b.customers_id
            WHERE b.booking_date BETWEEN ? AND ?
            ORDER BY b.booking_date DESC, p.created_at DESC
        ");
        $detailPayments->execute([$dateFrom, $dateTo]);
        $details = $detailPayments->fetchAll();
        ?>
        <?php if (empty($details)): ?>
        <div class="text-center py-4">
            <p class="text-muted">Tidak ada pembayaran pada periode ini.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0" id="detailPayTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th>Layanan</th>
                        <th>Total</th>
                        <th>Metode</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($details as $d): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= formatTanggal($d['booking_date']) ?></td>
                        <td><?= e($d['customer_name']) ?></td>
                        <td><?= e($d['service_name']) ?></td>
                        <td class="text-accent fw-semibold"><?= formatRupiah($d['total']) ?></td>
                        <td><span class="badge bg-dark-subtle"><?= ucfirst($d['metode_pembayaran']) ?></span></td>
                        <td><span class="badge bg-success"><?= ucfirst($d['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="border-top border-accent">
                        <td colspan="4" class="fw-bold text-end">TOTAL PENDAPATAN</td>
                        <td class="fw-bold text-accent fs-5" colspan="3"><?= formatRupiah($totalRevenue) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Data Chart Kunjungan
const visitLabels = <?= json_encode(array_map(function($d) { return formatTanggal($d['booking_date']); }, $chartData)) ?>;
const visitData = <?= json_encode(array_map(function($d) { return (int)$d['total']; }, $chartData)) ?>;

// Data Chart Pendapatan per Metode
const revLabels = <?= json_encode(array_map(function($r) { return ucfirst($r['metode_pembayaran']); }, $revenueByMethod)) ?>;
const revData = <?= json_encode(array_map(function($r) { return (int)$r['total']; }, $revenueByMethod)) ?>;

// Chart Kunjungan
const ctxVisit = document.getElementById('visitChart').getContext('2d');
new Chart(ctxVisit, {
    type: 'line',
    data: {
        labels: visitLabels,
        datasets: [{
            label: 'Kunjungan',
            data: visitData,
            borderColor: '#c9a96e',
            backgroundColor: 'rgba(201,169,110,0.1)',
            borderWidth: 2,
            tension: 0.3,
            fill: true,
            pointBackgroundColor: '#c9a96e',
            pointBorderColor: '#c9a96e',
            pointRadius: 4,
            pointHoverRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#ccc' } }
        },
        scales: {
            x: { ticks: { color: '#888', maxRotation: 45 }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y: { ticks: { color: '#888', stepSize: 1 }, grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true }
        }
    }
});

// Chart Pendapatan per Metode (Doughnut)
const revColors = ['#c9a96e', '#28a745', '#17a2b8'];
const ctxRev = document.getElementById('revenueChart').getContext('2d');
new Chart(ctxRev, {
    type: 'doughnut',
    data: {
        labels: revLabels,
        datasets: [{
            data: revData,
            backgroundColor: revColors.slice(0, revLabels.length),
            borderColor: '#1a1a1a',
            borderWidth: 3,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: '#ccc', padding: 15 }
            }
        },
        cutout: '65%'
    }
});

// Filter handler
function applyFilter() {
    const period = document.getElementById('filterPeriod').value;
    let url = 'owner-dashboard.php?filter=' + period;
    if (period === 'custom') {
        const from = document.getElementById('dateFrom').value;
        const to = document.getElementById('dateTo').value;
        if (from) url += '&from=' + from;
        if (to) url += '&to=' + to;
    }
    window.location.href = url;
}

// Tampilkan/sembunyikan tanggal kustom
document.getElementById('filterPeriod').addEventListener('change', function() {
    const custom = document.querySelectorAll('.filter-custom');
    custom.forEach(el => el.style.display = this.value === 'custom' ? '' : 'none');
});
</script>

<?php require_once '../includes/footer.php'; ?>s