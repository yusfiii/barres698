<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

checkAuth();
checkRole(['super_admin']);

$user = getCurrentUser();
$message = '';
$messageType = '';

// Get total BPK untuk sidebar
$conn = getConnection();
$total_bpk = $conn->query("SELECT COUNT(*) as total FROM bpk")->fetch_assoc()['total'];
$conn->close();

// Include sidebar dari folder includes
include __DIR__ . '/../../includes/sidebar.php';

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn = getConnection();

    $stmt = $conn->prepare("SELECT foto FROM hydrant WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $hydrant = $result->fetch_assoc();

    if ($hydrant && $hydrant['foto']) {
        $fotoPath = '../../assets/img/uploads/hydrant/' . $hydrant['foto'];
        if (file_exists($fotoPath)) {
            unlink($fotoPath);
        }
    }

    $stmt = $conn->prepare("DELETE FROM hydrant WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = "Data hydrant berhasil dihapus!";
        $messageType = "success";
    } else {
        $message = "Gagal menghapus data!";
        $messageType = "danger";
    }
    $stmt->close();
    $conn->close();
}

// Get all hydrants
$conn = getConnection();
$query = "SELECT * FROM hydrant ORDER BY created_at DESC";
$result = $conn->query($query);
$hydrants = [];
while ($row = $result->fetch_assoc()) {
    $hydrants[] = $row;
}
$total_hydrant = count($hydrants);
$berfungsi = $conn->query("SELECT COUNT(*) as total FROM hydrant WHERE status = 'berfungsi'")->fetch_assoc()['total'];
$rusak = $conn->query("SELECT COUNT(*) as total FROM hydrant WHERE status = 'rusak'")->fetch_assoc()['total'];
$conn->close();

