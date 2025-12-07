<?php
require_once __DIR__ . '/../../config.php';

// Force JSON output
header('Content-Type: application/json');

try {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $rate = $_POST['rate'] ?? 0;
    $category = $_POST['category'] ?? '';

    if (!$name || !$email || !$role || !$rate || !$category) {
        throw new Exception("All fields are required");
    }

    if ($id) {
        // Update
        $stmt = $pdo->prepare("UPDATE personnel SET name=?, email=?, role=?, rate=?, category=? WHERE id=?");
        $stmt->execute([$name, $email, $role, $rate, $category, $id]);
        echo json_encode(['success'=>true]);
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO personnel (name,email,role,rate,category,created_at) VALUES (?,?,?,?,?,NOW())");
        $stmt->execute([$name, $email, $role, $rate, $category]);
        echo json_encode(['success'=>true]);
    }

} catch(Exception $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
