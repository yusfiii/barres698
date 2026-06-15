<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

checkAuth();
checkRole(['super_admin']);

$user = getCurrentUser();

// Filter parameters
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$filter_kecamatan = isset($_GET['kecamatan']) ? $_GET['kecamatan'] : '';
$filter_tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Query laporan dengan filter
$conn = getConnection();

// Total BPK untuk sidebar
$total_bpk = $conn->query("SELECT COUNT(*) as total FROM bpk")->fetch_assoc()['total'];

// Include sidebar dari folder includes
include __DIR__ . '/../../includes/sidebar.php';

// Total keseluruhan
$total_query = "SELECT 
    COALESCE(COUNT(*), 0) as total_kejadian,
    COALESCE(SUM(jumlah_bangunan), 0) as total_bangunan,
    COALESCE(SUM(jumlah_KK), 0) as total_kk,
    COALESCE(SUM(jumlah_individu), 0) as total_individu,
    COALESCE(SUM(korban_luka), 0) as total_luka,
    COALESCE(SUM(korban_jiwa), 0) as total_jiwa
FROM kejadian_kebakaran WHERE 1=1";

$params = [];
$types = "";

if (!empty($filter_bulan)) {
    $total_query .= " AND DATE_FORMAT(waktu, '%Y-%m') = ?";
    $params[] = $filter_bulan;
    $types .= "s";
}

