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
$filter_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$conn = getConnection();

// Ambil data kejadian berdasarkan filter
$query = "SELECT * FROM kejadian_kebakaran WHERE 1=1";
$params = [];
$types = "";

if ($filter_id > 0) {
    $query .= " AND id = ?";
    $params[] = $filter_id;
    $types .= "i";
} else {
    if (!empty($filter_bulan)) {
        $query .= " AND DATE_FORMAT(waktu, '%Y-%m') = ?";
        $params[] = $filter_bulan;
        $types .= "s";
    }
    if (!empty($filter_kecamatan)) {
        $query .= " AND kecamatan = ?";
        $params[] = $filter_kecamatan;
        $types .= "s";
    }
}

$query .= " ORDER BY waktu DESC";
$query .= " LIMIT 1";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$kejadian = $result->fetch_assoc();
$stmt->close();

// Ambil semua kejadian untuk dropdown
$all_kejadian = $conn->query("SELECT id, alamat, waktu, kecamatan FROM kejadian_kebakaran ORDER BY waktu DESC");

// List kecamatan untuk filter
$kecamatan_list = $conn->query("SELECT DISTINCT kecamatan FROM kejadian_kebakaran WHERE kecamatan IS NOT NULL ORDER BY kecamatan");

$conn->close();

// Total BPK untuk sidebar
$conn = getConnection();
$total_bpk = $conn->query("SELECT COUNT(*) as total FROM bpk")->fetch_assoc()['total'];
$conn->close();

