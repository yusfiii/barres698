<?php
require_once '../../includes/config.php';
require_once '../../includes/session.php';
checkAuth();
checkRole(['super_admin']);

$response = ['success' => false, 'message' => ''];

$id = $_POST['id'] ?? '';
$waktu = $_POST['waktu'] ?? '';
$latitude = $_POST['latitude'] ?? '';
$longitude = $_POST['longitude'] ?? '';
$alamat = $_POST['alamat'] ?? '';
$kecamatan = $_POST['kecamatan'] ?? '';
$kelurahan = $_POST['kelurahan'] ?? '';
$jumlah_bangunan = $_POST['jumlah_bangunan'] ?? 0;
$jumlah_KK = $_POST['jumlah_KK'] ?? 0;
$jumlah_individu = $_POST['jumlah_individu'] ?? 0;
$korban_luka = $_POST['korban_luka'] ?? 0;
$korban_jiwa = $_POST['korban_jiwa'] ?? 0;

$conn = getConnection();

// Handle foto upload
$fotoName = null;
if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
    $uploadDir = '../../uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
    $fotoName = date('Ymd_His') . '_' . uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $fotoName);
}

if (empty($id)) {
    // INSERT new data
    $stmt = $conn->prepare("INSERT INTO kejadian_kebakaran (waktu, latitude, longitude, alamat, kecamatan, kelurahan, jumlah_bangunan, jumlah_KK, jumlah_individu, korban_luka, korban_jiwa, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sddsssiiiiis", $waktu, $latitude, $longitude, $alamat, $kecamatan, $kelurahan, $jumlah_bangunan, $jumlah_KK, $jumlah_individu, $korban_luka, $korban_jiwa, $fotoName);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Data berhasil disimpan';
    } else {
        $response['message'] = 'Gagal menyimpan data';
    }
    $stmt->close();
} else {
    // UPDATE existing data
    if ($fotoName) {
        // Delete old foto
        $stmt = $conn->prepare("SELECT foto FROM kejadian_kebakaran WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $old = $result->fetch_assoc();
        if ($old['foto'] && file_exists('../../uploads/' . $old['foto'])) {
            unlink('../../uploads/' . $old['foto']);
        }

        $stmt = $conn->prepare("UPDATE kejadian_kebakaran SET waktu=?, latitude=?, longitude=?, alamat=?, kecamatan=?, kelurahan=?, jumlah_bangunan=?, jumlah_KK=?, jumlah_individu=?, korban_luka=?, korban_jiwa=?, foto=? WHERE id=?");
        $stmt->bind_param("sddsssiiiiisi", $waktu, $latitude, $longitude, $alamat, $kecamatan, $kelurahan, $jumlah_bangunan, $jumlah_KK, $jumlah_individu, $korban_luka, $korban_jiwa, $fotoName, $id);
    } else {
        $stmt = $conn->prepare("UPDATE kejadian_kebakaran SET waktu=?, latitude=?, longitude=?, alamat=?, kecamatan=?, kelurahan=?, jumlah_bangunan=?, jumlah_KK=?, jumlah_individu=?, korban_luka=?, korban_jiwa=? WHERE id=?");
        $stmt->bind_param("sddsssiiiiii", $waktu, $latitude, $longitude, $alamat, $kecamatan, $kelurahan, $jumlah_bangunan, $jumlah_KK, $jumlah_individu, $korban_luka, $korban_jiwa, $id);
    }

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Data berhasil diupdate';
    } else {
        $response['message'] = 'Gagal mengupdate data';
    }
    $stmt->close();
}

$conn->close();
echo json_encode($response);
