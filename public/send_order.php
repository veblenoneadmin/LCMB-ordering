<?php
require_once __DIR__ . '/../config.php';

$order_id = $_POST['order_id'] ?? 0;

// ===================== FETCH ORDER =====================
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order) die("Order not found.");

// ===================== CALCULATE TOTAL =====================
$stmtItem = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItem->execute([$order_id]);
$items = $stmtItem->fetchAll();
$total = 0;
foreach ($items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// ===================== GET SERVICE M8 API KEY =====================
$servicem8_api_key = getenv('SERVICEM8_API_KEY');
if (!$servicem8_api_key) die("ServiceM8 API key not set.");

// ===================== GET STAFF UUID FROM PERSONNEL TABLE =====================
$technician_id = $order['technician_id']; // field in orders referencing personnel.id
$stmt = $pdo->prepare("SELECT servicem8_uuid FROM personnel WHERE id = ?");
$stmt->execute([$technician_id]);
$staff_uuid = $stmt->fetchColumn();

// ===================== BUILD JOB DESCRIPTION =====================
$description  = "Customer Name: {$order['customer_name']}\n";
$description .= "Contact Number: {$order['contact_number']}\n";
$description .= "Date: {$order['order_date']}\n";
$description .= "Total: $" . number_format($total, 2) . "\n";

// ===================== PREPARE PAYLOAD =====================
$payload = [
    'summary'     => "Order #$order_id - {$order['customer_name']}",
    'description' => $description
];

// Assign staff if UUID exists
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
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ===================== DEBUG =====================
if ($http_code < 200 || $http_code >= 300) {
    echo "Failed to send order. HTTP Code: $http_code\n";
    echo "Response: $response\n";
    exit;
}

// ===================== UPDATE DATABASE STATUS =====================
$stmt = $pdo->prepare("UPDATE orders SET status = 'sent' WHERE id = ?");
$stmt->execute([$order_id]);

// ===================== REDIRECT =====================
header("Location: orders.php?sent=1");
exit();
