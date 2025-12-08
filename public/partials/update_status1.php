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

    // Redirect back to index.php with a flag
    header("Location: ../index.php?approved=1");
    exit;
}

// Optional: redirect with error if missing data
header("Location: ../index.php?approved=0");
exit;
?>
