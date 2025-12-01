<?php
// Adjust path to your config.php
require_once '/var/www/html/config.php'; // use absolute path for safety

if (!isset($_POST['order_id'])) {
    die("Missing order_id");
}

$order_id = intval($_POST['order_id']);

// Fetch customer name from DB
$stmt = $pdo->prepare("SELECT customer_name FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

$customer_name = $order['customer_name'];

// Minimal payload
$data = [
    "title" => "Order #$order_id",
    "description" => "Customer: $customer_name",
];

$jsonData = json_encode($data);

// ServiceM8 API endpoint
$url = "https://api.servicem8.com/api_1.0/job.json";

// Your new API Key
$apiKey = "smk-c6666a-425f803efbbda9d8-c800c54f9fb4f1e8";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Accept: application/json",
        "X-API-Key: $apiKey"
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonData
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Display the result
echo "<h2>ServiceM8 Response</h2>";
echo "HTTP Code: $httpCode<br><br>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";
