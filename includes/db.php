<?php
// DO NOT call session_start() here
$host = 'localhost';
$db   = 'catalog_library'; // change this to your actual DB name
$user = 'root';            // default for MAMP
$pass = 'root';            // default password for MAMP
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
