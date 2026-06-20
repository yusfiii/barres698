<?php
// heatmap/kde_heatmap.php
require_once __DIR__ . '/kde_calculator.php';

// Set header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ambil parameter
$params = $_GET;

// Validasi parameter
if (isset($params['minLat']) && isset($params['maxLat']) && 
    isset($params['minLng']) && isset($params['maxLng'])) {
    // Validasi range
    if ($params['minLat'] >= $params['maxLat'] || $params['minLng'] >= $params['maxLng']) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid bounds: min must be less than max'
        ]);
        http_response_code(400);
        exit;
    }
}

// Koneksi database
$conn = getConnection();

// Proses KDE
$result = getKDEHeatmapData($conn, $params);
$conn->close();

// Kirim response
echo json_encode($result);
?>