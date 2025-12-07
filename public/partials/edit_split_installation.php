<?php
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $item_name = $_POST['item_name'] ?? '';
    $unit_price = $_POST['unit_price'] ?? 0;
    $quantity = $_POST['quantity'] ?? 0;
    $category = $_POST['category'] ?? '';

    if ($id && $item_name && $unit_price && $quantity && $category) {
        $stmt = $pdo->prepare("
            UPDATE split_installation
            SET item_name = ?, unit_price = ?, quantity = ?, category = ?
            WHERE id = ?
        ");
        $stmt->execute([$item_name, $unit_price, $quantity, $category, $id]);
    }
}

header("Location: ../split_installation.php");
exit;
?>
