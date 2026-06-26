<?php
/**
 * includes/pdf-helper.php
 *
 * Helper bersama untuk semua laporan PDF BARRES 698 (DomPDF).
 * Dipakai oleh setiap file cetak-pdf-*.php agar kop surat, footer,
 * dan setup DomPDF konsisten di semua jenis laporan, tanpa duplikasi kode.
 *
 * Cara pakai di file cetak-pdf-xxx.php:
 *
 *   require_once __DIR__ . '/../../includes/pdf-helper.php';
 *
 *   $isi_html = '<table class="detail-table">...</table>'; // konten laporan
 *
 *   pdfRender([
 *       'judul'        => 'LAPORAN DATA BPK',
 *       'nomor_urut'   => '023',                 // 3 digit nomor surat, beda tiap jenis laporan
 *       'tanggal_acuan'=> time(),                // timestamp acuan untuk nomor surat & tanggal
 *       'isi_html'     => $isi_html,
 *       'nama_file'    => 'Laporan-BPK-' . date('d-m-Y') . '.pdf',
 *       'foto_base64'  => null,                  // opsional, base64 data URI foto (jika ada)
 *       'tampilkan_ttd'=> true,                  // opsional, default true
 *   ]);
 */

// ====================== KONSTANTA ORGANISASI ======================
// Ubah di sini saja kalau ada perubahan data organisasi -- otomatis
// berlaku ke semua jenis laporan.

define('PDF_ORG_NAMA', 'BANJARBARU RESCUE "BARRES 698"');
define('PDF_ORG_ALAMAT', 'Jl. Zafri Zamzam II Komplek H. KA Ganie No. 06 RT. 013 RW. 003, Kel. Kemuning Kec. Banjarbaru Selatan, Kota Banjarbaru.');
define('PDF_ORG_KONTAK', 'WhatsApp : 0851 868 14698 / Freq : 15.698.0 Mhz');
define('PDF_ORG_EMAIL', 'E-mail : barres698.banjarbaru@gmail.com');
define('PDF_ORG_LOGO_PATH', __DIR__ . '/../assets/barres2.png');

define('PDF_TTD_NAMA', 'Kemas Akhmad Rudi Indrajaya');
define('PDF_TTD_JABATAN', 'KETUA UMUM BARRES 698');

// ====================== HELPER FUNCTIONS ======================

/**
 * Konversi angka bulan menjadi angka romawi (untuk nomor surat).
 */
function bulanRomawi($bulan)
{
    $romawi = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
    ];
    return $romawi[(int) $bulan] ?? '';
}

/**
 * Format tanggal Indonesia, contoh: 17 Januari 2026
 */
function tanggalIndo($timestamp)
{
    $bulan_id = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    return strtr(date('d F Y', $timestamp), $bulan_id);
}

/**
 * Encode gambar lokal menjadi base64 data URI agar dapat dirender DomPDF
 * tanpa bergantung pada permission/path relatif saat render PDF.
 * Return null kalau file tidak ada / gagal dibaca.
 */
function imageToBase64($path)
{
    if (!$path || !file_exists($path)) {
        return null;
    }

    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);

    if ($data === false) {
        return null;
    }

    return 'data:image/' . $type . ';base64,' . base64_encode($data);
}

/**
 * Bangun nomor surat lengkap, contoh: 023/BARRES698/I/2026
 */
function pdfNomorSurat($nomor_urut, $timestamp)
{
    $bulan_romawi = bulanRomawi(date('m', $timestamp));
    $tahun = date('Y', $timestamp);
    return $nomor_urut . '/BARRES698/' . $bulan_romawi . '/' . $tahun;
}

/**
 * CSS bersama dipakai semua laporan. Dipisah jadi fungsi sendiri supaya
 * mudah di-include sekali di <head>, tidak perlu copy-paste di tiap file.
 */
