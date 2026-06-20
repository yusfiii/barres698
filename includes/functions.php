<?php
// includes/functions.php
require_once __DIR__ . '/config.php';

/**
 * SANITASI - Hanya di sini (hapus dari config.php)
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Flash Message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type'];
        $message = $flash['message'];
        $class = $type === 'success' ? 'alert-success' : ($type === 'error' ? 'alert-danger' : 'alert-info');
        echo "<div class='alert $class alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}

/**
 * Upload File
 */
function uploadFile($file, $target_dir = "assets/img/uploads/") {
    // Buat direktori jika belum ada
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_file = $target_dir . basename($file["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return ['success' => false, 'message' => 'File bukan gambar'];
    }
    
    // Check file size (max 5MB)
    if ($file["size"] > 5000000) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar (maks 5MB)'];
    }
    
    // Allow certain file formats
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($imageFileType, $allowed)) {
        return ['success' => false, 'message' => 'Hanya file ' . implode(', ', $allowed) . ' yang diperbolehkan'];
    }
    
    // Generate unique filename
    $new_filename = date('Ymd_His') . '_' . uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => true, 'filename' => $new_filename, 'path' => $target_file];
    } else {
        return ['success' => false, 'message' => 'Gagal mengupload file'];
    }
}

/**
 * Delete file
 */
function deleteFile($filename, $target_dir = "assets/img/uploads/") {
    $filepath = $target_dir . $filename;
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Get Statistics
 */
function getStatistics() {
    $conn = getConnection();
    
    $stats = [];
    
    // Total kejadian
    $result = $conn->query("SELECT COUNT(*) as total FROM kejadian_kebakaran");
    $stats['total_kejadian'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Total korban
    $result = $conn->query("SELECT SUM(korban_luka) as luka, SUM(korban_jiwa) as jiwa FROM kejadian_kebakaran");
    $korban = $result->fetch_assoc();
    $stats['total_luka'] = $korban['luka'] ?? 0;
    $stats['total_jiwa'] = $korban['jiwa'] ?? 0;
    
    // Total bangunan
    $result = $conn->query("SELECT SUM(jumlah_bangunan) as total FROM kejadian_kebakaran");
    $stats['total_bangunan'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Total KK
    $result = $conn->query("SELECT SUM(jumlah_KK) as total FROM kejadian_kebakaran");
    $stats['total_kk'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Total individu
    $result = $conn->query("SELECT SUM(jumlah_individu) as total FROM kejadian_kebakaran");
    $stats['total_individu'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Statistik per kecamatan
    $result = $conn->query("
        SELECT kecamatan, COUNT(*) as total 
        FROM kejadian_kebakaran 
        WHERE kecamatan IS NOT NULL 
        GROUP BY kecamatan 
        ORDER BY total DESC
    ");
    $stats['per_kecamatan'] = [];
    while($row = $result->fetch_assoc()) {
        $stats['per_kecamatan'][] = $row;
    }
    
    // Data bulanan (12 bulan terakhir)
    $result = $conn->query("
        SELECT DATE_FORMAT(waktu, '%Y-%m') as bulan, COUNT(*) as total 
        FROM kejadian_kebakaran 
        WHERE waktu >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(waktu, '%Y-%m')
        ORDER BY bulan
    ");
    $stats['bulanan'] = [];
    while($row = $result->fetch_assoc()) {
        $stats['bulanan'][] = $row;
    }
    
    $conn->close();
    return $stats;
}

/**
 * Format tanggal Indonesia
 */
function formatTanggal($date, $format = 'd F Y H:i') {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 
        4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September',
        10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = (int)date('m', $timestamp);
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);
    
    return $day . ' ' . $months[$month] . ' ' . $year . ' ' . $time;
}

/**
 * Generate slug dari string
 */
function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Get data untuk dropdown
 */
function getDropdownData($table, $valueField, $textField, $where = '') {
    $conn = getConnection();
    $sql = "SELECT $valueField, $textField FROM $table";
    if ($where) {
        $sql .= " WHERE $where";
    }
    $sql .= " ORDER BY $textField";
    
    $result = $conn->query($sql);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $conn->close();
    return $data;
}

/**
 * Debug function (hanya untuk development)
 */
function debug($data, $die = false) {
    echo '<pre style="background: #f4f4f4; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #ddd;">';
    print_r($data);
    echo '</pre>';
    if ($die) die();
}
?>