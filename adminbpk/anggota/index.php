<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

checkAuth();
checkRole(['admin_bpk']);

$bpk_id = $_SESSION['bpk_id'];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$conn = getConnection();
$bpk = $conn->query("SELECT nama_bpk FROM bpk WHERE id = $bpk_id")->fetch_assoc();

// Include sidebar dari folder includes
include __DIR__ . '/../../includes/sidebar.php';

// Query anggota
$query = "SELECT * FROM anggota WHERE bpk_id = $bpk_id";
if (!empty($search)) {
    $query .= " AND (nama LIKE '%" . $conn->real_escape_string($search) . "%' 
                 OR nik LIKE '%" . $conn->real_escape_string($search) . "%'
                 OR no_hp LIKE '%" . $conn->real_escape_string($search) . "%'
                 OR nomor_anggota LIKE '%" . $conn->real_escape_string($search) . "%')";
}
if (!empty($status_filter)) {
    $query .= " AND status = '" . $conn->real_escape_string($status_filter) . "'";
}
$query .= " ORDER BY nomor_anggota ASC";

$anggota_list = $conn->query($query);
$total = $anggota_list->num_rows;
$aktif = $conn->query("SELECT COUNT(*) as t FROM anggota WHERE bpk_id=$bpk_id AND status='aktif'")->fetch_assoc()['t'];
$nonaktif = $conn->query("SELECT COUNT(*) as t FROM anggota WHERE bpk_id=$bpk_id AND status='nonaktif'")->fetch_assoc()['t'];

// Fungsi untuk mendapatkan nomor anggota berikutnya yang tersedia
function getNextNomorAnggota($conn, $bpk_id)
{
    $result = $conn->query("SELECT nomor_anggota FROM anggota WHERE bpk_id = $bpk_id AND nomor_anggota IS NOT NULL ORDER BY nomor_anggota");
    $usedNumbers = [];
    while ($row = $result->fetch_assoc()) {
        $usedNumbers[] = $row['nomor_anggota'];
    }

    for ($i = 1; $i <= 99; $i++) {
        if (!in_array($i, $usedNumbers)) {
            return $i;
        }
    }
    return null;
}

