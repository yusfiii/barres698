<?php
// includes/session.php
require_once __DIR__ . '/config.php';

/**
 * FUNGSI SESSION TAMBAHAN
 * (Tidak ada redeclare fungsi yang sudah ada di config.php)
 */

/**
 * Set session user setelah login
 */
function setUserSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $user;
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['username'] = $user['username'] ?? '';
    $_SESSION['login_time'] = time();
}

/**
 * Logout user
 */
function logoutUser() {
    // Hapus semua session
    $_SESSION = array();
    
    // Hapus session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

/**
 * Check if user has specific permission
 */
function hasPermission($permission) {
    if (!isLoggedIn()) return false;
    
    $user = getCurrentUser();
    if (!$user) return false;
    
    // Super admin has all permissions
    if ($user['role'] === 'super_admin') return true;
    if ($user['role'] === 'admin') return true;
    
    // Check specific permissions (sesuaikan dengan kebutuhan)
    $permissions = $_SESSION['permissions'] ?? [];
    return in_array($permission, $permissions);
}

/**
 * Regenerate session ID untuk keamanan
 */
function regenerateSession() {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = time();
}

/**
 * Cek session timeout (30 menit)
 */
function checkSessionTimeout($timeout = 1800) {
    if (isset($_SESSION['login_time'])) {
        $elapsed = time() - $_SESSION['login_time'];
        if ($elapsed > $timeout) {
            logoutUser();
        }
    }
}
?>