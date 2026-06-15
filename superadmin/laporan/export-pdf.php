<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // Jika menggunakan Composer untuk DomPDF

use Dompdf\Dompdf;
use Dompdf\Options;

checkAuth();
checkRole(['super_admin']);

// Filter parameters
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$filter_kecamatan = isset($_GET['kecamatan']) ? $_GET['kecamatan'] : '';

// Format bulan untuk judul
$bulan_text = date('F Y', strtotime($filter_bulan . '-01'));

$conn = getConnection();

// Total statistics
$total_query = "SELECT 
    COUNT(*) as total_kejadian,
    SUM(jumlah_bangunan) as total_bangunan,
    SUM(jumlah_KK) as total_kk,
    SUM(jumlah_individu) as total_individu,
    SUM(korban_luka) as total_luka,
    SUM(korban_jiwa) as total_jiwa
FROM kejadian_kebakaran WHERE DATE_FORMAT(waktu, '%Y-%m') = ?";

$stmt = $conn->prepare($total_query);
$stmt->bind_param("s", $filter_bulan);
$stmt->execute();
$total_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Detail kejadian
$detail_query = "SELECT * FROM kejadian_kebakaran WHERE DATE_FORMAT(waktu, '%Y-%m') = ?";
$params = [$filter_bulan];
$types = "s";

if (!empty($filter_kecamatan)) {
    $detail_query .= " AND kecamatan = ?";
    $params[] = $filter_kecamatan;
    $types .= "s";
}
$detail_query .= " ORDER BY waktu DESC";

