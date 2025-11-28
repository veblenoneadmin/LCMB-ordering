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

// Compute totals
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$tax = $subtotal * 0.10;
$grand_total = $subtotal + $tax;

// Build page content
ob_start();
?>

<!-- PAGE WRAPPER -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- LEFT SECTION -->
    <div class="lg:col-span-2 space-y-6">

        <!-- ORDER HEADER -->
        <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
            <h2 class="text-2xl font-semibold text-gray-800">Review Order #<?= htmlspecialchars($order_id) ?></h2>
            <p class="text-gray-500 text-sm mt-1">Verify all order details before sending to ServiceM8.</p>
        </div>

        <!-- CLIENT INFORMATION -->
        <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
            <h3 class="text-lg font-semibold mb-4 text-gray-700">Client Information</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-gray-500 text-sm font-medium">Customer Name</label>
                    <div class="mt-1 p-3 bg-gray-50 rounded-xl text-gray-800 border">
                        <?= htmlspecialchars($order['customer_name']) ?>
                    </div>
                </div>

                <div>
                    <label class="text-gray-500 text-sm font-medium">Order Date</label>
                    <div class="mt-1 p-3 bg-gray-50 rounded-xl text-gray-800 border">
                        <?= htmlspecialchars($order['order_date']) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ITEMS TABLE -->
        <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
            <h3 class="text-lg font-semibold mb-4 text-gray-700">Order Items</h3>

            <div class="overflow-auto rounded-xl border border-gray-200">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left">Item</th>
                            <th class="px-4 py-3 text-center">Price</th>
                            <th class="px-4 py-3 text-center">Quantity</th>
                            <th class="px-4 py-3 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): 
                        $item_sub = $item['price'] * $item['quantity'];
                    ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="px-4 py-3 text-left"><?= htmlspecialchars($item['item_name']) ?></td>
                            <td class="px-4 py-3 text-center"><?= number_format($item['price'], 2) ?></td>
                            <td class="px-4 py-3 text-center"><?= $item['quantity'] ?></td>
                            <td class="px-4 py-3 text-right font-medium"><?= number_format($item_sub, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- SUMMARY PANEL -->
    <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-100 h-fit sticky top-6">
        <h3 class="text-lg font-semibold text-gray-700 mb-6">Order Summary</h3>

        <!-- ITEM LIST -->
        <div class="space-y-3 mb-6">
            <?php foreach ($items as $item): ?>
                <div class="flex justify-between text-sm text-gray-700 border-b pb-2">
                    <span><?= htmlspecialchars($item['item_name']) ?> × <?= $item['quantity'] ?></span>
                    <span><?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- TOTALS -->
        <div class="border-t pt-4 text-sm space-y-2">
            <div class="flex justify-between text-gray-700">
                <span>Subtotal</span>
                <span><?= number_format($subtotal, 2) ?></span>
            </div>

            <div class="flex justify-between text-gray-700">
                <span>Tax (10%)</span>
                <span><?= number_format($tax, 2) ?></span>
            </div>

            <div class="flex justify-between font-semibold text-gray-900 text-base border-t pt-3">
                <span>Grand Total</span>
                <span><?= number_format($grand_total, 2) ?></span>
            </div>
        </div>

        <!-- SEND TO SERVICEM8 -->
        <form method="post" action="send_order.php" class="mt-6">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">

            <button
                type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-medium transition shadow">
                Send Order to ServiceM8
            </button>
        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
renderLayout("Review Order", $content, "orders");
?>