// Include sidebar
include __DIR__ . '/../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kejadian - BARRES 698</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

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
            padding: 10px 14px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #F7B801;
            box-shadow: 0 0 0 3px rgba(247, 184, 1, 0.1);
            outline: none;
        }

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

        /* Preview Container untuk A4 */
        .preview-container {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 40px 50px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            max-width: 210mm;
            margin: 0 auto;
        }

        /* ==================== STYLE LAPORAN ==================== */
        .laporan {
            font-family: 'Arial', sans-serif !important;
            font-size: 11pt;
            color: #000000;
            line-height: 1.6;
        }

        .laporan table,
        .laporan td,
        .laporan div,
        .laporan p,
        .laporan span,
        .laporan h1,
        .laporan h2,
        .laporan h3,
        .laporan h4 {
            font-family: 'Arial', sans-serif !important;
        }

        /* KOP SURAT - Logo di Samping Kiri */
        .laporan .kop-surat {
            border-bottom: 2px solid #000000;
            padding-bottom: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .laporan .kop-surat .logo-wrapper {
            flex-shrink: 0;
        }

        .laporan .kop-surat .logo-wrapper img {
            height: 65px;
            width: auto;
            display: block;
        }

        .laporan .kop-surat .kop-text {
            text-align: left;
        }

        .laporan .kop-surat .kop-text .nama-organisasi {
            font-size: 14pt;
            font-weight: 800;
            color: #000000;
            margin: 0;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }

        .laporan .kop-surat .kop-text .alamat-kop {
            font-size: 9pt;
            color: #333333;
            margin: 1px 0;
            line-height: 1.4;
        }

        .laporan .kop-surat .kop-text .kontak-kop {
            font-size: 9pt;
            color: #333333;
            margin: 1px 0;
            line-height: 1.4;
        }

        /* Surat Info */
        .laporan .surat-info {
            display: flex;
            justify-content: space-between;
            margin: 12px 0 15px 0;
            font-size: 11pt;
        }

        .laporan .surat-info .label {
            font-weight: 700;
        }

        /* Judul Laporan */
        .laporan .judul {
            text-align: center;
            margin: 15px 0 15px 0;
            font-weight: 700;
            font-size: 14pt;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Detail Table */
        .laporan .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        .laporan .detail-table td {
            padding: 4px 8px;
            border-bottom: 1px dashed #cccccc;
            font-size: 11pt;
            vertical-align: top;
        }

        .laporan .detail-table .label {
            font-weight: 700;
            width: 180px;
            color: #000000;
        }

        .laporan .detail-table .value {
            color: #000000;
        }

        /* Foto */
        .laporan .foto-section {
            margin: 15px 0 10px 0;
            padding: 10px;
            text-align: center;
            min-height: 30px;
        }

        .laporan .foto-section img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        /* TTD */
        .laporan .ttd-section {
            margin-top: 35px;
            text-align: right;
            padding-top: 5px;
        }

        .laporan .ttd-section .ttd-place {
            font-size: 11pt;
            color: #000000;
        }

        .laporan .ttd-section .ttd-name {
            font-weight: 700;
            font-size: 12pt;
            color: #000000;
            margin-top: 30px;
        }

        .laporan .ttd-section .ttd-title {
            font-size: 11pt;
            color: #000000;
        }

        /* Footer */
        .laporan .footer-report {
            margin-top: 15px;
            text-align: center;
            font-size: 9pt;
            color: #666666;
            border-top: 1px solid #cccccc;
            padding-top: 10px;
        }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .no-data i {
            font-size: 48px;
            color: #ddd;
            display: block;
            margin-bottom: 15px;
        }

        .no-data p {
            font-size: 16px;
            color: #666;
        }

        .no-data small {
            font-size: 13px;
            color: #999;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }

            .preview-container {
                padding: 20px;
            }

            .laporan .surat-info {
                flex-direction: column;
                gap: 5px;
            }

            .laporan .kop-surat {
                flex-direction: column;
                text-align: center;
            }

            .laporan .kop-surat .kop-text {
                text-align: center;
            }

            .laporan .detail-table .label {
                width: 120px;
            }

            .filter-section .row {
                flex-direction: column;
                gap: 12px;
            }
        }

        /* Print - A4 */
        @media print {
            * {
                margin: 0;
                padding: 0;
            }

            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .sidebar,
            .top-navbar,
            .dropdown-menu-custom,
            .user-avatar,
            .filter-section {
                display: none !important;
            }

            .preview-container {
                border: none !important;
                box-shadow: none !important;
                padding: 20px 30px !important;
                border-radius: 0 !important;
                max-width: 100% !important;
            }

            .laporan .foto-section img {
                max-width: 150px !important;
                max-height: 150px !important;
            }

            .laporan .detail-table td {
                padding: 3px 6px;
                font-size: 10pt;
            }

            .laporan .kop-surat .logo-wrapper img {
                height: 50px !important;
            }

            @page {
                size: A4;
                margin: 2cm;
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <div id="sidebarContainer">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar no-print">
            <div class="page-title">
                <h2>Laporan Kejadian Kebakaran</h2>
                <p>Preview dan cetak laporan kejadian kebakaran</p>
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

        <div class="dropdown-menu-custom no-print" id="dropdownMenu">
            <a href="../../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <!-- Filter Section -->
        <div class="filter-section no-print">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-search me-1"></i> Pilih Kejadian</label>
                    <select name="id" class="form-select">
                        <option value="">-- Pilih Kejadian Spesifik --</option>
                        <?php
                        if ($all_kejadian && $all_kejadian->num_rows > 0):
                            mysqli_data_seek($all_kejadian, 0);
                            while ($row = $all_kejadian->fetch_assoc()):
                        ?>
                                <option value="<?= $row['id'] ?>" <?= $filter_id == $row['id'] ? 'selected' : '' ?>>
                                    <?= date('d/m/Y', strtotime($row['waktu'])) ?> - <?= htmlspecialchars(substr($row['alamat'], 0, 40)) ?>...
                                </option>
                        <?php endwhile;
                        endif; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><i class="fas fa-calendar me-1"></i> Bulan</label>
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
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-gold">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <?php if ($kejadian): ?>
                            <button type="button" onclick="window.print()" class="btn-success-custom">
                                <i class="fas fa-print"></i> Cetak / PDF
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Preview Laporan -->
        <div class="preview-container laporan" id="laporanPreview">

            <?php if ($kejadian): ?>
                <!-- KOP SURAT - Logo di Samping Kiri -->
                <div class="kop-surat">
                    <div class="logo-wrapper">
                        <img src="../../assets/barres2.png" alt="BARRES 698">
                    </div>
                    <div class="kop-text">
                        <div class="nama-organisasi">BANJARBARU RESCUE "BARRES 698"</div>
                        <div class="alamat-kop">Jl. Zafri Zamzam II Komplek H. KA Ganie No. 06 RT. 013 RW. 003</div>
                        <div class="alamat-kop">Kel. Kemuning Kec. Banjarbaru Selatan, Kota Banjarbaru.</div>
                        <div class="kontak-kop">WhatsApp : 0851 868 14698 / Freq : 15.698.0 Mhz</div>
                        <div class="kontak-kop">E-mail : barres698.banjarbaru@gmail.com</div>
                    </div>
                </div>

                <!-- Surat Info -->
                <div class="surat-info">
                    <div class="left">
                        <span class="label">Nomor</span> : 022/BARRES698/<?= date('m/Y', strtotime($kejadian['waktu'])) ?><br>
                        <span class="label">Lampiran</span> : 1 (satu) berkas
                    </div>
                    <div class="right">
                        Banjarbaru, <?= date('d F Y', strtotime($kejadian['waktu'])) ?>
                    </div>
                </div>

                <!-- Judul Laporan -->
                <div class="judul">LAPORAN KEJADIAN KEBAKARAN</div>

                <!-- Detail Kejadian -->
                <table class="detail-table">
                    <tr>
                        <td class="label">Waktu Kejadian</td>
                        <td class="value">: <?= date('d/m/Y H:i', strtotime($kejadian['waktu'])) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Titik Koordinat</td>
                        <td class="value">: <?= number_format($kejadian['latitude'] ?? 0, 6) ?>, <?= number_format($kejadian['longitude'] ?? 0, 6) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Alamat</td>
                        <td class="value">: <?= htmlspecialchars($kejadian['alamat']) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Kecamatan</td>
                        <td class="value">: <?= htmlspecialchars($kejadian['kecamatan'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Kelurahan</td>
                        <td class="value">: <?= htmlspecialchars($kejadian['kelurahan'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Bangunan Terdampak</td>
                        <td class="value">: <?= ($kejadian['jumlah_bangunan'] ?? 0) ?> unit</td>
                    </tr>
                    <tr>
                        <td class="label">Jumlah KK</td>
                        <td class="value">: <?= ($kejadian['jumlah_KK'] ?? 0) > 0 ? $kejadian['jumlah_KK'] : '-' ?></td>
                    </tr>
                    <tr>
                        <td class="label">Jumlah Individu</td>
                        <td class="value">: <?= ($kejadian['jumlah_individu'] ?? 0) > 0 ? $kejadian['jumlah_individu'] : '-' ?></td>
                    </tr>
                    <tr>
                        <td class="label">Korban Luka/Cedera</td>
                        <td class="value">: <?= ($kejadian['korban_luka'] ?? 0) ?> orang</td>
                    </tr>
                    <tr>
                        <td class="label">Korban Jiwa</td>
                        <td class="value">: <?= ($kejadian['korban_jiwa'] ?? 0) ?> orang</td>
                    </tr>
                </table>

                <!-- Foto (hanya jika ada) -->
                <?php if ($kejadian['foto'] && file_exists('../../uploads/' . $kejadian['foto'])): ?>
                    <div class="foto-section">
                        <img src="../../uploads/<?= $kejadian['foto'] ?>" alt="Foto Kejadian">
                    </div>
                <?php endif; ?>

                <!-- TTD -->
                <div class="ttd-section">
                    <div class="ttd-place">Banjarbaru, <?= date('d F Y', strtotime($kejadian['waktu'])) ?></div>
                    <div class="ttd-name">Kemas Akhmad Rudi Indrajaya</div>
                    <div class="ttd-title">KETUA UMUM BARRES 698</div>
                </div>

                <!-- Footer -->
                <div class="footer-report">
                    Laporan Resmi BARRES 698 - Dicetak pada <?= date('d/m/Y H:i') ?>
                </div>

            <?php else: ?>
                <!-- No Data -->
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>Tidak ada data kejadian untuk periode ini</p>
                    <small>Silakan pilih filter lain atau tambahkan data kejadian terlebih dahulu.</small>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle dropdown
        document.getElementById('userAvatar').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('dropdownMenu').classList.toggle('show');
        });

        document.addEventListener('click', function() {
            document.getElementById('dropdownMenu').classList.remove('show');
        });

        <?php if (isset($_GET['print']) && $_GET['print'] == 1): ?>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 800);
            }
        <?php endif; ?>
    </script>
</body>

</html>