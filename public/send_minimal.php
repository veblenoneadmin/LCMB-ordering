<?php
require_once __DIR__ . '/../../config.php'; // adjust path if needed

if (!isset($_POST['order_id'])) {
    die("Missing order_id");
}

$order_id = intval($_POST['order_id']);

// Fetch minimal order info
$stmt = $pdo->prepare("SELECT customer_name FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

$customer_name = $order['customer_name'];

// Minimal job payload for testing
$data = [
    "title" => "Order #$order_id",
    "description" => "Customer: $customer_name",
];

$apiKey = "smk-c6666a-425f803efbbda9d8-c800c54f9fb4f1e8";

$url = "https://api.servicem8.com/api_job.json";
$json = json_encode($data);

// cURL request
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $apiKey . ":x",
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Accept: application/json"
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $json
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Display output
echo "<h2>ServiceM8 Response</h2>";
echo "HTTP Code: $httpCode<br><br>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";


