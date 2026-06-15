<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

checkAuth();
checkRole(['admin_bpk']);

$bpk_id = $_SESSION['bpk_id'];
$error = '';
$success = '';

$conn = getConnection();

// Include sidebar dari folder includes
include __DIR__ . '/../../includes/sidebar.php';

// Ambil data BPK
$query_bpk = "SELECT * FROM bpk WHERE id = $bpk_id";
$result_bpk = $conn->query($query_bpk);
$bpk = $result_bpk->fetch_assoc();

if (!$bpk) {
    die("Data BPK tidak ditemukan");
}

// Fungsi untuk decode JSON fasilitas
function getFasilitasData($json)
{
    if (empty($json) || $json === null) {
        return ['jumlah' => 0, 'keterangan' => '', 'foto' => null];
    }
    $data = json_decode($json, true);
    if (!$data) {
        return ['jumlah' => 0, 'keterangan' => '', 'foto' => null];
    }
    return $data;
}

// Proses update profil BPK (kecuali nomor_registrasi dan nama_bpk)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profil'])) {
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $kecamatan = $conn->real_escape_string($_POST['kecamatan']);
    $kelurahan = $conn->real_escape_string($_POST['kelurahan']);
    $latitude = $conn->real_escape_string($_POST['latitude']);
    $longitude = $conn->real_escape_string($_POST['longitude']);
    $tahun_berdiri = (int)$_POST['tahun_berdiri'];

    // Upload logo baru jika ada (folder logo)
    $logo_name = $bpk['logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $upload = uploadFile($_FILES['logo'], '../../assets/img/uploads/logo/');
        if ($upload['success']) {
            if ($logo_name && file_exists('../../assets/img/uploads/logo/' . $logo_name)) {
                unlink('../../assets/img/uploads/logo/' . $logo_name);
            }
            $logo_name = $upload['filename'];
        }
    }

    $update = "UPDATE bpk SET 
        alamat = '$alamat',
        kecamatan = '$kecamatan',
        kelurahan = '$kelurahan',
        latitude = '$latitude',
        longitude = '$longitude',
        tahun_berdiri = $tahun_berdiri,
        logo = '$logo_name'
        WHERE id = $bpk_id";

    if ($conn->query($update)) {
        $success = "Profil BPK berhasil diperbarui!";
        $result_bpk = $conn->query("SELECT * FROM bpk WHERE id = $bpk_id");
        $bpk = $result_bpk->fetch_assoc();
    } else {
        $error = "Gagal memperbarui profil: " . $conn->error;
    }
}

// Proses update fasilitas Pemadam Tangki
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_fasilitas_tangki'])) {
    $jumlah = (int)$_POST['jumlah_tangki'];
    $keterangan = $conn->real_escape_string($_POST['keterangan_tangki']);

    $foto_name = null;
    $old_data = getFasilitasData($bpk['fasilitas_pemadam_tangki']);
    $old_foto = $old_data['foto'];

    if (isset($_FILES['foto_tangki']) && $_FILES['foto_tangki']['error'] == 0) {
        $upload = uploadFile($_FILES['foto_tangki'], '../../assets/img/uploads/fasilitas/');
        if ($upload['success']) {
            $foto_name = $upload['filename'];
            if ($old_foto && file_exists('../../assets/img/uploads/fasilitas/' . $old_foto)) {
                unlink('../../assets/img/uploads/fasilitas/' . $old_foto);
            }
        } else {
            $foto_name = $old_foto;
        }
    } else {
        $foto_name = $old_foto;
    }

    $json_data = json_encode([
        'jumlah' => $jumlah,
        'keterangan' => $keterangan,
        'foto' => $foto_name
    ]);

    $update = "UPDATE bpk SET fasilitas_pemadam_tangki = '$json_data' WHERE id = $bpk_id";
    if ($conn->query($update)) {
        $success = "Fasilitas Pemadam Tangki berhasil diperbarui!";
        $result_bpk = $conn->query("SELECT * FROM bpk WHERE id = $bpk_id");
        $bpk = $result_bpk->fetch_assoc();
    } else {
        $error = "Gagal memperbarui fasilitas: " . $conn->error;
    }
}

