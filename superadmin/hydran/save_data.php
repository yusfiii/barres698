<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

checkAuth();
checkRole(['super_admin']);

header('Content-Type: application/json');

$conn = getConnection();

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_POST['id']) && !empty($_POST['id']) ? 'edit' : 'tambah');

if ($action == 'tambah' || ($_POST['id'] ?? '')) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $latitude = trim($_POST['latitude']);
    $longitude = trim($_POST['longitude']);
    $alamat = trim($_POST['alamat']);
    $kecamatan = $_POST['kecamatan'];
    $kelurahan = $_POST['kelurahan'];
    $tahun_pemasangan = !empty($_POST['tahun_pemasangan']) ? (int)$_POST['tahun_pemasangan'] : null;
    $status = $_POST['status'];
    $keterangan = trim($_POST['keterangan']);

    if (empty($latitude) || empty($longitude) || empty($alamat) || empty($kecamatan) || empty($kelurahan)) {
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi!']);
        exit();
    }

    // Upload foto
    $foto_name = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $uploadDir = '../../assets/img/uploads/hydrant/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $foto_name = time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $foto_name);
        }
    }

    if ($id > 0) {
        // Update
        if ($foto_name) {
            // Hapus foto lama
            $stmt = $conn->prepare("SELECT foto FROM hydrant WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $old = $result->fetch_assoc();
            if ($old['foto'] && file_exists('../../assets/img/uploads/hydrant/' . $old['foto'])) {
                unlink('../../assets/img/uploads/hydrant/' . $old['foto']);
            }
            $stmt->close();

            $stmt = $conn->prepare("UPDATE hydrant SET latitude=?, longitude=?, alamat=?, kecamatan=?, kelurahan=?, tahun_pemasangan=?, foto=?, status=?, keterangan=? WHERE id=?");
            $stmt->bind_param("ddsssisssi", $latitude, $longitude, $alamat, $kecamatan, $kelurahan, $tahun_pemasangan, $foto_name, $status, $keterangan, $id);
        } else {
            $stmt = $conn->prepare("UPDATE hydrant SET latitude=?, longitude=?, alamat=?, kecamatan=?, kelurahan=?, tahun_pemasangan=?, status=?, keterangan=? WHERE id=?");
            $stmt->bind_param("ddsssisssi", $latitude, $longitude, $alamat, $kecamatan, $kelurahan, $tahun_pemasangan, $status, $keterangan, $id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Data hydrant berhasil diupdate!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate data: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO hydrant (latitude, longitude, alamat, kecamatan, kelurahan, tahun_pemasangan, foto, status, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ddsssisss", $latitude, $longitude, $alamat, $kecamatan, $kelurahan, $tahun_pemasangan, $foto_name, $status, $keterangan);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Data hydrant berhasil ditambahkan!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan data: ' . $stmt->error]);
        }
        $stmt->close();
    }
}

$conn->close();
