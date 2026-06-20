<?php
// heatmap/test_kde.php
require_once __DIR__ . '/kde_calculator.php';

header('Content-Type: application/json');

$conn = getConnection();

// Test bounds
$bounds = [
    'minLat' => -3.50,
    'maxLat' => -3.42,
    'minLng' => 114.70,
    'maxLng' => 114.90
];

$params = [
    'minLat' => $bounds['minLat'],
    'maxLat' => $bounds['maxLat'],
    'minLng' => $bounds['minLng'],
    'maxLng' => $bounds['maxLng'],
    'gridSize' => 20
];

$result = getKDEHeatmapData($conn, $params);
$conn->close();

// Tampilkan hasil dengan format yang rapi
echo "<h1>Test KDE Calculator</h1>";
echo "<pre>";
print_r($result);
echo "</pre>";

// Tampilkan grid data sebagai tabel
if ($result['status'] === 'success') {
    echo "<h2>Grid Data Preview (5x5)</h2>";
    echo "<table border='1' cellpadding='5'>";
    $grid = $result['grid_data'];
    $size = min(5, count($grid));
    for ($i = 0; $i < $size; $i++) {
        echo "<tr>";
        for ($j = 0; $j < $size; $j++) {
            $val = isset($grid[$i][$j]) ? number_format($grid[$i][$j], 4) : '0';
            $color = $grid[$i][$j] > 0.7 ? '#ff0000' : ($grid[$i][$j] > 0.4 ? '#ffcc00' : '#00cc44');
            echo "<td style='background: $color; color: white; text-align: center; padding: 10px;'>$val</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}
?>