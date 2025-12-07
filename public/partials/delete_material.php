<?php
require_once __DIR__ . '/../../config.php';

$id = $_POST['id'] ?? 0;

if($id){
    $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'message'=>'Invalid ID']);
}
