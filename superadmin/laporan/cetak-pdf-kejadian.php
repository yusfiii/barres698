<?php
/**
 * cetak-pdf-kejadian.php
 * Generate Laporan Kejadian Kebakaran dalam format PDF (DomPDF).
 * Memakai includes/pdf-helper.php untuk kop surat, footer, dan setup DomPDF
 * yang seragam dengan laporan-laporan lain (BPK, Anggota, dst).
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/pdf-helper.php';

checkAuth();
checkRole(['super_admin']);

// ID kejadian wajib ada
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die('ID kejadian tidak valid.');
}

$conn = getConnection();

$stmt = $conn->prepare("SELECT * FROM kejadian_kebakaran WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$kejadian = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$kejadian) {
    die('Data kejadian tidak ditemukan.');
}

$waktu_kejadian = strtotime($kejadian['waktu']);

// Foto kejadian -> base64 (jika ada)
$foto_base64 = null;
if (!empty($kejadian['foto'])) {
    $foto_path = __DIR__ . '/../../uploads/' . $kejadian['foto'];
    $foto_base64 = imageToBase64($foto_path);
}

// ====================== SUSUN ISI LAPORAN (KHUSUS KEJADIAN) ======================

ob_start();
?>
<table class="detail-table">
    <tr>
        <td class="label">Waktu Kejadian</td>
        <td class="value">: <?= date('d/m/Y H:i', $waktu_kejadian) ?></td>
    </tr>
    <tr>
        <td class="label">Titik Koordinat</td>
        <td class="value">: <?= number_format($kejadian['latitude'] ?? 0, 6) ?>, <?= number_format($kejadian['longitude'] ?? 0, 6) ?></td>
    </tr>
    <tr>
        <td class="label">Alamat</td>
        <td class="value">: <?= htmlspecialchars($kejadian['alamat'] ?? '-') ?></td>
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
        <td class="value">: <?= (int) ($kejadian['jumlah_bangunan'] ?? 0) ?> unit</td>
    </tr>
    <tr>
        <td class="label">Jumlah KK</td>
        <td class="value">: <?= ($kejadian['jumlah_KK'] ?? 0) > 0 ? (int) $kejadian['jumlah_KK'] . ' KK' : '-' ?></td>
    </tr>
    <tr>
        <td class="label">Jumlah Individu</td>
        <td class="value">: <?= ($kejadian['jumlah_individu'] ?? 0) > 0 ? (int) $kejadian['jumlah_individu'] . ' orang' : '-' ?></td>
    </tr>
    <tr>
        <td class="label">Korban Luka/Cedera</td>
        <td class="value">: <?= (int) ($kejadian['korban_luka'] ?? 0) ?> orang</td>
    </tr>
    <tr>
        <td class="label">Korban Jiwa</td>
        <td class="value">: <?= (int) ($kejadian['korban_jiwa'] ?? 0) ?> orang</td>
    </tr>
</table>

<?php if ($foto_base64): ?>
    <div class="foto-section">
        <img src="<?= $foto_base64 ?>" alt="Foto Kejadian">
    </div>
<?php else: ?>
    <div class="foto-kosong">Tidak ada foto</div>
<?php endif; ?>
<?php
$isi_html = ob_get_clean();

// ====================== RENDER PDF ======================

pdfRender([
    'judul'         => 'LAPORAN KEJADIAN KEBAKARAN',
    'nomor_urut'    => '022',
    'tanggal_acuan' => $waktu_kejadian,
    'isi_html'      => $isi_html,
    'nama_file'     => 'Laporan-Kejadian-Kebakaran-' . date('d-m-Y', $waktu_kejadian) . '.pdf',
]);