<?php
require_once __DIR__ . '/config.php';

// Get order ID
$order_id = intval($_POST['order_id'] ?? 0);

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Fetch order info and personnel emails
$stmt = $pdo->prepare("
    SELECT o.appointment_date, p.name, p.email
    FROM orders o
    JOIN order_items oi ON oi.order_id=o.id AND oi.item_category='personnel'
    JOIN personnel p ON p.id=oi.item_id
    WHERE o.id=?
");
$stmt->execute([$order_id]);
$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$personnel) {
    echo json_encode(['success' => false, 'message' => 'No personnel found for this order']);
    exit;
}

// Loop through personnel and trigger calendar/N8N
foreach ($personnel as $tech) {
    $email = $tech['email'];
    $date  = $tech['appointment_date'];

    // Example: send webhook to N8N
    $webhook_url = "https://your-n8n-webhook-url";
    $payload = [
        'order_id' => $order_id,
        'technician_name' => $tech['name'],
        'technician_email' => $email,
        'appointment_date' => $date
    ];

    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
}

// Return success
echo json_encode(['success' => true, 'message' => 'Calendar/webhook triggered successfully']);
