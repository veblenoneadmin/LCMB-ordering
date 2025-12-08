<?php
require_once __DIR__ . '/../../config.php';

$order_id = intval($_POST['order_id'] ?? 0);

// Accept both "status" and "action"
$new_status = $_POST['status'] 
    ?? ($_POST['action'] === 'approve' ? 'approved' : '');

if ($order_id && $new_status) {

    // Update orders table
    $stmt = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
    $stmt->execute([$new_status, $order_id]);

    // Update dispatch table
    $stmt_dispatch = $pdo->prepare("UPDATE dispatch SET status=? WHERE order_id=?");
    $stmt_dispatch->execute([$new_status, $order_id]);

    echo json_encode([
        'success' => true,
        'updated_dispatch_rows' => $stmt_dispatch->rowCount()
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Missing order_id or status']);
?>
