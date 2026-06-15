<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

checkAuth();
checkRole(['admin_bpk']);

$user = getCurrentUser();
$bpk_id = $_SESSION['bpk_id'];

$conn = getConnection();

// Ambil data BPK
$stmt = $conn->prepare("SELECT * FROM bpk WHERE id = ?");
$stmt->bind_param("i", $bpk_id);
$stmt->execute();
$bpk = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Statistik anggota
$total_anggota = $conn->query("SELECT COUNT(*) as total FROM anggota WHERE bpk_id = $bpk_id")->fetch_assoc()['total'];
$anggota_aktif = $conn->query("SELECT COUNT(*) as total FROM anggota WHERE bpk_id = $bpk_id AND status = 'aktif'")->fetch_assoc()['total'];
$anggota_nonaktif = $conn->query("SELECT COUNT(*) as total FROM anggota WHERE bpk_id = $bpk_id AND status = 'nonaktif'")->fetch_assoc()['total'];

// Anggota terbaru
$anggota_terbaru = $conn->query("
    SELECT * FROM anggota 
    WHERE bpk_id = $bpk_id 
    ORDER BY created_at DESC 
    LIMIT 10
");

// Statistik kejadian di wilayah BPK
$kejadian_terkait = $conn->query("
    SELECT COUNT(*) as total 
    FROM kejadian_kebakaran 
    WHERE kecamatan LIKE '%" . $conn->real_escape_string($bpk['nama_bpk']) . "%'
");

$total_kejadian_wilayah = 0;
if ($kejadian_terkait) {
    $total_kejadian_wilayah = $kejadian_terkait->fetch_assoc()['total'];
}

$conn->close();

// Include sidebar
include __DIR__ . '/../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard BPK - BARRES 698</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 12px;
            color: #1A1A1A;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-outline-gold:hover {
            background: rgba(247, 184, 1, 0.1);
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

        .badge-aktif {
            background: rgba(40, 167, 69, 0.1);
            color: #1e7e34;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-nonaktif {
            background: rgba(108, 117, 125, 0.1);
            color: #5a6268;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
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

        /* Profile Card */
        .profile-card {
            background: #FFFFFF;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .profile-header {
            padding: 25px 20px;
            text-align: center;
            background: linear-gradient(135deg, rgba(247, 184, 1, 0.05), rgba(247, 184, 1, 0.02));
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        .profile-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 20px;
            border: 2px solid #F7B801;
            margin: 0 auto 15px;
            display: block;
        }

        .profile-logo-placeholder {
            width: 80px;
            height: 80px;
            background: rgba(247, 184, 1, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 35px;
            color: #F7B801;
        }

        .profile-header h6 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #1A1A1A;
        }

        .profile-header small {
            font-size: 11px;
            color: #666;
        }

        .profile-body {
            padding: 20px;
        }

        .profile-info {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 13px;
            border-bottom: 1px solid #E0E0E0;
        }

        .profile-info:last-child {
            border-bottom: none;
        }

        .profile-info .label {
            font-weight: 500;
            color: #666;
        }

        .profile-info .value {
            font-weight: 600;
            color: #1A1A1A;
        }

        /* Responsive */
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
                <h2>Dashboard BPK</h2>
                <p>Selamat datang kembali, <?= htmlspecialchars($user['username']) ?></p>
            </div>
            <div class="user-dropdown" style="display: flex; align-items: center; gap: 15px;">
                <div class="user-info">
                    <div class="username"><?= htmlspecialchars($user['username']) ?></div>
                    <div class="role">Admin BPK</div>
                </div>
                <div class="user-avatar" id="userAvatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>

        <div class="dropdown-menu-custom" id="dropdownMenu">
            <a href="../public/peta-statistik.php" target="_blank">
                <i class="fas fa-map"></i>
                <span>Lihat Peta</span>
            </a>
            <div class="dropdown-divider"></div>
            <a href="../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <!-- Statistics Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?= $total_anggota ?></div>
                    <div class="stat-label">Total Anggota</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                    <div class="stat-number"><?= $anggota_aktif ?></div>
                    <div class="stat-label">Anggota Aktif</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-times"></i></div>
                    <div class="stat-number"><?= $anggota_nonaktif ?></div>
                    <div class="stat-label">Non Aktif</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-fire"></i></div>
                    <div class="stat-number"><?= $total_kejadian_wilayah ?></div>
                    <div class="stat-label">Kejadian di Wilayah</div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row">
            <!-- Anggota Terbaru -->
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="fas fa-user-plus"></i> Anggota Terbaru</h3>
                        <a href="anggota/tambah.php" class="btn-gold">
                            <i class="fas fa-plus"></i> Tambah Anggota
                        </a>
                    </div>
                    <div class="card-body-custom p-0">
                        <div class="table-responsive">
                            <table class="table-custom">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Anggota</th>
                                        <th>No Kontak</th>
                                        <th>Status</th>
                                        <th>Bergabung</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($anggota_terbaru && $anggota_terbaru->num_rows > 0):
                                        $no = 1;
                                        while ($anggota = $anggota_terbaru->fetch_assoc()):
                                    ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><strong><?= htmlspecialchars($anggota['nama']) ?></strong></td>
                                                <td><?= htmlspecialchars($anggota['no_hp']) ?></td>
                                                <td>
                                                    <?php if ($anggota['status'] == 'aktif'): ?>
                                                        <span class="badge-aktif">Aktif</span>
                                                    <?php else: ?>
                                                        <span class="badge-nonaktif">Nonaktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><small><?= date('d/m/Y', strtotime($anggota['created_at'])) ?></small></td>
                                                <td>
                                                    <button class="btn-action btn-edit" onclick="window.location.href='anggota/edit.php?id=<?= $anggota['id'] ?>'">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn-action danger" onclick="if(confirm('Yakin ingin menghapus anggota ini?')) window.location.href='anggota/hapus.php?id=<?= $anggota['id'] ?>'">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile;
                                    else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <i class="fas fa-user-slash fa-3x mb-3 d-block" style="color: #999;"></i>
                                                <p class="mb-0">Belum ada anggota terdaftar</p>
                                                <a href="anggota/tambah.php" class="btn-gold mt-3" style="display: inline-flex;">
                                                    <i class="fas fa-plus"></i> Tambah Anggota Pertama
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if ($total_anggota > 10): ?>
                        <div style="padding: 16px 24px; border-top: 1px solid rgba(0, 0, 0, 0.08); text-align: center;">
                            <a href="anggota/" class="btn-outline-gold">
                                Lihat Semua Anggota <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile BPK -->
            <div class="col-lg-4">
                <div class="profile-card">
                    <div class="profile-header">
                        <?php
                        $logo_path = '../assets/img/uploads/logo/' . $bpk['logo'];
                        if ($bpk['logo'] && file_exists($logo_path)):
                        ?>
                            <img src="../assets/img/uploads/logo/<?= $bpk['logo'] ?>" class="profile-logo">
                        <?php else: ?>
                            <div class="profile-logo-placeholder">
                                <i class="fas fa-fire-extinguisher"></i>
                            </div>
                        <?php endif; ?>
                        <h6><?= htmlspecialchars($bpk['nama_bpk']) ?></h6>
                        <small>Admin BPK</small>
                    </div>
                    <div class="profile-body">
                        <div class="profile-info">
                            <span class="label">Tahun Berdiri</span>
                            <span class="value"><?= $bpk['tahun_berdiri'] ?></span>
                        </div>
                        <div class="profile-info">
                            <span class="label">Total Anggota</span>
                            <span class="value" style="color: #F7B801;"><?= $total_anggota ?> orang</span>
                        </div>
                        <div class="profile-info">
                            <span class="label">Anggota Aktif</span>
                            <span class="value" style="color: #28a745;"><?= $anggota_aktif ?> orang</span>
                        </div>
                        <div class="profile-info">
                            <span class="label">Username</span>
                            <span class="value"><?= htmlspecialchars($user['username']) ?></span>
                        </div>
                        <div class="profile-info">
                            <span class="label">No HP</span>
                            <span class="value"><?= htmlspecialchars($user['no_hp']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle dropdown
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