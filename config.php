<?php
declare(strict_types=1);

$host = $_ENV['MYSQLHOST'] ?? 'trolley.proxy.rlwy.net';
$port = $_ENV['MYSQLPORT'] ?? 33634;
$db   = $_ENV['MYSQLDATABASE'] ?? 'railway';
$user = $_ENV['MYSQLUSER'] ?? 'root';
$pass = $_ENV['MYSQLPASSWORD'] ?? 'wHstRwEcyamhrIAsZjvXbZaGooFqiIxR';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Connected to Railway MySQL successfully!";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}