$stmt = $conn->prepare($total_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_stats = $total_result->fetch_assoc();
$stmt->close();

// Data detail kejadian
$detail_query = "SELECT * FROM kejadian_kebakaran WHERE 1=1";
$detail_params = [];
$detail_types = "";

if (!empty($filter_bulan)) {
    $detail_query .= " AND DATE_FORMAT(waktu, '%Y-%m') = ?";
    $detail_params[] = $filter_bulan;
    $detail_types .= "s";
}

if (!empty($filter_kecamatan)) {
    $detail_query .= " AND kecamatan = ?";
    $detail_params[] = $filter_kecamatan;
    $detail_types .= "s";
}

$detail_query .= " ORDER BY waktu DESC";

$stmt = $conn->prepare($detail_query);
if (!empty($detail_params)) {
    $stmt->bind_param($detail_types, ...$detail_params);
}
$stmt->execute();
$detail_kejadian = $stmt->get_result();
$stmt->close();

// Statistik per kecamatan untuk bulan yang dipilih
$kec_query = "
    SELECT 
        kecamatan,
        COUNT(*) as total,
        COALESCE(SUM(jumlah_bangunan), 0) as bangunan,
        COALESCE(SUM(korban_luka), 0) as luka,
        COALESCE(SUM(korban_jiwa), 0) as jiwa
    FROM kejadian_kebakaran 
    WHERE DATE_FORMAT(waktu, '%Y-%m') = ?
    GROUP BY kecamatan
    ORDER BY total DESC";

$stmt = $conn->prepare($kec_query);
$stmt->bind_param("s", $filter_bulan);
$stmt->execute();
$kec_stats = $stmt->get_result();
$stmt->close();

// Statistik bulanan untuk grafik
$monthly_query = "
    SELECT 
        DATE_FORMAT(waktu, '%Y-%m') as bulan,
        COUNT(*) as total,
        COALESCE(SUM(korban_luka), 0) as luka,
        COALESCE(SUM(korban_jiwa), 0) as jiwa
    FROM kejadian_kebakaran 
    WHERE YEAR(waktu) = ?
    GROUP BY DATE_FORMAT(waktu, '%Y-%m')
    ORDER BY bulan";

$stmt = $conn->prepare($monthly_query);
$stmt->bind_param("s", $filter_tahun);
$stmt->execute();
$monthly_stats = $stmt->get_result();
$stmt->close();

// List kecamatan untuk filter
$kecamatan_list = $conn->query("SELECT DISTINCT kecamatan FROM kejadian_kebakaran WHERE kecamatan IS NOT NULL AND kecamatan != '' ORDER BY kecamatan");

// List tahun untuk filter
$tahun_list = $conn->query("SELECT DISTINCT YEAR(waktu) as tahun FROM kejadian_kebakaran WHERE waktu IS NOT NULL ORDER BY tahun DESC");

$conn->close();

// Format bulan untuk tampilan
$bulan_display = date('F Y', strtotime($filter_bulan . '-01'));

// Siapkan data untuk chart
$chart_labels = [];
$chart_totals = [];
$chart_luka = [];
$chart_jiwa = [];

if ($monthly_stats && $monthly_stats->num_rows > 0) {
    mysqli_data_seek($monthly_stats, 0);
    while ($row = $monthly_stats->fetch_assoc()) {
        $chart_labels[] = date('M', strtotime($row['bulan'] . '-01'));
        $chart_totals[] = (int)$row['total'];
        $chart_luka[] = (int)$row['luka'];
        $chart_jiwa[] = (int)$row['jiwa'];
    }
}

// Data untuk pie chart
$kec_labels = [];
$kec_values = [];

if ($kec_stats && $kec_stats->num_rows > 0) {
    mysqli_data_seek($kec_stats, 0);
    while ($row = $kec_stats->fetch_assoc()) {
        $kec_labels[] = $row['kecamatan'];
        $kec_values[] = (int)$row['total'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - BARRES 698</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #D1D5DB;
            background: linear-gradient(135deg, #E5E7EB 0%, #D1D5DB 100%);
            min-height: 100vh;
        }

        .main-content {
            margin-left: 280px;
            padding: 24px 32px;
            min-height: 100vh;
        }

        /* Top Navbar */
        .top-navbar {
            background: #FFFFFF;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            padding: 12px 24px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title h2 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            color: #1A1A1A;
        }

        .page-title p {
            font-size: 13px;
            margin: 4px 0 0 0;
            color: #666;
        }

        .user-info {
            text-align: right;
        }

        .user-info .username {
            font-size: 14px;
            font-weight: 600;
            color: #1A1A1A;
        }

        .user-info .role {
            font-size: 11px;
            color: #F7B801;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #F7B801, #E5A800);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .user-avatar:hover {
            transform: scale(1.05);
        }

        .user-avatar i {
            font-size: 22px;
            color: #1A1A1A;
        }

        .dropdown-menu-custom {
            position: absolute;
            top: 80px;
            right: 32px;
            background: #FFFFFF;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-radius: 16px;
            padding: 12px 0;
            min-width: 180px;
            display: none;
            z-index: 1000;
        }

        .dropdown-menu-custom.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-menu-custom a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 13px;
            color: #333;
        }

        .dropdown-menu-custom a:hover {
            background: rgba(247, 184, 1, 0.1);
            color: #F7B801;
        }

        .dropdown-divider {
            margin: 8px 0;
            border-color: #E0E0E0;
        }

        /* Filter Section */
        .filter-section {
            background: #FFFFFF;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            padding: 20px 24px;
            margin-bottom: 28px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1A1A1A;
        }

        .form-control,
        .form-select {
            background: #F8F8F8;
            border: 1px solid #E0E0E0;
            color: #1A1A1A;
            border-radius: 12px;
            padding: 10px 16px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #F7B801;
            box-shadow: 0 0 0 3px rgba(247, 184, 1, 0.1);
            outline: none;
        }

        /* Buttons */
        .btn-gold {
            background: linear-gradient(135deg, #F7B801, #E5A800);
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            color: #1A1A1A;
            transition: all 0.3s ease;
        }

        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(247, 184, 1, 0.3);
            color: #1A1A1A;
        }

        .btn-success-custom {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            color: #28a745;
            transition: all 0.2s;
        }

        .btn-success-custom:hover {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .btn-info-custom {
            background: rgba(23, 162, 184, 0.1);
            border: 1px solid rgba(23, 162, 184, 0.3);
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            color: #17a2b8;
            transition: all 0.2s;
        }

        .btn-info-custom:hover {
            background: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
        }

        /* Stats Cards */
        .stat-card {
            background: #FFFFFF;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s ease;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: #F7B801;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            background: rgba(247, 184, 1, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
        }

        .stat-icon i {
            font-size: 28px;
            color: #F7B801;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #1A1A1A;
        }

        .stat-label {
            font-size: 13px;
            font-weight: 500;
            color: #666;
        }

        /* Cards */
        .card-custom {
            background: #FFFFFF;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 28px;
        }

        .card-header-custom {
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #FFFFFF;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        .card-header-custom h3 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #F7B801;
        }

        .badge-stats {
            background: rgba(247, 184, 1, 0.1);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #F7B801;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Table */
        .table-custom {
            width: 100%;
            margin-bottom: 0;
            color: #1A1A1A;
        }

        .table-custom thead th {
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 600;
            background: #F8F8F8;
            color: #1A1A1A;
            border-bottom: 1px solid #E0E0E0;
        }

        .table-custom tbody td {
            padding: 12px 16px;
            font-size: 13px;
            vertical-align: middle;
            border-bottom: 1px solid #E0E0E0;
        }

        .table-custom tbody tr:hover {
            background: rgba(247, 184, 1, 0.03);
        }

        .table-custom tfoot td {
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
            background: #F8F8F8;
            border-top: 1px solid #E0E0E0;
        }

        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-rawan {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .badge-waspada {
            background: rgba(255, 193, 7, 0.1);
            color: #e6a000;
        }

        .badge-aman {
            background: rgba(40, 167, 69, 0.1);
            color: #1e7e34;
        }

        .text-muted {
            color: #999 !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }

            .card-header-custom {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .stat-card {
                margin-bottom: 16px;
            }

            .filter-section .row {
                flex-direction: column;
                gap: 12px;
            }
        }

        /* Print styles */
        @media print {

            .sidebar,
            .top-navbar,
            .filter-section,
            .no-print,
            .mobile-toggle,
            .dropdown-menu-custom,
            .user-avatar {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            body {
                background: white !important;
            }

            .card-custom {
                border: 1px solid #ddd !important;
                break-inside: avoid;
            }

            .stat-card {
                border: 1px solid #ddd !important;
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar sudah di-include dari includes/sidebar.php -->

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="page-title">
                <h2>Laporan Kebakaran</h2>
                <p>Rekapitulasi data kejadian kebakaran</p>
            </div>
            <div class="user-dropdown" style="display: flex; align-items: center; gap: 15px;">
                <div class="user-info">
                    <div class="username"><?= htmlspecialchars($user['username']) ?></div>
                    <div class="role">Super Administrator</div>
                </div>
                <div class="user-avatar" id="userAvatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>

        <div class="dropdown-menu-custom" id="dropdownMenu">
            <a href="../../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <!-- Filter Section -->
        <div class="filter-section no-print">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-calendar me-1"></i> Periode Bulan</label>
                    <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($filter_bulan) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-map-marker-alt me-1"></i> Kecamatan</label>
                    <select name="kecamatan" class="form-select">
                        <option value="">Semua Kecamatan</option>
                        <?php
                        if ($kecamatan_list && $kecamatan_list->num_rows > 0):
                            mysqli_data_seek($kecamatan_list, 0);
                            while ($kec = $kecamatan_list->fetch_assoc()):
                        ?>
                                <option value="<?= htmlspecialchars($kec['kecamatan']) ?>" <?= $filter_kecamatan == $kec['kecamatan'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kec['kecamatan']) ?>
                                </option>
                        <?php endwhile;
                        endif; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><i class="fas fa-chart-line me-1"></i> Tahun Grafik</label>
                    <select name="tahun" class="form-select">
                        <?php
                        if ($tahun_list && $tahun_list->num_rows > 0):
                            mysqli_data_seek($tahun_list, 0);
                            while ($thn = $tahun_list->fetch_assoc()):
                        ?>
                                <option value="<?= $thn['tahun'] ?>" <?= $filter_tahun == $thn['tahun'] ? 'selected' : '' ?>>
                                    <?= $thn['tahun'] ?>
                                </option>
                        <?php endwhile;
                        else: ?>
                            <option value="<?= date('Y') ?>"><?= date('Y') ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-gold">
                            <i class="fas fa-filter"></i> Tampilkan
                        </button>
                        <a href="export-pdf.php?bulan=<?= urlencode($filter_bulan) ?>&kecamatan=<?= urlencode($filter_kecamatan) ?>&tahun=<?= urlencode($filter_tahun) ?>"
                            class="btn-success-custom" target="_blank">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </a>
                        <button type="button" onclick="window.print()" class="btn-info-custom">
                            <i class="fas fa-print"></i> Cetak
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-2 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-fire"></i></div>
                    <div class="stat-number"><?= number_format($total_stats['total_kejadian'] ?? 0) ?></div>
                    <div class="stat-label">Total Kejadian</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-building"></i></div>
                    <div class="stat-number"><?= number_format($total_stats['total_bangunan'] ?? 0) ?></div>
                    <div class="stat-label">Bangunan Terdampak</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?= number_format($total_stats['total_individu'] ?? 0) ?></div>
                    <div class="stat-label">Individu Terdampak</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-injured"></i></div>
                    <div class="stat-number"><?= number_format($total_stats['total_luka'] ?? 0) ?></div>
                    <div class="stat-label">Korban Luka</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-skull"></i></div>
                    <div class="stat-number"><?= number_format($total_stats['total_jiwa'] ?? 0) ?></div>
                    <div class="stat-label">Korban Jiwa</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-home"></i></div>
                    <div class="stat-number"><?= number_format($total_stats['total_kk'] ?? 0) ?></div>
                    <div class="stat-label">KK Terdampak</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="fas fa-chart-line"></i> Tren Kebakaran Tahun <?= htmlspecialchars($filter_tahun) ?></h3>
                        <span class="badge-stats"><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($filter_tahun) ?></span>
                    </div>
                    <div class="card-body-custom" style="padding: 20px;">
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="fas fa-chart-pie"></i> Kejadian per Kecamatan</h3>
                        <span class="badge-stats"><?= htmlspecialchars($bulan_display) ?></span>
                    </div>
                    <div class="card-body-custom" style="padding: 20px;">
                        <div class="chart-container">
                            <canvas id="kecamatanChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Kejadian Table -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3><i class="fas fa-list"></i> Detail Kejadian Kebakaran</h3>
                <span class="badge-stats"><i class="fas fa-database"></i> <?= $detail_kejadian->num_rows ?> Data</span>
            </div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Waktu</th>
                                <th>Alamat</th>
                                <th>Kecamatan</th>
                                <th>Kelurahan</th>
                                <th class="text-center">Bangunan</th>
                                <th class="text-center">KK</th>
                                <th class="text-center">Individu</th>
                                <th class="text-center">Luka</th>
                                <th class="text-center">Jiwa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($detail_kejadian && $detail_kejadian->num_rows > 0):
                                $no = 1;
                                mysqli_data_seek($detail_kejadian, 0);
                                while ($row = $detail_kejadian->fetch_assoc()):
                            ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($row['waktu'])) ?></td>
                                        <td><?= htmlspecialchars(substr($row['alamat'], 0, 45)) ?><?= strlen($row['alamat'] ?? '') > 45 ? '...' : '' ?></td>
                                        <td><?= htmlspecialchars($row['kecamatan'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['kelurahan'] ?? '-') ?></td>
                                        <td class="text-center"><?= number_format($row['jumlah_bangunan'] ?? 0) ?></td>
                                        <td class="text-center"><?= number_format($row['jumlah_KK'] ?? 0) ?></td>
                                        <td class="text-center"><?= number_format($row['jumlah_individu'] ?? 0) ?></td>
                                        <td class="text-center">
                                            <?php if (($row['korban_luka'] ?? 0) > 0): ?>
                                                <span class="badge-status badge-waspada"><?= number_format($row['korban_luka']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if (($row['korban_jiwa'] ?? 0) > 0): ?>
                                                <span class="badge-status badge-rawan"><?= number_format($row['korban_jiwa']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">0</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block" style="color: #999;"></i>
                                        <p class="mb-0">Tidak ada data untuk periode ini</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end fw-bold">TOTAL:</td>
                                <td class="text-center fw-bold"><?= number_format($total_stats['total_bangunan'] ?? 0) ?></td>
                                <td class="text-center fw-bold"><?= number_format($total_stats['total_kk'] ?? 0) ?></td>
                                <td class="text-center fw-bold"><?= number_format($total_stats['total_individu'] ?? 0) ?></td>
                                <td class="text-center fw-bold" style="color: #e6a000;"><?= number_format($total_stats['total_luka'] ?? 0) ?></td>
                                <td class="text-center fw-bold" style="color: #dc3545;"><?= number_format($total_stats['total_jiwa'] ?? 0) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Statistik per Kecamatan -->
        <?php if ($kec_stats && $kec_stats->num_rows > 0): ?>
            <div class="card-custom">
                <div class="card-header-custom">
                    <h3><i class="fas fa-chart-bar"></i> Statistik per Kecamatan</h3>
                    <span class="badge-stats"><?= htmlspecialchars($bulan_display) ?></span>
                </div>
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Kecamatan</th>
                                    <th class="text-center">Kejadian</th>
                                    <th class="text-center">Bangunan</th>
                                    <th class="text-center">Korban Luka</th>
                                    <th class="text-center">Korban Jiwa</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                mysqli_data_seek($kec_stats, 0);
                                while ($row = $kec_stats->fetch_assoc()):
                                    $status_class = ($row['total'] ?? 0) >= 5 ? 'badge-rawan' : (($row['total'] ?? 0) >= 3 ? 'badge-waspada' : 'badge-aman');
                                    $status_text = ($row['total'] ?? 0) >= 5 ? 'Rawan' : (($row['total'] ?? 0) >= 3 ? 'Waspada' : 'Aman');
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['kecamatan'] ?? '-') ?></strong></td>
                                        <td class="text-center">
                                            <span class="badge-status <?= $status_class ?>"><?= number_format($row['total'] ?? 0) ?> kejadian</span>
                                        </td>
                                        <td class="text-center"><?= number_format($row['bangunan'] ?? 0) ?></td>
                                        <td class="text-center"><?= number_format($row['luka'] ?? 0) ?></td>
                                        <td class="text-center"><?= number_format($row['jiwa'] ?? 0) ?></td>
                                        <td><span class="badge-status <?= $status_class ?>"><?= $status_text ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        <table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let trendChart, kecamatanChart;

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Data untuk trend chart
            const chartLabels = <?= json_encode($chart_labels) ?>;
            const chartTotals = <?= json_encode($chart_totals) ?>;
            const chartLuka = <?= json_encode($chart_luka) ?>;
            const chartJiwa = <?= json_encode($chart_jiwa) ?>;

            const ctx1 = document.getElementById('trendChart')?.getContext('2d');
            if (ctx1 && chartLabels.length > 0) {
                trendChart = new Chart(ctx1, {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Total Kejadian',
                            data: chartTotals,
                            backgroundColor: 'rgba(247, 184, 1, 0.7)',
                            borderColor: '#F7B801',
                            borderWidth: 2,
                            borderRadius: 8
                        }, {
                            label: 'Korban Luka',
                            data: chartLuka,
                            backgroundColor: 'rgba(255, 193, 7, 0.6)',
                            borderColor: '#ffc107',
                            borderWidth: 2,
                            borderRadius: 8
                        }, {
                            label: 'Korban Jiwa',
                            data: chartJiwa,
                            type: 'line',
                            borderColor: '#dc3545',
                            backgroundColor: 'transparent',
                            borderWidth: 3,
                            pointBackgroundColor: '#dc3545',
                            pointBorderColor: '#FFFFFF',
                            pointRadius: 5,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    color: '#666'
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            },
                            x: {
                                ticks: {
                                    color: '#666'
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#666',
                                    usePointStyle: true,
                                    boxWidth: 10
                                }
                            }
                        }
                    }
                });
            } else if (ctx1) {
                // Tampilkan pesan jika tidak ada data
                ctx1.font = '14px Poppins';
                ctx1.fillStyle = '#999';
                ctx1.textAlign = 'center';
                ctx1.fillText('Belum ada data kejadian untuk tahun ini', ctx1.canvas.width / 2, ctx1.canvas.height / 2);
            }

            // Data untuk pie chart
            const kecLabels = <?= json_encode($kec_labels) ?>;
            const kecValues = <?= json_encode($kec_values) ?>;

            const ctx2 = document.getElementById('kecamatanChart')?.getContext('2d');
            if (ctx2 && kecLabels.length > 0) {
                kecamatanChart = new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: kecLabels,
                        datasets: [{
                            data: kecValues,
                            backgroundColor: ['#F7B801', '#E5A800', '#D49A00', '#C38B00', '#B27C00', '#A16D00', '#906000'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15,
                                    color: '#666'
                                }
                            }
                        }
                    }
                });
            } else if (ctx2) {
                ctx2.font = '14px Poppins';
                ctx2.fillStyle = '#999';
                ctx2.textAlign = 'center';
                ctx2.fillText('Belum ada data kejadian', ctx2.canvas.width / 2, ctx2.canvas.height / 2);
            }
        });

        // Toggle dropdown
        document.getElementById('userAvatar').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('dropdownMenu').classList.toggle('show');
        });

        document.addEventListener('click', function() {
            document.getElementById('dropdownMenu').classList.remove('show');
        });
    </script>
</body>

</html>