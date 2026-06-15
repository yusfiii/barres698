<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

checkAuth();
checkRole(['super_admin']);

$user = getCurrentUser();
$stats = getStatistics();

// Include sidebar dari folder includes
include __DIR__ . '/../includes/sidebar.php';

// Get recent incidents (LIMIT 4)
$conn = getConnection();
$recentIncidents = $conn->query("
    SELECT k.*, 
           DATE_FORMAT(waktu, '%d/%m/%Y %H:%i') as formatted_time
    FROM kejadian_kebakaran k 
    ORDER BY waktu DESC 
    LIMIT 4
");

// Get monthly data for chart
$monthlyData = $conn->query("
    SELECT DATE_FORMAT(waktu, '%M %Y') as bulan, 
           COUNT(*) as total,
           SUM(korban_luka) as luka,
           SUM(korban_jiwa) as jiwa
    FROM kejadian_kebakaran 
    WHERE waktu >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(waktu, '%Y-%m')
    ORDER BY DATE_FORMAT(waktu, '%Y-%m')
");

// Get total BPK untuk badge
$total_bpk = $conn->query("SELECT COUNT(*) as total FROM bpk")->fetch_assoc()['total'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Super Admin - SIG Kebakaran BARRES 698</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        .card-header-custom h3 i {
            font-size: 18px;
        }

        .btn-view-all {
            background: transparent;
            border: 1px solid rgba(247, 184, 1, 0.4);
            padding: 8px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #F7B801;
        }

        .btn-view-all:hover {
            background: rgba(247, 184, 1, 0.1);
            color: #F7B801;
            transform: translateY(-2px);
        }

        .card-body-custom {
            padding: 20px 24px;
        }

        .chart-container {
            position: relative;
            height: 280px;
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
            padding: 14px 16px;
            font-size: 13px;
            vertical-align: middle;
            border-bottom: 1px solid #E0E0E0;
        }

        .table-custom tbody tr:hover {
            background: rgba(247, 184, 1, 0.03);
        }

        .badge-luka {
            background: rgba(247, 184, 1, 0.15);
            color: #B8860B;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-jiwa {
            background: rgba(220, 53, 69, 0.1);
            color: #DC3545;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .table-footer {
            padding: 16px 24px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            text-align: center;
            background: #FFFFFF;
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

            .chart-container {
                height: 220px;
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
                <h2>Dashboard</h2>
                <p>Selamat datang kembali, <?= htmlspecialchars($user['username']) ?></p>
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

        <!-- Dropdown Menu -->
        <div class="dropdown-menu-custom" id="dropdownMenu">
            <a href="../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?= $stats['total_kejadian'] ?></div>
                            <div class="stat-label">Total Kejadian</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-fire"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?= $stats['total_luka'] ?></div>
                            <div class="stat-label">Korban Luka</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-user-injured"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?= $stats['total_jiwa'] ?></div>
                            <div class="stat-label">Korban Jiwa</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-skull"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?= count($stats['per_kecamatan']) ?></div>
                            <div class="stat-label">Kecamatan Terdampak</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-lg-7">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="fas fa-chart-line"></i> Statistik Kejadian per Bulan</h3>
                    </div>
                    <div class="card-body-custom">
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="fas fa-chart-pie"></i> Distribusi per Kecamatan</h3>
                    </div>
                    <div class="card-body-custom">
                        <div class="chart-container">
                            <canvas id="kecamatanPieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Incidents Table -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3><i class="fas fa-list"></i> Kejadian Terbaru</h3>
                <a href="kejadian/index.php" class="btn-view-all">
                    <i class="fas fa-eye"></i> Lihat Semua Kejadian
                </a>
            </div>
            <div class="card-body-custom p-0">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Waktu</th>
                                <th>Lokasi</th>
                                <th>Kecamatan</th>
                                <th>Korban Luka</th>
                                <th>Korban Jiwa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $incidentsData = [];
                            while ($row = $recentIncidents->fetch_assoc()):
                                $incidentsData[] = $row;
                            endwhile;

                            if (count($incidentsData) > 0):
                                foreach ($incidentsData as $row):
                            ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= $row['formatted_time'] ?></td>
                                        <td><?= htmlspecialchars(substr($row['alamat'], 0, 50)) ?><?= strlen($row['alamat']) > 50 ? '...' : '' ?></td>
                                        <td><?= htmlspecialchars($row['kecamatan'] ?? '-') ?></td>
                                        <td><span class="badge-luka"><?= $row['korban_luka'] ?></span></td>
                                        <td><span class="badge-jiwa"><?= $row['korban_jiwa'] ?></span></td>
                                    </tr>
                                <?php
                                endforeach;
                            else:
                                ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">Belum ada data kejadian</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($incidentsData) > 0): ?>
                    <div class="table-footer">
                        <a href="kejadian/index.php" class="btn-view-all">
                            <i class="fas fa-arrow-right"></i> Kelola Semua Data Kejadian
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        let monthlyChart, kecamatanChart;

        // Dropdown
        document.getElementById('userAvatar').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('dropdownMenu').classList.toggle('show');
        });

        document.addEventListener('click', function() {
            document.getElementById('dropdownMenu').classList.remove('show');
        });

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            const monthlyData = <?php
                                $labels = [];
                                $totals = [];
                                if ($monthlyData) {
                                    $monthlyData->data_seek(0);
                                    while ($row = $monthlyData->fetch_assoc()) {
                                        $labels[] = $row['bulan'];
                                        $totals[] = $row['total'];
                                    }
                                }
                                echo json_encode(['labels' => $labels, 'totals' => $totals]);
                                ?>;

            const ctx1 = document.getElementById('monthlyChart').getContext('2d');
            monthlyChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: monthlyData.labels,
                    datasets: [{
                        label: 'Total Kejadian',
                        data: monthlyData.totals,
                        borderColor: '#F7B801',
                        backgroundColor: 'rgba(247, 184, 1, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#F7B801',
                        pointBorderColor: '#FFFFFF',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#666'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                color: '#666'
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#666'
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            }
                        }
                    }
                }
            });

            const kecamatanData = <?= json_encode($stats['per_kecamatan']) ?>;
            const ctx2 = document.getElementById('kecamatanPieChart').getContext('2d');
            kecamatanChart = new Chart(ctx2, {
                type: 'pie',
                data: {
                    labels: kecamatanData.map(d => d.kecamatan),
                    datasets: [{
                        data: kecamatanData.map(d => d.total),
                        backgroundColor: ['#F7B801', '#E5A800', '#D49A00', '#C38B00', '#B27C00'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#666',
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>

</html>