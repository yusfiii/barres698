<?php
// sidebar.php - Sidebar untuk Admin BPK dan Super Admin
// File ini diletakkan di folder includes/

// Pastikan session sudah dimulai dan role sudah tersedia
$current_page = basename($_SERVER['PHP_SELF']);
$current_url = $_SERVER['PHP_SELF'];
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

// Tentukan base URL untuk link (karena file di includes, halaman di folder masing-masing)
// Kita akan menggunakan path relatif dari folder halaman
?>

<style>
    /* ========== SIDEBAR STYLES ========== */
    .sidebar-wrapper {
        position: fixed;
        left: 0;
        top: 0;
        width: 280px;
        height: 100vh;
        z-index: 1000;
        transition: all 0.3s ease;
        overflow-y: auto;
        background: #FFFFFF;
        border-right: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.03);
    }

    .sidebar-wrapper::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar-wrapper::-webkit-scrollbar-track {
        background: #E0E0E0;
    }

    .sidebar-wrapper::-webkit-scrollbar-thumb {
        background: #F7B801;
        border-radius: 5px;
    }

    .sidebar-brand {
        padding: 24px 20px;
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    }

    .sidebar-brand .brand-link {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }

    .sidebar-brand .brand-icon {
        width: 40px;
        height: 40px;
        background: rgba(247, 184, 1, 0.15);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sidebar-brand .brand-icon i {
        font-size: 22px;
        color: #F7B801;
    }

    .sidebar-brand .brand-text {
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.5px;
        color: #1A1A1A;
        font-family: 'Poppins', sans-serif;
    }

    .sidebar-brand .brand-sub {
        font-size: 11px;
        color: #F7B801;
        margin-top: 4px;
        font-family: 'Poppins', sans-serif;
    }

    .nav-menu {
        list-style: none;
        padding: 0 16px;
    }

    .nav-item {
        margin-bottom: 8px;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 16px;
        border-radius: 14px;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 14px;
        font-weight: 500;
        color: #1A1A1A !important;
        font-family: 'Poppins', sans-serif;
    }

    .nav-link i {
        width: 22px;
        font-size: 18px;
        text-align: center;
    }

    .nav-link:hover {
        background: rgba(247, 184, 1, 0.12);
        color: #E5A800;
    }

    .nav-link.active {
        background: rgba(247, 184, 1, 0.1);
        color: #E5A800 !important;
        border-left: 3px solid #F7B801;
    }

    .nav-link .badge {
        margin-left: auto;
        background: rgba(247, 184, 1, 0.15);
        color: #E5A800;
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 20px;
        font-family: 'Poppins', sans-serif;
    }

    /* Mobile toggle button */
    .mobile-toggle {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1200;
        background: #F7B801;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .mobile-toggle i {
        color: #1A1A1A;
        font-size: 22px;
    }

    @media (max-width: 768px) {
        .sidebar-wrapper {
            transform: translateX(-100%);
            z-index: 1100;
        }

        .sidebar-wrapper.open {
            transform: translateX(0);
        }

        .mobile-toggle {
            display: flex;
        }
    }

    /* Overlay for mobile */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1050;
        display: none;
    }

    .sidebar-overlay.show {
        display: block;
    }
</style>

<!-- Mobile Toggle Button -->
<div class="mobile-toggle" id="mobileToggle">
    <i class="fas fa-bars"></i>
</div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar-wrapper" id="sidebarWrapper">
    <div class="sidebar-brand">
        <a href="<?= $role == 'super_admin' ? '../superadmin/dashboard.php' : '../adminbpk/dashboard.php' ?>" class="brand-link">
            <div class="brand-icon">
                <img src="/barres_698/assets/barres2.png" alt="BARRES Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
            </div>
            <div>
                <div class="brand-text">BARRES 698</div>
                <div class="brand-sub">
                    <?= $role == 'super_admin' ? 'Super Admin Panel' : 'Admin BPK Panel' ?>
                </div>
            </div>
        </a>
    </div>

    <ul class="nav-menu">
        <?php if ($role == 'super_admin'): ?>
            <!-- MENU SUPER ADMIN -->
            <li class="nav-item">
                <a href="/barres_698/superadmin/dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/barres_698/superadmin/kejadian/" class="nav-link <?= strpos($current_page, 'kejadian') !== false ? 'active' : '' ?>">
                    <i class="fas fa-fire"></i>
                    <span>Data Kejadian</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/barres_698/superadmin/bpk/" class="nav-link <?= strpos($current_page, 'bpk') !== false ? 'active' : '' ?>">
                    <i class="fas fa-building"></i>
                    <span>Data BPK</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/barres_698/superadmin/hydran/" class="nav-link <?= strpos($current_page, 'bpk') !== false ? 'active' : '' ?>">
                    <i class="fas fa-hydrant"></i>
                    <span>Data Hydran</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/barres_698/superadmin/heatmap/pengaturan.php" class="nav-link <?= strpos($current_page, 'heatmap') !== false ? 'active' : '' ?>">
                    <i class="fas fa-map-marked-alt"></i>
                    <span>Pengaturan Heatmap</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/barres_698/superadmin/laporan/" class="nav-link <?= strpos($current_page, 'laporan') !== false ? 'active' : '' ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Laporan</span>
                </a>
            </li>
        <?php else: ?>
            <!-- MENU ADMIN BPK -->
            <li class="nav-item">
                <a href="/barres_698/adminbpk/dashboard.php"
                    class="nav-link <?= strpos($current_url, '/adminbpk/dashboard.php') !== false ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="/barres_698/adminbpk/bpk/"
                    class="nav-link <?= strpos($current_url, '/adminbpk/bpk/') !== false ? 'active' : '' ?>">
                    <i class="fas fa-info-circle"></i>
                    <span>Profil BPK</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="/barres_698/adminbpk/anggota/"
                    class="nav-link <?= strpos($current_url, '/adminbpk/anggota/') !== false ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    <span>Data Anggota</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</aside>

<script>
    // Mobile sidebar toggle
    const mobileToggle = document.getElementById('mobileToggle');
    const sidebarWrapper = document.getElementById('sidebarWrapper');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            sidebarWrapper.classList.toggle('open');
            sidebarOverlay.classList.toggle('show');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebarWrapper.classList.remove('open');
            sidebarOverlay.classList.remove('show');
        });
    }

    // Close sidebar on window resize if open
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebarWrapper.classList.remove('open');
            sidebarOverlay.classList.remove('show');
        }
    });
</script>