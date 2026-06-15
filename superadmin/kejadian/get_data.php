<?php
require_once '../../includes/config.php';
require_once '../../includes/session.php';
checkAuth();
checkRole(['super_admin']);

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM kejadian_kebakaran WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
    }
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
}
