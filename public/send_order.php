<?php
require_once __DIR__ . '/../config.php';

// 1️⃣ Get order_id from POST or GET
$order_id = $_POST['order_id'] ?? $_GET['order_id'] ?? 0;
$order_id = intval($order_id);

if ($order_id <= 0) {
    die("❌ Invalid order ID.");
}

// 2️⃣ Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("❌ Order not found.");
}

// 3️⃣ Fetch order items
$stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItems->execute([$order_id]);
$itemsRaw = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Optional: build readable names
$items = [];
foreach ($itemsRaw as $item) {
    $name = '';
    switch($item['item_type'] ?? '') {
        case 'product':
            $stmtName = $pdo->prepare("SELECT name FROM products WHERE id=?");
            $stmtName->execute([$item['item_id']]);
            $name = $stmtName->fetchColumn() ?? 'Unknown Product';
            break;
        case 'installation':
            if (($item['installation_type'] ?? '') === 'split') {
                $stmtName = $pdo->prepare("SELECT item_name FROM split_installation WHERE id=?");
                $stmtName->execute([$item['item_id']]);
                $name = $stmtName->fetchColumn() ?? 'Unknown Split Installation';
            } else {
                $stmtName = $pdo->prepare("SELECT equipment_name FROM ductedinstallations WHERE id=?");
                $stmtName->execute([$item['item_id']]);
                $name = $stmtName->fetchColumn() ?? 'Unknown Ducted Installation';
                $name .= ' (' . ($item['installation_type'] ?? '') . ')';
            }
            break;
        case 'personnel':
            $stmtName = $pdo->prepare("SELECT name FROM personnel WHERE id=?");
            $stmtName->execute([$item['item_id']]);
            $name = $stmtName->fetchColumn() ?? 'Unknown Personnel';
            break;
        case 'equipment':
            $stmtName = $pdo->prepare("SELECT item FROM equipment WHERE id=?");
            $stmtName->execute([$item['item_id']]);
            $name = $stmtName->fetchColumn() ?? 'Unknown Equipment';
            break;
        case 'expense':
            $name = $item['installation_type'] ?? 'Other Expense';
            break;
        default:
            $name = 'Unknown Item';
    }

    $items[] = [
        'name'  => $name,
        'qty'   => $item['qty'] ?? 0,
        'price' => $item['price'] ?? 0
    ];
}

// 4️⃣ Build payload
$payload = [
    "order_number"     => $order['order_number'],
    "customer_name"    => $order['customer_name'],
    "customer_email"   => $order['customer_email'],
    "contact_number"   => $order['contact_number'],
    "appointment_date" => $order['appointment_date'],
    "total_amount"     => $order['total_amount'],
    "tax"              => $order['tax'],
    "grand_total"      => $order['total'],
    "discount"         => $order['discount'],
    "technician_uuid"  => $order['technician_uuid'] ?? null,
    "items"            => $items
];

// 5️⃣ Send to n8n webhook
$webhook_url = "https://primary-s0q-production.up.railway.app/webhook/8dc36143-3e26-4e47-a0f7-ab0cb8b2143d";

$options = [
    "http" => [
        "header"  => "Content-Type: application/json\r\n",
        "method"  => "POST",
        "content" => json_encode($payload)
    ]
];

$context  = stream_context_create($options);
$response = @file_get_contents($webhook_url, false, $context);

if ($response === FALSE) {
    die("❌ Failed to send order to n8n. Check webhook URL.");
}

echo "<h2 style='padding:20px;color:green;'>✅ Order #{$order_id} successfully sent to ServiceM8!</h2>";
echo "<pre>".htmlspecialchars($response)."</pre>";
