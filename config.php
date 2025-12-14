<?php
declare(strict_types=1);

// -----------------------------
// Local defaults for XAMPP / testing
// -----------------------------
// These will be overridden by Railway environment variables if available
$defaultHost = 'trolley.proxy.rlwy.net';   // public Railway proxy host for local testing
$defaultPort = 33634;                      // public port
$defaultDB   = 'railway';
$defaultUser = 'root';
$defaultPass = 'wHstRwEcyamhrIAsZjvXbZaGooFqiIxR';

// -----------------------------
// Use environment variables if set (Railway will provide these automatically)
// -----------------------------
$host = $_ENV['MYSQLHOST'] ?? $defaultHost;
$port = $_ENV['MYSQLPORT'] ?? $defaultPort;
$db   = $_ENV['MYSQLDATABASE'] ?? $defaultDB;
$user = $_ENV['MYSQLUSER'] ?? $defaultUser;
$pass = $_ENV['MYSQLPASSWORD'] ?? $defaultPass;

// -----------------------------
// Validate credentials
// -----------------------------
if (!$host || !$db || !$user || !$pass) {
    die('Database credentials are missing');
}

// -----------------------------
// Create PDO connection
// -----------------------------
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
    // Optional: Uncomment for testing
    // echo "Connected to MySQL successfully!";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
