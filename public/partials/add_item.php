<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $order_id   = $_POST['order_id'] ?? 0;
    $item_name  = $_POST['item_name'] ?? '';
    $quantity   = $_POST['quantity'] ?? 1;
    $unit_price = $_POST['unit_price'] ?? 0;

    if (!$order_id || empty($item_name)) {
        die("Invalid data.");
    }

    // Insert item
    $stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, item_name, quantity, unit_price)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$order_id, $item_name, $quantity, $unit_price]);

    // Update the order total
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET total_amount = (
            SELECT SUM(quantity * unit_price) FROM order_items WHERE order_id = ?
        )
        WHERE id = ?
    ");
    $stmt->execute([$order_id, $order_id]);

    // Redirect back to review_order.php
    header("Location: review_order.php?order_id=" . $order_id);
    exit;
}

echo "Invalid request.";
