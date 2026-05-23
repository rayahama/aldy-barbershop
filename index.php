<?php
// index.php - Landing Page Publik
require_once 'config/database.php';
require_once 'config/functions.php';

// Ambil data layanan menggunakan MySQLi
$servicesResult = $conn->query("SELECT * FROM services ORDER BY service_price ASC");
$services = $servicesResult ? $servicesResult->fetch_all(MYSQLI_ASSOC) : [];

// Ambil jumlah booking aktif (statistik) menggunakan MySQLi
$statsResult = $conn->query("
    SELECT COUNT(*) AS total FROM bookings WHERE status NOT IN ('ditolak','dibatalkan')
");
$statsActive = $statsResult ? $statsResult->fetch_assoc() : ['total' => 0];

// Ambil testimonial / booking terbaru yang sudah dibayar menggunakan MySQLi
$recentResult = $conn->query("
    SELECT b.service_name, b.booking_date, c.name AS customer_name
    FROM bookings b
    LEFT JOIN customers c ON c.id_customers = b.customers_id
    WHERE b.status = 'dikonfirmasi'
    ORDER BY b.created_at DESC LIMIT 5
");
$recentBookings = $recentResult ? $recentResult->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aldy Barbershop - Booking Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* === Landing Page Specific === */
        .hero-section {
            min-height: 100vh;
            position: relative;
            display: flex;
            align-items: center;
            overflow: hidden;
        }
        .hero-bg {
            position: absolute;
            inset: 0;
            z-index: -1;
            background:
                radial-gradient(ellipse at 30% 40%, rgba(201,169,110,0.12) 0%, transparent 55%),
                radial-gradient(ellipse at 70% 60%, rgba(201,169,110,0.06) 0%, transparent 50%),
                radial-gradient(circle at 50% 100%, rgba(0,0,0,0.8) 0%, transparent 60%),
                var(--bg-primary);
        }
        .hero-pattern {
            position: absolute;
            inset: 0;
            z-index: -1;
            opacity: 0.03;
            background-image: 
                repeating-linear-gradient(45deg, #c9a96e 0px, #c9a96e 1px, transparent 1px, transparent 20px),
                repeating-linear-gradient(-45deg, #c9a96e 0px, #c9a96e 1px, transparent 1px, transparent 20px);
        }
        .hero-scissors {
            position: absolute;
            right: -5%;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20rem;
            color: rgba(201,169,110,0.04);
            z-index: -1;
        }
        .hero-title {
            font-family: 'Playfair Display', serif;
            font-weight: 900;
            font-size: clamp(2.5rem, 7vw, 5rem);
            line-height: 1.05;
            letter-spacing: -0.02em;
        }
        .hero-title .accent {
            color: var(--accent);
            display: block;
        }
        .hero-subtitle {
            font-size: clamp(0.95rem, 2vw, 1.2rem);
            font-weight: 300;
            color: var(--text-muted);
            max-width: 500px;
        }
        .hero-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.85rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 50px;
            transition: all 0.3s;
        }
        .hero-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(201,169,110,0.4);
        }
        .hero-btn-outline {
            border: 2px solid var(--border-color);
            color: var(--text-primary);
            background: transparent;
        }
        .hero-btn-outline:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: transparent;
        }

         /* === PERBAIKAN WARNA PARAGRAF TERANG === */
        .hero-subtitle {
            color: rgba(255, 255, 255, 0.9) !important;
        }
        .text-muted {
            color: rgba(255, 255, 255, 0.7) !important;
        }
        .section-title.text-light {
            color: #ffffff !important;
        }
        .hours-item span.fw-medium {
            color: rgba(255, 255, 255, 0.85) !important;
}
        .stat-inline {
            display: flex;
            gap: 2rem;
            margin-top: 2rem;
        }
        .stat-inline-item {
            text-align: left;
        }
        .stat-inline-num {
            font-family: 'Playfair Display', serif;
            font-weight: 900;
            font-size: 1.8rem;
            color: var(--accent);
            line-height: 1;
        }
        .stat-inline-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Section layanan */
        .landing-section {
            padding: 5rem 0;
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-weight: 900;
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            margin-bottom: 0.5rem;
        }
        .section-title .accent { color: var(--accent); }
        .section-divider {
            width: 60px;
            height: 3px;
            background: var(--accent);
            border-radius: 2px;
            margin-bottom: 1rem;
        }
        .service-landing-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            transition: all 0.4s;
            position: relative;
            overflow: hidden;
        }
        .service-landing-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent);
            transform: scaleX(0);
            transition: transform 0.4s;
        }
        .service-landing-card:hover::before {
            transform: scaleX(1);
        }
        .service-landing-card:hover {
            transform: translateY(-8px);
            border-color: rgba(201,169,110,0.3);
            box-shadow: 0 15px 40px rgba(0,0,0,0.4), 0 0 30px var(--accent-glow);
        }
        .service-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #8b6914);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        .service-icon i {
            font-size: 1.8rem;
            color: var(--bg-primary);
        }
        .service-landing-name {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 0.3rem;
        }
        .price-range {
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .price-range-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.85rem;
        }
        .price-range-item.anak {
            background: rgba(52,152,219,0.1);
            border: 1px solid rgba(52,152,219,0.2);
        }
        .price-range-item.dewasa {
            background: rgba(201,169,110,0.1);
            border: 1px solid rgba(201,169,110,0.2);
        }

        /* Jam operasional */
        .hours-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
        }
        .hours-item {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .hours-item:last-child { border-bottom: none; }

        /* How it works */
        .step-card {
            text-align: center;
            position: relative;
        }
        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--accent);
            color: var(--bg-primary);
            font-family: 'Playfair Display', serif;
            font-weight: 900;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        .step-connector {
            position: absolute;
            top: 25px;
            left: calc(50% + 30px);
            width: calc(100% - 60px);
            height: 2px;
            background: var(--border-color);
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, rgba(201,169,110,0.15), rgba(201,169,110,0.05));
            border-top: 1px solid rgba(201,169,110,0.2);
            border-bottom: 1px solid rgba(201,169,110,0.2);
        }

        /* Navbar landing */
        .landing-nav {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        .landing-nav.scrolled {
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        /* Footer */
        .landing-footer {
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
        }

        /* Animasi masuk */
        .fade-up {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease-out;
        }
        .fade-up.visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 768px) {
            .stat-inline { gap: 1.2rem; }
            .stat-inline-num { font-size: 1.4rem; }
            .step-connector { display: none; }
            .hero-scissors { display: none; }
        }

        @media print {
            .landing-nav, .cta-section { display: none; }
        }
    </style>
</head>
<body>

<!-- ===== NAVBAR LANDING ===== -->
<nav class="navbar navbar-dark landing-nav fixed-top" id="landingNav">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <i class="bi bi-scissors me-2 brand-icon"></i>
            <span class="brand-text">ALDY<span class="brand-accent">BARBERSHOP</span></span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="pages/login.php" class="btn btn-outline-accent btn-sm px-3">
                <i class="bi bi-box-arrow-in-right me-1"></i>Login
            </a>
            <a href="pages/register.php" class="btn btn-accent btn-sm px-3">
                <i class="bi bi-person-plus me-1"></i>Daftar
            </a>
        </div>
    </div>
</nav>

<!-- ===== HERO SECTION ===== -->
<section class="hero-section">
    <div class="hero-bg"></div>
    <div class="hero-pattern"></div>
    <i class="bi bi-scissors hero-scissors"></i>

    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="mb-3">
                    <span class="badge px-3 py-2 rounded-pill" style="background:rgba(201,169,110,0.15);color:var(--accent);border:1px solid rgba(201,169,110,0.3);">
                        <i class="bi bi-star-fill me-1"></i>Booking Online Tersedia
                    </span>
                </div>
                <h1 class="hero-title text-light mb-3">
                    Potongan Rapi,<br>
                    <span class="accent">Harga Bersahabat</span>
                </h1>
                <p class="hero-subtitle mb-4">
                    Aldy Barbershop melayani haircut dan haircoloring dengan harga terjangkau. Anak-anak & dewasa, semua welcome. Booking sekarang, tanpa antre!
                </p>
                <div class="d-flex flex-wrap gap-3 mb-2">
                    <a href="pages/register.php" class="hero-btn btn-accent">
                        <i class="bi bi-calendar-check"></i>Booking Sekarang
                    </a>
                    <a href="#layanan" class="hero-btn hero-btn-outline">
                        <i class="bi bi-scissors"></i>Lihat Layanan
                    </a>
                </div>
                <div class="stat-inline">
                    <div class="stat-inline-item">
                        <div class="stat-inline-num"><?= number_format($statsActive['total']) ?>+</div>
                        <div class="stat-inline-label">Booking</div>
                    </div>
                    <div class="stat-inline-item">
                        <div class="stat-inline-num">2</div>
                        <div class="stat-inline-label">Layanan</div>
                    </div>
                    <div class="stat-inline-item">
                        <div class="stat-inline-num">Cash</div>
                        <div class="stat-inline-label">Pembayaran</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== LAYANAN ===== -->
<section class="landing-section" id="layanan">
    <div class="container">
        <div class="text-center mb-5 fade-up">
            <h2 class="section-title text-light">Layanan <span class="accent">Kami</span></h2>
            <div class="section-divider mx-auto"></div>
            <p class="text-muted">Harga berbeda berdasarkan usia pelanggan</p>
        </div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($services as $svc):
                $icon = ($svc['service_name'] === 'Haircut') ? 'content-cut' : 'palette';
            ?>
            <div class="col-12 col-sm-6 col-lg-5 fade-up">
                <div class="service-landing-card h-100">
                    <div class="service-icon">
                        <i class="bi bi-<?= $icon ?>"></i>
                    </div>
                    <h3 class="service-landing-name text-light"><?= e($svc['service_name']) ?></h3>
                    <p class="text-muted small mb-0">Layanan profesional dengan peralatan bersih & steril</p>
                    <div class="price-range">
                        <div class="price-range-item anak">
                            <span><span class="badge bg-info me-1">Anak</span> &lt; 15 tahun</span>
                            <strong class="text-info"><?= formatRupiah(10000) ?></strong>
                        </div>
                        <div class="price-range-item dewasa">
                            <span><span class="badge bg-accent me-1">Dewasa</span> &ge; 15 tahun</span>
                            <strong class="text-accent"><?= formatRupiah(15000) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== CARA BOOKING ===== -->
<section class="landing-section" style="background:var(--bg-secondary);">
    <div class="container">
        <div class="text-center mb-5 fade-up">
            <h2 class="section-title text-light">Cara <span class="accent">Booking</span></h2>
            <div class="section-divider mx-auto"></div>
            <p class="text-muted">Hanya 4 langkah mudah</p>
        </div>
        <div class="row g-4">
            <?php
            $steps = [
                ['1', 'bi-person-plus', 'Daftar Akun', 'Isi nama, no HP, tanggal lahir, dan password'],
                ['2', 'bi-box-arrow-in-right', 'Login', 'Masuk menggunakan no HP atau email'],
                ['3', 'bi-calendar-plus', 'Pilih Layanan', 'Pilih layanan, tanggal, dan waktu yang diinginkan'],
                ['4', 'bi-check-circle', 'Datang & Bayar', 'Datang sesuai jadwal dan bayar cash di kasir'],
            ];
            ?>
            <?php foreach ($steps as $i => $step): ?>
            <div class="col-6 col-lg-3 fade-up" style="transition-delay: <?= $i * 0.1 ?>s">
                <div class="step-card">
                    <?php if ($i < 3): ?>
                    <div class="step-connector d-none d-lg-block"></div>
                    <?php endif; ?>
                    <div class="step-number"><?= $step[0] ?></div>
                    <i class="bi <?= $step[1] ?> d-block mb-2" style="font-size:1.5rem;color:var(--accent);"></i>
                    <h5 class="fw-bold text-light small mb-1"><?= $step[2] ?></h5>
                    <p class="text-muted small mb-0"><?= $step[3] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== JAM OPERASIONAL ===== -->
<section class="landing-section" id="info">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-5 fade-up">
                <h2 class="section-title text-light">Jam <span class="accent">Operasional</span></h2>
                <div class="section-divider"></div>
                <p class="text-muted mb-4">Kunjungi kami di jam operasional berikut. Booking online tersedia untuk slot 30 menit.</p>
                <div class="hours-card">
                    <div class="hours-item">
                        <span class="fw-medium">Senin - Sabtu</span>
                        <span class="text-accent fw-semibold">09:00 - 20:00</span>
                    </div>
                    <div class="hours-item">
                        <span class="fw-medium">Minggu</span>
                        <span class="text-danger fw-semibold">Tutup</span>
                    </div>
                    <div class="hours-item">
                        <span class="fw-medium">Slot Booking</span>
                        <span class="text-light">Setiap 30 menit</span>
                    </div>
                    <div class="hours-item">
                        <span class="fw-medium">Pembayaran</span>
                        <span class="text-light"><i class="bi bi-cash-stack me-1 text-success"></i>Cash (Tunai)</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 fade-up">
                <div class="hours-card h-100">
                    <h4 class="fw-bold text-light mb-3"><i class="bi bi-info-circle me-2 text-accent"></i>Informasi Penting</h4>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="p-3 rounded" style="background:var(--bg-input);border:1px solid var(--border-color);">
                                <i class="bi bi-calendar-check text-accent d-block mb-1" style="font-size:1.3rem;"></i>
                                <div class="small fw-medium text-light">Maks. Booking</div>
                                <div class="small text-muted">1x per hari per pelanggan</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 rounded" style="background:var(--bg-input);border:1px solid var(--border-color);">
                                <i class="bi bi-clock text-accent d-block mb-1" style="font-size:1.3rem;"></i>
                                <div class="small fw-medium text-light">Slot Terakhir</div>
                                <div class="small text-muted">19:30 WIB</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 rounded" style="background:var(--bg-input);border:1px solid var(--border-color);">
                                <i class="bi bi-calendar-plus text-accent d-block mb-1" style="font-size:1.3rem;"></i>
                                <div class="small fw-medium text-light">Booking Maks.</div>
                                <div class="small text-muted">30 hari ke depan</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 rounded" style="background:var(--bg-input);border:1px solid var(--border-color);">
                                <i class="bi bi-x-circle text-accent d-block mb-1" style="font-size:1.3rem;"></i>
                                <div class="small fw-medium text-light">Pembatalan</div>
                                <div class="small text-muted">Hanya status menunggu</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 rounded d-flex align-items-start gap-2" style="background:rgba(201,169,110,0.08);border:1px solid rgba(201,169,110,0.2);">
                                <i class="bi bi-tag text-accent" style="font-size:1.3rem;"></i>
                                <div>
                                    <div class="small fw-medium text-light">Harga Berdasarkan Umur</div>
                                    <div class="small text-muted">Anak-anak (&lt;15 tahun) mendapat harga khusus Rp10.000 untuk semua layanan</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== CTA ===== -->
<section class="landing-section cta-section">
    <div class="container text-center fade-up">
        <h2 class="section-title text-light mb-3">Siap <span class="accent">Potong Rambut?</span></h2>
        <p class="text-muted mb-4 mx-auto" style="max-width:500px;">Daftar sekarang, booking layanan, dan datang sesuai jadwal. Tanpa antre, tanpa ribet!</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="pages/register.php" class="hero-btn btn-accent">
                <i class="bi bi-person-plus"></i>Daftar Sekarang
            </a>
            <a href="pages/login.php" class="hero-btn hero-btn-outline">
                <i class="bi bi-box-arrow-in-right"></i>Sudah Punya Akun
            </a>
        </div>
    </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="landing-footer py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
                <a class="navbar-brand d-inline-flex align-items-center text-decoration-none" href="index.php">
                    <i class="bi bi-scissors me-2 brand-icon"></i>
                    <span class="brand-text">ALDY<span class="brand-accent">BARBERSHOP</span></span>
                </a>
                <p class="text-muted small mt-2 mb-0">Sistem Informasi Booking Online</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="pages/login.php" class="text-muted small text-decoration-none me-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login
                </a>
                <a href="pages/register.php" class="text-muted small text-decoration-none me-3">
                    <i class="bi bi-person-plus me-1"></i>Daftar
                </a>
                <a href="#layanan" class="text-muted small text-decoration-none">
                    <i class="bi bi-scissors me-1"></i>Layanan
                </a>
                <div class="small text-muted mt-2">
                    &copy; <?= date('Y') ?> Aldy Barbershop. All rights reserved.
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Navbar scroll effect
window.addEventListener('scroll', function() {
    const nav = document.getElementById('landingNav');
    if (window.scrollY > 50) {
        nav.classList.add('scrolled');
    } else {
        nav.classList.remove('scrolled');
    }
});

// Scroll animation (fade-up)
const observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

document.querySelectorAll('.fade-up').forEach(function(el) {
    observer.observe(el);
});

// Smooth scroll untuk anchor links
document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
</script>

</body>
</html>