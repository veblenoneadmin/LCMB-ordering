<?php
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = $_POST['item_name'] ?? '';
    $unit_price = $_POST['unit_price'] ?? 0;
    $quantity = $_POST['quantity'] ?? 0;
    $category = $_POST['category'] ?? '';

    if ($item_name && $unit_price && $quantity && $category) {
        $stmt = $pdo->prepare("
            INSERT INTO split_installation (item_name, unit_price, quantity, category)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$item_name, $unit_price, $quantity, $category]);
    }
}

header("Location: ../split_installation.php");
exit;
?>
