<?php
require_once __DIR__ . '/../../config.php';

$id       = $_POST['id'] ?? 0;
$name     = $_POST['name'] ?? '';
$email    = $_POST['email'] ?? '';
$role     = $_POST['role'] ?? '';
$rate     = $_POST['rate'] ?? 0;
$category = $_POST['category'] ?? '';

if ($id && $name && $email && $role) {
    $stmt = $pdo->prepare("
        UPDATE personnel 
        SET name = ?, email = ?, role = ?, rate = ?, category = ? 
        WHERE id = ?
    ");
    $stmt->execute([$name, $email, $role, $rate, $category, $id]);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
}
