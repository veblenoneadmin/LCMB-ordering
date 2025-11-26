<?php
require_once __DIR__ . '/../config.php';

$order_id = $_POST['order_id'] ?? 0;

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

// Fetch items
$stmtItem = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItem->execute([$order_id]);
$items = $stmtItem->fetchAll();

// Send to ServiceM8
$api_key = getenv('SERVICEM8_API_KEY');

$payload = [
    'CustomerName' => $order['customer_name'],
    'Notes' => 'Order ID: ' . $order_id,
    'LineItems' => array_map(fn($i) => [
        'Description' => $i['item_name'],
        'Quantity' => $i['quantity'],
        'UnitPrice' => $i['price']
    ], $items)
];

$ch = curl_init("https://api.servicem8.com/api_1.0/job.json");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $api_key",
    "Content-Type: application/json",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
$response = curl_exec($ch);
curl_close($ch);

// Update order status
$stmt = $pdo->prepare("UPDATE orders SET status = 'sent' WHERE id = ?");
$stmt->execute([$order_id]);

header("Location: orders.php");
exit();
