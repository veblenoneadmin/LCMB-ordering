<?php
declare(strict_types=1);

$host = $_ENV['MYSQLHOST'] ?? null;
$port = $_ENV['MYSQLPORT'] ?? 3306;
$db   = $_ENV['MYSQLDATABASE'] ?? null;
$user = $_ENV['MYSQLUSER'] ?? null;
$pass = $_ENV['MYSQLPASSWORD'] ?? null;

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