// Proses update fasilitas Pemadam Portable (mobil biasa dengan sirine)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_fasilitas_portable'])) {
    $jumlah = (int)$_POST['jumlah_portable'];
    $keterangan = $conn->real_escape_string($_POST['keterangan_portable']);

    $foto_name = null;
    $old_data = getFasilitasData($bpk['fasilitas_pemadam_portable']);
    $old_foto = $old_data['foto'];

    if (isset($_FILES['foto_portable']) && $_FILES['foto_portable']['error'] == 0) {
        $upload = uploadFile($_FILES['foto_portable'], '../../assets/img/uploads/fasilitas/');
        if ($upload['success']) {
            $foto_name = $upload['filename'];
            if ($old_foto && file_exists('../../assets/img/uploads/fasilitas/' . $old_foto)) {
                unlink('../../assets/img/uploads/fasilitas/' . $old_foto);
            }
        } else {
            $foto_name = $old_foto;
        }
    } else {
        $foto_name = $old_foto;
    }

    $json_data = json_encode([
        'jumlah' => $jumlah,
        'keterangan' => $keterangan,
        'foto' => $foto_name
    ]);

    $update = "UPDATE bpk SET fasilitas_pemadam_portable = '$json_data' WHERE id = $bpk_id";
    if ($conn->query($update)) {
        $success = "Fasilitas Pemadam Portable berhasil diperbarui!";
        $result_bpk = $conn->query("SELECT * FROM bpk WHERE id = $bpk_id");
        $bpk = $result_bpk->fetch_assoc();
    } else {
        $error = "Gagal memperbarui fasilitas: " . $conn->error;
    }
}

// Proses update fasilitas Ambulance
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_fasilitas_ambulance'])) {
    $jumlah = (int)$_POST['jumlah_ambulance'];
    $keterangan = $conn->real_escape_string($_POST['keterangan_ambulance']);

    $foto_name = null;
    $old_data = getFasilitasData($bpk['fasilitas_ambulance']);
    $old_foto = $old_data['foto'];

    if (isset($_FILES['foto_ambulance']) && $_FILES['foto_ambulance']['error'] == 0) {
        $upload = uploadFile($_FILES['foto_ambulance'], '../../assets/img/uploads/fasilitas/');
        if ($upload['success']) {
            $foto_name = $upload['filename'];
            if ($old_foto && file_exists('../../assets/img/uploads/fasilitas/' . $old_foto)) {
                unlink('../../assets/img/uploads/fasilitas/' . $old_foto);
            }
        } else {
            $foto_name = $old_foto;
        }
    } else {
        $foto_name = $old_foto;
    }

    $json_data = json_encode([
        'jumlah' => $jumlah,
        'keterangan' => $keterangan,
        'foto' => $foto_name
    ]);

    $update = "UPDATE bpk SET fasilitas_ambulance = '$json_data' WHERE id = $bpk_id";
    if ($conn->query($update)) {
        $success = "Fasilitas Ambulance berhasil diperbarui!";
        $result_bpk = $conn->query("SELECT * FROM bpk WHERE id = $bpk_id");
        $bpk = $result_bpk->fetch_assoc();
    } else {
        $error = "Gagal memperbarui fasilitas: " . $conn->error;
    }
}

