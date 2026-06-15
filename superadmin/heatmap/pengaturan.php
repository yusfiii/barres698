<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

checkAuth();
checkRole(['super_admin']);

$user = getCurrentUser();

// Ambil pengaturan heatmap
$heatmapSettings = getHeatmapSettings();

// Ambil total BPK untuk sidebar
$conn = getConnection();
$total_bpk = $conn->query("SELECT COUNT(*) as total FROM bpk")->fetch_assoc()['total'];

// Ambil data kejadian untuk preview
$kejadian = $conn->query("SELECT * FROM kejadian_kebakaran ORDER BY waktu DESC");
$totalKejadian = $kejadian->num_rows;

// Statistik untuk KDE
$kecamatanStats = $conn->query("
    SELECT kecamatan, COUNT(*) as total,
           AVG(latitude) as avg_lat, 
           AVG(longitude) as avg_lng
    FROM kejadian_kebakaran 
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL
    GROUP BY kecamatan
");

$conn->close();

// Include sidebar dari folder includes
include __DIR__ . '/../../includes/sidebar.php';

// Proses update pengaturan
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $radius = (int)$_POST['radius'];
    $blur = (int)$_POST['blur'];
    $intensity = (int)$_POST['intensity'];

    if ($radius < 10 || $radius > 50) {
        $error = 'Radius harus antara 10-50';
    } elseif ($blur < 5 || $blur > 30) {
        $error = 'Blur harus antara 5-30';
    } elseif ($intensity < 10 || $intensity > 100) {
        $error = 'Intensitas harus antara 10-100%';
    } else {
        $conn = getConnection();
        $stmt = $conn->prepare("INSERT INTO heatmap_settings (radius, blur, intensity) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $radius, $blur, $intensity);

        if ($stmt->execute()) {
            $success = 'Pengaturan heatmap berhasil disimpan!';
            $heatmapSettings = getHeatmapSettings();
        } else {
            $error = 'Gagal menyimpan pengaturan';
        }
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Heatmap - BARRES 698</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

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

        /* Stats Cards */
        .stat-card {
            background: #FFFFFF;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s ease;
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

        /* Buttons */
        .btn-gold {
            background: linear-gradient(135deg, #F7B801, #E5A800);
            border: none;
            padding: 10px 20px;
            border-radius: 14px;
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

        .btn-outline-gold {
            background: transparent;
            border: 1px solid rgba(247, 184, 1, 0.4);
            padding: 10px 20px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 13px;
            color: #F7B801;
            transition: all 0.2s;
        }

        .btn-outline-gold:hover {
            background: rgba(247, 184, 1, 0.1);
            color: #F7B801;
        }

        .btn-info-custom {
            background: rgba(247, 184, 1, 0.1);
            border: 1px solid rgba(247, 184, 1, 0.3);
            padding: 10px 20px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 13px;
            color: #F7B801;
            transition: all 0.2s;
        }

        .btn-info-custom:hover {
            background: rgba(247, 184, 1, 0.2);
            color: #F7B801;
        }

        /* Map Container */
        .map-container {
            height: 450px;
            width: 100%;
            border-radius: 16px;
            overflow: hidden;
        }

        #heatmapPreview {
            height: 100%;
            width: 100%;
        }

        /* Slider Group */
        .slider-group {
            padding: 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            background: #F8F8F8;
            border: 1px solid #E0E0E0;
        }

        .slider-group label {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 10px;
            color: #1A1A1A;
        }

        .slider-value {
            display: inline-block;
            background: linear-gradient(135deg, #F7B801, #E5A800);
            color: #1A1A1A;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            min-width: 50px;
            text-align: center;
        }

        .range-slider {
            width: 100%;
            height: 6px;
            border-radius: 5px;
            outline: none;
            -webkit-appearance: none;
            appearance: none;
            background: #E0E0E0;
        }

        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #F7B801;
            cursor: pointer;
            border: 2px solid #FFFFFF;
            transition: all 0.2s;
        }

        .range-slider::-webkit-slider-thumb:hover {
            transform: scale(1.15);
        }

        /* Info Box */
        .info-box {
            border-radius: 14px;
            padding: 15px;
            margin-bottom: 15px;
            background: #F8F9FA;
            border-left: 4px solid #F7B801;
        }

        .info-box h6 {
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: #1A1A1A;
        }

        .info-box p {
            font-size: 12px;
            line-height: 1.5;
            color: #666;
        }

        /* Legend */
        .gradient-preview {
            height: 8px;
            border-radius: 4px;
            margin: 12px 0;
            background: linear-gradient(to right, #00cc44, #ffcc00, #ff3300);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: #666;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
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

        .stat-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }

        .bg-high {
            background: rgba(247, 184, 1, 0.15);
            color: #B8860B;
        }

        .bg-medium {
            background: rgba(247, 184, 1, 0.1);
            color: #B8860B;
        }

        .bg-low {
            background: rgba(247, 184, 1, 0.06);
            color: #B8860B;
        }

        /* Alert */
        .alert-custom {
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        .alert-success-custom {
            background: rgba(40, 167, 69, 0.1);
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .alert-danger-custom {
            background: rgba(220, 53, 69, 0.1);
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Preview Overlay */
        .preview-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10;
            border-radius: 16px;
        }

        .preview-overlay.show {
            display: flex;
        }

        hr {
            border-color: #E0E0E0;
            margin: 20px 0;
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
                <h2>Pengaturan Heatmap</h2>
                <p>Konfigurasi visualisasi Kepadatan Titik Kebakaran</p>
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

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert-custom alert-success-custom mb-4">
                <i class="fas fa-check-circle"></i>
                <span><?= $success ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-custom alert-danger-custom mb-4">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?= $totalKejadian ?></div>
                            <div class="stat-label">Total Kejadian</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-fire"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?= $totalKejadian ?></div>
                            <div class="stat-label">Titik Data</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?= $heatmapSettings['radius'] ?? 25 ?></div>
                            <div class="stat-label">Radius (px)</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-expand-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?= $heatmapSettings['intensity'] ?? 70 ?>%</div>
                            <div class="stat-label">Intensitas</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Preview Map -->
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="fas fa-map"></i> Preview Heatmap</h3>
                        <div>
                            <button class="btn-outline-gold" onclick="resetMapView()" style="margin-right: 8px;">
                                <i class="fas fa-sync-alt"></i> Reset View
                            </button>
                            <button class="btn-outline-gold" onclick="fitAllMarkers()">
                                <i class="fas fa-expand"></i> Tampilkan Semua
                            </button>
                        </div>
                    </div>
                    <div class="card-body-custom" style="padding: 20px;">
                        <div class="map-container position-relative">
                            <div id="heatmapPreview"></div>
                            <div class="preview-overlay" id="previewLoading">
                                <div class="text-center">
                                    <div class="spinner-border" style="color: #F7B801;" role="status"></div>
                                    <p class="mt-2 mb-0" style="color: #F7B801;">Memperbarui heatmap...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Panel -->
            <div class="col-lg-4">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="fas fa-sliders-h"></i> Konfigurasi KDE</h3>
                    </div>
                    <div class="card-body-custom" style="padding: 20px;">
                        <form method="POST" id="heatmapForm">
                            <!-- Radius -->
                            <div class="slider-group">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label><i class="fas fa-circle me-1" style="color: #F7B801; font-size: 10px;"></i> Radius</label>
                                    <span class="slider-value" id="radiusDisplay"><?= $heatmapSettings['radius'] ?? 25 ?></span>
                                </div>
                                <input type="range" class="range-slider" id="radiusSlider" name="radius"
                                    min="10" max="50" value="<?= $heatmapSettings['radius'] ?? 25 ?>"
                                    oninput="updateSliderDisplay()">
                                <small class="text-muted">Semakin besar radius, semakin luas area panas</small>
                            </div>

                            <!-- Blur -->
                            <div class="slider-group">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label><i class="fas fa-brush me-1" style="color: #F7B801; font-size: 10px;"></i> Blur</label>
                                    <span class="slider-value" id="blurDisplay"><?= $heatmapSettings['blur'] ?? 15 ?></span>
                                </div>
                                <input type="range" class="range-slider" id="blurSlider" name="blur"
                                    min="5" max="30" value="<?= $heatmapSettings['blur'] ?? 15 ?>"
                                    oninput="updateSliderDisplay()">
                                <small class="text-muted">Semakin tinggi blur, semakin halus gradasi</small>
                            </div>

                            <!-- Intensity -->
                            <div class="slider-group">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label><i class="fas fa-sun me-1" style="color: #F7B801; font-size: 10px;"></i> Intensitas</label>
                                    <span class="slider-value" id="intensityDisplay"><?= $heatmapSettings['intensity'] ?? 70 ?>%</span>
                                </div>
                                <input type="range" class="range-slider" id="intensitySlider" name="intensity"
                                    min="10" max="100" value="<?= $heatmapSettings['intensity'] ?? 70 ?>"
                                    oninput="updateSliderDisplay()">
                                <small class="text-muted">Intensitas warna pada heatmap (10-100%)</small>
                            </div>

                            <hr>

                            <div class="d-grid gap-2">
                                <button type="button" class="btn-info-custom" onclick="previewHeatmap()">
                                    <i class="fas fa-eye"></i> Preview Heatmap
                                </button>
                                <button type="submit" class="btn-gold">
                                    <i class="fas fa-save"></i> Simpan Pengaturan
                                </button>
                                <button type="button" class="btn-outline-gold" onclick="resetToDefault()">
                                    <i class="fas fa-undo-alt"></i> Reset ke Default
                                </button>
                            </div>

                            <!-- Legend -->
                            <div class="mt-4 pt-3">
                                <h6 class="mb-2" style="font-weight: 600; color: #1A1A1A;">
                                    <i class="fas fa-palette me-1" style="color: #F7B801;"></i> Legenda Warna
                                </h6>
                                <div class="gradient-preview"></div>
                                <div class="d-flex justify-content-between mt-2">
                                    <div class="legend-item">
                                        <div class="legend-color" style="background: #00cc44;"></div>
                                        <span>Rendah</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background: #ffcc00;"></div>
                                        <span>Sedang</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background: #ff3300;"></div>
                                        <span>Tinggi</span>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- KDE Info -->
                <div class="card-custom mt-3">
                    <div class="card-header-custom">
                        <h3><i class="fas fa-info-circle"></i> Tentang KDE</h3>
                    </div>
                    <div class="card-body-custom" style="padding: 20px;">
                        <div class="info-box">
                            <h6><i class="fas fa-chart-line me-1"></i> Kernel Density Estimation</h6>
                            <p>Metode statistik untuk mengestimasi fungsi kepadatan probabilitas dari titik data spasial.</p>
                        </div>
                        <div class="info-box">
                            <h6><i class="fas fa-circle me-1"></i> Radius</h6>
                            <p>Jarak pengaruh setiap titik terhadap area sekitarnya. Radius besar = area panas lebih luas.</p>
                        </div>
                        <div class="info-box">
                            <h6><i class="fas fa-brush me-1"></i> Blur</h6>
                            <p>Tingkat kehalusan transisi antar titik. Semakin tinggi blur, gradasi semakin halus.</p>
                        </div>
                        <div class="info-box">
                            <h6><i class="fas fa-sun me-1"></i> Intensitas</h6>
                            <p>Kekuatan warna heatmap. Intensitas tinggi = warna lebih pekat pada area padat.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Table -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="fas fa-chart-bar"></i> Statistik per Kecamatan</h3>
                    </div>
                    <div class="card-body-custom p-0">
                        <div class="table-responsive">
                            <table class="table-custom">
                                <thead>
                                    <tr>
                                        <th>Kecamatan</th>
                                        <th>Jumlah Kejadian</th>
                                        <th>Koordinat Rata-rata</th>
                                        <th>Tingkat Kepadatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($kecamatanStats && $kecamatanStats->num_rows > 0):
                                        mysqli_data_seek($kecamatanStats, 0);
                                        while ($row = $kecamatanStats->fetch_assoc()):
                                            $density = '';
                                            $densityClass = '';
                                            if ($row['total'] >= 5) {
                                                $density = 'Tinggi';
                                                $densityClass = 'bg-high';
                                            } elseif ($row['total'] >= 3) {
                                                $density = 'Sedang';
                                                $densityClass = 'bg-medium';
                                            } else {
                                                $density = 'Rendah';
                                                $densityClass = 'bg-low';
                                            }
                                    ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($row['kecamatan']) ?></strong></td>
                                                <td><span class="stat-badge <?= $densityClass ?>"><?= $row['total'] ?> kejadian</span></td>
                                                <td>
                                                    <small><?= number_format($row['avg_lat'], 6) ?> , <?= number_format($row['avg_lng'], 6) ?></small>
                                                </td>
                                                <td><span class="<?= $densityClass ?> stat-badge"><?= $density ?></span></td>
                                            </tr>
                                        <?php
                                        endwhile;
                                    else:
                                        ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <i class="fas fa-database fa-2x mb-2 d-block" style="color: #999;"></i>
                                                Belum ada data kejadian
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Initialize map
        const map = L.map('heatmapPreview').setView([-3.468, 114.832], 12);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);

        let heatmapLayer = null;
        let markers = [];

        // Data kejadian dari PHP
        const kejadianData = <?php
                                if ($kejadian && $kejadian->num_rows > 0) {
                                    mysqli_data_seek($kejadian, 0);
                                    $data = [];
                                    while ($row = $kejadian->fetch_assoc()) {
                                        if ($row['latitude'] && $row['longitude']) {
                                            $data[] = [
                                                'lat' => (float)$row['latitude'],
                                                'lng' => (float)$row['longitude'],
                                                'waktu' => $row['waktu'],
                                                'alamat' => $row['alamat'],
                                                'kecamatan' => $row['kecamatan']
                                            ];
                                        }
                                    }
                                    echo json_encode($data);
                                } else {
                                    echo '[]';
                                }
                                ?>;

        // Initialize heatmap
        function initHeatmap(radius, blur, intensity) {
            if (heatmapLayer) {
                map.removeLayer(heatmapLayer);
            }
            markers.forEach(m => map.removeLayer(m));
            markers = [];

            if (kejadianData.length === 0) return;

            // Add markers with gold accent
            kejadianData.forEach(data => {
                const marker = L.circleMarker([data.lat, data.lng], {
                    radius: 5,
                    fillColor: '#F7B801',
                    color: '#FFFFFF',
                    weight: 1.5,
                    fillOpacity: 0.8
                }).bindPopup(`
                    <div style="font-family: 'Poppins', sans-serif;">
                        <strong style="color: #F7B801;">Kejadian Kebakaran</strong><br>
                        <small>${new Date(data.waktu).toLocaleString('id-ID')}</small><br>
                        ${data.alamat ? (data.alamat.substring(0, 80) + (data.alamat.length > 80 ? '...' : '')) : '-'}<br>
                        <em>${data.kecamatan || '-'}</em>
                    </div>
                `);
                marker.addTo(map);
                markers.push(marker);
            });

            // Heatmap data
            const heatData = kejadianData.map(d => [d.lat, d.lng, 1]);

            // Adjust max value based on intensity
            const maxVal = Math.max(1.2, 1.5 * (70 / intensity));

            // Create heatmap with gold gradient
            heatmapLayer = L.heatLayer(heatData, {
                radius: radius,
                blur: blur,
                maxZoom: 18,
                max: maxVal,
                gradient: {
                    0.2: '#00cc44',
                    0.5: '#ffcc00',
                    0.8: '#ff6600',
                    1.0: '#ff3300'
                }
            }).addTo(map);
        }

        // Update slider displays
        function updateSliderDisplay() {
            document.getElementById('radiusDisplay').textContent = document.getElementById('radiusSlider').value;
            document.getElementById('blurDisplay').textContent = document.getElementById('blurSlider').value;
            document.getElementById('intensityDisplay').textContent = document.getElementById('intensitySlider').value + '%';
        }

        // Preview heatmap
        function previewHeatmap() {
            const loading = document.getElementById('previewLoading');
            loading.classList.add('show');

            const radius = parseInt(document.getElementById('radiusSlider').value);
            const blur = parseInt(document.getElementById('blurSlider').value);
            const intensity = parseInt(document.getElementById('intensitySlider').value);

            setTimeout(() => {
                if (kejadianData.length > 0) {
                    initHeatmap(radius, blur, intensity);
                }
                loading.classList.remove('show');
            }, 300);
        }

        // Reset to default values
        function resetToDefault() {
            document.getElementById('radiusSlider').value = 25;
            document.getElementById('blurSlider').value = 15;
            document.getElementById('intensitySlider').value = 70;
            updateSliderDisplay();
            previewHeatmap();
        }

        // Reset map view
        function resetMapView() {
            map.setView([-3.468, 114.832], 12);
        }

        // Fit all markers
        function fitAllMarkers() {
            if (kejadianData.length > 0) {
                const bounds = kejadianData.map(d => [d.lat, d.lng]);
                map.fitBounds(bounds, {
                    padding: [30, 30]
                });
            }
        }

        // Event Listeners
        document.getElementById('userAvatar').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('dropdownMenu').classList.toggle('show');
        });

        document.addEventListener('click', function() {
            document.getElementById('dropdownMenu').classList.remove('show');
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            if (kejadianData.length > 0) {
                const radius = parseInt(document.getElementById('radiusSlider').value);
                const blur = parseInt(document.getElementById('blurSlider').value);
                const intensity = parseInt(document.getElementById('intensitySlider').value);
                initHeatmap(radius, blur, intensity);
                setTimeout(() => fitAllMarkers(), 500);
            }
        });
    </script>
</body>

</html>