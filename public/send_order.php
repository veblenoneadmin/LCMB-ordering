<?php
require_once __DIR__ . '/../config.php';

$order_id = $_POST['order_id'] ?? 0;

// ========== FETCH ORDER ==========
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order) die("Order not found.");

// ========== FETCH ITEMS ==========
$stmtItem = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItem->execute([$order_id]);
$items = $stmtItem->fetchAll();
if (!$items) die("No items found.");

// ========== CALCULATE TOTAL ==========
$total = 0;
foreach ($items as $item) $total += $item['price'] * $item['quantity'];

// ========== GET SERVICE M8 API KEY ==========
$servicem8_api_key = getenv('SERVICEM8_API_KEY');
if (!$servicem8_api_key) die("ServiceM8 API key not set.");

// ========== BUILD JOB DESCRIPTION ==========
$description  = "Order ID: $order_id\n";
$description .= "Customer: {$order['customer_name']}\n";
$description .= "Order Date: {$order['order_date']}\n";
$description .= "Total: $" . number_format($total, 2) . "\n\n";
$description .= "Items:\n";
foreach ($items as $i) {
    $description .= "- {$i['item_name']} ({$i['quantity']} × {$i['price']})\n";
}

// ========== PREPARE PAYLOAD ==========
$payload = [
    'summary'     => "Order #$order_id - {$order['customer_name']}",
    'description' => $description,
    'contact'     => $order['customer_name']
];

// ========== SEND TO SERVICEM8 ==========
$ch = curl_init("https://api.servicem8.com/api_1.0/job.json");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Accept: application/json",
    "X-API-Key: $servicem8_api_key" // <-- Use X-API-Key now
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code < 200 || $http_code >= 300) {
    die("Failed to send order. HTTP Code: $http_code Response: $response");
}

// ========== UPDATE DATABASE STATUS ==========
$stmt = $pdo->prepare("UPDATE orders SET status = 'sent' WHERE id = ?");
$stmt->execute([$order_id]);

// ========== REDIRECT ==========
header("Location: orders.php?sent=1");
exit();
