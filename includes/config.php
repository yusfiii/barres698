<?php
// config.php
session_start();

define('BASE_URL', 'http://localhost/barres698_db/');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'barres698_db');

// Koneksi database
function getConnection()
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Timezone
date_default_timezone_set('Asia/Makassar');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
