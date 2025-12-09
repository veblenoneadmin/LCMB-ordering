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

    // 3. If approved, send to N8N
    if ($new_status === 'approved') {
        // Fetch customer info
        $stmt_order = $pdo->prepare("SELECT customer_name, job_address, appointment_date, hours FROM orders WHERE id=?");
        $stmt_order->execute([$order_id]);
        $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Fetch assigned personnel for this order
            $stmt_personnel = $pdo->prepare("
                SELECT p.name, p.email
                FROM personnel p
                JOIN order_items oi ON oi.item_id = p.id AND oi.item_category = 'personnel'
                WHERE oi.order_id = ?
            ");
            $stmt_personnel->execute([$order_id]);
            $technicians = $stmt_personnel->fetchAll(PDO::FETCH_ASSOC);

            $webhookUrl = "https://n8n.example.com/webhook/your-webhook-path"; // <-- replace with your N8N webhook URL

            foreach ($technicians as $tech) {
                $data = [
                    'customer_name' => $order['customer_name'],
                    'job_address'   => $order['job_address'],
                    'date'          => $order['appointment_date'],
                    'hours'         => $order['hours'],
                    'technician_name' => $tech['name'],
                    'technician_email'=> $tech['email'],
                    'order_id'      => $order_id
                ];

                // Send POST to N8N webhook
                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                curl_close($ch);
            }
        }
    }

    // Redirect back to index.php with approved flag
    header("Location: ../index.php?approved=1");
    exit;
}

// Optional: redirect with error if missing data
header("Location: ../index.php?approved=0");
exit;
?>
