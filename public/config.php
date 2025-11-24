<?php
declare(strict_types=1);

// -----------------------------
// Database Configuration
// -----------------------------
$host     = "shuttle.proxy.rlwy.net";
$port     = 25965;
$user     = "root";
$pass     = "THgMALdtucPApKGCBKzkeMQjyvoNwsLK";
$dbname   = "railway";
$charset  = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

$pdo = null;
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
