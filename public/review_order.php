<?php
require_once __DIR__ . '/../config.php';

$order_id = $_GET['order_id'] ?? 0;

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

// Fetch items
$stmtItem = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItem->execute([$order_id]);
$items = $stmtItem->fetchAll();
?>

<h2>Review Order #<?= $order_id ?></h2>
<p>Customer: <?= htmlspecialchars($order['customer_name']) ?></p>
<p>Order Date: <?= htmlspecialchars($order['order_date']) ?></p>

<table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>Item</th>
        <th>Price</th>
        <th>Quantity</th>
    </tr>
    <?php foreach ($items as $item): ?>
    <tr>
        <td><?= htmlspecialchars($item['item_name']) ?></td>
        <td><?= htmlspecialchars($item['price']) ?></td>
        <td><?= htmlspecialchars($item['quantity']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<form method="post" action="send_order.php">
    <input type="hidden" name="order_id" value="<?= $order_id ?>">
    <button type="submit" class="px-3 py-1 bg-green-600 text-white rounded">Send Order to ServiceM8</button>
</form>
