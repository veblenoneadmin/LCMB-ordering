<?php
require_once __DIR__ . '/../../config.php';

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$role = $_POST['role'] ?? '';
$rate = $_POST['rate'] ?? 0;
$category = $_POST['category'] ?? '';

if ($name && $email && $role && $rate && $category) {
    $stmt = $pdo->prepare("INSERT INTO personnel (name, email, role, rate, category, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$name, $email, $role, $rate, $category]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
}