// Ambil data fasilitas yang sudah di-decode
$fasilitas_tangki = getFasilitasData($bpk['fasilitas_pemadam_tangki']);
$fasilitas_portable = getFasilitasData($bpk['fasilitas_pemadam_portable']);
$fasilitas_ambulance = getFasilitasData($bpk['fasilitas_ambulance']);

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil BPK - BARRES 698</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

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

        /* Cards */
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
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 12px;
            color: #F7B801;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
        }

        .btn-outline-gold:hover {
            background: rgba(247, 184, 1, 0.1);
            color: #F7B801;
        }

        /* Form */
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

        .form-control:disabled {
            background: #F0F0F0;
            color: #888;
            cursor: not-allowed;
        }

        /* Info Box */
        .info-box {
            background: #FEF9E6;
            border-radius: 12px;
            padding: 15px;
            border-left: 4px solid #F7B801;
            color: #1A1A1A;
        }

        /* Fasilitas Card */
        .fasilitas-card {
            background: #FFFFFF;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
        }

        .fasilitas-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
            border-color: #F7B801;
        }

        .fasilitas-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }

        .fasilitas-icon {
            width: 100%;
            height: 180px;
            background: rgba(247, 184, 1, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 56px;
            color: #F7B801;
        }

        .fasilitas-body {
            padding: 18px;
        }

        .fasilitas-title {
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 12px;
            color: #1A1A1A;
        }

        .fasilitas-title i {
            color: #F7B801;
            margin-right: 10px;
        }

        .fasilitas-jumlah {
            font-size: 28px;
            font-weight: 700;
            color: #F7B801;
            margin-bottom: 5px;
        }

        .btn-edit-fasilitas {
            margin-top: 12px;
            width: 100%;
        }

        /* Profile Image */
        .profile-logo {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 16px;
            border: 2px solid #F7B801;
            background: #FFFFFF;
        }

        .profile-logo-placeholder {
            width: 150px;
            height: 150px;
            background: rgba(247, 184, 1, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #F7B801;
        }

        /* Modal */
        .modal-content {
            border-radius: 20px;
            overflow: hidden;
            border: none;
        }

        .modal-header-gradient {
            background: linear-gradient(135deg, #F7B801, #E5A800);
            color: #1A1A1A;
            border: none;
        }

        .modal-header-gradient .btn-close {
            filter: brightness(0) invert(1);
        }

        .required:after {
            content: ' *';
            color: #F7B801;
        }

        .preview-img {
            max-width: 100px;
            border-radius: 8px;
            margin-top: 8px;
            border: 1px solid #ddd;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar sudah di-include dari includes/sidebar.php -->

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <div class="page-title">
                <h2>Profil BPK</h2>
                <p>Kelola data BPK dan fasilitas</p>
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

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" style="background: #d4edda; border: none; border-radius: 12px;">
                <i class="fas fa-check-circle me-2"></i> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="background: #f8d7da; border: none; border-radius: 12px;">
                <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Info Peringatan -->
        <div class="info-box mb-4">
            <i class="fas fa-info-circle me-2" style="color: #F7B801;"></i>
            <strong>Informasi:</strong> Nomor Registrasi dan Nama BPK tidak dapat diubah. Jika ada kesalahan, silakan hubungi Super Admin.
        </div>

        <!-- Form Edit Profil BPK -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3><i class="fas fa-edit"></i> Data BPK</h3>
                <button type="button" class="btn-gold" data-bs-toggle="modal" data-bs-target="#modalEditProfil">
                    <i class="fas fa-pen"></i> Edit Profil
                </button>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <?php
                        $logo_path = '../../assets/img/uploads/logo/' . $bpk['logo'];
                        if ($bpk['logo'] && file_exists($logo_path)):
                        ?>
                            <img src="../../assets/img/uploads/logo/<?= $bpk['logo'] ?>" class="profile-logo">
                        <?php else: ?>
                            <div class="profile-logo-placeholder">
                                <i class="fas fa-fire-extinguisher"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-9">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Nomor Registrasi:</strong><br>
                                <?= htmlspecialchars($bpk['nomor_registrasi'] ?? '-') ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Nama BPK:</strong><br>
                                <?= htmlspecialchars($bpk['nama_bpk']) ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Alamat:</strong><br>
                                <?= htmlspecialchars($bpk['alamat'] ?? '-') ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Kecamatan / Kelurahan:</strong><br>
                                <?= htmlspecialchars($bpk['kecamatan'] ?? '-') ?> / <?= htmlspecialchars($bpk['kelurahan'] ?? '-') ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Koordinat:</strong><br>
                                <?= $bpk['latitude'] ?? '-' ?>, <?= $bpk['longitude'] ?? '-' ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Tahun Berdiri:</strong><br>
                                <?= $bpk['tahun_berdiri'] ?? '-' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fasilitas Section -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3><i class="fas fa-truck-fire"></i> Fasilitas BPK</h3>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <!-- Pemadam Tangki (Mobil Pemadam) -->
                    <div class="col-md-4 mb-4">
                        <div class="fasilitas-card">
                            <?php if ($fasilitas_tangki['foto'] && file_exists('../../assets/img/uploads/fasilitas/' . $fasilitas_tangki['foto'])): ?>
                                <img src="../../assets/img/uploads/fasilitas/<?= $fasilitas_tangki['foto'] ?>" class="fasilitas-img">
                            <?php else: ?>
                                <div class="fasilitas-icon">
                                    <i class="fas fa-truck-fire"></i>
                                </div>
                            <?php endif; ?>
                            <div class="fasilitas-body">
                                <h5 class="fasilitas-title"><i class="fas fa-truck-fire"></i> Pemadam Tangki</h5>
                                <div class="fasilitas-jumlah"><?= $fasilitas_tangki['jumlah'] ?> Unit</div>
                                <p class="text-muted small mt-2 mb-0"><?= htmlspecialchars($fasilitas_tangki['keterangan'] ?: 'Tidak ada keterangan') ?></p>
                                <button class="btn-outline-gold btn-edit-fasilitas" data-bs-toggle="modal" data-bs-target="#modalEditTangki">
                                    <i class="fas fa-edit"></i> Edit Fasilitas
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Pemadam Portable (Mobil Biasa dengan Sirine) -->
                    <div class="col-md-4 mb-4">
                        <div class="fasilitas-card">
                            <?php if ($fasilitas_portable['foto'] && file_exists('../../assets/img/uploads/fasilitas/' . $fasilitas_portable['foto'])): ?>
                                <img src="../../assets/img/uploads/fasilitas/<?= $fasilitas_portable['foto'] ?>" class="fasilitas-img">
                            <?php else: ?>
                                <div class="fasilitas-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                            <?php endif; ?>
                            <div class="fasilitas-body">
                                <h5 class="fasilitas-title"><i class="fas fa-truck"></i> Pemadam Portable</h5>
                                <div class="fasilitas-jumlah"><?= $fasilitas_portable['jumlah'] ?> Unit</div>
                                <p class="text-muted small mt-2 mb-0"><?= htmlspecialchars($fasilitas_portable['keterangan'] ?: 'Tidak ada keterangan') ?></p>
                                <button class="btn-outline-gold btn-edit-fasilitas" data-bs-toggle="modal" data-bs-target="#modalEditPortable">
                                    <i class="fas fa-edit"></i> Edit Fasilitas
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Ambulance -->
                    <div class="col-md-4 mb-4">
                        <div class="fasilitas-card">
                            <?php if ($fasilitas_ambulance['foto'] && file_exists('../../assets/img/uploads/fasilitas/' . $fasilitas_ambulance['foto'])): ?>
                                <img src="../../assets/img/uploads/fasilitas/<?= $fasilitas_ambulance['foto'] ?>" class="fasilitas-img">
                            <?php else: ?>
                                <div class="fasilitas-icon">
                                    <i class="fas fa-truck-medical"></i>
                                </div>
                            <?php endif; ?>
                            <div class="fasilitas-body">
                                <h5 class="fasilitas-title"><i class="fas fa-truck-medical"></i> Ambulance</h5>
                                <div class="fasilitas-jumlah"><?= $fasilitas_ambulance['jumlah'] ?> Unit</div>
                                <p class="text-muted small mt-2 mb-0"><?= htmlspecialchars($fasilitas_ambulance['keterangan'] ?: 'Tidak ada keterangan') ?></p>
                                <button class="btn-outline-gold btn-edit-fasilitas" data-bs-toggle="modal" data-bs-target="#modalEditAmbulance">
                                    <i class="fas fa-edit"></i> Edit Fasilitas
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit Profil BPK -->
    <div class="modal fade" id="modalEditProfil" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Profil BPK</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor Registrasi</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($bpk['nomor_registrasi'] ?? '-') ?>" disabled>
                                <small class="text-muted">Tidak dapat diubah</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama BPK</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($bpk['nama_bpk']) ?>" disabled>
                                <small class="text-muted">Tidak dapat diubah</small>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Alamat</label>
                                <input type="text" name="alamat" class="form-control" value="<?= htmlspecialchars($bpk['alamat'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kecamatan</label>
                                <input type="text" name="kecamatan" class="form-control" value="<?= htmlspecialchars($bpk['kecamatan'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kelurahan</label>
                                <input type="text" name="kelurahan" class="form-control" value="<?= htmlspecialchars($bpk['kelurahan'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="latitude" class="form-control" value="<?= htmlspecialchars($bpk['latitude'] ?? '') ?>" placeholder="-3.45236090">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="longitude" class="form-control" value="<?= htmlspecialchars($bpk['longitude'] ?? '') ?>" placeholder="114.84423233">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tahun Berdiri</label>
                                <input type="number" name="tahun_berdiri" class="form-control" value="<?= $bpk['tahun_berdiri'] ?? '' ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Logo BPK</label>
                                <input type="file" name="logo" class="form-control" accept="image/*">
                                <small class="text-muted">Kosongkan jika tidak ingin mengganti logo</small>
                                <?php if ($bpk['logo'] && file_exists('../../assets/img/uploads/logo/' . $bpk['logo'])): ?>
                                    <div class="mt-2">
                                        <small>Logo saat ini:</small><br>
                                        <img src="../../assets/img/uploads/logo/<?= $bpk['logo'] ?>" class="preview-img">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-outline-gold" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_profil" class="btn-gold"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Fasilitas Pemadam Tangki -->
    <div class="modal fade" id="modalEditTangki" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-truck-fire"></i> Edit Pemadam Tangki</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Jumlah Unit</label>
                            <input type="number" name="jumlah_tangki" class="form-control" min="0" value="<?= $fasilitas_tangki['jumlah'] ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan_tangki" class="form-control" rows="2" placeholder="Contoh: Kondisi baik, siap operasional"><?= htmlspecialchars($fasilitas_tangki['keterangan'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Foto Fasilitas</label>
                            <input type="file" name="foto_tangki" class="form-control" accept="image/*">
                            <small class="text-muted">Kosongkan jika tidak ingin mengganti foto</small>
                            <?php if ($fasilitas_tangki['foto'] && file_exists('../../assets/img/uploads/fasilitas/' . $fasilitas_tangki['foto'])): ?>
                                <div class="mt-2">
                                    <small>Foto saat ini:</small><br>
                                    <img src="../../assets/img/uploads/fasilitas/<?= $fasilitas_tangki['foto'] ?>" class="preview-img">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-outline-gold" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_fasilitas_tangki" class="btn-gold"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Fasilitas Pemadam Portable (Mobil Biasa dengan Sirine) -->
    <div class="modal fade" id="modalEditPortable" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-truck"></i> Edit Pemadam Portable</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Jumlah Unit</label>
                            <input type="number" name="jumlah_portable" class="form-control" min="0" value="<?= $fasilitas_portable['jumlah'] ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan_portable" class="form-control" rows="2" placeholder="Contoh: Kondisi baik, siap operasional"><?= htmlspecialchars($fasilitas_portable['keterangan'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Foto Fasilitas</label>
                            <input type="file" name="foto_portable" class="form-control" accept="image/*">
                            <small class="text-muted">Kosongkan jika tidak ingin mengganti foto</small>
                            <?php if ($fasilitas_portable['foto'] && file_exists('../../assets/img/uploads/fasilitas/' . $fasilitas_portable['foto'])): ?>
                                <div class="mt-2">
                                    <small>Foto saat ini:</small><br>
                                    <img src="../../assets/img/uploads/fasilitas/<?= $fasilitas_portable['foto'] ?>" class="preview-img">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-outline-gold" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_fasilitas_portable" class="btn-gold"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Fasilitas Ambulance -->
    <div class="modal fade" id="modalEditAmbulance" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-truck-medical"></i> Edit Ambulance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Jumlah Unit</label>
                            <input type="number" name="jumlah_ambulance" class="form-control" min="0" value="<?= $fasilitas_ambulance['jumlah'] ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan_ambulance" class="form-control" rows="2" placeholder="Contoh: Kondisi baik, siap operasional"><?= htmlspecialchars($fasilitas_ambulance['keterangan'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Foto Fasilitas</label>
                            <input type="file" name="foto_ambulance" class="form-control" accept="image/*">
                            <small class="text-muted">Kosongkan jika tidak ingin mengganti foto</small>
                            <?php if ($fasilitas_ambulance['foto'] && file_exists('../../assets/img/uploads/fasilitas/' . $fasilitas_ambulance['foto'])): ?>
                                <div class="mt-2">
                                    <small>Foto saat ini:</small><br>
                                    <img src="../../assets/img/uploads/fasilitas/<?= $fasilitas_ambulance['foto'] ?>" class="preview-img">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-outline-gold" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_fasilitas_ambulance" class="btn-gold"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Dropdown
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