// Proses Tambah/Edit via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] == 'tambah' || $_POST['action'] == 'edit') {
        $nama = trim($_POST['nama']);
        $tempat_lahir = trim($_POST['tempat_lahir']);
        $tanggal_lahir = trim($_POST['tanggal_lahir']);
        $jenis_kelamin = $_POST['jenis_kelamin'];
        $alamat = trim($_POST['alamat']);
        $nik = trim($_POST['nik']);
        $no_hp = trim($_POST['no_hp']);
        $status_anggota = $_POST['status_anggota'];
        $jabatan = isset($_POST['jabatan']) ? $_POST['jabatan'] : 'Anggota';
        $nomor_anggota = isset($_POST['nomor_anggota']) && !empty($_POST['nomor_anggota']) ? (int)$_POST['nomor_anggota'] : null;

        if (empty($nama) || empty($no_hp)) {
            echo json_encode(['success' => false, 'message' => 'Nama dan No HP wajib diisi!']);
            exit();
        }

        if (!empty($nik)) {
            $cek_query = "SELECT id FROM anggota WHERE nik = '$nik'";
            if ($_POST['action'] == 'edit') {
                $edit_id = (int)$_POST['id'];
                $cek_query .= " AND id != $edit_id";
            }
            $cek = $conn->query($cek_query);
            if ($cek && $cek->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'NIK sudah terdaftar!']);
                exit();
            }
        }

        // Upload foto
        $foto_name = $_POST['foto_lama'] ?? null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
            $upload = uploadFile($_FILES['foto'], '../../assets/img/uploads/anggota/');
            if ($upload['success']) {
                if ($foto_name && file_exists('../../assets/img/uploads/anggota/' . $foto_name)) {
                    unlink('../../assets/img/uploads/anggota/' . $foto_name);
                }
                $foto_name = $upload['filename'];
            }
        }

        $foto_ktp_name = $_POST['foto_ktp_lama'] ?? null;
        if (isset($_FILES['foto_ktp']) && $_FILES['foto_ktp']['error'] == 0) {
            $upload = uploadFile($_FILES['foto_ktp'], '../../assets/img/uploads/ktp/');
            if ($upload['success']) {
                if ($foto_ktp_name && file_exists('../../assets/img/uploads/ktp/' . $foto_ktp_name)) {
                    unlink('../../assets/img/uploads/ktp/' . $foto_ktp_name);
                }
                $foto_ktp_name = $upload['filename'];
            }
        }

        if ($_POST['action'] == 'tambah') {
            $nomor_anggota = getNextNomorAnggota($conn, $bpk_id);
            if ($nomor_anggota === null) {
                echo json_encode(['success' => false, 'message' => 'Nomor anggota sudah mencapai batas maksimal (99 anggota)!']);
                exit();
            }
            $stmt = $conn->prepare("INSERT INTO anggota (bpk_id, nomor_anggota, nama, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, nik, no_hp, status, jabatan, foto, foto_ktp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssssssssss", $bpk_id, $nomor_anggota, $nama, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $alamat, $nik, $no_hp, $status_anggota, $jabatan, $foto_name, $foto_ktp_name);
        } else {
            $edit_id = (int)$_POST['id'];
            $cek_nomor = $conn->query("SELECT nomor_anggota FROM anggota WHERE id = $edit_id AND bpk_id = $bpk_id");
            $current_nomor = $cek_nomor->fetch_assoc()['nomor_anggota'];

            if ($nomor_anggota != $current_nomor) {
                $cek = $conn->query("SELECT id FROM anggota WHERE nomor_anggota = $nomor_anggota AND bpk_id = $bpk_id AND id != $edit_id");
                if ($cek && $cek->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'Nomor anggota ' . sprintf("%02d", $nomor_anggota) . ' sudah digunakan oleh anggota lain!']);
                    exit();
                }
            }

            $stmt = $conn->prepare("UPDATE anggota SET nama=?, tempat_lahir=?, tanggal_lahir=?, jenis_kelamin=?, alamat=?, nik=?, no_hp=?, status=?, jabatan=?, foto=?, foto_ktp=?, nomor_anggota=? WHERE id=? AND bpk_id=?");
            $stmt->bind_param("ssssssssssssii", $nama, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $alamat, $nik, $no_hp, $status_anggota, $jabatan, $foto_name, $foto_ktp_name, $nomor_anggota, $edit_id, $bpk_id);
        }

        if ($stmt->execute()) {
            $conn->query("UPDATE bpk SET jumlah_anggota = (SELECT COUNT(*) FROM anggota WHERE bpk_id = $bpk_id AND status = 'aktif') WHERE id = $bpk_id");
            echo json_encode(['success' => true, 'message' => $_POST['action'] == 'tambah' ? 'Anggota berhasil ditambahkan! (Nomor: ' . sprintf("%02d", $nomor_anggota) . ')' : 'Data berhasil diupdate!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data!']);
        }
        $stmt->close();
        exit();
    }

    if ($_POST['action'] == 'hapus') {
        $hapus_id = (int)$_POST['id'];
        $stmt = $conn->prepare("SELECT foto, foto_ktp FROM anggota WHERE id = ? AND bpk_id = ?");
        $stmt->bind_param("ii", $hapus_id, $bpk_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['foto'] && file_exists('../../assets/img/uploads/anggota/' . $row['foto'])) {
                unlink('../../assets/img/uploads/anggota/' . $row['foto']);
            }
            if ($row['foto_ktp'] && file_exists('../../assets/img/uploads/ktp/' . $row['foto_ktp'])) {
                unlink('../../assets/img/uploads/ktp/' . $row['foto_ktp']);
            }
        }
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM anggota WHERE id = ? AND bpk_id = ?");
        $stmt->bind_param("ii", $hapus_id, $bpk_id);
        if ($stmt->execute()) {
            $conn->query("UPDATE bpk SET jumlah_anggota = (SELECT COUNT(*) FROM anggota WHERE bpk_id = $bpk_id AND status = 'aktif') WHERE id = $bpk_id");
            echo json_encode(['success' => true, 'message' => 'Anggota berhasil dihapus!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus!']);
        }
        $stmt->close();
        exit();
    }
}

