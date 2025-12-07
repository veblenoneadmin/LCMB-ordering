<?php
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $rate = $_POST['rate'] ?? 0;
    $category = $_POST['category'] ?? '';

    if ($name && $email && $role && $rate && $category) {
        $stmt = $pdo->prepare("
            INSERT INTO personnel (name, email, role, rate, category)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $email, $role, $rate, $category]);
    }
}

header("Location: ../personnel.php");
exit;
?>
