<?php
require_once __DIR__ . '/../../config.php';

$id = $_POST['id'] ?? 0;
$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? 0;

if($id && $name){
    $stmt = $pdo->prepare("UPDATE products SET name=?, description=?, price=? WHERE id=?");
    $stmt->execute([$name, $description, $price, $id]);
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'message'=>'Invalid data']);
}
