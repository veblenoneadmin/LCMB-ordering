<?php
declare(strict_types=1);

// -----------------------------
// Database Configuration
// -----------------------------
$host     = "mysql.railway.internal";   // IMPORTANT
$port     = 3306;                       // ALWAYS 3306 internally
$user     = "root";
$pass     = "YOUR_RAILWAY_PASSWORD";
$dbname   = "railway";
$charset  = "utf8mb4";

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";


$pdo = null;
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