$next_nomor = getNextNomorAnggota($conn, $bpk_id);
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Anggota - BARRES 698</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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

        /* Stats Cards */
        .stat-card {
            background: #FFFFFF;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s ease;
            text-align: center;
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
            margin: 0 auto 12px;
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

        /* Filter Section */
        .filter-section {
            background: #FFFFFF;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            padding: 20px 24px;
            margin-bottom: 28px;
        }

        .form-label {
            color: #1A1A1A;
            font-weight: 500;
            font-size: 13px;
            margin-bottom: 8px;
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

        /* Buttons */
        .btn-gold {
            background: linear-gradient(135deg, #F7B801, #E5A800);
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            color: #1A1A1A;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
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
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            color: #F7B801;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
        }

        .btn-outline-gold:hover {
            background: rgba(247, 184, 1, 0.1);
            color: #F7B801;
        }

        /* Card */
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

        .badge-stats {
            background: rgba(247, 184, 1, 0.1);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #F7B801;
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

        .nomor-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, rgba(247, 184, 1, 0.2), rgba(247, 184, 1, 0.1));
            color: #F7B801;
            font-weight: 700;
            font-size: 16px;
            border-radius: 14px;
            border: 1px solid rgba(247, 184, 1, 0.3);
        }

        .avatar-sm {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid #F7B801;
        }

        .avatar-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: rgba(247, 184, 1, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #F7B801;
            font-weight: bold;
            font-size: 18px;
        }

        .badge-aktif {
            background: rgba(40, 167, 69, 0.1);
            color: #1e7e34;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-nonaktif {
            background: rgba(108, 117, 125, 0.1);
            color: #5a6268;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-jabatan {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-ketua {
            background: rgba(247, 184, 1, 0.2);
            color: #D4A000;
        }

        .badge-wakil {
            background: rgba(247, 184, 1, 0.15);
            color: #B8860B;
        }

        .badge-sekretaris {
            background: rgba(247, 184, 1, 0.12);
            color: #8B6914;
        }

        .badge-anggota {
            background: rgba(108, 117, 125, 0.1);
            color: #5a6268;
        }

        .btn-action {
            background: transparent;
            border: none;
            padding: 6px 10px;
            border-radius: 10px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-action i {
            font-size: 14px;
            color: #999;
        }

        .btn-action:hover i {
            color: #F7B801;
        }

        .btn-action.danger:hover i {
            color: #dc3545;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 20px;
            overflow: hidden;
            background: #FFFFFF;
            border: 1px solid rgba(247, 184, 1, 0.2);
        }

        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid rgba(247, 184, 1, 0.15);
        }

        .modal-header .modal-title {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header-gradient,
        .modal-header-gradient-edit,
        .modal-header-gradient-detail {
            background: linear-gradient(135deg, #F7B801, #E5A800);
            color: #1A1A1A;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-foto {
            width: 120px;
            height: 150px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid rgba(247, 184, 1, 0.3);
        }

        .modal-ktp {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
            border-radius: 12px;
            border: 2px solid rgba(247, 184, 1, 0.3);
        }

        .nomor-info {
            background: rgba(247, 184, 1, 0.1);
            border-radius: 12px;
            padding: 12px 15px;
            text-align: center;
            margin-bottom: 20px;
        }

        .nomor-info span {
            font-size: 24px;
            font-weight: 700;
            color: #F7B801;
            display: inline-block;
            margin-left: 10px;
        }

        .required:after {
            content: ' *';
            color: #F7B801;
        }

        .detail-label {
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #F7B801;
        }

        .nomor-badge-detail {
            background: linear-gradient(135deg, rgba(247, 184, 1, 0.2), rgba(247, 184, 1, 0.1));
            border: 2px solid #F7B801;
            color: #F7B801;
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            margin: 0 auto 15px;
        }

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

            .nomor-badge {
                width: 40px;
                height: 40px;
                font-size: 14px;
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
                <h2>Data Anggota</h2>
                <p><?= htmlspecialchars($bpk['nama_bpk']) ?></p>
            </div>
            <div class="user-dropdown" style="display: flex; align-items: center; gap: 15px;">
                <div class="user-info">
                    <div class="username"><?= htmlspecialchars($_SESSION['username']) ?></div>
                    <div class="role">Admin BPK</div>
                </div>
                <div class="user-avatar" id="userAvatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>

        <div class="dropdown-menu-custom" id="dropdownMenu">
            <a href="../dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <div class="dropdown-divider"></div>
            <a href="../../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?= $total ?></div>
                    <div class="stat-label">Total Anggota</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                    <div class="stat-number"><?= $aktif ?></div>
                    <div class="stat-label">Aktif</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-times"></i></div>
                    <div class="stat-number"><?= $nonaktif ?></div>
                    <div class="stat-label">Nonaktif</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-percent"></i></div>
                    <div class="stat-number"><?= $total > 0 ? round(($aktif / $total) * 100) : 0 ?>%</div>
                    <div class="stat-label">Keaktifan</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-search me-1"></i>Cari Anggota</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama, NIK, No HP, atau No Anggota..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-filter me-1"></i>Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?= $status_filter == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $status_filter == 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn-gold w-100">
                        <i class="fas fa-filter"></i> Tampilkan
                    </button>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn-outline-gold w-100" onclick="bukaModalTambah()">
                        <i class="fas fa-plus"></i> Tambah Anggota
                    </button>
                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3><i class="fas fa-list"></i> Daftar Anggota</h3>
                <span class="badge-stats"><i class="fas fa-users"></i> <?= $total ?> Anggota</span>
            </div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Foto</th>
                                <th>Nama / Jabatan</th>
                                <th>JK</th>
                                <th>No HP</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="anggotaTableBody">
                            <?php if ($anggota_list && $anggota_list->num_rows > 0):
                                mysqli_data_seek($anggota_list, 0);
                                while ($row = $anggota_list->fetch_assoc()):
                                    $jabatanClass = '';
                                    if ($row['jabatan'] == 'Ketua') $jabatanClass = 'badge-ketua';
                                    elseif ($row['jabatan'] == 'Wakil Ketua') $jabatanClass = 'badge-wakil';
                                    elseif ($row['jabatan'] == 'Sekretaris') $jabatanClass = 'badge-sekretaris';
                                    else $jabatanClass = 'badge-anggota';
                            ?>
                                    <tr id="row-<?= $row['id'] ?>">
                                        <td>
                                            <div class="nomor-badge">
                                                <?= sprintf("%02d", $row['nomor_anggota']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($row['foto'] && file_exists('../../assets/img/uploads/anggota/' . $row['foto'])): ?>
                                                <img src="../../assets/img/uploads/anggota/<?= $row['foto'] ?>" class="avatar-sm">
                                            <?php else: ?>
                                                <div class="avatar-placeholder"><?= strtoupper(substr($row['nama'], 0, 1)) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['nama']) ?></strong>
                                            <br>
                                            <span class="badge-jabatan <?= $jabatanClass ?>"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($row['jabatan'] ?? 'Anggota') ?></span>
                                        </td>
                                        <td><?= $row['jenis_kelamin'] == 'Laki-laki' ? 'L' : 'P' ?></td>
                                        <td>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $row['no_hp']) ?>" target="_blank" style="color: #F7B801; text-decoration: none;">
                                                <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($row['no_hp']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge-<?= $row['status'] == 'aktif' ? 'aktif' : 'nonaktif' ?>">
                                                <?= ucfirst($row['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn-action" title="Detail" onclick="bukaModalDetail(<?= $row['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-action" title="Edit" onclick="bukaModalEdit(<?= $row['id'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-action danger" title="Hapus" onclick="hapusAnggota(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama']) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-users-slash fa-3x mb-3 d-block" style="color: #999;"></i>
                                        <p class="mb-0">Belum ada anggota terdaftar</p>
                                        <button class="btn-gold mt-3" onclick="bukaModalTambah()" style="display: inline-flex;">
                                            <i class="fas fa-plus"></i> Tambah Anggota Pertama
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL TAMBAH ==================== -->
    <div class="modal fade" id="modalTambah" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i>Tambah Anggota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formTambah" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="nomor-info">
                            <i class="fas fa-id-card"></i> Nomor Anggota yang akan didapatkan:
                            <span><?= $next_nomor ? sprintf("%02d", $next_nomor) : 'Penuh!' ?></span>
                            <?php if (!$next_nomor): ?>
                                <br><small class="text-danger">Maaf, kuota anggota sudah mencapai batas maksimal 99 orang!</small>
                            <?php endif; ?>
                        </div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label required">Nama Lengkap</label>
                                        <input type="text" name="nama" class="form-control" required placeholder="Sesuai KTP" <?= !$next_nomor ? 'disabled' : '' ?>>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label required">Jenis Kelamin</label>
                                        <select name="jenis_kelamin" class="form-select" required <?= !$next_nomor ? 'disabled' : '' ?>>
                                            <option value="Laki-laki">Laki-laki</option>
                                            <option value="Perempuan">Perempuan</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tempat Lahir</label>
                                        <input type="text" name="tempat_lahir" class="form-control" placeholder="Kota/Kabupaten" <?= !$next_nomor ? 'disabled' : '' ?>>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tanggal Lahir</label>
                                        <input type="date" name="tanggal_lahir" class="form-control" <?= !$next_nomor ? 'disabled' : '' ?>>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">NIK (16 digit)</label>
                                        <input type="text" name="nik" class="form-control" maxlength="16" oninput="this.value=this.value.replace(/[^0-9]/g,'')" placeholder="Masukkan NIK" <?= !$next_nomor ? 'disabled' : '' ?>>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required">Nomor HP</label>
                                        <input type="text" name="no_hp" class="form-control" required placeholder="08123456789" <?= !$next_nomor ? 'disabled' : '' ?>>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Alamat Lengkap</label>
                                    <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat sesuai KTP" <?= !$next_nomor ? 'disabled' : '' ?>></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required">Status</label>
                                        <select name="status_anggota" class="form-select" required <?= !$next_nomor ? 'disabled' : '' ?>>
                                            <option value="aktif">Aktif</option>
                                            <option value="nonaktif">Nonaktif</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required">Jabatan</label>
                                        <select name="jabatan" class="form-select" required <?= !$next_nomor ? 'disabled' : '' ?>>
                                            <option value="Ketua">Ketua</option>
                                            <option value="Wakil Ketua">Wakil Ketua</option>
                                            <option value="Sekretaris">Sekretaris</option>
                                            <option value="Anggota" selected>Anggota</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center mb-3 p-3 border rounded" style="border-color: rgba(247, 184, 1, 0.2);">
                                    <label class="form-label">Pas Foto</label>
                                    <img id="previewTambahFoto" class="modal-foto mb-2 d-block mx-auto" style="display:none;">
                                    <input type="file" name="foto" class="form-control form-control-sm" accept="image/*" onchange="previewFoto(this, 'previewTambahFoto')" <?= !$next_nomor ? 'disabled' : '' ?>>
                                </div>
                                <div class="text-center p-3 border rounded" style="border-color: rgba(247, 184, 1, 0.2);">
                                    <label class="form-label">Foto KTP</label>
                                    <img id="previewTambahKtp" class="modal-ktp mb-2 d-block mx-auto" style="display:none;">
                                    <input type="file" name="foto_ktp" class="form-control form-control-sm" accept="image/*" onchange="previewFoto(this, 'previewTambahKtp')" <?= !$next_nomor ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-outline-gold" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn-gold" <?= !$next_nomor ? 'disabled' : '' ?>><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL EDIT ==================== -->
    <div class="modal fade" id="modalEdit" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient-edit">
                    <h5 class="modal-title"><i class="fas fa-edit"></i>Edit Anggota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEdit" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body" id="editContent"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn-outline-gold" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn-gold"><i class="fas fa-save"></i> Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL DETAIL ==================== -->
    <div class="modal fade" id="modalDetail" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient-detail">
                    <h5 class="modal-title"><i class="fas fa-id-card"></i>Detail Anggota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent"></div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-gold" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn-gold" onclick="cetakDetail()"><i class="fas fa-print"></i> Cetak</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Data anggota dalam JSON
        const anggotaData = <?php
                            mysqli_data_seek($anggota_list, 0);
                            $all_data = [];
                            while ($r = $anggota_list->fetch_assoc()) {
                                $all_data[$r['id']] = $r;
                            }
                            echo json_encode($all_data);
                            ?>;

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        function hitungUmur(tanggalLahir) {
            const today = new Date();
            const birthDate = new Date(tanggalLahir);
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
            return age;
        }

        function previewFoto(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function bukaModalTambah() {
            document.getElementById('formTambah').reset();
            document.getElementById('previewTambahFoto').style.display = 'none';
            document.getElementById('previewTambahKtp').style.display = 'none';
            const modal = new bootstrap.Modal(document.getElementById('modalTambah'));
            modal.show();
        }

        function bukaModalEdit(id) {
            const data = anggotaData[id];
            if (!data) return;

            const jabatanOptions = ['Ketua', 'Wakil Ketua', 'Sekretaris', 'Anggota'];
            let jabatanHtml = '';
            for (let opt of jabatanOptions) {
                const selected = (data.jabatan == opt) ? 'selected' : '';
                jabatanHtml += `<option value="${opt}" ${selected}>${opt}</option>`;
            }

            const content = document.getElementById('editContent');
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-8">
                        <input type="hidden" name="foto_lama" value="${data.foto || ''}">
                        <input type="hidden" name="foto_ktp_lama" value="${data.foto_ktp || ''}">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Nomor Anggota</label>
                                <input type="number" name="nomor_anggota" class="form-control" value="${data.nomor_anggota}" min="1" max="99" required>
                                <small class="text-muted">Nomor 2 digit (01-99), pastikan tidak ada yang sama</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Status</label>
                                <select name="status_anggota" class="form-select" required>
                                    <option value="aktif" ${data.status=='aktif'?'selected':''}>Aktif</option>
                                    <option value="nonaktif" ${data.status=='nonaktif'?'selected':''}>Nonaktif</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label required">Nama Lengkap</label>
                                <input type="text" name="nama" class="form-control" value="${escapeHtml(data.nama)}" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Jenis Kelamin</label>
                                <select name="jenis_kelamin" class="form-select" required>
                                    <option value="Laki-laki" ${data.jenis_kelamin=='Laki-laki'?'selected':''}>Laki-laki</option>
                                    <option value="Perempuan" ${data.jenis_kelamin=='Perempuan'?'selected':''}>Perempuan</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" class="form-control" value="${escapeHtml(data.tempat_lahir || '')}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" class="form-control" value="${data.tanggal_lahir || ''}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIK</label>
                                <input type="text" name="nik" class="form-control" maxlength="16" value="${escapeHtml(data.nik || '')}" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">No HP</label>
                                <input type="text" name="no_hp" class="form-control" value="${escapeHtml(data.no_hp)}" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Alamat</label>
                                <textarea name="alamat" class="form-control" rows="2">${escapeHtml(data.alamat || '')}</textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Jabatan</label>
                                <select name="jabatan" class="form-select" required>
                                    ${jabatanHtml}
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center mb-3 p-3 border rounded" style="border-color: rgba(247, 184, 1, 0.2);">
                            <label class="form-label">Pas Foto</label>
                            ${data.foto && data.foto !== '' ? `<img src="../../assets/img/uploads/anggota/${data.foto}" class="modal-foto mb-2 d-block mx-auto">` : ''}
                            <img id="previewEditFoto" class="modal-foto mb-2 d-block mx-auto" style="display:none;">
                            <input type="file" name="foto" class="form-control form-control-sm" accept="image/*" onchange="previewFoto(this, 'previewEditFoto')">
                            <small class="text-muted">Biarkan kosong jika tidak ganti</small>
                        </div>
                        <div class="text-center p-3 border rounded" style="border-color: rgba(247, 184, 1, 0.2);">
                            <label class="form-label">Foto KTP</label>
                            ${data.foto_ktp && data.foto_ktp !== '' ? `<img src="../../assets/img/uploads/ktp/${data.foto_ktp}" class="modal-ktp mb-2 d-block mx-auto">` : ''}
                            <img id="previewEditKtp" class="modal-ktp mb-2 d-block mx-auto" style="display:none;">
                            <input type="file" name="foto_ktp" class="form-control form-control-sm" accept="image/*" onchange="previewFoto(this, 'previewEditKtp')">
                            <small class="text-muted">Biarkan kosong jika tidak ganti</small>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('edit_id').value = id;
            const modal = new bootstrap.Modal(document.getElementById('modalEdit'));
            modal.show();
        }

        function bukaModalDetail(id) {
            const data = anggotaData[id];
            if (!data) return;

            const umur = data.tanggal_lahir ? hitungUmur(data.tanggal_lahir) : '-';
            const statusBadgeClass = data.status == 'aktif' ? 'badge-aktif' : 'badge-nonaktif';
            const statusText = data.status.toUpperCase();

            let jabatanClass = '';
            if (data.jabatan == 'Ketua') jabatanClass = 'badge-ketua';
            else if (data.jabatan == 'Wakil Ketua') jabatanClass = 'badge-wakil';
            else if (data.jabatan == 'Sekretaris') jabatanClass = 'badge-sekretaris';
            else jabatanClass = 'badge-anggota';

            const hasFoto = data.foto && data.foto !== '';
            const fotoUrl = hasFoto ? `../../assets/img/uploads/anggota/${data.foto}` : '';
            const hasKTP = data.foto_ktp && data.foto_ktp !== '';
            const ktpUrl = hasKTP ? `../../assets/img/uploads/ktp/${data.foto_ktp}` : '';

            const content = document.getElementById('detailContent');
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-4 text-center border-end">
                        <div class="nomor-badge-detail mx-auto mb-3">
                            ${String(data.nomor_anggota).padStart(2, '0')}
                        </div>
                        ${hasFoto ? 
                            `<img src="${fotoUrl}" class="modal-foto mb-3 d-block mx-auto" style="width:150px;height:180px; object-fit:cover; border-radius:16px;">` : 
                            `<div class="modal-foto mb-3 d-flex align-items-center justify-content-center mx-auto" style="width:150px;height:180px; background:rgba(247,184,1,0.1); border-radius:16px; font-size:64px; color:#F7B801;">
                                <i class="fas fa-user-circle"></i>
                            </div>`
                        }
                        <h6 class="fw-bold mb-2" style="color: #F7B801;">${escapeHtml(data.nama)}</h6>
                        <span class="${statusBadgeClass}">${statusText}</span>
                        <div class="mt-2">
                            <span class="badge-jabatan ${jabatanClass}"><i class="fas fa-tag me-1"></i>${escapeHtml(data.jabatan || 'Anggota')}</span>
                        </div>
                        <hr>
                        <p class="small mb-1" style="color: #666;">Terdaftar sejak</p>
                        <p class="fw-bold" style="color: #1A1A1A;">${new Date(data.created_at).toLocaleDateString('id-ID', {day:'numeric', month:'long', year:'numeric'})}</p>
                    </div>
                    <div class="col-md-8">
                        <table class="table table-borderless table-sm">
                            <tr><td class="detail-label" width="140">NOMOR ANGGOTA</td><td class="detail-value">: ${String(data.nomor_anggota).padStart(2, '0')}</td></tr>
                            <tr><td class="detail-label">NIK</td><td class="detail-value">: ${escapeHtml(data.nik || '-')}</td></tr>
                            <tr><td class="detail-label">JENIS KELAMIN</td><td class="detail-value">: ${data.jenis_kelamin}</td></tr>
                            <tr><td class="detail-label">TEMPAT LAHIR</td><td class="detail-value">: ${escapeHtml(data.tempat_lahir || '-')}</td></tr>
                            <tr><td class="detail-label">TANGGAL LAHIR</td><td class="detail-value">: ${data.tanggal_lahir ? new Date(data.tanggal_lahir).toLocaleDateString('id-ID', {day:'numeric', month:'long', year:'numeric'}) : '-'} (${umur} tahun)</td></tr>
                            <tr><td class="detail-label">ALAMAT</td><td class="detail-value">: ${escapeHtml(data.alamat || '-')}</td></tr>
                            <tr><td class="detail-label">NO HP</td><td class="detail-value">: <a href="https://wa.me/${data.no_hp.replace(/[^0-9]/g,'')}" target="_blank" style="color:#F7B801;"><i class="fab fa-whatsapp"></i> ${escapeHtml(data.no_hp)}</a></td></tr>
                            <tr><td class="detail-label">JABATAN</td><td class="detail-value">: ${escapeHtml(data.jabatan || 'Anggota')}</td></tr>
                        </table>
                        <h6 class="mt-4 mb-3 fw-bold" style="color: #F7B801;"><i class="fas fa-image me-2"></i>FOTO KTP</h6>
                        ${hasKTP ? 
                            `<img src="${ktpUrl}" class="modal-ktp" style="max-width:100%; max-height:200px; border-radius:12px; border:1px solid rgba(247,184,1,0.3);">` : 
                            '<p class="text-muted"><i class="fas fa-times-circle me-1"></i> Tidak ada foto KTP</p>'
                        }
                    </div>
                </div>
            `;

            const modal = new bootstrap.Modal(document.getElementById('modalDetail'));
            modal.show();
        }

        function hapusAnggota(id, nama) {
            Swal.fire({
                title: 'Yakin hapus?',
                html: `<strong>${escapeHtml(nama)}</strong> akan dihapus permanen!<br>Foto dan data tidak dapat dikembalikan.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#F7B801',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'hapus');
                    formData.append('id', id);

                    fetch('index.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Terhapus!', data.message, 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Error!', data.message, 'error');
                            }
                        });
                }
            });
        }

        function cetakDetail() {
            const detailEl = document.getElementById('detailContent').innerHTML;
            const win = window.open('', '', 'width=800,height=600');
            win.document.write(`
                <html><head><title>Detail Anggota</title>
                <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                <style>
                    body{padding:30px; background:white; font-family:'Poppins',sans-serif;} 
                    .badge-aktif{background:#28a745;color:white;padding:4px 12px;border-radius:20px;} 
                    .badge-nonaktif{background:#6c757d;color:white;padding:4px 12px;border-radius:20px;}
                    .nomor-badge-detail{display:inline-block;background:#F7B801;color:#1A1A1A;padding:8px 16px;border-radius:12px;font-weight:bold;}
                    .detail-label{font-weight:bold;color:#F7B801;}
                    .badge-ketua{background:#F7B801;color:#1A1A1A;padding:4px 10px;border-radius:20px;font-size:12px;}
                    .badge-wakil{background:#E5A800;color:#1A1A1A;padding:4px 10px;border-radius:20px;font-size:12px;}
                    .badge-sekretaris{background:#D49A00;color:#1A1A1A;padding:4px 10px;border-radius:20px;font-size:12px;}
                    .badge-anggota{background:#6c757d;color:white;padding:4px 10px;border-radius:20px;font-size:12px;}
                </style>
                </head><body>
                <h3 style="color:#F7B801">Detail Anggota BARRES 698</h3><hr>
                ${detailEl}
                </body></html>
            `);
            win.document.close();
            setTimeout(() => win.print(), 500);
        }

        // Submit forms
        document.getElementById('formTambah').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'tambah');
            fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Sukses!', data.message, 'success').then(() => location.reload());
                        bootstrap.Modal.getInstance(document.getElementById('modalTambah')).hide();
                    } else {
                        Swal.fire('Error!', data.message, 'error');
                    }
                });
        });

        document.getElementById('formEdit').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit');
            fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Sukses!', data.message, 'success').then(() => location.reload());
                        bootstrap.Modal.getInstance(document.getElementById('modalEdit')).hide();
                    } else {
                        Swal.fire('Error!', data.message, 'error');
                    }
                });
        });

        // User dropdown
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