<?php
require_once __DIR__ . '/../config.php';

$order_id = $_POST['order_id'] ?? 0;

// ========== FETCH ORDER ==========
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Order not found.");
}

// ========== FETCH ORDER ITEMS ==========
$stmtItem = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItem->execute([$order_id]);
$items = $stmtItem->fetchAll();

if (!$items) {
    die("No items found for this order.");
}

// ========== CALCULATE TOTAL ==========
$total = 0;
foreach ($items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// ========== SERVICEM8 CREDENTIALS ==========
$servicem8_email   = getenv('SERVICEM8_EMAIL');
$servicem8_api_key = getenv('SERVICEM8_API_KEY');

// Debug: make sure variables are loaded (optional, remove after testing)
if (!$servicem8_email || !$servicem8_api_key) {
    die("ServiceM8 credentials not set. Check Railway variables for PHP service.\nEmail: $servicem8_email\nAPI Key: $servicem8_api_key");
}

// ========== BUILD JOB DESCRIPTION ==========
$description  = "Order ID: $order_id\n";
$description .= "Customer: {$order['customer_name']}\n";
$description .= "Order Date: {$order['order_date']}\n";
$description .= "Total: $" . number_format($total, 2) . "\n\n";
$description .= "Items:\n";

foreach ($items as $i) {
    $description .= "- {$i['item_name']} ({$i['quantity']} × {$i['price']})\n";
}

// ========== PAYLOAD FOR SERVICEM8 ==========
$payload = [[
    'summary'     => "Order #$order_id - {$order['customer_name']}",
    'description' => $description,
    'contact'     => $order['customer_name']
]];

// ========== SEND TO SERVICEM8 ==========
$ch = curl_init("https://api.servicem8.com/api/1.0/job.json");
curl_setopt($ch, CURLOPT_USERPWD, "$servicem8_email:$servicem8_api_key"); // Basic Auth
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    die("cURL error: " . curl_error($ch));
}

curl_close($ch);

// ========== ERROR HANDLING ==========
if ($http_code < 200 || $http_code >= 300) {
    die("Failed to send order to ServiceM8. HTTP Code: $http_code\nResponse: $response");
}

// Optional: log response for debugging
// file_put_contents(__DIR__ . '/servicem8_response.log', $response);

// ========== UPDATE STATUS IN DATABASE ==========
$stmt = $pdo->prepare("UPDATE orders SET status = 'sent' WHERE id = ?");
$stmt->execute([$order_id]);

// ========== DONE — REDIRECT ==========
header("Location: orders.php?sent=1");
exit();
