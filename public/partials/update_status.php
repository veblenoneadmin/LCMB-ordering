<?php
require_once __DIR__ . '/../../config.php';

// Get order_id and new status (button sends "action")
$order_id = intval($_POST['order_id'] ?? 0);
$new_status = $_POST['action'] ?? '';   // <-- FIXED

if ($order_id && $new_status) {

    // 1. Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
    $stmt->execute([$new_status, $order_id]);

    // 2. Update dispatch status for this order
    $stmt_dispatch = $pdo->prepare("UPDATE dispatch SET status=? WHERE order_id=?");
    $stmt_dispatch->execute([$new_status, $order_id]);

    // 3. If approved, process N8N / Calendar triggers
    if ($new_status === 'approved') {

        $stmt = $pdo->prepare("
            SELECT p.name, p.email, o.appointment_date
            FROM personnel p
            JOIN order_items oi 
                ON oi.item_id = p.id 
               AND oi.item_category = 'personnel'
            JOIN orders o 
                ON o.id = oi.order_id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($personnel as $tech) {
            // sendWebhook($tech['email'], $tech['appointment_date']);
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
