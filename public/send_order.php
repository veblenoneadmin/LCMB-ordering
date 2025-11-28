<?php
require_once __DIR__ . '/../config.php';

// ===================== GET ORDER ID =====================
$order_id = $_POST['order_id'] ?? 0;
if (!$order_id) die("Order ID not provided.");

// ===================== FETCH ORDER =====================
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order) die("Order not found.");

// ===================== FETCH ORDER ITEMS =====================
$stmtItem = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItem->execute([$order_id]);
$items = $stmtItem->fetchAll();

// ===================== CALCULATE TOTAL =====================
$total = 0;
foreach ($items as $item) {
    $qty = $item['quantity'] ?? 1;
    $price = $item['price'] ?? 0;
    $total += $price * $qty;
}

// ===================== GET SERVICE M8 API KEY =====================
$servicem8_api_key = getenv('SERVICEM8_API_KEY');
if (!$servicem8_api_key) die("ServiceM8 API key not set.");

// ===================== GET STAFF UUID =====================
$technician_id = $order['technician_id'] ?? $order['technician'] ?? null;
$staff_uuid = null;
if ($technician_id) {
    $stmt = $pdo->prepare("SELECT servicem8_uuid FROM personnel WHERE id = ?");
    $stmt->execute([$technician_id]);
    $staff_uuid = $stmt->fetchColumn();
}

// ===================== BUILD JOB DESCRIPTION =====================
$order_date = $order['order_date'] ?? date('Y-m-d');
$contact_number = $order['contact_number'] ?? 'N/A';
$customer_name = $order['customer_name'] ?? 'N/A';

// Put all optional details inside description (required by ServiceM8)
$description = "Customer Name: $customer_name; Contact Number: $contact_number; Date: $order_date; Total: $" . number_format($total, 2);

// ===================== PREPARE PAYLOAD =====================
$payload = [
    'summary' => "Order #$order_id - $customer_name",
    'description' => $description
];

// Assign staff if available
if ($staff_uuid) {
    $payload['staff'] = [$staff_uuid];
}

// ===================== SEND TO SERVICEM8 =====================
$ch = curl_init("https://api.servicem8.com/api_1.0/job.json");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Accept: application/json",
    "X-API-Key: $servicem8_api_key"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$record_uuid = curl_getinfo($ch, CURLINFO_HEADER_OUT); // Optional: header info
curl_close($ch);

// ===================== CHECK RESPONSE =====================
if ($http_code < 200 || $http_code >= 300) {
    echo "Failed to send order. HTTP Code: $http_code\n";
    echo "Response: $response\n";
    exit;
}

// ===================== PARSE RETURNED UUID =====================
// ServiceM8 returns x-record-uuid in headers; PHP cURL can capture it if needed
// You can store this in your database for future reference
// Example (simplest): just decode JSON response if returned
$response_data = json_decode($response, true);
$created_job_uuid = $response_data['uuid'] ?? '';

// ===================== UPDATE ORDER STATUS =====================
$stmt = $pdo->prepare("UPDATE orders SET status = 'sent', servicem8_job_uuid = ? WHERE id = ?");
$stmt->execute([$created_job_uuid, $order_id]);

// ===================== REDIRECT =====================
header("Location: orders.php?sent=1");
exit();
