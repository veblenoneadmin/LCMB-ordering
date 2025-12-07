<?php
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $rate = $_POST['rate'] ?? 0;
    $category = $_POST['category'] ?? '';

    if ($id && $name && $email && $role && $rate && $category) {
        $stmt = $pdo->prepare("
            UPDATE personnel
            SET name = ?, email = ?, role = ?, rate = ?, category = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $role, $rate, $category, $id]);
    }
}

header("Location: ../personnel.php");
exit;
?>
