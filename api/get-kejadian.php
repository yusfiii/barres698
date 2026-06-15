<?php
// api/get-kejadian.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$conn = getConnection();

$kecamatan = isset($_GET['kecamatan']) && $_GET['kecamatan'] !== '' ? $_GET['kecamatan'] : null;
$periode = isset($_GET['periode']) ? $_GET['periode'] : 'all';

$query = "SELECT * FROM kejadian_kebakaran WHERE 1=1";
$params = [];

if ($kecamatan) {
    $query .= " AND kecamatan = ?";
    $params[] = $kecamatan;
}

if ($periode !== 'all') {
    switch ($periode) {
        case 'month':
            $query .= " AND waktu >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case '3months':
            $query .= " AND waktu >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
        case 'year':
            $query .= " AND waktu >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
}

$query .= " ORDER BY waktu DESC";

$stmt = $conn->prepare($query);
if (count($params) > 0) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$incidents = [];
while ($row = $result->fetch_assoc()) {
    $incidents[] = [
        'id' => $row['id'],
        'waktu' => $row['waktu'],
        'alamat' => $row['alamat'],
        'kecamatan' => $row['kecamatan'],
        'kelurahan' => $row['kelurahan'],
        'latitude' => floatval($row['latitude']),
        'longitude' => floatval($row['longitude']),
        'korban_luka' => intval($row['korban_luka']),
        'korban_jiwa' => intval($row['korban_jiwa']),
        'jumlah_bangunan' => intval($row['jumlah_bangunan']),
        'jumlah_KK' => intval($row['jumlah_KK'])
    ];
}

$conn->close();
echo json_encode($incidents);
