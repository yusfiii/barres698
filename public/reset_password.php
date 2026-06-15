<?php
// reset_password.php - Jalankan sekali untuk membuat password hash yang benar
require_once '../includes/config.php';

$conn = getConnection();

// Password yang akan digunakan
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

echo "Password hash: " . $hashed_password . "<br><br>";

// Update password untuk super admin
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'superadmin'");
$stmt->bind_param("s", $hashed_password);
if ($stmt->execute()) {
    echo "Password superadmin berhasil diupdate!<br>";
} else {
    echo "Error updating superadmin: " . $conn->error . "<br>";
}
$stmt->close();

// Update password untuk admin BPK
$users = ['admin_bpk1', 'admin_bpk2', 'admin_bpk3'];
foreach ($users as $user) {
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->bind_param("ss", $hashed_password, $user);
    if ($stmt->execute()) {
        echo "Password $user berhasil diupdate!<br>";
    } else {
        echo "Error updating $user: " . $conn->error . "<br>";
    }
    $stmt->close();
}

$conn->close();
echo "<br><b>Semua password telah direset menjadi: admin123</b>";
echo "<br><a href='public/login.php'>Klik untuk login</a>";
