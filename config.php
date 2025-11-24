<?php
declare(strict_types=1);

// -----------------------------
// Railway Internal DB Config
// -----------------------------
$host     = "mysql.railway.internal";   // ends in .railway.internal
$port     = 3306;
$user     = "root";
$pass     = "YOUR_INTERNAL_PASSWORD";
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