$stmt = $conn->prepare($detail_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$detail = $stmt->get_result();
$stmt->close();

// Kecamatan stats
$kec_query = "SELECT 
    kecamatan,
    COUNT(*) as total,
    SUM(jumlah_bangunan) as bangunan,
    SUM(korban_luka) as luka,
    SUM(korban_jiwa) as jiwa
FROM kejadian_kebakaran 
WHERE DATE_FORMAT(waktu, '%Y-%m') = ?
GROUP BY kecamatan
ORDER BY total DESC";

$stmt = $conn->prepare($kec_query);
$stmt->bind_param("s", $filter_bulan);
$stmt->execute();
$kec_stats = $stmt->get_result();
$stmt->close();

$conn->close();

// Build HTML for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Kebakaran - BARRES 698</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #dc3545;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h2 {
            color: #dc3545;
            margin: 0;
            font-size: 20px;
        }
        .header p {
            margin: 5px 0 0;
            color: #666;
        }
        .info-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px 15px;
            margin-bottom: 20px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .summary-table td {
            padding: 10px;
            border: 1px solid #dee2e6;
            text-align: center;
            width: 25%;
        }
        .summary-table .label {
            font-weight: bold;
            color: #dc3545;
        }
        .summary-table .value {
            font-size: 18px;
            font-weight: bold;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data-table th {
            background: #dc3545;
            color: white;
            padding: 8px 10px;
            font-size: 11px;
            text-align: left;
        }
        table.data-table td {
            padding: 6px 10px;
            border: 1px solid #dee2e6;
            font-size: 11px;
        }
        table.data-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
            font-size: 10px;
            color: #999;
        }
        .page-break {
            page-break-before: always;
        }
        h4 {
            color: #dc3545;
            margin: 20px 0 10px;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            color: white;
        }
        .badge-danger { background: #dc3545; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-success { background: #28a745; }
    </style>
</head>
<body>
    <div class="header">
        <h2>LAPORAN KEJADIAN KEBAKARAN</h2>
        <p>BARRES 698 - Banjarbaru Rescue 698</p>
        <p>Periode: ' . $bulan_text . '</p>
        ' . (!empty($filter_kecamatan) ? '<p>Kecamatan: ' . htmlspecialchars($filter_kecamatan) . '</p>' : '') . '
        <p>Tanggal Cetak: ' . date('d F Y') . '</p>
    </div>
    
    <div class="info-box">
        <strong>Ringkasan Laporan:</strong> 
        Laporan ini berisi data kejadian kebakaran yang terjadi di Kota Banjarbaru 
        selama periode <strong>' . $bulan_text . '</strong>.
    </div>
    
    <h4>A. Ringkasan Statistik</h4>
    <table class="summary-table">
        <tr>
            <td class="label">Total Kejadian</td>
            <td class="label">Bangunan Terdampak</td>
            <td class="label">Korban Luka</td>
            <td class="label">Korban Jiwa</td>
        </tr>
        <tr>
            <td class="value" style="color: #dc3545;">' . ($total_stats['total_kejadian'] ?? 0) . '</td>
            <td class="value">' . ($total_stats['total_bangunan'] ?? 0) . '</td>
            <td class="value" style="color: #ffc107;">' . ($total_stats['total_luka'] ?? 0) . '</td>
            <td class="value" style="color: #dc3545;">' . ($total_stats['total_jiwa'] ?? 0) . '</td>
        </tr>
        <tr>
            <td class="label">KK Terdampak</td>
            <td class="label">Individu Terdampak</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td class="value">' . ($total_stats['total_kk'] ?? 0) . '</td>
            <td class="value">' . ($total_stats['total_individu'] ?? 0) . '</td>
            <td colspan="2"></td>
        </tr>
    </table>
    
    <h4>B. Statistik per Kecamatan</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>Kecamatan</th>
                <th>Jumlah Kejadian</th>
                <th>Bangunan</th>
                <th>Korban Luka</th>
                <th>Korban Jiwa</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';

if ($kec_stats && $kec_stats->num_rows > 0) {
    mysqli_data_seek($kec_stats, 0);
    while ($row = $kec_stats->fetch_assoc()) {
        $badge = $row['total'] >= 5 ? 'danger' : ($row['total'] >= 3 ? 'warning' : 'success');
        $status = $row['total'] >= 5 ? 'RAWAN' : ($row['total'] >= 3 ? 'WASPADA' : 'AMAN');

        $html .= '
            <tr>
                <td>' . htmlspecialchars($row['kecamatan']) . '</td>
                <td align="center">' . $row['total'] . '</td>
                <td align="center">' . $row['bangunan'] . '</td>
                <td align="center">' . $row['luka'] . '</td>
                <td align="center">' . $row['jiwa'] . '</td>
                <td align="center"><span class="badge badge-' . $badge . '">' . $status . '</span></td>
            </tr>';
    }
} else {
    $html .= '<tr><td colspan="6" align="center">Tidak ada data</td></tr>';
}

$html .= '
        </tbody>
    </table>
    
    <h4>C. Detail Kejadian Kebakaran</h4>
    <table class="data-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Waktu</th>
                <th>Alamat</th>
                <th>Kecamatan</th>
                <th>Bangunan</th>
                <th>Luka</th>
                <th>Jiwa</th>
            </tr>
        </thead>
        <tbody>';

if ($detail && $detail->num_rows > 0) {
    $no = 1;
    mysqli_data_seek($detail, 0);
    while ($row = $detail->fetch_assoc()) {
        $html .= '
            <tr>
                <td>' . $no++ . '</td>
                <td>' . date('d/m/Y H:i', strtotime($row['waktu'])) . '</td>
                <td>' . htmlspecialchars($row['alamat']) . '</td>
                <td>' . htmlspecialchars($row['kecamatan']) . '</td>
                <td align="center">' . $row['jumlah_bangunan'] . '</td>
                <td align="center">' . $row['korban_luka'] . '</td>
                <td align="center">' . $row['korban_jiwa'] . '</td>
            </tr>';
    }
} else {
    $html .= '<tr><td colspan="7" align="center">Tidak ada data kejadian</td></tr>';
}

$html .= '
        </tbody>
    </table>
    
    <div class="footer">
        <p>Laporan ini digenerate secara otomatis oleh Sistem Informasi Geografis Kebakaran - BARRES 698</p>
        <p>&copy; ' . date('Y') . ' BARRES 698 Banjarbaru. All rights reserved.</p>
        <p>Dicetak pada: ' . date('d/m/Y H:i:s') . '</p>
    </div>
</body>
</html>';

// Initialize DomPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output PDF
$filename = 'Laporan_KEBAKARAN_' . $bulan_text . '.pdf';
$dompdf->stream($filename, ['Attachment' => 0]); // 0 = preview, 1 = download
