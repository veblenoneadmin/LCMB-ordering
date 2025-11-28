<?php
require_once __DIR__ . '/../config.php';

$order_id = $_POST['order_id'] ?? 0;

// ========== FETCH ORDER ==========
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) die("Order not found.");

// ========== FETCH ITEMS ==========
$stmtItem = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItem->execute([$order_id]);
$items = $stmtItem->fetchAll(PDO::FETCH_ASSOC);
if (!$items) die("No items found.");

// ========== CALCULATE TOTAL ==========
$total = 0;
foreach ($items as $item) {
    $quantity = $item['quantity'] ?? 0;
    $price    = $item['price'] ?? 0;
    $total   += $quantity * $price;
}

// ========== GET TECHNICIAN UUID ==========
$technician_id = $order['technician_id'] ?? null;
$staff_uuid = null;

if ($technician_id) {
    $stmt = $pdo->prepare("SELECT servicem8_uuid FROM personnel WHERE id = ?");
    $stmt->execute([$technician_id]);
    $staff_uuid = $stmt->fetchColumn();
}

// ========== BUILD PAYLOAD FOR N8N ==========
$payload = [
    "order_id"        => $order_id,
    "customer_name"   => $order['customer_name'] ?? '',
    "contact_number"  => $order['contact_number'] ?? '',
    "technician_uuid" => $staff_uuid,
    "order_date"      => $order['order_date'] ?? '',
    "total"           => number_format($total, 2),
    "items"           => array_map(function($i) {
        return [
            "item_name" => $i['item_name'] ?? '',
            "quantity"  => $i['quantity'] ?? 0,
            "price"     => $i['price'] ?? 0
        ];
    }, $items)
];

// ========== SEND TO N8N WEBHOOK ==========
$webhook_url = "https://primary-s0q-production.up.railway.app/webhook/8dc36143-3e26-4e47-a0f7-ab0cb8b2143d";

$ch = curl_init($webhook_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code < 200 || $http_code >= 300) {
    die("Failed to send order to N8N. HTTP Code: $http_code Response: $response");
}

// ========== UPDATE ORDER STATUS ==========
$stmt = $pdo->prepare("UPDATE orders SET status = 'sent' WHERE id = ?");
$stmt->execute([$order_id]);

// ========== REDIRECT ==========
header("Location: orders.php?sent=1");
exit();
