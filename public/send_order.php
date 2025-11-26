<?php
require_once __DIR__ . '/../config.php';

$order_id = $_POST['order_id'] ?? 0;

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Order not found.");
}

// Fetch items
$stmtItem = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItem->execute([$order_id]);
$items = $stmtItem->fetchAll();

// Calculate total
$total = 0;
foreach ($items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// === ServiceM8 credentials ===
// These should be added in Railway → Variables
$servicem8_email   = getenv('admin@veblengroup.com.au');
$servicem8_api_key = getenv('smk-db7e04-a25eb7fb42817c4e-4a86ac05140b3758');

if (!$servicem8_email || !$servicem8_api_key) {
    die("ServiceM8 credentials not set. Add SERVICEM8_EMAIL and SERVICEM8_API_KEY.");
}

// Build job description
$description = "Order ID: $order_id\n";
$description .= "Customer: {$order['customer_name']}\n";
$description .= "Order Date: {$order['order_date']}\n";
$description .= "Total: $" . number_format($total, 2) . "\n\n";
$description .= "Items:\n";

foreach ($items as $i) {
    $description .= "- {$i['item_name']} ({$i['quantity']} × {$i['price']})\n";
}

// Prepare Job object (must be inside an array for ServiceM8)
$payload = [[
    'summary'     => "Order #$order_id - {$order['customer_name']}",
    'description' => $description,
    'contact'     => $order['customer_name']
]];

// Send to ServiceM8
$ch = curl_init("https://api.servicem8.com/api/1.0/job.json");

curl_setopt($ch, CURLOPT_USERPWD, "$servicem8_email:$servicem8_api_key"); // BASIC AUTH
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Debug output (optional)
// file_put_contents("servicem8_response.txt", $response);

// Error handling
if ($http_code < 200 || $http_code >= 300) {
    die("Failed to send order to ServiceM8. Response: " . $response);
}

// Update order status
$stmt = $pdo->prepare("UPDATE orders SET status = 'sent' WHERE id = ?");
$stmt->execute([$order_id]);

// Redirect back to orders page
header("Location: orders.php");
exit();
