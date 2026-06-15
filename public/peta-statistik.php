<?php
// public/peta-statistik.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Get heatmap settings
$heatmapSettings = getHeatmapSettings();

// Get all fire incidents
$conn = getConnection();
$result = $conn->query("SELECT * FROM kejadian_kebakaran ORDER BY waktu DESC");
$incidents = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $incidents[] = $row;
    }
}

// Get per-kecamatan statistics
$kecamatanStats = $conn->query("
    SELECT 
        kecamatan,
        COUNT(*) as total,
        SUM(korban_luka) as total_luka,
        SUM(korban_jiwa) as total_jiwa,
        SUM(jumlah_bangunan) as total_bangunan
    FROM kejadian_kebakaran
    GROUP BY kecamatan
    ORDER BY total DESC
");

$kecamatanData = [];
while ($row = $kecamatanStats->fetch_assoc()) {
    $kecamatanData[] = $row;
}

// Get monthly trend
$monthlyTrend = $conn->query("
    SELECT 
        DATE_FORMAT(waktu, '%Y-%m') as bulan,
        COUNT(*) as total
    FROM kejadian_kebakaran
    WHERE waktu >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(waktu, '%Y-%m')
    ORDER BY bulan ASC
");

$trendData = [];
while ($row = $monthlyTrend->fetch_assoc()) {
    $trendData[] = $row;
}

$conn->close();

$stats = getStatistics();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peta & Statistik - BARRES 698</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <style>
        :root {
            --jet-black: #0D0D0D;
            --dark-grey: #2A2A2A;
            --gold: #F7B801;
            --gold-dark: #E0A600;
            --off-white: #F5F5F5;
            --off-white-dim: #E8E5DF;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--off-white);
            color: var(--jet-black);
            overflow-x: hidden;
        }

        /* Navbar */
        .site-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 40px;
            background: rgba(13, 13, 13, 0.96);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(247, 184, 1, 0.25);
            transition: padding .3s;
        }

        .site-nav.compact {
            padding: 12px 40px;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .nav-logo-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--jet-black);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .nav-logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .nav-logo-text {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.4rem;
            letter-spacing: 1px;
            color: #fff;
            line-height: 1;
        }

        .nav-logo-sub {
            font-size: .6rem;
            color: rgba(255, 255, 255, .45);
            letter-spacing: 3px;
            text-transform: uppercase;
            display: block;
            font-weight: 400;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav-links a {
            font-size: .82rem;
            font-weight: 500;
            letter-spacing: .5px;
            color: rgba(255, 255, 255, .65);
            text-decoration: none;
            padding: 7px 14px;
            border-radius: 8px;
            transition: color .2s, background .2s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #fff;
            background: rgba(255, 255, 255, .07);
        }

        .nav-cta {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark)) !important;
            color: var(--jet-black) !important;
            padding: 7px 18px !important;
            font-weight: 600 !important;
        }

        .nav-cta:hover {
            background: linear-gradient(135deg, var(--gold-dark), var(--gold)) !important;
        }

        .nav-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
        }

        .nav-toggle span {
            display: block;
            width: 22px;
            height: 2px;
            background: #fff;
            margin: 5px 0;
            transition: all .3s;
        }

        /* Page Hero */
        .page-hero {
            background: var(--jet-black);
            padding: 140px 0 60px;
            position: relative;
            overflow: hidden;
        }

        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.04'/%3E%3C/svg%3E");
            pointer-events: none;
        }

        .page-hero::after {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 55%;
            height: 140%;
            background: linear-gradient(160deg, rgba(247, 184, 1, 0.08) 0%, rgba(247, 184, 1, 0.02) 60%, transparent 100%);
            transform: skewX(-8deg);
            z-index: 0;
        }

        .page-hero .container {
            position: relative;
            z-index: 1;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-family: 'DM Mono', monospace;
            font-size: .7rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 24px;
        }

        .hero-badge::before {
            content: '';
            display: block;
            width: 28px;
            height: 2px;
            background: var(--gold);
        }

        .page-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: clamp(2.5rem, 5vw, 4rem);
            color: #fff;
            margin-bottom: 20px;
        }

        .page-hero .lead {
            color: rgba(255, 255, 255, .5);
            font-size: 1.1rem;
            max-width: 600px;
        }

        /* Map Styles */
        #map {
            height: 500px;
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        }

        .map-container {
            position: relative;
        }

        .legend-card {
            background: rgba(13, 13, 13, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 16px;
            border: 1px solid rgba(247, 184, 1, 0.2);
        }

        .legend-card h6 {
            color: var(--gold);
            font-family: 'DM Mono', monospace;
            font-size: .7rem;
            letter-spacing: 2px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin: 10px 0;
        }

        .color-box {
            width: 24px;
            height: 24px;
            margin-right: 12px;
            border-radius: 6px;
        }

        .kde-info {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(13, 13, 13, 0.9);
            backdrop-filter: blur(8px);
            color: #fff;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 11px;
            font-family: 'DM Mono', monospace;
            z-index: 1000;
            border: 1px solid rgba(247, 184, 1, 0.3);
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(13, 13, 13, 0.85);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            border-radius: 20px;
        }

        .filter-section {
            background: var(--dark-grey);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid rgba(247, 184, 1, 0.12);
        }

        .filter-section label {
            color: var(--gold);
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: 1px;
            margin-bottom: 8px;
            display: block;
        }

        .form-control-custom,
        .form-select-custom {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(247, 184, 1, 0.2);
            border-radius: 12px;
            padding: 12px 16px;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            width: 100%;
        }

        .form-control-custom:focus,
        .form-select-custom:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--gold);
            outline: none;
            color: #fff;
        }

        .btn-gold {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--jet-black);
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 12px;
            border: none;
            transition: all .3s;
        }

        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(247, 184, 1, 0.35);
        }

        .btn-outline-gold-sm {
            background: transparent;
            border: 1px solid var(--gold);
            color: var(--gold);
            padding: 8px 16px;
            border-radius: 10px;
            font-size: .8rem;
            font-weight: 500;
            transition: all .2s;
        }

        .btn-outline-gold-sm:hover {
            background: var(--gold);
            color: var(--jet-black);
        }

        /* Range Slider */
        .form-range {
            background: rgba(255, 255, 255, 0.05);
            height: 4px;
            border-radius: 4px;
        }

        .form-range::-webkit-slider-thumb {
            background: var(--gold);
        }

        /* Cards */
        .stat-card-mini {
            background: var(--dark-grey);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(247, 184, 1, 0.12);
        }

        .stat-card-mini .number {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 2rem;
            color: var(--gold);
        }

        .stat-card-mini .label {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, .5);
        }

        .chart-card {
            background: var(--dark-grey);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid rgba(247, 184, 1, 0.12);
            height: 100%;
        }

        .chart-card .card-header-custom {
            border-bottom: 1px solid rgba(247, 184, 1, 0.15);
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .chart-card .card-header-custom h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--gold);
            margin: 0;
        }

        .data-table-card {
            background: var(--dark-grey);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid rgba(247, 184, 1, 0.12);
            margin-top: 30px;
        }

        .data-table-card h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--gold);
            margin-bottom: 20px;
        }

        .table-custom {
            color: rgba(255, 255, 255, .8);
        }

        .table-custom thead th {
            background: rgba(0, 0, 0, 0.3);
            border-bottom: 1px solid rgba(247, 184, 1, 0.2);
            color: var(--gold);
            font-weight: 600;
            font-size: .75rem;
            letter-spacing: 1px;
        }

        .table-custom tbody td {
            border-bottom: 1px solid rgba(247, 184, 1, 0.08);
            vertical-align: middle;
        }

        .table-custom tbody tr:hover {
            background: rgba(247, 184, 1, 0.05);
        }

        /* Footer */
        .site-footer {
            background: var(--jet-black);
            padding: 60px 0 32px;
            border-top: 1px solid rgba(247, 184, 1, 0.1);
            margin-top: 60px;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .footer-brand-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .footer-brand-icon i {
            color: var(--jet-black);
            font-size: 1.1rem;
        }

        .footer-brand-name {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: 1px;
            color: #fff;
            line-height: 1;
        }

        .footer-brand-tagline {
            font-size: .65rem;
            color: rgba(255, 255, 255, .35);
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .footer-desc {
            font-size: .87rem;
            line-height: 1.75;
            max-width: 300px;
            color: rgba(255, 255, 255, .3);
        }

        .footer-heading {
            font-family: 'DM Mono', monospace;
            font-size: .68rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 20px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            font-size: .88rem;
            color: rgba(255, 255, 255, .4);
            text-decoration: none;
            transition: color .2s;
        }

        .footer-links a:hover {
            color: var(--gold);
        }

        .footer-contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: .85rem;
            color: rgba(255, 255, 255, .4);
            margin-bottom: 12px;
        }

        .footer-contact-item i {
            color: var(--gold);
            width: 16px;
        }

        .emergency-box {
            background: rgba(247, 184, 1, 0.08);
            border: 1px solid rgba(247, 184, 1, 0.2);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
        }

        .emergency-box .label {
            font-size: .65rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .4);
            font-family: 'DM Mono', monospace;
            margin-bottom: 8px;
        }

        .emergency-box .number {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 3rem;
            color: var(--gold);
            line-height: 1;
        }

        .footer-divider {
            border: none;
            border-top: 1px solid rgba(255, 255, 255, .07);
            margin: 40px 0 24px;
        }

        .footer-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .78rem;
            color: rgba(255, 255, 255, .3);
        }

        .footer-socials {
            display: flex;
            gap: 12px;
        }

        .footer-socials a {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, .4);
            text-decoration: none;
            transition: all .2s;
        }

        .footer-socials a:hover {
            background: var(--gold);
            border-color: var(--gold);
            color: var(--jet-black);
        }

        .reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity .7s ease, transform .7s ease;
        }

        .reveal.visible {
            opacity: 1;
            transform: none;
        }

        @media (max-width: 992px) {
            .site-nav {
                padding: 16px 24px;
            }

            .nav-links {
                display: none;
            }

            .nav-toggle {
                display: block;
            }

            .nav-links.open {
                display: flex;
                flex-direction: column;
                gap: 4px;
                position: fixed;
                top: 72px;
                left: 0;
                right: 0;
                background: rgba(13, 13, 13, 0.98);
                padding: 20px 24px;
                border-bottom: 1px solid rgba(247, 184, 1, 0.15);
            }

            .footer-bottom {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="site-nav" id="siteNav">
        <a class="nav-logo" href="index.php">
            <div class="nav-logo-icon">
                <img src="../assets/barres2.png" alt="BARRES Logo">
            </div>
            <div>
                <span class="nav-logo-text">BARRES 698</span>
                <span class="nav-logo-sub">Banjarbaru Rescue</span>
            </div>
        </a>

        <div class="nav-links" id="navLinks">
            <a href="index.php">Beranda</a>
            <a href="profil.php">Profil</a>
            <a href="peta-statistik.php" class="active">Peta & Statistik</a>
            <a href="kontak.php">Kontak</a>
            <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                <?php if ($_SESSION['role'] == 'super_admin'): ?>
                    <a href="../admin/dashboard.php" class="nav-cta">Dashboard</a>
                <?php else: ?>
                    <a href="../bpk/dashboard.php" class="nav-cta">Dashboard</a>
                <?php endif; ?>
                <a href="../logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php" class="nav-cta">Login Admin</a>
            <?php endif; ?>
        </div>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </nav>

    <!-- PAGE HERO -->
    <section class="page-hero">
        <div class="container">
            <div class="hero-badge reveal">Visualisasi Data</div>
            <h1 class="reveal">Peta & <span style="color: var(--gold);">Statistik</span></h1>
            <p class="lead reveal">Analisis persebaran kejadian kebakaran menggunakan metode Kernel Density Estimation (KDE)</p>
        </div>
    </section>

    <div class="container py-4">
        <!-- Filter Section -->
        <div class="filter-section reveal">
            <div class="row align-items-end">
                <div class="col-md-3 mb-3">
                    <label>KECAMATAN</label>
                    <select id="filterKecamatan" class="form-select-custom">
                        <option value="">Semua Kecamatan</option>
                        <option value="Banjarbaru Utara">Banjarbaru Utara</option>
                        <option value="Banjarbaru Selatan">Banjarbaru Selatan</option>
                        <option value="Cempaka">Cempaka</option>
                        <option value="Landasan Ulin">Landasan Ulin</option>
                        <option value="Liang Anggang">Liang Anggang</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label>PERIODE</label>
                    <select id="filterPeriode" class="form-select-custom">
                        <option value="all">Semua Waktu</option>
                        <option value="month">Bulan Ini</option>
                        <option value="3months">3 Bulan Terakhir</option>
                        <option value="year">Tahun Ini</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label>METODE KDE</label>
                    <select id="kdeMethod" class="form-select-custom">
                        <option value="gaussian">Gaussian Kernel</option>
                        <option value="epanechnikov">Epanechnikov Kernel</option>
                        <option value="quartic">Quartic Kernel</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <button class="btn-gold w-100" onclick="applyFilter()">
                        <i class="fas fa-filter me-2"></i> Terapkan Filter
                    </button>
                </div>
            </div>
        </div>

        <!-- Map Section -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-3">
                <div class="map-container reveal">
                    <div id="map"></div>
                    <div class="loading-overlay" id="loadingOverlay">
                        <div class="text-center">
                            <div class="spinner-border text-gold" style="color: var(--gold);" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2" style="color: var(--gold);">Memproses KDE Heatmap...</p>
                        </div>
                    </div>
                    <div class="kde-info" id="kdeInfo">
                        <i class="fas fa-chart-line"></i> KDE: Initializing...
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-3">
                <div class="legend-card reveal">
                    <h6><i class="fas fa-palette me-2"></i> LEGENDA DENSITAS</h6>
                    <div class="legend-item">
                        <div class="color-box" style="background: #ff0000;"></div>
                        <span style="color: #fff;">Tinggi (Zona Merah)</span>
                    </div>
                    <div class="legend-item">
                        <div class="color-box" style="background: #ffff00;"></div>
                        <span style="color: #fff;">Sedang (Zona Kuning)</span>
                    </div>
                    <div class="legend-item">
                        <div class="color-box" style="background: #00ff00;"></div>
                        <span style="color: #fff;">Rendah (Zona Hijau)</span>
                    </div>
                    <hr style="border-color: rgba(247, 184, 1, 0.2);">
                    <h6><i class="fas fa-sliders-h me-2"></i> PENGATURAN KDE</h6>
                    <div class="mb-3">
                        <label style="font-size: .75rem;">Bandwidth: <span id="bandwidthValue" style="color: var(--gold);">Auto</span></label>
                        <input type="range" class="form-range w-100" id="bandwidthSlider"
                            min="0.001" max="0.05" step="0.001" value="0.01">
                    </div>
                    <div class="mb-3">
                        <label style="font-size: .75rem;">Radius: <span id="radiusValue" style="color: var(--gold);">25</span></label>
                        <input type="range" class="form-range w-100" id="radiusSlider" min="10" max="50" value="25">
                    </div>
                    <div class="mb-3">
                        <label style="font-size: .75rem;">Blur: <span id="blurValue" style="color: var(--gold);">15</span></label>
                        <input type="range" class="form-range w-100" id="blurSlider" min="5" max="30" value="15">
                    </div>
                    <div class="mb-3">
                        <label class="d-flex align-items-center gap-2" style="font-size: .75rem; cursor: pointer;">
                            <input type="checkbox" id="weightBySeverity" checked>
                            Bobot Berdasarkan Keparahan
                        </label>
                    </div>
                    <button class="btn-outline-gold-sm w-100" onclick="updateHeatmap()">
                        <i class="fas fa-sync-alt me-2"></i> Update KDE Heatmap
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3 reveal">
                <div class="stat-card-mini">
                    <div class="number"><?= number_format($stats['total_kejadian'] ?? 0, 0, ',', '.') ?></div>
                    <div class="label">Total Kejadian</div>
                </div>
            </div>
            <div class="col-md-3 mb-3 reveal">
                <div class="stat-card-mini">
                    <div class="number"><?= number_format($stats['total_luka'] ?? 0, 0, ',', '.') ?></div>
                    <div class="label">Korban Luka</div>
                </div>
            </div>
            <div class="col-md-3 mb-3 reveal">
                <div class="stat-card-mini">
                    <div class="number"><?= number_format($stats['total_jiwa'] ?? 0, 0, ',', '.') ?></div>
                    <div class="label">Korban Jiwa</div>
                </div>
            </div>
            <div class="col-md-3 mb-3 reveal">
                <div class="stat-card-mini">
                    <div class="number"><?= number_format($stats['total_bangunan'] ?? 0, 0, ',', '.') ?></div>
                    <div class="label">Bangunan Terdampak</div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-3 reveal">
                <div class="chart-card">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-chart-bar me-2"></i> Statistik per Kecamatan</h5>
                    </div>
                    <canvas id="kecamatanChart" style="max-height: 350px;"></canvas>
                </div>
            </div>
            <div class="col-lg-6 mb-3 reveal">
                <div class="chart-card">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-chart-line me-2"></i> Tren Kebakaran (12 Bulan)</h5>
                    </div>
                    <canvas id="trendChart" style="max-height: 350px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="data-table-card reveal">
            <h5><i class="fas fa-table me-2"></i> Data Kejadian Kebakaran</h5>
            <div class="table-responsive">
                <table class="table table-custom" id="incidentTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Waktu</th>
                            <th>Lokasi</th>
                            <th>Kecamatan</th>
                            <th>Kelurahan</th>
                            <th>Luka</th>
                            <th>Jiwa</th>
                            <th>Bangunan</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach ($incidents as $index => $incident): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($incident['waktu'])) ?></td>
                                <td><?= htmlspecialchars(substr($incident['alamat'], 0, 40)) ?>...</td>
                                <td><?= htmlspecialchars($incident['kecamatan']) ?></td>
                                <td><?= htmlspecialchars($incident['kelurahan'] ?? '-') ?></td>
                                <td><span class="badge" style="background: rgba(247, 184, 1, 0.15); color: var(--gold);"><?= $incident['korban_luka'] ?></span></td>
                                <td><span class="badge" style="background: rgba(220, 53, 69, 0.15); color: #ff6b6b;"><?= $incident['korban_jiwa'] ?></span></td>
                                <td><?= $incident['jumlah_bangunan'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="site-footer">
        <div class="container">
            <div class="row gy-5">
                <div class="col-lg-4">
                    <div class="footer-brand">
                        <div class="footer-brand-icon"><i class="fas fa-fire"></i></div>
                        <div>
                            <div class="footer-brand-name">BARRES 698</div>
                            <div class="footer-brand-tagline">Banjarbaru Rescue</div>
                        </div>
                    </div>
                    <p class="footer-desc">
                        Sistem Informasi Geografis pemetaan lokasi kebakaran berbasis web dengan metode Kernel Density Estimation (KDE).
                    </p>
                </div>

                <div class="col-6 col-lg-2">
                    <div class="footer-heading">Menu</div>
                    <ul class="footer-links">
                        <li><a href="index.php">Beranda</a></li>
                        <li><a href="profil.php">Profil</a></li>
                        <li><a href="peta-statistik.php">Peta & Statistik</a></li>
                        <li><a href="kontak.php">Kontak</a></li>
                    </ul>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="footer-heading">Kontak</div>
                    <div class="footer-contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Kota Banjarbaru, Kalimantan Selatan</span>
                    </div>
                    <div class="footer-contact-item">
                        <i class="fas fa-phone"></i>
                        <span>(0511) 123456</span>
                    </div>
                    <div class="footer-contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>info@barres698.id</span>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="footer-heading">Darurat</div>
                    <div class="emergency-box">
                        <div class="label">Pemadam Kebakaran</div>
                        <div class="number">113</div>
                    </div>
                </div>
            </div>

            <hr class="footer-divider">

            <div class="footer-bottom">
                <span>&copy; <?= date('Y') ?> BARRES 698 — SIG Pemetaan Kebakaran Banjarbaru</span>
                <div class="footer-socials">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll compact
        const nav = document.getElementById('siteNav');
        window.addEventListener('scroll', () => {
            nav.classList.toggle('compact', window.scrollY > 60);
        });

        // Hamburger toggle
        const toggle = document.getElementById('navToggle');
        const links = document.getElementById('navLinks');
        toggle.addEventListener('click', () => links.classList.toggle('open'));

        // Reveal on scroll
        const reveals = document.querySelectorAll('.reveal');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((e, i) => {
                if (e.isIntersecting) {
                    setTimeout(() => e.target.classList.add('visible'), i * 60);
                    observer.unobserve(e.target);
                }
            });
        }, {
            threshold: 0.12
        });
        reveals.forEach(el => observer.observe(el));
        document.querySelectorAll('.page-hero .reveal').forEach((el, i) => {
            setTimeout(() => el.classList.add('visible'), 200 + i * 100);
        });

        // ==================== KDE IMPLEMENTATION ====================
        const map = L.map('map').setView([-3.468, 114.832], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        let markers = [];
        let heatmapLayer = null;
        let currentIncidents = <?= json_encode($incidents) ?>;

        function calculateWeight(incident) {
            const useWeight = document.getElementById('weightBySeverity')?.checked ?? true;
            if (!useWeight) return 1;
            return (incident.korban_jiwa * 5) + (incident.korban_luka * 2) + (incident.jumlah_bangunan * 1) + 1;
        }

        const KernelFunctions = {
            gaussian: (u) => (1 / Math.sqrt(2 * Math.PI)) * Math.exp(-0.5 * u * u),
            epanechnikov: (u) => Math.abs(u) <= 1 ? (3 / 4) * (1 - u * u) : 0,
            quartic: (u) => Math.abs(u) <= 1 ? (15 / 16) * Math.pow(1 - u * u, 2) : 0
        };

        function calculateOptimalBandwidth(points) {
            if (points.length === 0) return 0.01;
            const n = points.length;
            const center = {
                lat: points.reduce((sum, p) => sum + parseFloat(p.latitude), 0) / n,
                lng: points.reduce((sum, p) => sum + parseFloat(p.longitude), 0) / n
            };
            const distances = points.map(p => Math.sqrt(Math.pow(parseFloat(p.latitude) - center.lat, 2) + Math.pow(parseFloat(p.longitude) - center.lng, 2)));
            const mean = distances.reduce((a, b) => a + b, 0) / n;
            const variance = distances.reduce((sum, d) => sum + Math.pow(d - mean, 2), 0) / n;
            const stdDev = Math.sqrt(variance);
            let bandwidth = 1.06 * stdDev * Math.pow(n, -0.2);
            return Math.min(Math.max(bandwidth, 0.002), 0.03);
        }

        function computeContinuousKDE(incidents, bounds, bandwidth) {
            if (incidents.length === 0) return [];
            const kernelType = document.getElementById('kdeMethod')?.value || 'gaussian';
            const kernelFunction = KernelFunctions[kernelType];
            const weights = incidents.map(inc => calculateWeight(inc));
            const gridSize = 150;
            const latStep = (bounds.maxLat - bounds.minLat) / gridSize;
            const lngStep = (bounds.maxLng - bounds.minLng) / gridSize;
            const heatmapData = [];
            for (let i = 0; i <= gridSize; i++) {
                const lat = bounds.minLat + i * latStep;
                for (let j = 0; j <= gridSize; j++) {
                    const lng = bounds.minLng + j * lngStep;
                    let density = 0;
                    for (let k = 0; k < incidents.length; k++) {
                        const inc = incidents[k];
                        const distance = Math.sqrt(Math.pow(lat - parseFloat(inc.latitude), 2) + Math.pow(lng - parseFloat(inc.longitude), 2));
                        const u = distance / bandwidth;
                        density += weights[k] * kernelFunction(u);
                    }
                    heatmapData.push([lat, lng, density]);
                }
            }
            const densities = heatmapData.map(d => d[2]);
            const maxDensity = Math.max(...densities);
            const minDensity = Math.min(...densities);
            if (maxDensity === minDensity) return heatmapData;
            return heatmapData.map(d => [d[0], d[1], (d[2] - minDensity) / (maxDensity - minDensity)]);
        }

        function initHeatmapWithKDE(data) {
            if (heatmapLayer) map.removeLayer(heatmapLayer);
            markers.forEach(marker => map.removeLayer(marker));
            markers = [];
            data.forEach(incident => {
                const weight = calculateWeight(incident);
                const severityColor = weight > 5 ? '#ff0000' : (weight > 3 ? '#ffaa00' : '#00ff00');
                const marker = L.circleMarker([parseFloat(incident.latitude), parseFloat(incident.longitude)], {
                    radius: Math.min(6, 3 + weight / 3),
                    color: severityColor,
                    weight: 1.5,
                    opacity: 0.6,
                    fillColor: severityColor,
                    fillOpacity: 0.3
                }).bindPopup(`
                    <strong>Kejadian Kebakaran</strong><br>
                    Waktu: ${new Date(incident.waktu).toLocaleString('id-ID')}<br>
                    Lokasi: ${incident.alamat}<br>
                    Kecamatan: ${incident.kecamatan}<br>
                    Korban Luka: ${incident.korban_luka}<br>
                    Korban Jiwa: ${incident.korban_jiwa}
                `);
                marker.addTo(map);
                markers.push(marker);
            });
            if (data.length === 0) {
                document.getElementById('kdeInfo').innerHTML = '<i class="fas fa-exclamation-triangle"></i> Tidak ada data';
                return;
            }
            let bandwidth = parseFloat(document.getElementById('bandwidthSlider')?.value || 0);
            if (bandwidth === 0 || isNaN(bandwidth)) bandwidth = calculateOptimalBandwidth(data);
            document.getElementById('bandwidthValue').textContent = bandwidth.toFixed(4);
            let minLat = Math.min(...data.map(d => parseFloat(d.latitude)));
            let maxLat = Math.max(...data.map(d => parseFloat(d.latitude)));
            let minLng = Math.min(...data.map(d => parseFloat(d.longitude)));
            let maxLng = Math.max(...data.map(d => parseFloat(d.longitude)));
            const latPadding = (maxLat - minLat) * 0.2;
            const lngPadding = (maxLng - minLng) * 0.2;
            const bounds = {
                minLat: minLat - latPadding,
                maxLat: maxLat + latPadding,
                minLng: minLng - lngPadding,
                maxLng: maxLng + lngPadding
            };
            const kdeData = computeContinuousKDE(data, bounds, bandwidth);
            const radius = parseInt(document.getElementById('radiusSlider')?.value || 25);
            const blur = parseInt(document.getElementById('blurSlider')?.value || 15);
            heatmapLayer = L.heatLayer(kdeData, {
                radius: radius,
                blur: blur,
                maxZoom: 18,
                minOpacity: 0.4,
                gradient: {
                    0.0: '#00ff00',
                    0.2: '#88ff00',
                    0.4: '#ccff00',
                    0.5: '#ffff00',
                    0.6: '#ffcc00',
                    0.7: '#ff9900',
                    0.8: '#ff6600',
                    1.0: '#ff0000'
                }
            }).addTo(map);
            const kernelType = document.getElementById('kdeMethod')?.value || 'gaussian';
            document.getElementById('kdeInfo').innerHTML = `<i class="fas fa-chart-line"></i> KDE: ${kernelType.toUpperCase()} | BW: ${bandwidth.toFixed(4)} | Points: ${data.length}`;
            if (data.length > 0) {
                const latLngs = data.map(d => [parseFloat(d.latitude), parseFloat(d.longitude)]);
                map.fitBounds(L.latLngBounds(latLngs).pad(0.15));
            }
        }

        function updateTable(data) {
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '';
            data.forEach((incident, index) => {
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${new Date(incident.waktu).toLocaleString('id-ID')}</td>
                    <td>${incident.alamat.substring(0, 40)}...</td>
                    <td>${incident.kecamatan}</td>
                    <td>${incident.kelurahan || '-'}</td>
                    <td><span class="badge" style="background: rgba(247, 184, 1, 0.15); color: #F7B801;">${incident.korban_luka}</span></td>
                    <td><span class="badge" style="background: rgba(220, 53, 69, 0.15); color: #ff6b6b;">${incident.korban_jiwa}</span></td>
                    <td>${incident.jumlah_bangunan}</td>
                `;
            });
        }

        function applyFilter() {
            const kecamatan = document.getElementById('filterKecamatan').value;
            const periode = document.getElementById('filterPeriode').value;
            document.getElementById('loadingOverlay').style.display = 'flex';
            fetch(`../api/get-kejadian.php?kecamatan=${kecamatan}&periode=${periode}`)
                .then(response => response.json())
                .then(data => {
                    currentIncidents = data;
                    initHeatmapWithKDE(data);
                    updateTable(data);
                    document.getElementById('loadingOverlay').style.display = 'none';
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('loadingOverlay').style.display = 'none';
                });
        }

        function updateHeatmap() {
            document.getElementById('loadingOverlay').style.display = 'flex';
            setTimeout(() => {
                initHeatmapWithKDE(currentIncidents);
                document.getElementById('loadingOverlay').style.display = 'none';
            }, 100);
            document.getElementById('radiusValue').textContent = document.getElementById('radiusSlider').value;
            document.getElementById('blurValue').textContent = document.getElementById('blurSlider').value;
        }

        function initCharts() {
            const kecamatanData = <?= json_encode($kecamatanData) ?>;
            const ctx1 = document.getElementById('kecamatanChart').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: kecamatanData.map(d => d.kecamatan),
                    datasets: [{
                        label: 'Jumlah Kejadian',
                        data: kecamatanData.map(d => d.total),
                        backgroundColor: '#F7B801',
                        borderRadius: 8,
                        barPercentage: 0.7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#fff',
                                font: {
                                    family: 'Poppins'
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                color: '#fff'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#fff'
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            const trendData = <?= json_encode($trendData) ?>;
            const ctx2 = document.getElementById('trendChart').getContext('2d');
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: trendData.map(d => d.bulan),
                    datasets: [{
                        label: 'Jumlah Kejadian',
                        data: trendData.map(d => d.total),
                        borderColor: '#F7B801',
                        backgroundColor: 'rgba(247, 184, 1, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#F7B801',
                        pointBorderColor: '#0D0D0D',
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#fff',
                                font: {
                                    family: 'Poppins'
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                color: '#fff'
                            },
                            grid: {
                                color: 'rgba(255,255,255,0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#fff',
                                rotation: -45
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (currentIncidents.length > 0) initHeatmapWithKDE(currentIncidents);
            initCharts();
            document.getElementById('radiusSlider').addEventListener('input', () => document.getElementById('radiusValue').textContent = document.getElementById('radiusSlider').value);
            document.getElementById('blurSlider').addEventListener('input', () => document.getElementById('blurValue').textContent = document.getElementById('blurSlider').value);
            document.getElementById('bandwidthSlider').addEventListener('input', () => document.getElementById('bandwidthValue').textContent = document.getElementById('bandwidthSlider').value);
            document.getElementById('weightBySeverity').addEventListener('change', () => updateHeatmap());
            document.getElementById('kdeMethod').addEventListener('change', () => updateHeatmap());
        });
    </script>
</body>

</html>