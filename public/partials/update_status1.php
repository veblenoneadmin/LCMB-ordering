<?php
require_once __DIR__ . '/../../config.php';

$order_id = intval($_POST['order_id'] ?? 0);

// Accept both "status" and "action"
$new_status = $_POST['status'] 
    ?? ($_POST['action'] === 'approve' ? 'approved' : '');

if ($order_id && $new_status) {

    // 1. Update orders table
    $stmt = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
    $stmt->execute([$new_status, $order_id]);

    // 2. Update dispatch table
    $stmt_dispatch = $pdo->prepare("UPDATE dispatch SET status=? WHERE order_id=?");
    $stmt_dispatch->execute([$new_status, $order_id]);

    // 3. If approved → send data to N8N
    if ($new_status === 'approved') {

        // Fetch all required data with proper joins
        $stmt = $pdo->prepare("
            SELECT 
                o.customer_name,
                o.job_address,
                o.appointment_date AS date,
                
                p.name AS technician_name,
                p.email AS technician_email,
                
                d.hours
            FROM orders o
            JOIN order_items oi 
                ON oi.order_id = o.id 
                AND oi.item_category = 'personnel'
            JOIN personnel p 
                ON p.id = oi.item_id
            JOIN dispatch d
                ON d.order_id = o.id 
                AND d.personnel_id = p.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Your actual N8N webhook URL
        $webhookUrl = "https://primary-s0q-production.up.railway.app/webhook/4b01aa5b-c817-47a4-889a-30792ac9a92f";

        foreach ($rows as $row) {

            // Add order_id into payload
            $row['order_id'] = $order_id;

            // Send to n8n webhook
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($row));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            curl_close($ch);
        }
    }

    // Redirect back to index.php with flag
    header("Location: ../index.php?approved=1");
    exit;
}

// If failed
header("Location: ../index.php?approved=0");
exit;
?>
