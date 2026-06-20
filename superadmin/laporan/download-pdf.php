<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

checkAuth();
checkRole(['super_admin']);

// Filter parameters
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$filter_kecamatan = isset($_GET['kecamatan']) ? $_GET['kecamatan'] : '';
$filter_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$conn = getConnection();

// Ambil data kejadian
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
$conn->close();

if (!$kejadian) {
    die("Tidak ada data kejadian untuk periode ini");
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kejadian - BARRES 698</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Arial', sans-serif !important;
            background: white;
            color: #000000;
            padding: 20px 40px;
            font-size: 11pt;
            line-height: 1.6;
        }

        body, table, td, div, p, span, h1, h2, h3, h4 {
            font-family: 'Arial', sans-serif !important;
        }

        .container { max-width: 100%; margin: 0 auto; }

       /* ==================== KOP SURAT - LOGO DI KIRI ==================== */
.laporan .kop-surat {
    border-bottom: 2px solid #000000;
    padding-bottom: 10px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
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
    flex: 1;
}

.laporan .kop-surat .kop-text .nama-organisasi {
    font-size: 14pt;
    font-weight: 800;
    color: #000000;
    margin: 0;
    letter-spacing: 0.5px;
    line-height: 1.3;
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
        .surat-info {
            display: flex;
            justify-content: space-between;
            margin: 12px 0 15px 0;
            font-size: 11pt;
        }

        .surat-info .label { font-weight: 700; }

        /* Judul */
        .judul {
            text-align: center;
            margin: 15px 0 15px 0;
            font-weight: 700;
            font-size: 14pt;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Detail Table */
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        .detail-table td {
            padding: 4px 8px;
            border-bottom: 1px dashed #cccccc;
            font-size: 11pt;
            vertical-align: top;
        }

        .detail-table .label {
            font-weight: 700;
            width: 180px;
            color: #000000;
        }

        .detail-table .value { color: #000000; }

        /* Foto */
        .foto-section {
            margin: 15px 0 10px 0;
            padding: 10px;
            text-align: center;
            min-height: 30px;
        }

        .foto-section img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        /* TTD */
        .ttd-section {
            margin-top: 35px;
            text-align: right;
            padding-top: 5px;
        }

        .ttd-section .ttd-place {
            font-size: 11pt;
            color: #000000;
        }

        .ttd-section .ttd-name {
            font-weight: 700;
            font-size: 12pt;
            color: #000000;
            margin-top: 30px;
        }

        .ttd-section .ttd-title {
            font-size: 11pt;
            color: #000000;
        }

        /* Footer */
        .footer-report {
            margin-top: 15px;
            text-align: center;
            font-size: 9pt;
            color: #666666;
            border-top: 1px solid #cccccc;
            padding-top: 10px;
        }

        @media print {
            body { padding: 0; margin: 0; }
            @page { size: A4; margin: 2cm; }
        }

        @media (max-width: 768px) {
            body { padding: 10px; }
            .surat-info { flex-direction: column; gap: 5px; }
            .laporan .kop-surat-table .logo-cell { width: 60px; }
            .laporan .kop-surat-table .logo-cell img { height: 50px; }
            .detail-table .label { width: 120px; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="laporan">

        <!-- KOP SURAT - Logo di Samping Kiri, Teks Sejajar (pakai table, bukan flexbox, agar konsisten saat print/PDF) -->
        <!-- KOP SURAT - LOGO DI KIRI -->
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

        <!-- Judul -->
        <div class="judul">LAPORAN KEJADIAN KEBAKARAN</div>

        <!-- Detail -->
        <table class="detail-table">
            <tr><td class="label">Waktu Kejadian</td><td class="value">: <?= date('d/m/Y H:i', strtotime($kejadian['waktu'])) ?></td></tr>
            <tr><td class="label">Titik Koordinat</td><td class="value">: <?= number_format($kejadian['latitude'] ?? 0, 6) ?>, <?= number_format($kejadian['longitude'] ?? 0, 6) ?></td></tr>
            <tr><td class="label">Alamat</td><td class="value">: <?= htmlspecialchars($kejadian['alamat']) ?></td></tr>
            <tr><td class="label">Kecamatan</td><td class="value">: <?= htmlspecialchars($kejadian['kecamatan'] ?? '-') ?></td></tr>
            <tr><td class="label">Kelurahan</td><td class="value">: <?= htmlspecialchars($kejadian['kelurahan'] ?? '-') ?></td></tr>
            <tr><td class="label">Bangunan Terdampak</td><td class="value">: <?= ($kejadian['jumlah_bangunan'] ?? 0) ?> unit</td></tr>
            <tr><td class="label">Jumlah KK</td><td class="value">: <?= ($kejadian['jumlah_KK'] ?? 0) > 0 ? $kejadian['jumlah_KK'] : '-' ?></td></tr>
            <tr><td class="label">Jumlah Individu</td><td class="value">: <?= ($kejadian['jumlah_individu'] ?? 0) > 0 ? $kejadian['jumlah_individu'] : '-' ?></td></tr>
            <tr><td class="label">Korban Luka/Cedera</td><td class="value">: <?= ($kejadian['korban_luka'] ?? 0) ?> orang</td></tr>
            <tr><td class="label">Korban Jiwa</td><td class="value">: <?= ($kejadian['korban_jiwa'] ?? 0) ?> orang</td></tr>
        </table>

        <!-- Foto hanya jika ada -->
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

    </div>

</div>

<script>
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    }
</script>

</body>
</html>