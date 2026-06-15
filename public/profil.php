<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

$conn = getConnection();

// Ambil data BPK dengan informasi anggota
$bpk_query = "
    SELECT 
        b.*,
        COUNT(DISTINCT a.id) as total_anggota,
        GROUP_CONCAT(DISTINCT a.nama ORDER BY a.jabatan = 'Ketua' DESC SEPARATOR '||') as anggota_list
    FROM bpk b
    LEFT JOIN anggota a ON a.bpk_id = b.id AND a.status = 'aktif'
    GROUP BY b.id
    ORDER BY b.nama_bpk
";
$bpk_result = $conn->query($bpk_query);
$bpk_list = [];
while ($row = $bpk_result->fetch_assoc()) {
    $anggota_names = [];
    if ($row['anggota_list']) {
        $anggota_names = explode('||', $row['anggota_list']);
    }
    $row['anggota_count'] = $row['total_anggota'];
    $row['anggota_names'] = $anggota_names;
    $bpk_list[] = $row;
}
$total_bpk = count($bpk_list);
// Ambil statistik total anggota
$total_anggota_query = "SELECT COUNT(*) as total FROM anggota WHERE status = 'aktif'";
$total_anggota_result = $conn->query($total_anggota_query);
$total_anggota = $total_anggota_result->fetch_assoc()['total'] ?? 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - BARRES 698</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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

        /* Hero Section Profil */
        .page-hero {
            background: var(--jet-black);
            padding: 140px 0 80px;
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

        /* Section Umum */
        .section {
            padding: 80px 0;
        }

        .section-dark {
            background: var(--jet-black);
            color: var(--off-white);
        }

        .section-light {
            background: var(--off-white);
        }

        .section-label {
            font-family: 'DM Mono', monospace;
            font-size: .68rem;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(247, 184, 1, 0.3);
            max-width: 60px;
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: clamp(1.8rem, 3.5vw, 2.8rem);
            line-height: 1.2;
            margin-bottom: 24px;
        }

        .section-dark .section-title {
            color: #fff;
        }

        .section-light .section-title {
            color: var(--jet-black);
        }

        .section-text {
            font-size: 1rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, .6);
        }

        .section-light .section-text {
            color: #4a4540;
        }

        /* Vision & Mission Cards */
        .vm-card {
            background: var(--dark-grey);
            border-radius: 20px;
            padding: 32px;
            border: 1px solid rgba(247, 184, 1, 0.12);
            transition: all .3s;
            height: 100%;
        }

        .vm-card:hover {
            transform: translateY(-5px);
            border-color: rgba(247, 184, 1, 0.3);
        }

        .vm-icon {
            width: 60px;
            height: 60px;
            background: rgba(247, 184, 1, 0.12);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }

        .vm-icon i {
            font-size: 28px;
            color: var(--gold);
        }

        .vm-card h3 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--gold);
            margin-bottom: 16px;
        }

        .vm-card p,
        .vm-card li {
            color: rgba(255, 255, 255, .7);
            line-height: 1.7;
        }

        .vm-card ul {
            padding-left: 20px;
        }

        .vm-card li {
            margin-bottom: 10px;
        }

        /* Stats Row */
        .stats-row {
            display: flex;
            gap: 24px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 40px;
        }

        .stat-mini-card {
            background: rgba(247, 184, 1, 0.08);
            border: 1px solid rgba(247, 184, 1, 0.2);
            border-radius: 20px;
            padding: 24px 32px;
            text-align: center;
            min-width: 180px;
        }

        .stat-mini-card .number {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            color: var(--gold);
        }

        .stat-mini-card .label {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: rgba(255, 255, 255, .5);
        }

        /* BPK Grid */
        .bpk-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 40px;
        }

        .bpk-card {
            background: var(--dark-grey);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid rgba(247, 184, 1, 0.12);
            transition: all .3s;
        }

        .bpk-card:hover {
            border-color: rgba(247, 184, 1, 0.3);
            transform: translateY(-4px);
        }

        .bpk-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .bpk-icon {
            width: 55px;
            height: 55px;
            background: rgba(247, 184, 1, 0.12);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bpk-icon i {
            font-size: 24px;
            color: var(--gold);
        }

        .bpk-title h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
        }

        .bpk-title p {
            font-size: .75rem;
            color: var(--gold);
            margin-bottom: 0;
        }

        .bpk-detail-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: .85rem;
            color: rgba(255, 255, 255, .6);
            margin-bottom: 12px;
        }

        .bpk-detail-item i {
            color: var(--gold);
            width: 20px;
        }

        .anggota-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 12px;
        }

        .anggota-tag {
            background: rgba(247, 184, 1, 0.1);
            border-radius: 12px;
            padding: 3px 10px;
            font-size: .7rem;
            color: rgba(255, 255, 255, .7);
        }

        /* Footer */
        .site-footer {
            background: var(--jet-black);
            padding: 60px 0 32px;
            border-top: 1px solid rgba(247, 184, 1, 0.1);
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
            <a href="profil.php" class="active">Profil</a>
            <a href="peta-statistik.php">Peta & Statistik</a>
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
            <div class="hero-badge reveal">Tentang Kami</div>
            <h1 class="reveal">Profil <span style="color: var(--gold);">BARRES 698</span></h1>
            <p class="lead reveal">Banjarbaru Rescue 698 — Organisasi relawan kebakaran yang berdedikasi melayani masyarakat Kota Banjarbaru</p>
        </div>
    </section>

    <!-- SEJARAH SECTION -->
    <section class="section section-light">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-6 reveal">
                    <div class="section-label">Perjalanan Kami</div>
                    <h2 class="section-title">Sejarah <span style="color: var(--gold);">BARRES 698</span></h2>
                    <p class="section-text" style="color: #4a4540;">
                        BARRES 698 didirikan pada tahun 2015 sebagai organisasi relawan kebakaran yang berdedikasi
                        untuk membantu masyarakat Kota Banjarbaru dalam penanggulangan kebakaran. Dengan semangat
                        kerelawanan dan profesionalisme, BARRES 698 terus berkembang menjadi organisasi yang disegani
                        di Kalimantan Selatan.
                    </p>
                    <div class="stats-row">
                        <div class="stat-mini-card">
                            <div class="number">2015</div>
                            <div class="label">Tahun Berdiri</div>
                        </div>
                        <div class="stat-mini-card">
                            <div class="number"><?= $total_bpk ?></div>
                            <div class="label">Unit BPK</div>
                        </div>
                        <div class="stat-mini-card">
                            <div class="number"><?= $total_anggota ?></div>
                            <div class="label">Anggota Aktif</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 reveal">
                    <img src="https://placehold.co/600x400/2A2A2A/F7B801?text=BARRES+698" alt="BARRES 698" class="img-fluid rounded-4" style="border: 1px solid rgba(247, 184, 1, 0.2);">
                </div>
            </div>
        </div>
    </section>

    <!-- VISI & MISI SECTION -->
    <section class="section section-dark">
        <div class="container">
            <div class="row justify-content-center text-center reveal">
                <div class="col-lg-6">
                    <div class="section-label" style="justify-content: center;">Visi & Misi</div>
                    <h2 class="section-title">Tujuan <span style="color: var(--gold);">Kami</span></h2>
                    <p class="section-text" style="margin: 0 auto;">Landasan utama dalam menjalankan tugas dan pengabdian kepada masyarakat</p>
                </div>
            </div>

            <div class="row mt-5 gy-4">
                <div class="col-md-6 reveal">
                    <div class="vm-card">
                        <div class="vm-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h3>Visi</h3>
                        <p>Menjadi organisasi relawan kebakaran terdepan dalam pelayanan masyarakat dan penanggulangan bencana kebakaran di Kota Banjarbaru.</p>
                    </div>
                </div>
                <div class="col-md-6 reveal">
                    <div class="vm-card">
                        <div class="vm-icon">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <h3>Misi</h3>
                        <ul>
                            <li>Memberikan pelayanan cepat tanggap dalam penanggulangan kebakaran</li>
                            <li>Melakukan edukasi dan sosialisasi pencegahan kebakaran kepada masyarakat</li>
                            <li>Membangun kerjasama dengan instansi terkait dalam penanggulangan bencana</li>
                            <li>Meningkatkan kapasitas anggota melalui pelatihan berkelanjutan</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- STRUKTUR ORGANISASI SECTION -->
    <section class="section section-light">
        <div class="container">
            <div class="row justify-content-center text-center reveal">
                <div class="col-lg-6">
                    <div class="section-label" style="justify-content: center;">Struktur Organisasi</div>
                    <h2 class="section-title">Unit <span style="color: var(--gold);">BPK</span> Aktif</h2>
                    <p class="section-text" style="color: #4a4540; margin: 0 auto;">Barisan Pemadam Kebakaran yang tersebar di seluruh wilayah Kota Banjarbaru</p>
                </div>
            </div>

            <div class="bpk-grid reveal">
                <?php foreach ($bpk_list as $bpk): ?>
                    <div class="bpk-card">
                        <div class="bpk-header">
                            <div class="bpk-icon">
                                <i class="fas fa-fire-extinguisher"></i>
                            </div>
                            <div class="bpk-title">
                                <h4><?= htmlspecialchars($bpk['nama_bpk'] ?? $bpk['kecamatan']) ?></h4>
                                <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($bpk['kecamatan'] ?? '-') ?></p>
                            </div>
                        </div>
                        <div class="bpk-detail-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Tahun Berdiri: <?= htmlspecialchars($bpk['tahun_berdiri'] ?? '-') ?></span>
                        </div>
                        <div class="bpk-detail-item">
                            <i class="fas fa-phone"></i>
                            <span>Kontak: <?= htmlspecialchars($bpk['kontak'] ?? '-') ?></span>
                        </div>
                        <div class="bpk-detail-item">
                            <i class="fas fa-location-dot"></i>
                            <span>Alamat: <?= htmlspecialchars(substr($bpk['alamat'] ?? '-', 0, 50)) ?></span>
                        </div>
                        <?php if ($bpk['anggota_count'] > 0): ?>
                            <div class="anggota-list">
                                <span class="anggota-tag"><i class="fas fa-users"></i> <?= $bpk['anggota_count'] ?> Anggota</span>
                                <?php foreach (array_slice($bpk['anggota_names'], 0, 3) as $anggota): ?>
                                    <span class="anggota-tag"><?= htmlspecialchars(substr($anggota, 0, 15)) ?></span>
                                <?php endforeach; ?>
                                <?php if ($bpk['anggota_count'] > 3): ?>
                                    <span class="anggota-tag">+<?= $bpk['anggota_count'] - 3 ?> lagi</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($bpk_list)): ?>
                    <div class="text-center py-4" style="color: #666;">Belum ada data BPK</div>
                <?php endif; ?>
            </div>
        </div>
    </section>

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

        // Hero reveals on load
        document.querySelectorAll('.page-hero .reveal').forEach((el, i) => {
            setTimeout(() => el.classList.add('visible'), 200 + i * 100);
        });
    </script>
</body>

</html>