function pdfBaseCss()
{
    return <<<CSS
        @page {
            size: A4 portrait;
            margin: 2cm 2cm 2cm 2cm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            color: #000000;
        }

        body {
            padding: 1.5cm 2cm 2.2cm 2cm;
        }

        body, p, div, td, th, span, h1, h2, h3, h4, table {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            color: #000000;
            line-height: 1.6;
        }

        a, a:link, a:visited, a:hover, a:active {
            color: #000000 !important;
            text-decoration: none !important;
        }

        /* KOP SURAT - logo absolut di kiri, teks benar-benar rata tengah halaman */
        .kop-surat {
            position: relative;
            width: 100%;
            border-bottom: 2px solid #000000;
            padding-bottom: 10px;
            margin-bottom: 15px;
            min-height: 90px;
        }

        .kop-logo {
            position: absolute;
            left: 0;
            top: 0;
        }

        .kop-logo img {
            height: 85px;
            width: auto;
        }

        .kop-text {
            width: 100%;
            text-align: center;
        }

        .kop-text .nama-organisasi {
            font-size: 14pt;
            font-weight: bold;
            color: #000000 !important;
            margin: 0 0 2px 0;
            letter-spacing: 0.5px;
        }

        .kop-text .alamat-kop {
            font-size: 9pt;
            color: #000000 !important;
            margin: 1px auto;
            line-height: 1.4;
            max-width: 420px;
        }

        .kop-text .kontak-kop {
            font-size: 9pt;
            color: #000000 !important;
            margin: 1px 0;
            line-height: 1.4;
        }

        /* SURAT INFO */
        .surat-info {
            width: 100%;
            margin: 12px 0 15px 0;
            font-size: 11pt;
        }

        .surat-info table {
            width: 100%;
        }

        .surat-info .label {
            font-weight: bold;
            color: #000000 !important;
        }

        .surat-info .kanan {
            text-align: right;
        }

        /* JUDUL LAPORAN */
        .judul {
            text-align: center;
            margin: 15px 0 15px 0;
            font-weight: bold;
            font-size: 14pt;
            color: #000000 !important;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* DETAIL TABLE (key-value, dipakai laporan single-record seperti kejadian) */
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        .detail-table td {
            padding: 5px 8px;
            border-bottom: 1px dashed #cccccc;
            font-size: 11pt;
            vertical-align: top;
        }

        .detail-table .label {
            font-weight: bold;
            width: 180px;
            color: #000000 !important;
        }

        .detail-table .value {
            color: #000000 !important;
        }

        /* DATA TABLE (tabel list/rekap, dipakai laporan multi-baris seperti BPK, Anggota) */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .data-table th {
            background: #ECECEC !important;
            color: #000000 !important;
            font-weight: bold;
            font-size: 10pt;
            padding: 6px 8px;
            border: 1px solid #999999;
            text-align: left;
        }

        .data-table td {
            font-size: 10pt;
            padding: 6px 8px;
            border: 1px solid #cccccc;
            color: #000000 !important;
            vertical-align: top;
        }

        .data-table .center {
            text-align: center;
        }

        /* FOTO */
        .foto-section {
            margin: 15px 0 10px 0;
            text-align: center;
        }

        .foto-section img {
            max-width: 150px;
            max-height: 150px;
            border: 1px solid #dddddd;
        }

        .foto-kosong {
            margin: 15px 0 10px 0;
            text-align: center;
            font-size: 10pt;
            color: #999999 !important;
            font-style: italic;
        }

        /* TTD */
        .ttd-section {
            width: 100%;
            margin-top: 35px;
        }

        .ttd-section table {
            width: 100%;
        }

        .ttd-section .ttd-box {
            text-align: right;
            width: 100%;
        }

        .ttd-place {
            font-size: 11pt;
            color: #000000 !important;
        }

        .ttd-name {
            font-weight: bold;
            font-size: 12pt;
            color: #000000 !important;
            padding-top: 55px;
        }

        .ttd-title {
            font-size: 11pt;
            color: #000000 !important;
            text-transform: uppercase;
        }

        /* FOOTER - fixed di bagian paling bawah setiap halaman PDF.
           DomPDF mendukung position:fixed relatif terhadap @page, sehingga
           footer selalu menempel di bawah kertas walau konten laporan
           pendek atau panjang (multi-halaman). */
        .footer-report {
            position: fixed;
            bottom: -1.2cm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9pt;
            color: #666666 !important;
            border-top: 1px solid #cccccc;
            padding-top: 10px;
        }
CSS;
}

/**
 * Render bagian kop surat (logo + nama organisasi + alamat + kontak).
 */
function pdfKopSurat()
{
    $logo_base64 = imageToBase64(PDF_ORG_LOGO_PATH);
    ob_start();
    ?>
    <div class="kop-surat">
        <?php if ($logo_base64): ?>
            <div class="kop-logo">
                <img src="<?= $logo_base64 ?>" alt="Logo BARRES 698">
            </div>
        <?php endif; ?>
        <div class="kop-text">
            <div class="nama-organisasi"><?= htmlspecialchars(PDF_ORG_NAMA) ?></div>
            <div class="alamat-kop"><?= htmlspecialchars(PDF_ORG_ALAMAT) ?></div>
            <div class="kontak-kop"><?= htmlspecialchars(PDF_ORG_KONTAK) ?></div>
            <div class="kontak-kop"><?= htmlspecialchars(PDF_ORG_EMAIL) ?></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render baris nomor surat + tanggal, rata kiri/kanan.
 */
function pdfSuratInfo($nomor_urut, $timestamp, $lampiran = '1 (satu) berkas')
{
    $nomor_surat = pdfNomorSurat($nomor_urut, $timestamp);
    $tanggal = tanggalIndo($timestamp);
    ob_start();
    ?>
    <div class="surat-info">
        <table>
            <tr>
                <td class="label" style="width: 60%;">
                    Nomor &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <?= htmlspecialchars($nomor_surat) ?><br>
                    Lampiran &nbsp;&nbsp;: <?= htmlspecialchars($lampiran) ?>
                </td>
                <td class="kanan" style="width: 40%; vertical-align: bottom;">
                    Banjarbaru, <?= htmlspecialchars($tanggal) ?>
                </td>
            </tr>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render blok tanda tangan rata kanan.
 */
function pdfTtdSection($timestamp)
{
    $tanggal = tanggalIndo($timestamp);
    ob_start();
    ?>
    <div class="ttd-section">
        <table>
            <tr>
                <td class="ttd-box">
                    <div class="ttd-place">Banjarbaru, <?= htmlspecialchars($tanggal) ?></div>
                    <div class="ttd-name"><?= htmlspecialchars(PDF_TTD_NAMA) ?></div>
                    <div class="ttd-title"><?= htmlspecialchars(PDF_TTD_JABATAN) ?></div>
                </td>
            </tr>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render footer laporan.
 */
function pdfFooter()
{
    $tanggal_cetak = date('d/m/Y H:i');
    return '<div class="footer-report">Laporan Resmi BARRES 698 - Dicetak pada ' . htmlspecialchars($tanggal_cetak) . '</div>';
}

// ====================== PREVIEW HTML (BROWSER) ======================
// Fungsi-fungsi di bawah ini dipakai oleh halaman preview (laporan-xxx.php)
// supaya tampilan preview di browser identik dengan PDF, tanpa duplikasi
// kode kop surat/footer/style. Bedanya hanya konteks render: di sini di-echo
// langsung ke halaman HTML biasa, bukan di-stream sebagai file PDF.

/**
 * CSS untuk membungkus tampilan preview di browser. Memakai pdfBaseCss()
 * yang sama dengan PDF (supaya identik), ditambah beberapa override agar
 * nyaman dilihat di layar (lebar terbatas seperti kertas A4, bayangan, dst).
 * Dipanggil sekali di dalam <style> pada halaman preview.
 */
function pdfPreviewCss()
{
    $base = pdfBaseCss();
    // Hilangkan @page dan rule selector tunggal "body { ... }" (hanya relevan
    // untuk PDF/print; di halaman preview, <body> berisi sidebar & navbar juga,
    // jadi padding body tidak boleh ikut -- cukup .laporan-preview yang diberi
    // padding). Regex pakai lookahead negatif supaya rule gabungan seperti
    // "body, p, div, ... { font-family: ... }" tidak ikut terhapus.
    $base = preg_replace('/@page\s*\{[^}]*\}/', '', $base);
    $base = preg_replace('/\bbody\s*\{[^}]*\}/', '', $base);

    return <<<CSS
        .laporan-preview {
            background: #FFFFFF;
            max-width: 210mm;
            margin: 0 auto;
            padding: 40px 50px;
            border-radius: 20px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
        }

        .laporan-preview, .laporan-preview * {
            font-family: 'Arial', 'Helvetica', sans-serif;
        }

        $base

        /* Override: di browser, footer mengikuti alur konten biasa (bukan
           fixed seperti di PDF, karena browser tidak punya konsep halaman
           kertas seperti DomPDF). */
        .laporan-preview .footer-report {
            position: static;
            margin-top: 25px;
        }

        @media print {
            .laporan-preview {
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 20px 30px !important;
                max-width: 100% !important;
            }

            @page {
                size: A4;
                margin: 2cm;
            }
        }

        @media (max-width: 768px) {
            .laporan-preview {
                padding: 20px;
            }
        }
CSS;
}

/**
 * Bungkus konten preview lengkap (kop surat + nomor surat + judul + isi +
 * ttd + footer) dalam satu div siap di-echo ke halaman preview HTML.
 * Memakai fungsi yang sama dengan PDF (pdfKopSurat, pdfSuratInfo, dst)
 * sehingga selalu sinkron dengan hasil cetak PDF-nya.
 *
 * @param array $opt sama seperti pdfRender(), kecuali tidak ada nama_file/download.
 */
function pdfPreviewHtml(array $opt)
{
    $judul         = $opt['judul'] ?? 'LAPORAN';
    $nomor_urut    = $opt['nomor_urut'] ?? '000';
    $timestamp     = $opt['tanggal_acuan'] ?? time();
    $isi_html      = $opt['isi_html'] ?? '';
    $lampiran      = $opt['lampiran'] ?? '1 (satu) berkas';
    $tampilkan_ttd = $opt['tampilkan_ttd'] ?? true;

    ob_start();
    ?>
    <div class="laporan-preview laporan">
        <?= pdfKopSurat() ?>
        <?= pdfSuratInfo($nomor_urut, $timestamp, $lampiran) ?>
        <div class="judul"><?= htmlspecialchars(mb_strtoupper($judul)) ?></div>
        <?= $isi_html ?>
        <?php if ($tampilkan_ttd): ?>
            <?= pdfTtdSection($timestamp) ?>
        <?php endif; ?>
        <?= pdfFooter() ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Tampilkan blok "tidak ada data" yang konsisten di semua halaman preview,
 * dipakai saat filter tidak menemukan data apa pun.
 */
function pdfPreviewNoData($pesan = 'Tidak ada data untuk filter yang dipilih', $sub = 'Silakan ubah filter atau tambahkan data terlebih dahulu.')
{
    ob_start();
    ?>
    <div class="no-data">
        <i class="fas fa-inbox"></i>
        <p><?= htmlspecialchars($pesan) ?></p>
        <small><?= htmlspecialchars($sub) ?></small>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Susun seluruh dokumen HTML lalu render & stream sebagai PDF via DomPDF.
 *
 * @param array $opt
 *   judul          string  Judul laporan, akan otomatis di-uppercase
 *   nomor_urut     string  3 digit nomor urut surat (beda tiap jenis laporan)
 *   tanggal_acuan  int     Timestamp acuan untuk nomor surat & tanggal surat
 *   isi_html       string  HTML konten utama laporan (tabel, dsb)
 *   nama_file      string  Nama file PDF saat di-download/ditampilkan
 *   lampiran       string  Opsional, teks lampiran (default: '1 (satu) berkas')
 *   tampilkan_ttd  bool    Opsional, default true
 *   download       bool    Opsional, true = force download, false = tampil di browser (default false)
 */
function pdfRender(array $opt)
{
    require_once __DIR__ . '/../vendor/autoload.php';

    $judul         = $opt['judul'] ?? 'LAPORAN';
    $nomor_urut    = $opt['nomor_urut'] ?? '000';
    $timestamp     = $opt['tanggal_acuan'] ?? time();
    $isi_html      = $opt['isi_html'] ?? '';
    $nama_file     = $opt['nama_file'] ?? ('Laporan-' . date('d-m-Y') . '.pdf');
    $lampiran      = $opt['lampiran'] ?? '1 (satu) berkas';
    $tampilkan_ttd = $opt['tampilkan_ttd'] ?? true;
    $download      = $opt['download'] ?? false;

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <style>
            <?= pdfBaseCss() ?>
        </style>
    </head>

    <body>

        <?= pdfKopSurat() ?>

        <?= pdfSuratInfo($nomor_urut, $timestamp, $lampiran) ?>

        <div class="judul"><?= htmlspecialchars(mb_strtoupper($judul)) ?></div>

        <?= $isi_html ?>

        <?php if ($tampilkan_ttd): ?>
            <?= pdfTtdSection($timestamp) ?>
        <?php endif; ?>

        <?= pdfFooter() ?>

    </body>

    </html>
    <?php
    $html = ob_get_clean();

    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml($html);
    $dompdf->render();
    $dompdf->stream($nama_file, ['Attachment' => $download]);
    exit;
}