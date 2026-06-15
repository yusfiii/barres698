<?php
// includes/session.php
require_once __DIR__ . '/config.php';

function checkAuth()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'public/login.php');
        exit();
    }
}

function checkRole($allowed_roles)
{
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        header('Location: ' . BASE_URL . 'public/index.php');
        exit();
    }
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function getCurrentUser()
{
    if (!isLoggedIn()) return null;

    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    return $user;
}
