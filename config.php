<?php
declare(strict_types=1);

$host = $_ENV['MYSQLHOST'] ?? 'trolley.proxy.rlwy.net';
$port = $_ENV['MYSQLPORT'] ?? 33634;
$db   = $_ENV['MYSQLDATABASE'] ?? 'railway';
$user = $_ENV['MYSQLUSER'] ?? 'root';
$pass = $_ENV['MYSQLPASSWORD'] ?? 'wHstRwEcyamhrIAsZjvXbZaGooFqiIxR';


if (!$host || !$db || !$user || !$pass) {
    die('Database environment variables are missing');
}

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
