<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

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
        <th>Subtotal</th>
    </tr>
    <?php
    $total = 0;
    foreach ($items as $item):
        $subtotal = $item['price'] * $item['quantity'];
        $total += $subtotal;
    ?>
    <tr>
        <td><?= htmlspecialchars($item['item_name']) ?></td>
        <td><?= number_format($item['price'], 2) ?></td>
        <td><?= $item['quantity'] ?></td>
        <td><?= number_format($subtotal, 2) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr>
        <td colspan="3" style="text-align:right;font-weight:bold;">Total</td>
        <td style="font-weight:bold;"><?= number_format($total, 2) ?></td>
    </tr>
</table>

<form method="post" action="send_order.php">
    <input type="hidden" name="order_id" value="<?= $order_id ?>">
    <button type="submit" class="px-3 py-1 bg-green-600 text-white rounded">Send Order to ServiceM8</button>
</form>
