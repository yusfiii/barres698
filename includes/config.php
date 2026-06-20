<?php
// includes/config.php
session_start();

define('BASE_URL', 'http://localhost/barres_698/');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'barres698_db');

// Koneksi database
function getConnection()
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Timezone
date_default_timezone_set('Asia/Makassar');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// FUNGSI AUTHENTIKASI
// ============================================

/**
 * Cek autentikasi
 */
function checkAuth() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
        header('Location: ' . BASE_URL . 'public/login.php');
        exit;
    }
}

/**
 * Cek role user
 */
function checkRole($roles) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
        header('Location: ' . BASE_URL . 'public/login.php');
        exit;
    }
    
    $userRole = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? '';
    
    if (!in_array($userRole, (array)$roles)) {
        die('Access denied. You do not have permission to access this page.');
    }
}

/**
 * Get current user
 */
function getCurrentUser() {
    if (isset($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    
    if (isset($_SESSION['user_id'])) {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $user;
    }
    
    return [
        'username' => 'Admin',
        'role' => 'super_admin',
        'id' => 1
    ];
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) || isset($_SESSION['user']);
}

// ============================================
// FUNGSI KDE / HEATMAP
// ============================================

/**
 * Get heatmap settings terbaru
 */
function getHeatmapSettings() {
    $conn = getConnection();
    $result = $conn->query("SELECT * FROM heatmap_settings ORDER BY id DESC LIMIT 1");
    
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $conn->close();
        return $data;
    }
    
    $conn->close();
    return [
        'radius' => 25, 
        'blur' => 15, 
        'intensity' => 70
    ];
}
?>