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

// Compute total
$total = 0;
foreach ($items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Build page content
ob_start();
?>

<!-- PAGE WRAPPER -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- LEFT SECTION -->
    <div class="lg:col-span-2 space-y-6">

        <!-- ORDER HEADER -->
        <div class="bg-white p-6 rounded-xl shadow-sm">
            <h2 class="text-xl font-semibold text-gray-800">Review Order #<?= htmlspecialchars($order_id) ?></h2>
            <p class="text-gray-500 text-sm mt-1">Please verify the details below before sending to ServiceM8.</p>
        </div>

        <!-- CLIENT INFORMATION -->
        <div class="bg-white p-6 rounded-xl shadow-sm">
            <h3 class="text-lg font-semibold mb-4 text-gray-700">Client Information</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-gray-500 text-sm font-medium">Customer Name</label>
                    <div class="mt-1 p-2 bg-gray-100 rounded-lg text-gray-700">
                        <?= htmlspecialchars($order['customer_name']) ?>
                    </div>
                </div>

                <div>
                    <label class="text-gray-500 text-sm font-medium">Order Date</label>
                    <div class="mt-1 p-2 bg-gray-100 rounded-lg text-gray-700">
                        <?= htmlspecialchars($order['order_date']) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ITEMS TABLE -->
        <div class="bg-white p-6 rounded-xl shadow-sm">
            <h3 class="text-lg font-semibold mb-4 text-gray-700">Order Items</h3>

            <div class="overflow-auto rounded-lg border border-gray-200">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-3 py-3 text-left">Item</th>
                            <th class="px-3 py-3">Price</th>
                            <th class="px-3 py-3">Quantity</th>
                            <th class="px-3 py-3">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): 
                        $subtotal = $item['price'] * $item['quantity'];
                    ?>
                        <tr class="border-t">
                            <td class="px-3 py-2 text-left"><?= htmlspecialchars($item['item_name']) ?></td>
                            <td class="px-3 py-2"><?= number_format($item['price'], 2) ?></td>
                            <td class="px-3 py-2"><?= $item['quantity'] ?></td>
                            <td class="px-3 py-2 font-medium"><?= number_format($subtotal, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- SUMMARY PANEL -->
    <div class="bg-white p-6 rounded-xl shadow-sm h-fit">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">Order Summary</h3>

        <div class="space-y-3 mb-4">
            <?php foreach ($items as $item): ?>
                <div class="flex justify-between text-sm text-gray-700 border-b pb-1">
                    <span><?= htmlspecialchars($item['item_name']) ?> × <?= $item['quantity'] ?></span>
                    <span><?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="border-t pt-3 text-sm">
            <div class="flex justify-between text-gray-700 mb-1">
                <span class="font-medium">Total</span>
                <span class="font-semibold"><?= number_format($total, 2) ?></span>
            </div>
        </div>

        <!-- SEND TO SERVICEM8 -->
        <form method="post" action="send_order.php" class="mt-6">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">
            <button
                type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-medium text-center transition">
                Send Order to ServiceM8
            </button>
        </form>
    </div>

</div>

<?php
$content = ob_get_clean();
renderLayout("Review Order", $content, "orders");
?>
