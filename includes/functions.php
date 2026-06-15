<?php
// functions.php
require_once 'config.php';

function sanitize($data) {
    $conn = getConnection();
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = $conn->real_escape_string($data);
    $conn->close();
    return $data;
}

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

function uploadFile($file, $target_dir = "assets/img/uploads/") {
    $target_file = $target_dir . basename($file["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return ['success' => false, 'message' => 'File bukan gambar'];
    }
    
    // Check file size (max 5MB)
    if ($file["size"] > 5000000) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar'];
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
        return ['success' => false, 'message' => 'Hanya file JPG, JPEG, PNG yang diperbolehkan'];
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => true, 'filename' => $new_filename];
    } else {
        return ['success' => false, 'message' => 'Gagal mengupload file'];
    }
}

function getStatistics() {
    $conn = getConnection();
    
    $stats = [];
    
    // Total kejadian
    $result = $conn->query("SELECT COUNT(*) as total FROM kejadian_kebakaran");
    $stats['total_kejadian'] = $result->fetch_assoc()['total'];
    
    // Total korban
    $result = $conn->query("SELECT SUM(korban_luka) as luka, SUM(korban_jiwa) as jiwa FROM kejadian_kebakaran");
    $korban = $result->fetch_assoc();
    $stats['total_luka'] = $korban['luka'] ?? 0;
    $stats['total_jiwa'] = $korban['jiwa'] ?? 0;
    
    // Statistik per kecamatan
    $result = $conn->query("SELECT kecamatan, COUNT(*) as total FROM kejadian_kebakaran GROUP BY kecamatan");
    $stats['per_kecamatan'] = [];
    while($row = $result->fetch_assoc()) {
        $stats['per_kecamatan'][] = $row;
    }
    
    // Data bulanan
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

function getHeatmapSettings() {
    $conn = getConnection();
    $result = $conn->query("SELECT * FROM heatmap_settings ORDER BY id DESC LIMIT 1");
    $settings = $result->fetch_assoc();
    $conn->close();
    return $settings;
}

// KDE Calculation Function
function calculateKDE($points, $bandwidth = null) {
    if (empty($points)) return [];
    
    // Default bandwidth using Silverman's rule of thumb
    if (!$bandwidth) {
        $n = count($points);
        $stdDev = calculateStandardDeviation($points);
        $bandwidth = 1.06 * $stdDev * pow($n, -0.2);
    }
    
    $kdeValues = [];
    
    foreach ($points as $point) {
        $density = 0;
        foreach ($points as $other) {
            $distance = calculateDistance($point, $other);
            $density += kernelFunction($distance / $bandwidth);
        }
        $kdeValues[] = [
            'lat' => $point['lat'],
            'lng' => $point['lng'],
            'density' => $density / (count($points) * $bandwidth)
        ];
    }
    
    return $kdeValues;
}

function kernelFunction($x) {
    // Gaussian kernel
    return (1 / sqrt(2 * M_PI)) * exp(-0.5 * $x * $x);
}

function calculateDistance($point1, $point2) {
    // Haversine formula for distance in kilometers
    $lat1 = deg2rad($point1['lat']);
    $lat2 = deg2rad($point2['lat']);
    $lng1 = deg2rad($point1['lng']);
    $lng2 = deg2rad($point2['lng']);
    
    $dlat = $lat2 - $lat1;
    $dlng = $lng2 - $lng1;
    
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlng/2) * sin($dlng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    $R = 6371; // Earth radius in kilometers
    return $R * $c;
}

function calculateStandardDeviation($points) {
    $n = count($points);
    if ($n < 2) return 0;
    
    // Calculate center
    $center = ['lat' => 0, 'lng' => 0];
    foreach ($points as $point) {
        $center['lat'] += $point['lat'];
        $center['lng'] += $point['lng'];
    }
    $center['lat'] /= $n;
    $center['lng'] /= $n;
    
    // Calculate variance
    $variance = 0;
    foreach ($points as $point) {
        $dist = calculateDistance($point, $center);
        $variance += $dist * $dist;
    }
    
    return sqrt($variance / $n);
}
?>