// Data untuk JSON
$all_hydrant_data = [];
foreach ($hydrants as $row) {
    $all_hydrant_data[$row['id']] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Hydrant - BARRES 698</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

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

        .btn-tambah {
            background: linear-gradient(135deg, #F7B801, #E5A800);
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            color: #1A1A1A;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-tambah:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(247, 184, 1, 0.3);
        }

        .card-custom {
            background: #FFFFFF;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            overflow: hidden;
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

        .foto-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 10px;
            background: #F5F5F5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-action {
            background: transparent;
            border: none;
            padding: 6px 10px;
            border-radius: 10px;
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

        .badge-berfungsi {
            background: rgba(40, 167, 69, 0.1);
            color: #1e7e34;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }

        .badge-rusak {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }

        /* Alert */
        .alert-custom {
            border-radius: 14px;
            padding: 12px 18px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Modal Floating */
        .modal-floating {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-floating.show {
            display: flex;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-floating-content {
            background: #FFFFFF;
            border-radius: 28px;
            width: 95%;
            max-width: 1200px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalFlyIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modalFlyIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-30px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-floating-header {
            padding: 20px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
            background: #FFFFFF;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        .modal-floating-header h4 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #F7B801;
        }

        .close-modal {
            background: transparent;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            color: #666;
        }

        .close-modal:hover {
            background: rgba(247, 184, 1, 0.1);
            color: #F7B801;
        }

        .modal-floating-body {
            padding: 28px;
            position: relative;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            color: #1A1A1A;
        }

        .form-label .required {
            color: #F7B801;
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

        .map-container {
            height: 400px;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        .coordinate-info {
            background: #F8F9FA;
            border-radius: 14px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .coordinate-info p {
            margin: 0 0 10px 0;
            font-size: 13px;
            font-weight: 600;
            color: #F7B801;
        }

        .coordinate-input-group {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .coordinate-input-group .form-control {
            flex: 1;
        }

        .btn-location {
            background: rgba(247, 184, 1, 0.1);
            border: 1px solid rgba(247, 184, 1, 0.3);
            padding: 10px 16px;
            border-radius: 14px;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            color: #F7B801;
        }

        .btn-location:hover {
            background: #F7B801;
            color: #1A1A1A;
        }

        .btn-submit {
            background: linear-gradient(135deg, #F7B801, #E5A800);
            border: none;
            padding: 12px 24px;
            border-radius: 14px;
            font-weight: 600;
            transition: all 0.2s;
            color: #1A1A1A;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(247, 184, 1, 0.3);
        }

        .btn-cancel {
            background: transparent;
            padding: 12px 24px;
            border-radius: 14px;
            font-weight: 600;
            transition: all 0.2s;
            color: #666;
            border: 1px solid #ccc;
        }

        .btn-cancel:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .image-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 12px;
            margin-top: 10px;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 28px;
            z-index: 20;
        }

        .loading-overlay.show {
            display: flex;
        }

        .info-text {
            font-size: 12px;
            margin-top: 5px;
            color: #666;
        }

        /* DataTables overrides */
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            background: #F8F8F8;
            border: 1px solid #E0E0E0;
            color: #1A1A1A;
            border-radius: 10px;
            padding: 6px 12px;
        }

        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            color: #666;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            background: #F8F8F8 !important;
            border-color: #E0E0E0 !important;
            color: #1A1A1A !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #F7B801 !important;
            color: #1A1A1A !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }

            .coordinate-input-group {
                flex-direction: column;
                gap: 10px;
            }

            .card-header-custom {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
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
                <h2>Data Hydrant</h2>
                <p>Kelola data titik hydrant pemadam kebakaran - Kota Banjarbaru, Kalimantan Selatan</p>
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

        <div class="dropdown-menu-custom" id="dropdownMenu">
            <a href="../../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert-custom alert-<?= $messageType ?>">
                <i class="fas <?= $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                <span><?= $message ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-fire-hydrant"></i></div>
                    <div class="stat-number"><?= $total_hydrant ?></div>
                    <div class="stat-label">Total Hydrant</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?= $berfungsi ?></div>
                    <div class="stat-label">Berfungsi</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-number"><?= $rusak ?></div>
                    <div class="stat-label">Rusak</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-number"><?= $total_hydrant > 0 ? round(($berfungsi / $total_hydrant) * 100) : 0 ?>%</div>
                    <div class="stat-label">Kondisi Baik</div>
                </div>
            </div>
        </div>

        <div class="card-custom">
            <div class="card-header-custom">
                <h3><i class="fas fa-list"></i> Daftar Hydrant</h3>
                <button class="btn-tambah" id="btnTambah"><i class="fas fa-plus"></i> Tambah Hydrant</button>
            </div>
            <div class="table-responsive">
                <table class="table-custom table" id="dataTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Foto</th>
                            <th>Koordinat</th>
                            <th>Alamat</th>
                            <th>Kecamatan</th>
                            <th>Kelurahan</th>
                            <th>Tahun</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hydrants as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <?php if ($row['foto'] && file_exists('../../assets/img/uploads/hydrant/' . $row['foto'])): ?>
                                        <img src="../../assets/img/uploads/hydrant/<?= $row['foto'] ?>" class="foto-thumb">
                                    <?php else: ?>
                                        <div class="foto-thumb d-flex align-items-center justify-content-center">
                                            <i class="fas fa-fire-hydrant" style="color:#999; font-size:24px;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= number_format($row['latitude'], 6) ?><br>
                                    <?= number_format($row['longitude'], 6) ?>
                                </td>
                                <td><?= htmlspecialchars(substr($row['alamat'], 0, 40)) ?><?= strlen($row['alamat'] ?? '') > 40 ? '...' : '' ?></td>
                                <td><?= htmlspecialchars($row['kecamatan'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['kelurahan'] ?? '-') ?></td>
                                <td><?= $row['tahun_pemasangan'] ?? '-' ?></td>
                                <td>
                                    <?php if ($row['status'] == 'berfungsi'): ?>
                                        <span class="badge-berfungsi"><i class="fas fa-check-circle me-1"></i> Berfungsi</span>
                                    <?php else: ?>
                                        <span class="badge-rusak"><i class="fas fa-times-circle me-1"></i> Rusak</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-action btn-detail" data-id="<?= $row['id'] ?>"><i class="fas fa-eye"></i></button>
                                    <button class="btn-action btn-edit" data-id="<?= $row['id'] ?>"><i class="fas fa-edit"></i></button>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn-action danger" onclick="return confirm('Yakin hapus data ini?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Floating -->
    <div class="modal-floating" id="modalFloating">
        <div class="modal-floating-content">
            <div class="modal-floating-header">
                <h4><i class="fas fa-fire-hydrant"></i> <span id="modalTitle">Tambah Data Hydrant</span></h4>
                <button class="close-modal" id="closeModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-floating-body" style="position: relative;">
                <div class="loading-overlay" id="loadingOverlay">
                    <div class="spinner-border text-warning"></div>
                </div>
                <form id="hydrantForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="editId">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-map-marker-alt me-1"></i> Kecamatan <span class="required">*</span></label>
                                <select name="kecamatan" id="kecamatan" class="form-select" required>
                                    <option value="">Pilih Kecamatan</option>
                                    <option value="Landasan Ulin">Landasan Ulin</option>
                                    <option value="Cempaka">Cempaka</option>
                                    <option value="Banjarbaru Utara">Banjarbaru Utara</option>
                                    <option value="Banjarbaru Selatan">Banjarbaru Selatan</option>
                                    <option value="Liang Anggang">Liang Anggang</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-building"></i> Kelurahan/Desa <span class="required">*</span></label>
                                <input type="text" name="kelurahan" id="kelurahan" class="form-control" placeholder="Kelurahan/Desa" required>
                            </div>
                        </div>
                    </div>

                    <!-- Map -->
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-map me-1"></i> Pilih Lokasi di Peta</label>
                        <div class="map-container">
                            <div id="map"></div>
                        </div>
                        <div class="info-text"><i class="fas fa-info-circle"></i> Klik pada peta atau seret marker untuk memilih titik hydrant</div>
                    </div>

                    <!-- Koordinat Manual -->
                    <div class="coordinate-info">
                        <p><i class="fas fa-crosshairs"></i> Koordinat Lokasi</p>
                        <div class="coordinate-input-group">
                            <div class="form-group" style="flex:1; margin-bottom:0;">
                                <label class="form-label" style="font-size:12px;">Latitude</label>
                                <input type="number" step="any" name="latitude" id="latitude" class="form-control" placeholder="-3.4422" required>
                            </div>
                            <div class="form-group" style="flex:1; margin-bottom:0;">
                                <label class="form-label" style="font-size:12px;">Longitude</label>
                                <input type="number" step="any" name="longitude" id="longitude" class="form-control" placeholder="114.8325" required>
                            </div>
                            <div style="display: flex; align-items: flex-end;">
                                <button type="button" class="btn-location" id="applyCoordinates" style="margin-bottom:0;"><i class="fas fa-check"></i> Terapkan</button>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn-location" id="getCurrentLocation"><i class="fas fa-location-dot"></i> Gunakan Lokasi Saya</button>
                            <button type="button" class="btn-location" id="searchAddressBtn"><i class="fas fa-search"></i> Cari Alamat</button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-location-dot"></i> Alamat Lengkap <span class="required">*</span></label>
                                <textarea name="alamat" id="alamat" class="form-control" rows="2" placeholder="Alamat lengkap lokasi hydrant" required></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-calendar-alt me-1"></i> Tahun Pemasangan</label>
                                <input type="number" name="tahun_pemasangan" id="tahun_pemasangan" class="form-control" placeholder="2024" min="1990" max="2030">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-info-circle me-1"></i> Status <span class="required">*</span></label>
                                <select name="status" id="status" class="form-select" required>
                                    <option value="berfungsi">Berfungsi</option>
                                    <option value="rusak">Rusak</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-sticky-note me-1"></i> Keterangan</label>
                                <textarea name="keterangan" id="keterangan" class="form-control" rows="2" placeholder="Keterangan tambahan tentang hydrant"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-image"></i> Foto Hydrant</label>
                                <input type="file" name="foto" id="foto" class="form-control" accept="image/*">
                                <div id="fotoPreview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn-submit" id="submitBtn"><i class="fas fa-save"></i> Simpan Data</button>
                        <button type="button" class="btn-cancel" id="cancelModal"><i class="fas fa-times"></i> Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal-floating" id="modalDetail" style="z-index: 2100;">
        <div class="modal-floating-content" style="max-width: 800px;">
            <div class="modal-floating-header">
                <h4><i class="fas fa-fire-hydrant"></i> Detail Hydrant</h4>
                <button class="close-modal" id="closeDetailModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-floating-body" id="detailContent"></div>
        </div>
    </div>

    <!-- Modal Cari Alamat -->
    <div class="modal-floating" id="searchModal" style="z-index: 2200;">
        <div class="modal-floating-content" style="max-width: 500px;">
            <div class="modal-floating-header">
                <h4><i class="fas fa-search"></i> Cari Alamat</h4>
                <button class="close-modal" id="closeSearchModal"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-floating-body">
                <div class="form-group">
                    <label class="form-label">Masukkan nama jalan, kelurahan, atau kecamatan</label>
                    <input type="text" id="searchAddress" class="form-control" placeholder="Contoh: Landasan Ulin, Banjarbaru">
                </div>
                <div id="searchResults" class="mt-3" style="max-height: 300px; overflow-y: auto;"></div>
                <div class="d-flex gap-3 mt-4">
                    <button type="button" class="btn-submit" id="doSearch"><i class="fas fa-search"></i> Cari</button>
                    <button type="button" class="btn-cancel" id="cancelSearchModal">Batal</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        // Data hydrant dari PHP
        const hydrantData = <?= json_encode($all_hydrant_data) ?>;

        // Initialize DataTable
        $('#dataTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json'
            },
            order: [
                [0, 'asc']
            ]
        });

        // Map variables
        let map, marker;
        const defaultLat = -3.4422;
        const defaultLng = 114.8325;

        // Data Kelurahan Banjarbaru yang valid
        const validKelurahan = [
            'Landasan Ulin Timur', 'Landasan Ulin Barat', 'Landasan Ulin Utara',
            'Syamsudin Noor', 'Guntung Payung', 'Guntung Manggis',
            'Cempaka', 'Bangkal', 'Palam', 'Sungai Tiung', 'Cempaka Baru',
            'Loktabat Utara', 'Loktabat Selatan', 'Mentaos', 'Sungai Ulin', 'Komet',
            'Guntung Paikat', 'Kemuning', 'Sungai Besar', 'Sungai Lulut',
            'Landasan Ulin Tengah', 'Pangeran', 'Basirih', 'Liang Anggang Baru'
        ];

        const kelurahanToKecamatan = {
            'Landasan Ulin Timur': 'Landasan Ulin',
            'Landasan Ulin Barat': 'Landasan Ulin',
            'Landasan Ulin Utara': 'Landasan Ulin',
            'Syamsudin Noor': 'Landasan Ulin',
            'Guntung Payung': 'Landasan Ulin',
            'Guntung Manggis': 'Landasan Ulin',
            'Cempaka': 'Cempaka',
            'Bangkal': 'Cempaka',
            'Palam': 'Cempaka',
            'Sungai Tiung': 'Cempaka',
            'Cempaka Baru': 'Cempaka',
            'Loktabat Utara': 'Banjarbaru Utara',
            'Loktabat Selatan': 'Banjarbaru Utara',
            'Mentaos': 'Banjarbaru Utara',
            'Sungai Ulin': 'Banjarbaru Utara',
            'Komet': 'Banjarbaru Utara',
            'Guntung Paikat': 'Banjarbaru Selatan',
            'Kemuning': 'Banjarbaru Selatan',
            'Sungai Besar': 'Banjarbaru Selatan',
            'Sungai Lulut': 'Banjarbaru Selatan',
            'Landasan Ulin Tengah': 'Liang Anggang',
            'Pangeran': 'Liang Anggang',
            'Basirih': 'Liang Anggang',
            'Liang Anggang Baru': 'Liang Anggang'
        };

        function initMap(lat = defaultLat, lng = defaultLng) {
            if (map) map.remove();
            const tileUrl = 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
            map = L.map('map').setView([lat, lng], 14);
            L.tileLayer(tileUrl, {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
                subdomains: 'abcd',
                maxZoom: 19
            }).addTo(map);

            marker = L.marker([lat, lng], {
                draggable: true
            }).addTo(map);
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;

            marker.on('dragend', function(e) {
                const pos = e.target.getLatLng();
                updateCoordinatesFromLatLng(pos.lat, pos.lng);
                reverseGeocode(pos.lat, pos.lng);
            });

            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                updateCoordinatesFromLatLng(e.latlng.lat, e.latlng.lng);
                reverseGeocode(e.latlng.lat, e.latlng.lng);
            });
        }

        function updateCoordinatesFromLatLng(lat, lng) {
            document.getElementById('latitude').value = lat.toFixed(8);
            document.getElementById('longitude').value = lng.toFixed(8);
        }

        async function reverseGeocode(lat, lng) {
            document.getElementById('loadingOverlay').classList.add('show');
            const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=id&addressdetails=1`;

            try {
                const response = await fetch(url);
                const data = await response.json();
                if (data.display_name) {
                    document.getElementById('alamat').value = data.display_name;
                    if (data.address) {
                        let kelurahan = data.address.village || data.address.suburb || data.address.neighbourhood || '';
                        let kecamatan = data.address.city_district || data.address.county || '';
                        kelurahan = kelurahan.replace(/^(Kelurahan|Desa)\s+/i, '');
                        kecamatan = kecamatan.replace(/^Kecamatan\s+/i, '');
                        let foundKelurahan = '';
                        for (let k of validKelurahan) {
                            if (kelurahan.toLowerCase().includes(k.toLowerCase()) || k.toLowerCase().includes(kelurahan.toLowerCase())) {
                                foundKelurahan = k;
                                break;
                            }
                        }
                        if (foundKelurahan) {
                            document.getElementById('kelurahan').value = foundKelurahan;
                            const matchedKec = kelurahanToKecamatan[foundKelurahan];
                            if (matchedKec) document.getElementById('kecamatan').value = matchedKec;
                        } else if (kelurahan) {
                            document.getElementById('kelurahan').value = kelurahan;
                        }
                        if (kecamatan && !document.getElementById('kecamatan').value) {
                            const kecOptions = ['Landasan Ulin', 'Cempaka', 'Banjarbaru Utara', 'Banjarbaru Selatan', 'Liang Anggang'];
                            for (let k of kecOptions) {
                                if (kecamatan.toLowerCase().includes(k.toLowerCase())) {
                                    document.getElementById('kecamatan').value = k;
                                    break;
                                }
                            }
                        }
                    }
                }
            } catch (err) {
                console.log('Geocoding error:', err);
            } finally {
                document.getElementById('loadingOverlay').classList.remove('show');
            }
        }

        async function searchAddress(query) {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ', Banjarbaru, Kalimantan Selatan')}&limit=10`;
            const response = await fetch(url);
            return response.json();
        }

        function applyManualCoordinates() {
            const lat = parseFloat(document.getElementById('latitude').value);
            const lng = parseFloat(document.getElementById('longitude').value);
            if (isNaN(lat) || isNaN(lng)) {
                alert('Masukkan latitude dan longitude yang valid!');
                return;
            }
            map.setView([lat, lng], 16);
            marker.setLatLng([lat, lng]);
            reverseGeocode(lat, lng);
        }

        function getCurrentLocation() {
            if (navigator.geolocation) {
                document.getElementById('loadingOverlay').classList.add('show');
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    map.setView([lat, lng], 16);
                    marker.setLatLng([lat, lng]);
                    updateCoordinatesFromLatLng(lat, lng);
                    reverseGeocode(lat, lng);
                    document.getElementById('loadingOverlay').classList.remove('show');
                }, function(error) {
                    alert('Gagal mendapatkan lokasi: ' + error.message);
                    document.getElementById('loadingOverlay').classList.remove('show');
                });
            } else {
                alert('Browser tidak mendukung geolocation');
            }
        }

        // Modal handlers
        const modal = document.getElementById('modalFloating');
        const detailModal = document.getElementById('modalDetail');
        const searchModal = document.getElementById('searchModal');
        const btnTambah = document.getElementById('btnTambah');

        function openModal() {
            modal.classList.add('show');
            setTimeout(() => {
                map?.invalidateSize();
            }, 200);
        }

        function closeModalFunc() {
            modal.classList.remove('show');
        }

        function closeDetailModal() {
            detailModal.classList.remove('show');
        }

        function openSearchModal() {
            searchModal.classList.add('show');
        }

        function closeSearchModal() {
            searchModal.classList.remove('show');
            document.getElementById('searchAddress').value = '';
            document.getElementById('searchResults').innerHTML = '';
        }

        // Reset form untuk tambah
        btnTambah.addEventListener('click', function() {
            document.getElementById('hydrantForm').reset();
            document.getElementById('editId').value = '';
            document.getElementById('modalTitle').innerHTML = 'Tambah Data Hydrant';
            document.getElementById('fotoPreview').innerHTML = '';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Simpan Data';
            initMap(defaultLat, defaultLng);
            openModal();
        });

        document.getElementById('closeModal').addEventListener('click', closeModalFunc);
        document.getElementById('cancelModal').addEventListener('click', closeModalFunc);
        document.getElementById('closeDetailModal').addEventListener('click', closeDetailModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModalFunc();
        });
        detailModal.addEventListener('click', function(e) {
            if (e.target === detailModal) closeDetailModal();
        });
        document.getElementById('applyCoordinates').addEventListener('click', applyManualCoordinates);
        document.getElementById('getCurrentLocation').addEventListener('click', getCurrentLocation);
        document.getElementById('searchAddressBtn').addEventListener('click', openSearchModal);
        document.getElementById('closeSearchModal').addEventListener('click', closeSearchModal);
        document.getElementById('cancelSearchModal').addEventListener('click', closeSearchModal);
        searchModal.addEventListener('click', function(e) {
            if (e.target === searchModal) closeSearchModal();
        });

        document.getElementById('doSearch').addEventListener('click', async function() {
            const query = document.getElementById('searchAddress').value;
            if (!query) {
                alert('Masukkan alamat yang ingin dicari!');
                return;
            }
            document.getElementById('loadingOverlay').classList.add('show');
            const results = await searchAddress(query);
            document.getElementById('loadingOverlay').classList.remove('show');
            const resultsDiv = document.getElementById('searchResults');
            if (results.length === 0) {
                resultsDiv.innerHTML = '<div class="alert alert-danger">Tidak ditemukan hasil</div>';
                return;
            }
            let html = '<div class="list-group">';
            results.forEach(result => {
                html += `<button type="button" class="list-group-item list-group-item-action" style="background:#FFF; border:1px solid #E0E0E0; margin-bottom:8px; border-radius:12px; padding:12px; text-align:left; width:100%;" onclick="selectSearchResult(${result.lat}, ${result.lon}, '${result.display_name.replace(/'/g, "\\'")}')">
                            <strong>${result.display_name.substring(0, 80)}</strong><br>
                            <small>Lat: ${parseFloat(result.lat).toFixed(6)}, Lng: ${parseFloat(result.lon).toFixed(6)}</small>
                        </button>`;
            });
            html += '</div>';
            resultsDiv.innerHTML = html;
        });

        window.selectSearchResult = function(lat, lng, displayName) {
            map.setView([lat, lng], 17);
            marker.setLatLng([lat, lng]);
            updateCoordinatesFromLatLng(lat, lng);
            reverseGeocode(lat, lng);
            closeSearchModal();
        };

        // Function untuk detail
        function bukaDetailModal(id) {
            const data = hydrantData[id];
            if (!data) return;

            const statusClass = data.status == 'berfungsi' ? 'badge-berfungsi' : 'badge-rusak';
            const statusText = data.status == 'berfungsi' ? 'Berfungsi' : 'Rusak';
            const statusIcon = data.status == 'berfungsi' ? 'fa-check-circle' : 'fa-times-circle';
            const fotoUrl = data.foto && data.foto !== '' ? `../../assets/img/uploads/hydrant/${data.foto}` : null;

            document.getElementById('detailContent').innerHTML = `
                <div class="row">
                    <div class="col-md-5 text-center">
                        ${fotoUrl ? 
                            `<img src="${fotoUrl}" class="img-fluid rounded mb-3" style="max-height: 200px; object-fit: cover; border-radius: 12px;">` : 
                            `<div class="d-flex align-items-center justify-content-center mb-3" style="height: 150px; background: #F8F8F8; border-radius: 12px;">
                                <i class="fas fa-fire-hydrant fa-4x" style="color: #F7B801;"></i>
                            </div>`
                        }
                        <div class="mb-3">
                            <span class="${statusClass}"><i class="fas ${statusIcon} me-1"></i> ${statusText}</span>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <table class="table table-borderless">
                            <tr><td style="width: 120px; font-weight: 600; color: #F7B801;">Koordinat</td><td>: ${parseFloat(data.latitude).toFixed(6)}, ${parseFloat(data.longitude).toFixed(6)}</td></tr>
                            <tr><td style="font-weight: 600; color: #F7B801;">Alamat</td><td>: ${escapeHtml(data.alamat)}</td></tr>
                            <tr><td style="font-weight: 600; color: #F7B801;">Kecamatan</td><td>: ${escapeHtml(data.kecamatan)}</td></tr>
                            <tr><td style="font-weight: 600; color: #F7B801;">Kelurahan</td><td>: ${escapeHtml(data.kelurahan)}</td></tr>
                            <tr><td style="font-weight: 600; color: #F7B801;">Tahun Pemasangan</td><td>: ${data.tahun_pemasangan || '-'}</td></tr>
                            <tr><td style="font-weight: 600; color: #F7B801;">Keterangan</td><td>: ${escapeHtml(data.keterangan || '-')}</td></tr>
                            <tr><td style="font-weight: 600; color: #F7B801;">Terdaftar</td><td>: ${new Date(data.created_at).toLocaleDateString('id-ID')}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="mt-3">
                    <div id="detailMap" style="height: 300px; border-radius: 12px;"></div>
                </div>
            `;

            detailModal.classList.add('show');

            setTimeout(() => {
                const lat = parseFloat(data.latitude);
                const lng = parseFloat(data.longitude);
                const mapDetail = L.map('detailMap').setView([lat, lng], 16);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
                    maxZoom: 19
                }).addTo(mapDetail);
                L.marker([lat, lng]).addTo(mapDetail)
                    .bindPopup(`<strong>Hydrant</strong><br>${escapeHtml(data.alamat)}`).openPopup();
                mapDetail.invalidateSize();
            }, 300);
        }

        // Edit data
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const data = hydrantData[id];
                if (!data) return;

                document.getElementById('editId').value = data.id;
                document.getElementById('kecamatan').value = data.kecamatan;
                document.getElementById('kelurahan').value = data.kelurahan;
                document.getElementById('alamat').value = data.alamat;
                document.getElementById('tahun_pemasangan').value = data.tahun_pemasangan;
                document.getElementById('status').value = data.status;
                document.getElementById('keterangan').value = data.keterangan || '';
                document.getElementById('modalTitle').innerHTML = 'Edit Data Hydrant';
                document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Update Data';

                if (data.foto) {
                    document.getElementById('fotoPreview').innerHTML = `<img src="../../assets/img/uploads/hydrant/${data.foto}" class="image-preview"><p class="text-muted small mt-1">Foto saat ini</p>`;
                } else {
                    document.getElementById('fotoPreview').innerHTML = '';
                }

                const lat = parseFloat(data.latitude);
                const lng = parseFloat(data.longitude);
                initMap(lat, lng);
                updateCoordinatesFromLatLng(lat, lng);
                openModal();
            });
        });

        // Detail data
        document.querySelectorAll('.btn-detail').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                bukaDetailModal(id);
            });
        });

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        // Submit form
        $('#hydrantForm').on('submit', function(e) {
            e.preventDefault();
            document.getElementById('loadingOverlay').classList.add('show');
            const formData = new FormData(this);
            $.ajax({
                url: 'save_data.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    document.getElementById('loadingOverlay').classList.remove('show');
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    document.getElementById('loadingOverlay').classList.remove('show');
                    alert('Terjadi kesalahan!');
                }
            });
        });

        document.getElementById('foto').addEventListener('change', function(e) {
            const preview = document.getElementById('fotoPreview');
            preview.innerHTML = '';
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" class="image-preview">';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Dropdown
        document.getElementById('userAvatar').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('dropdownMenu').classList.toggle('show');
        });
        document.addEventListener('click', function() {
            document.getElementById('dropdownMenu').classList.remove('show');
        });

        // Initialize map
        initMap();
    </script>
</body>

</html>