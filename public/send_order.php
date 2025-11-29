<?php
require_once __DIR__ . '/../config.php';

// 1. Get order_id from URL
$order_id = $_GET['order_id'] ?? 0;

// 2. Fetch order from DB
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Order not found.");
}

// 3. Extract fields
$data = [
    "name"             => $order['name'],
    "phone"            => $order['phone'],
    "date"             => $order['date'],
    "total"            => $order['total'],
    "technician_uuid"  => $order['technician_uuid']
];

// 4. Your REAL n8n webhook URL (replace this!)
$webhook_url = "https://n8n.yourdomain.com/webhook/8dc36143-3e26-4e47-a0f7-ab0cb8b2143d";

// 5. Send POST request to n8n
$options = [
    "http" => [
        "header"  => "Content-type: application/json\r\n",
        "method"  => "POST",
        "content" => json_encode($data)
    ]
];

$context  = stream_context_create($options);
$response = file_get_contents($webhook_url, false, $context);

echo $response;
