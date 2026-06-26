<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/pdf-helper.php';

checkAuth();
checkRole(['super_admin']);

$user = getCurrentUser();

// Filter parameters
$filter_kecamatan = isset($_GET['kecamatan']) ? $_GET['kecamatan'] : '';

$conn = getConnection();

// Ambil data BPK berdasarkan filter
$query = "SELECT * FROM bpk WHERE 1=1";
$params = [];
$types = "";

if (!empty($filter_kecamatan)) {
    $query .= " AND kecamatan = ?";
    $params[] = $filter_kecamatan;
    $types .= "s";
}

$query .= " ORDER BY nomor_registrasi ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$daftar_bpk = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// List kecamatan untuk dropdown filter
$kecamatan_list = $conn->query("SELECT DISTINCT kecamatan FROM bpk WHERE kecamatan IS NOT NULL ORDER BY kecamatan");

$conn->close();

// Total BPK untuk sidebar
$conn = getConnection();
$total_bpk = $conn->query("SELECT COUNT(*) as total FROM bpk")->fetch_assoc()['total'];
$conn->close();

/**
 * Ringkas data JSON fasilitas jadi teks singkat.
 * Format kolom di DB: {"jumlah":2,"keterangan":"Baik","foto":null} atau NULL.
 */
function ringkasFasilitas($json_string)
{
    if (empty($json_string)) {
        return '-';
    }

    $data = json_decode($json_string, true);
    if (!is_array($data) || empty($data['jumlah'])) {
        return '-';
    }

    $teks = $data['jumlah'] . ' unit';
    if (!empty($data['keterangan'])) {
        $teks .= ' (' . $data['keterangan'] . ')';
    }

    return $teks;
}

// Include sidebar
include __DIR__ . '/../../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan BPK - BARRES 698</title>

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

        .btn-pdf-custom {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            color: #dc3545;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-pdf-custom:hover {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
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

            .filter-section .row {
                flex-direction: column;
                gap: 12px;
            }
        }

        /* Print - A4 */
        @media print {
            .sidebar,
            .top-navbar,
            .dropdown-menu-custom,
            .user-avatar,
            .filter-section,
            .no-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
        }

        <?= pdfPreviewCss() ?>
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
                <h2>Laporan BPK</h2>
                <p>Preview dan cetak laporan data BPK terdaftar</p>
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
                <div class="col-md-4">
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
                <div class="col-md-8">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-gold">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <a href="cetak-pdf-bpk.php?kecamatan=<?= urlencode($filter_kecamatan) ?>" target="_blank" class="btn-pdf-custom">
                            <i class="fas fa-file-pdf"></i> Cetak PDF
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Preview Laporan -->
        <?php if (count($daftar_bpk) > 0): ?>
            <?php
            ob_start();
            ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 6%;">No</th>
                        <th style="width: 12%;">No. Registrasi</th>
                        <th style="width: 22%;">Nama BPK/PMK</th>
                        <th style="width: 16%;">Kecamatan</th>
                        <th style="width: 14%;">Kelurahan</th>
                        <th class="center" style="width: 8%;">Tahun Berdiri</th>
                        <th class="center" style="width: 8%;">Anggota</th>
                        <th style="width: 14%;">Fasilitas Pemadam Portable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar_bpk as $i => $bpk): ?>
                        <tr>
                            <td class="center"><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($bpk['nomor_registrasi'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($bpk['nama_bpk'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($bpk['kecamatan'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($bpk['kelurahan'] ?? '-') ?></td>
                            <td class="center"><?= htmlspecialchars($bpk['tahun_berdiri'] ?? '-') ?></td>
                            <td class="center"><?= (int) ($bpk['jumlah_anggota'] ?? 0) ?></td>
                            <td><?= htmlspecialchars(ringkasFasilitas($bpk['fasilitas_pemadam_portable'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top: 10px; font-size: 10pt;">
                Total BPK/PMK terdaftar: <strong><?= count($daftar_bpk) ?></strong>
                <?php if (!empty($filter_kecamatan)): ?>
                    (Kecamatan: <?= htmlspecialchars($filter_kecamatan) ?>)
                <?php endif; ?>
            </p>
            <?php
            $isi_html = ob_get_clean();

            echo pdfPreviewHtml([
                'judul'         => 'LAPORAN DATA BPK',
                'nomor_urut'    => '023',
                'tanggal_acuan' => time(),
                'isi_html'      => $isi_html,
            ]);
            ?>
        <?php else: ?>
            <div class="laporan-preview">
                <?= pdfPreviewNoData('Tidak ada data BPK untuk filter ini', 'Silakan ubah filter kecamatan atau tambahkan data BPK terlebih dahulu.') ?>
            </div>
        <?php endif; ?>

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
    </script>
</body>

</html>