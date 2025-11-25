<?php
require_once __DIR__ . '/../config.php'; // config.php in root
require_once __DIR__ . '/layout.php';    // layout.php in public

// Fetch orders with items
$stmt = $pdo->query("
    SELECT o.id, o.customer_name, o.order_date, oi.item_name, oi.price, oi.quantity
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    ORDER BY o.order_date DESC
");

$orders = [];
while ($row = $stmt->fetch()) {
    $orders[$row['id']]['customer_name'] = $row['customer_name'];
    $orders[$row['id']]['order_date'] = $row['order_date'];
    $orders[$row['id']]['items'][] = $row;
}

ob_start();
?>
<?php foreach ($orders as $id => $order): ?>
    <div class="mb-6 p-4 border rounded shadow">
        <h2 class="font-semibold text-lg mb-2">
            Order #<?= $id ?> - <?= htmlspecialchars($order['customer_name']) ?>
        </h2>
        <p class="text-gray-500 mb-2"><?= $order['order_date'] ?></p>
        <table class="w-full border">
            <thead>
                <tr class="bg-gray-100">
                    <th>Item</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $total = 0; ?>
                <?php foreach ($order['items'] as $item): ?>
                    <?php $subtotal = $item['price'] * $item['quantity']; ?>
                    <?php $total += $subtotal; ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <td><?= number_format($item['price'], 2) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= number_format($subtotal, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="font-semibold">
                    <td colspan="3" class="text-right">Total:</td>
                    <td><?= number_format($total, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>
<?php
$content = ob_get_clean();
renderLayout('Orders', $content, 'orders');
