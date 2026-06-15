<?php
// kde-calculator.php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['points']) || empty($input['points'])) {
    echo json_encode([]);
    exit();
}

$points = $input['points'];
$radius = isset($input['radius']) ? (int)$input['radius'] : 25;
$blur = isset($input['blur']) ? (int)$input['blur'] : 15;

// Calculate bandwidth based on radius
$bandwidth = $radius / 1000; // Convert to kilometers

// Calculate KDE for each point
$kdePoints = [];
$maxIntensity = 0;

// First pass: calculate densities
foreach ($points as $point) {
    $density = 0;

    foreach ($points as $other) {
        $distance = calculateDistance(
            ['lat' => $point['lat'], 'lng' => $point['lng']],
            ['lat' => $other['lat'], 'lng' => $other['lng']]
        );

        // Gaussian kernel
        $kernel = exp(- ($distance * $distance) / (2 * $bandwidth * $bandwidth));
        $density += $kernel;
    }

    // Normalize by number of points
    $density = $density / count($points);

    $kdePoints[] = [
        'lat' => $point['lat'],
        'lng' => $point['lng'],
        'density' => $density
    ];

    if ($density > $maxIntensity) {
        $maxIntensity = $density;
    }
}

// Second pass: normalize intensities to 0-1 range
foreach ($kdePoints as &$point) {
    $point['intensity'] = $maxIntensity > 0 ? $point['density'] / $maxIntensity : 0;
}

echo json_encode($kdePoints);
