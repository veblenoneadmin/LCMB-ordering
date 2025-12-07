<?php
require_once __DIR__ . '/../../config.php';

$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? 0;

// Generate SKU like P0001
$stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
$sku = 'P' . str_pad($count, 4, '0', STR_PAD_LEFT);

if($name){
    $stmt = $pdo->prepare("INSERT INTO products (sku, name, description, price, category, created_at) VALUES (?, ?, ?, ?, 'Product', NOW())");
    $stmt->execute([$sku, $name, $description, $price]);
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'message'=>'Name is required']);
}
