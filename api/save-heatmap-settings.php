<?php
// api/save-heatmap-settings.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

// Check if user is logged in and is super_admin
if (!isLoggedIn() || $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$conn = getConnection();

// Update or insert heatmap settings
$radius = isset($data['radius']) ? intval($data['radius']) : 25;
$blur = isset($data['blur']) ? intval($data['blur']) : 15;
$intensity = isset($data['intensity']) ? floatval($data['intensity']) : 0.01;
$kernel = isset($data['kernel']) ? $data['kernel'] : 'gaussian';
$gridSize = isset($data['gridSize']) ? intval($data['gridSize']) : 100;
$weightBySeverity = isset($data['weightBySeverity']) ? 1 : 0;
$adaptiveBandwidth = isset($data['adaptiveBandwidth']) ? 1 : 0;

$query = "INSERT INTO heatmap_settings (radius, blur, intensity, kernel, grid_size, weight_by_severity, adaptive_bandwidth, updated_at) 
          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
          ON DUPLICATE KEY UPDATE 
          radius = VALUES(radius), 
          blur = VALUES(blur), 
          intensity = VALUES(intensity),
          kernel = VALUES(kernel),
          grid_size = VALUES(grid_size),
          weight_by_severity = VALUES(weight_by_severity),
          adaptive_bandwidth = VALUES(adaptive_bandwidth),
          updated_at = NOW()";

$stmt = $conn->prepare($query);
$stmt->bind_param('iidsiii', $radius, $blur, $intensity, $kernel, $gridSize, $weightBySeverity, $adaptiveBandwidth);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>