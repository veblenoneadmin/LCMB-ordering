<?php
require_once __DIR__ . '/../config.php';

// Get the order ID from POST
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
if ($order_id <= 0) {
    die('Invalid order ID.');
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    die('Order not found.');
}

// Fetch order items
$stmtItem = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItem->execute([$order_id]);
$itemsRaw = $stmtItem->fetchAll(PDO::FETCH_ASSOC);

// Normalize items for n8n
$items = [];
foreach ($itemsRaw as $item) {
    $name = '';
    $price = $item['price'] ?? 0;
    $qty = $item['qty'] ?? 1;

    switch ($item['item_type'] ?? '') {
        case 'product':
            $stmtName = $pdo->prepare("SELECT name FROM products WHERE id=?");
            $stmtName->execute([$item['item_id']]);
            $name = $stmtName->fetchColumn() ?: 'Unknown Product';
            break;

        case 'installation':
            if (($item['installation_type'] ?? '') === 'split') {
                $stmtName = $pdo->prepare("SELECT item_name FROM split_installation WHERE id=?");
                $stmtName->execute([$item['item_id']]);
                $name = $stmtName->fetchColumn() ?: 'Unknown Split Installation';
            } else {
                $stmtName = $pdo->prepare("SELECT equipment_name FROM ductedinstallations WHERE id=?");
                $stmtName->execute([$item['item_id']]);
                $name = $stmtName->fetchColumn() ?: 'Unknown Ducted Installation';
                $name .= " (" . ($item['installation_type'] ?? '') . ")";
            }
            break;

        case 'personnel':
            $stmtName = $pdo->prepare("SELECT name, rate FROM personnel WHERE id=? OR technician_uuid=?");
            $stmtName->execute([$item['item_id'], $item['item_id']]);
            $row = $stmtName->fetch(PDO::FETCH_ASSOC);
            $name = $row['name'] ?? 'Unknown Personnel';
            $price = $row['rate'] ?? $price;
            break;

        case 'equipment':
            $stmtName = $pdo->prepare("SELECT item FROM equipment WHERE id=?");
            $stmtName->execute([$item['item_id']]);
            $name = $stmtName->fetchColumn() ?: 'Unknown Equipment';
            break;

        default:
            $name = $item['name'] ?? 'Other Expense';
            break;
    }

    $items[] = [
        'name'  => $name,
        'price' => floatval($price),
        'qty'   => intval($qty),
        'subtotal' => floatval($price) * intval($qty)
    ];
}

// Calculate grand total
$grand_total = array_sum(array_column($items, 'subtotal'));

// Prepare data to send to n8n webhook
$orderData = [
    'order_id' => $order_id,
    'customer_email' => $order['customer_email'] ?? '',
    'items' => $items,
    'grand_total' => $grand_total
];

// n8n webhook URL
$webhookUrl = 'https://your-n8n-domain.com/webhook/send-order-email';

// Send POST request
$options = [
    'http' => [
        'header'  => "Content-Type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($orderData),
    ],
];
$context  = stream_context_create($options);
$result = file_get_contents($webhookUrl, false, $context);

// Feedback
if ($result === FALSE) {
    echo "Failed to send to n8n webhook.";
} else {
    echo "Order email sent successfully!";
}
?>
