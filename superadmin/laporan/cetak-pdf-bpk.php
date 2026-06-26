<?php
/**
 * cetak-pdf-bpk.php
 * Generate Laporan Data BPK (semua BPK Sekota Banjarbaru yang terdaftar)
 * dalam format PDF (DomPDF), memakai includes/pdf-helper.php.
 *
 * Ini adalah CONTOH POLA untuk laporan bertipe "daftar/list" (multi-baris),
 * berbeda dengan cetak-pdf-kejadian.php yang bertipe "detail satu record".
 * Laporan-laporan lain (Anggota, Hotspot, dst) bisa mengikuti pola file ini:
 *   1. Ambil data dari database
 *   2. Susun isi_html (di sini pakai class="data-table" dari helper)
 *   3. Panggil pdfRender([...])
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/pdf-helper.php';

checkAuth();
checkRole(['super_admin']);

$conn = getConnection();

// Opsional: filter kecamatan via query string, contoh: cetak-pdf-bpk.php?kecamatan=Banjarbaru%20Selatan
$filter_kecamatan = isset($_GET['kecamatan']) ? $_GET['kecamatan'] : '';

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
$conn->close();

/**
 * Helper khusus laporan ini: ringkas data JSON fasilitas jadi teks singkat.
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

// ====================== SUSUN ISI LAPORAN (KHUSUS BPK) ======================

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
        <?php if (count($daftar_bpk) > 0): ?>
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
        <?php else: ?>
            <tr>
                <td colspan="8" class="center">Tidak ada data BPK terdaftar.</td>
            </tr>
        <?php endif; ?>
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

// ====================== RENDER PDF ======================

pdfRender([
    'judul'         => 'LAPORAN DATA BPK',
    'nomor_urut'    => '023',
    'tanggal_acuan' => time(),
    'isi_html'      => $isi_html,
    'nama_file'     => 'Laporan-Data-BPK-' . date('d-m-Y') . '.pdf',
    'tampilkan_ttd' => true,
]);