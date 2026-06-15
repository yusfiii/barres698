<?php
// public/index.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

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
    ORDER BY b.kecamatan
";
$bpk_result = $conn->query($bpk_query);
$bpk_list = [];
while ($row = $bpk_result->fetch_assoc()) {
    // Proses anggota list
    $anggota_names = [];
    if ($row['anggota_list']) {
        $anggota_names = explode('||', $row['anggota_list']);
    }
    $row['anggota_count'] = $row['total_anggota'];
    $row['anggota_names'] = $anggota_names;
    $bpk_list[] = $row;
}
$total_bpk = count($bpk_list);

// Ambil data statistik dari kejadian kebakaran
$stats_query = "
    SELECT 
        COUNT(*) as total_kejadian,
        COALESCE(SUM(korban_luka), 0) as total_luka,
        COALESCE(SUM(korban_jiwa), 0) as total_jiwa,
        COALESCE(SUM(jumlah_bangunan), 0) as total_bangunan
    FROM kejadian_kebakaran
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc() ?: ['total_kejadian' => 0, 'total_luka' => 0, 'total_jiwa' => 0, 'total_bangunan' => 0];

// Ambil data kejadian terbaru untuk preview
$recent_incidents = $conn->query("
    SELECT * FROM kejadian_kebakaran 
    ORDER BY waktu DESC 
    LIMIT 5
");

// Ambil statistik per kecamatan untuk visualisasi
$kecamatan_stats = $conn->query("
    SELECT 
        kecamatan,
        COUNT(*) as total_kejadian,
        SUM(jumlah_bangunan) as total_bangunan,
        SUM(korban_luka) as total_luka,
        SUM(korban_jiwa) as total_jiwa
    FROM kejadian_kebakaran
    GROUP BY kecamatan
    ORDER BY total_kejadian DESC
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BARRES 698 – Sistem Informasi Geografis Kebakaran Banjarbaru</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css">
    <style>
        /* ─── ROOT ─────────────────────────────── */
        :root {
            --jet-black: #0D0D0D;
            --dark-grey: #2A2A2A;
            --gold: #F7B801;
            --gold-dark: #E0A600;
            --off-white: #F5F5F5;
            --off-white-dim: #E8E5DF;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--off-white);
            color: var(--jet-black);
            overflow-x: hidden;
        }

        /* ─── NAVBAR ────────────────────────────── */
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
            background: #0D0D0D;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-logo-icon svg {
            width: 20px;
            height: 20px;
            fill: var(--jet-black);
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

        .nav-links a:hover {
            color: #fff;
            background: rgba(255, 255, 255, .07);
        }

        .nav-links a.active {
            color: #fff;
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

        /* Hamburger */
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

        /* ─── BUTTONS ──────────────────────────── */
        .btn-gold {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--jet-black);
            font-weight: 600;
            font-size: .88rem;
            padding: 13px 28px;
            border-radius: 12px;
            border: none;
            text-decoration: none;
            transition: all .3s ease;
            letter-spacing: .3px;
        }

        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(247, 184, 1, 0.35);
            color: var(--jet-black);
        }

        .btn-outline-gold {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: transparent;
            color: var(--gold);
            font-weight: 500;
            font-size: .88rem;
            padding: 12px 26px;
            border-radius: 12px;
            border: 1.5px solid rgba(247, 184, 1, 0.4);
            text-decoration: none;
            transition: all .2s;
        }

        .btn-outline-gold:hover {
            border-color: var(--gold);
            color: var(--gold);
            background: rgba(247, 184, 1, 0.08);
        }

        /* ─── HERO SECTION ──────────────────────── */
        .hero {
            min-height: 100vh;
            background: var(--jet-black);
            display: grid;
            grid-template-columns: 1fr 1fr;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.04'/%3E%3C/svg%3E");
            pointer-events: none;
        }

        .hero::after {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 55%;
            height: 140%;
            background: linear-gradient(160deg, rgba(247, 184, 1, 0.12) 0%, rgba(247, 184, 1, 0.03) 60%, transparent 100%);
            transform: skewX(-8deg);
            z-index: 0;
        }

        .hero-left {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 120px 60px 80px 60px;
        }

        .hero-eyebrow {
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

        .hero-eyebrow::before {
            content: '';
            display: block;
            width: 28px;
            height: 2px;
            background: var(--gold);
        }

        .hero-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: clamp(3.5rem, 7vw, 6rem);
            line-height: .95;
            color: #fff;
            margin-bottom: 28px;
            letter-spacing: -0.5px;
        }

        .hero-title em {
            font-style: normal;
            color: var(--gold);
            display: block;
        }

        .hero-desc {
            font-size: 1rem;
            line-height: 1.75;
            color: rgba(255, 255, 255, .5);
            max-width: 480px;
            margin-bottom: 40px;
            font-weight: 400;
        }

        .hero-desc strong {
            color: var(--gold);
            font-weight: 600;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Hero Right — visual rings */
        .hero-right {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-visual {
            position: relative;
            width: 380px;
            height: 380px;
        }

        .ring {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(247, 184, 1, 0.25);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: ringPulse 3s ease-in-out infinite;
        }

        .ring-1 {
            width: 140px;
            height: 140px;
            animation-delay: 0s;
        }

        .ring-2 {
            width: 240px;
            height: 240px;
            animation-delay: .4s;
            border-color: rgba(247, 184, 1, 0.15);
        }

        .ring-3 {
            width: 340px;
            height: 340px;
            animation-delay: .8s;
            border-color: rgba(247, 184, 1, 0.08);
        }

        @keyframes ringPulse {

            0%,
            100% {
                opacity: .6;
                transform: translate(-50%, -50%) scale(1);
            }

            50% {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1.03);
            }
        }

        .hero-icon-wrap {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 96px;
            height: 96px;
            border-radius: 24px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 60px rgba(247, 184, 1, 0.5);
            animation: iconBreathe 4s ease-in-out infinite;
        }

        .hero-icon-wrap i {
            font-size: 2.6rem;
            color: var(--jet-black);
        }

        @keyframes iconBreathe {

            0%,
            100% {
                box-shadow: 0 0 60px rgba(247, 184, 1, 0.5);
            }

            50% {
                box-shadow: 0 0 90px rgba(247, 184, 1, 0.8);
            }
        }

        /* Floating nodes */
        .node {
            position: absolute;
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(247, 184, 1, 0.15);
            border-radius: 12px;
            padding: 10px 16px;
            font-size: .7rem;
            color: rgba(255, 255, 255, .55);
            font-family: 'DM Mono', monospace;
            white-space: nowrap;
            animation: nodeFloat 6s ease-in-out infinite;
        }

        .node span {
            display: block;
            font-size: .85rem;
            color: var(--gold);
            font-weight: 600;
        }

        .node-1 {
            top: 12%;
            left: -10px;
            animation-delay: 0s;
        }

        .node-2 {
            bottom: 18%;
            left: -20px;
            animation-delay: 1.5s;
        }

        .node-3 {
            top: 8%;
            right: -10px;
            animation-delay: .8s;
        }

        .node-4 {
            bottom: 10%;
            right: -20px;
            animation-delay: 2s;
        }

        @keyframes nodeFloat {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        /* ─── SECTION UMUM ──────────────────────── */
        .section {
            padding: 100px 0;
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

        .section-dark .section-label::after {
            background: rgba(247, 184, 1, 0.25);
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: clamp(2.2rem, 4vw, 3.5rem);
            line-height: 1.1;
            letter-spacing: -0.3px;
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
            color: rgba(255, 255, 255, .55);
            max-width: 500px;
            font-weight: 400;
        }

        .section-light .section-text {
            color: #4a4540;
        }

        /* ─── STATS CARD ───────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-top: 48px;
        }

        .stat-card {
            background: var(--dark-grey);
            border-radius: 20px;
            padding: 32px 24px;
            text-align: center;
            border: 1px solid rgba(247, 184, 1, 0.15);
            transition: all .3s;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            border-color: rgba(247, 184, 1, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .stat-card-icon {
            width: 60px;
            height: 60px;
            background: rgba(247, 184, 1, 0.12);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .stat-card-icon i {
            font-size: 28px;
            color: var(--gold);
        }

        .stat-card-number {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 3.2rem;
            line-height: 1;
            color: var(--gold);
            margin-bottom: 8px;
        }

        .stat-card-label {
            font-size: .85rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .55);
            font-family: 'DM Mono', monospace;
        }

        /* ─── BPK GRID ─────────────────────────── */
        .bpk-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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
            margin-bottom: 16px;
        }

        .bpk-icon {
            width: 55px;
            height: 55px;
            background: rgba(247, 184, 1, 0.12);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
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
            font-family: 'Poppins', sans-serif;
        }

        .bpk-title p {
            font-size: .75rem;
            color: var(--gold);
            margin-bottom: 0;
        }

        .bpk-details {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(247, 184, 1, 0.1);
        }

        .bpk-detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .8rem;
            color: rgba(255, 255, 255, .6);
            margin-bottom: 8px;
        }

        .bpk-detail-item i {
            color: var(--gold);
            width: 20px;
            font-size: .85rem;
        }

        .bpk-anggota {
            margin-top: 12px;
        }

        .bpk-anggota-label {
            font-size: .7rem;
            color: var(--gold);
            font-weight: 600;
            margin-bottom: 6px;
        }

        .anggota-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .anggota-tag {
            background: rgba(247, 184, 1, 0.1);
            border-radius: 12px;
            padding: 3px 10px;
            font-size: .7rem;
            color: rgba(255, 255, 255, .7);
        }

        /* ─── INCIDENTS TABLE ──────────────────── */
        .incidents-table {
            background: var(--dark-grey);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(247, 184, 1, 0.12);
        }

        .incidents-table thead th {
            background: rgba(0, 0, 0, 0.3);
            padding: 16px 20px;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--gold);
            border-bottom: 1px solid rgba(247, 184, 1, 0.2);
            font-family: 'Poppins', sans-serif;
        }

        .incidents-table tbody td {
            padding: 14px 20px;
            font-size: .85rem;
            color: rgba(255, 255, 255, .7);
            border-bottom: 1px solid rgba(247, 184, 1, 0.08);
            font-weight: 400;
        }

        .incidents-table tbody tr:hover {
            background: rgba(247, 184, 1, 0.05);
        }

        .badge-luka {
            background: rgba(247, 184, 1, 0.15);
            color: var(--gold);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: .7rem;
            font-weight: 600;
        }

        .badge-jiwa {
            background: rgba(220, 53, 69, 0.15);
            color: #ff6b6b;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: .7rem;
            font-weight: 600;
        }

        /* Chart Container */
        .chart-container {
            background: var(--dark-grey);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid rgba(247, 184, 1, 0.12);
            margin-top: 40px;
        }

        .chart-container canvas {
            max-height: 300px;
        }

        /* ─── FOOTER ────────────────────────────── */
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
            font-weight: 400;
        }

        .footer-desc {
            font-size: .87rem;
            line-height: 1.75;
            max-width: 300px;
            color: rgba(255, 255, 255, .3);
            font-weight: 400;
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
            font-weight: 400;
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
            text-align: center;
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
            font-weight: 400;
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
            font-size: .85rem;
            text-decoration: none;
            transition: all .2s;
        }

        .footer-socials a:hover {
            background: var(--gold);
            border-color: var(--gold);
            color: var(--jet-black);
        }

        /* ─── REVEAL ANIMATION ──────────────────── */
        .reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity .7s ease, transform .7s ease;
        }

        .reveal.visible {
            opacity: 1;
            transform: none;
        }

        /* ─── RESPONSIVE ────────────────────────── */
        @media (max-width: 992px) {
            .hero {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .hero-left {
                padding: 120px 32px 60px;
            }

            .hero-right {
                padding: 0 32px 80px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

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
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
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
                <img src="../assets/barres2.png" alt="BARRES Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
            </div>
            <div>
                <span class="nav-logo-text">BARRES 698</span>
                <span class="nav-logo-sub">Banjarbaru Rescue</span>
            </div>
        </a>

        <div class="nav-links" id="navLinks">
            <a href="index.php" class="active">Beranda</a>
            <a href="profil.php">Profil</a>
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

    <!-- HERO SECTION -->
    <section class="hero" id="hero">
        <div class="hero-left">
            <div class="hero-eyebrow reveal">Sistem Informasi Geografis</div>
            <h1 class="hero-title reveal">
                Pemetaan<br>Lokasi
                <em>Kebakaran</em>
            </h1>
            <p class="hero-desc reveal">
                Platform SIG berbasis web untuk visualisasi dan analisis persebaran kejadian kebakaran Kota Banjarbaru
                menggunakan metode <strong>Kernel Density Estimation (KDE)</strong>.
            </p>
            <div class="hero-actions reveal">
                <a href="peta-statistik.php" class="btn-gold">
                    <i class="fas fa-map-marked-alt"></i> Buka Peta Interaktif
                </a>
                <a href="#statistik" class="btn-outline-gold">
                    <i class="fas fa-chart-line"></i> Lihat Statistik
                </a>
            </div>
        </div>

        <div class="hero-right reveal">
            <div class="hero-visual">
                <div class="ring ring-1"></div>
                <div class="ring ring-2"></div>
                <div class="ring ring-3"></div>
                <div class="hero-icon-wrap">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="node node-1">Lat / Lng<span>-3.4422 / 114.829</span></div>
                <div class="node node-2">KDE Radius<span>Adaptive</span></div>
                <div class="node node-3">Density<span>High Zone</span></div>
                <div class="node node-4">Bandwidth<span>Silverman</span></div>
            </div>
        </div>
    </section>

    <!-- STATISTIK SECTION -->
    <section class="section section-dark" id="statistik">
        <div class="container">
            <div class="row justify-content-center text-center reveal">
                <div class="col-lg-6">
                    <div class="section-label" style="justify-content: center;">Rekapitulasi Data</div>
                    <h2 class="section-title">Statistik Kebakaran</h2>
                    <p class="section-text" style="margin: 0 auto;">Data kumulatif kejadian kebakaran yang tercatat dalam sistem</p>
                </div>
            </div>

            <div class="stats-grid reveal">
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-fire"></i></div>
                    <div class="stat-card-number"><?= number_format($stats['total_kejadian'] ?? 0, 0, ',', '.') ?></div>
                    <div class="stat-card-label">Total Kejadian</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-user-injured"></i></div>
                    <div class="stat-card-number"><?= number_format($stats['total_luka'] ?? 0, 0, ',', '.') ?></div>
                    <div class="stat-card-label">Korban Luka</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-skull"></i></div>
                    <div class="stat-card-number"><?= number_format($stats['total_jiwa'] ?? 0, 0, ',', '.') ?></div>
                    <div class="stat-card-label">Korban Jiwa</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon"><i class="fas fa-building"></i></div>
                    <div class="stat-card-number"><?= number_format($stats['total_bangunan'] ?? 0, 0, ',', '.') ?></div>
                    <div class="stat-card-label">Bangunan Terdampak</div>
                </div>
            </div>

            <!-- Chart Statistik per Kecamatan -->
            <div class="chart-container reveal">
                <canvas id="kecamatanChart"></canvas>
            </div>
        </div>
    </section>

    <!-- BPK SECTION -->
    <section class="section section-light">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-5 reveal">
                    <div class="section-label">Unit Operasional</div>
                    <h2 class="section-title">Barisan Pemadam Kebakaran (BPK)</h2>
                    <p class="section-text" style="color: #4a4540;">
                        BPK adalah ujung tombak penanganan kebakaran di tingkat kecamatan. Terdiri dari relawan terlatih
                        yang tersebar di seluruh wilayah Kota Banjarbaru.
                    </p>
                    <div style="margin-top: 32px;">
                        <div class="stat-card" style="background: rgba(247, 184, 1, 0.05); border-color: rgba(247, 184, 1, 0.15);">
                            <div class="stat-card-icon" style="background: rgba(247, 184, 1, 0.12);"><i class="fas fa-users"></i></div>
                            <div class="stat-card-number"><?= $total_bpk ?> Unit</div>
                            <div class="stat-card-label">BPK Aktif</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 reveal">
                    <div class="bpk-grid">
                        <?php foreach ($bpk_list as $bpk): ?>
                            <div class="bpk-card">
                                <div class="bpk-header">
                                    <div class="bpk-icon">
                                        <i class="fas fa-fire-extinguisher"></i>
                                    </div>
                                    <div class="bpk-title">
                                        <h4><?= htmlspecialchars($bpk['nama_bpk'] ?? $bpk['kecamatan']) ?></h4>
                                        <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($bpk['kecamatan']) ?></p>
                                    </div>
                                </div>
                                <div class="bpk-details">
                                    <div class="bpk-detail-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Tahun Berdiri: <?= htmlspecialchars($bpk['tahun_berdiri'] ?? '-') ?></span>
                                    </div>
                                    <div class="bpk-detail-item">
                                        <i class="fas fa-phone"></i>
                                        <span>Kontak: <?= htmlspecialchars($bpk['kontak'] ?? '-') ?></span>
                                    </div>
                                    <?php if ($bpk['anggota_count'] > 0): ?>
                                        <div class="bpk-anggota">
                                            <div class="bpk-anggota-label">
                                                <i class="fas fa-user-friends"></i> Anggota (<?= $bpk['anggota_count'] ?>)
                                            </div>
                                            <div class="anggota-list">
                                                <?php foreach (array_slice($bpk['anggota_names'], 0, 4) as $anggota): ?>
                                                    <span class="anggota-tag"><?= htmlspecialchars(substr($anggota, 0, 15)) ?></span>
                                                <?php endforeach; ?>
                                                <?php if ($bpk['anggota_count'] > 4): ?>
                                                    <span class="anggota-tag">+<?= $bpk['anggota_count'] - 4 ?> lagi</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($bpk_list)): ?>
                            <div class="text-center py-4" style="color: #666;">Belum ada data BPK</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- KEJADIAN TERBARU SECTION -->
    <section class="section section-dark">
        <div class="container">
            <div class="row justify-content-center text-center reveal">
                <div class="col-lg-6">
                    <div class="section-label" style="justify-content: center;">Kejadian Terkini</div>
                    <h2 class="section-title">5 Kejadian Terbaru</h2>
                    <p class="section-text" style="margin: 0 auto;">Data kejadian kebakaran terbaru yang tercatat</p>
                </div>
            </div>

            <div class="reveal" style="margin-top: 40px;">
                <div class="incidents-table">
                    <table class="table table-dark table-hover mb-0" style="background: transparent;">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Lokasi</th>
                                <th>Kecamatan</th>
                                <th>Kelurahan</th>
                                <th>Bangunan</th>
                                <th>Luka</th>
                                <th>Jiwa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_incidents && $recent_incidents->num_rows > 0):
                                while ($inc = $recent_incidents->fetch_assoc()):
                            ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($inc['waktu'])) ?></td>
                                        <td><?= htmlspecialchars(substr($inc['alamat'], 0, 40)) ?><?= strlen($inc['alamat']) > 40 ? '...' : '' ?></td>
                                        <td><?= htmlspecialchars($inc['kecamatan'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($inc['kelurahan'] ?? '-') ?></td>
                                        <td><?= $inc['jumlah_bangunan'] ?>学
                                        <td><span class="badge-luka"><?= $inc['korban_luka'] ?></span></td>
                                        <td><span class="badge-jiwa"><?= $inc['korban_jiwa'] ?></span></td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">Belum ada data kejadian</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-4">
                    <a href="peta-statistik.php" class="btn-outline-gold">
                        <i class="fas fa-chart-bar"></i> Lihat Selengkapnya
                    </a>
                </div>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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
        document.querySelectorAll('.hero .reveal').forEach((el, i) => {
            setTimeout(() => el.classList.add('visible'), 200 + i * 100);
        });

        // Chart.js untuk statistik per kecamatan
        <?php
        $kecamatan_labels = [];
        $kecamatan_data = [];
        while ($row = $kecamatan_stats->fetch_assoc()) {
            $kecamatan_labels[] = $row['kecamatan'];
            $kecamatan_data[] = $row['total_kejadian'];
        }
        ?>

        const ctx = document.getElementById('kecamatanChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($kecamatan_labels) ?>,
                datasets: [{
                    label: 'Jumlah Kejadian Kebakaran',
                    data: <?= json_encode($kecamatan_data) ?>,
                    backgroundColor: 'rgba(247, 184, 1, 0.7)',
                    borderColor: '#F7B801',
                    borderWidth: 2,
                    borderRadius: 8,
                    barPercentage: 0.65
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: {
                            color: '#F5F5F5',
                            font: {
                                family: 'Poppins',
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#2A2A2A',
                        titleColor: '#F7B801',
                        bodyColor: '#F5F5F5',
                        borderColor: '#F7B801',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#F5F5F5',
                            font: {
                                family: 'Poppins',
                                size: 11
                            },
                            stepSize: 1
                        },
                        title: {
                            display: true,
                            text: 'Jumlah Kejadian',
                            color: '#F7B801',
                            font: {
                                family: 'Poppins',
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#F5F5F5',
                            font: {
                                family: 'Poppins',
                                size: 11
                            }
                        },
                        title: {
                            display: true,
                            text: 'Kecamatan',
                            color: '#F7B801',
                            font: {
                                family: 'Poppins',
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>