<?php
// DO NOT call session_start() here
$host = 'localhost';        // No port needed since it's the default
$db   = 'catalog_library'; 
$user = 'root';            
$pass = 'root';            
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>