<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("<h2 style='color:red; padding:20px;'>❌ Order not found or has been deleted.</h2>");
}

$profit = $subtotal * 0.30;
$percent_margin = ($profit / $subtotal) * 100;
$net_profit = (($profit - $gst) / $subtotal) * 100;
$total_profit = $profit;

// Fetch order items
$stmtItem = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItem->execute([$order_id]);
$itemsRaw = $stmtItem->fetchAll(PDO::FETCH_ASSOC);

// Organize items by type
$groupedItems = [
    'products' => [],
    'split' => [],
    'ducted' => [],
    'personnel' => [],
    'equipment' => [],
    'expense' => []
];

foreach ($itemsRaw as $item) {
    $name = '';
    switch($item['item_type'] ?? '') {
        case 'product':
            $stmtName = $pdo->prepare("SELECT name FROM products WHERE id=?");
            $stmtName->execute([$item['item_id']]);
            $name = $stmtName->fetchColumn() ?? 'Unknown Product';
            $groupedItems['products'][] = array_merge($item, ['name'=>$name]);
            break;
        case 'installation':
            if (($item['installation_type'] ?? '') === 'split') {
                $stmtName = $pdo->prepare("SELECT item_name FROM split_installation WHERE id=?");
                $stmtName->execute([$item['item_id']]);
                $name = $stmtName->fetchColumn() ?? 'Unknown Split Installation';
                $groupedItems['split'][] = array_merge($item, ['name'=>$name]);
            } else {
                $stmtName = $pdo->prepare("SELECT equipment_name FROM ductedinstallations WHERE id=?");
                $stmtName->execute([$item['item_id']]);
                $name = $stmtName->fetchColumn() ?? 'Unknown Ducted Installation';
                $name .= ' (' . ($item['installation_type'] ?? '') . ')';
                $groupedItems['ducted'][] = array_merge($item, ['name'=>$name]);
            }
            break;
        case 'personnel':
            $stmtName = $pdo->prepare("SELECT name FROM personnel WHERE id=?");
            $stmtName->execute([$item['item_id']]);
            $name = $stmtName->fetchColumn() ?? 'Unknown Personnel';
            $groupedItems['personnel'][] = array_merge($item, ['name'=>$name]);
            break;
        case 'equipment':
            $stmtName = $pdo->prepare("SELECT item FROM equipment WHERE id=?");
            $stmtName->execute([$item['item_id']]);
            $name = $stmtName->fetchColumn() ?? 'Unknown Equipment';
            $groupedItems['equipment'][] = array_merge($item, ['name'=>$name]);
            break;
        case 'expense':
        default:
            $name = $item['name'] ?? 'Other Expense';
            $groupedItems['expense'][] = array_merge($item, ['name'=>$name]);
            break;
    }
}

// Compute totals
$subtotal = 0;
foreach ($itemsRaw as $item) {
    $subtotal += $item['qty'] * $item['price'];
}
$tax = $subtotal * 0.10;
$grand_total = $subtotal + $tax;

ob_start();
?>

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
                        <?= htmlspecialchars($order['customer_name'] ?? '') ?>
                    </div>
                </div>
                <div>
                    <label class="text-gray-500 text-sm font-medium">Order Date</label>
                    <div class="mt-1 p-3 bg-gray-50 rounded-xl text-gray-800 border">
                        <?= htmlspecialchars($order['appointment_date'] ?? '') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- GROUPED ITEMS -->
        <?php foreach (['products'=>'Ordered Products', 'split'=>'Split Installations', 'ducted'=>'Ducted Installations', 'personnel'=>'Personnel', 'equipment'=>'Equipment', 'expense'=>'Other Expenses'] as $key => $title): ?>
            <?php if (!empty($groupedItems[$key])): ?>
            <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
                <h3 class="text-lg font-semibold mb-4 text-gray-700"><?= $title ?></h3>
                <div class="overflow-auto rounded-xl border border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left">Item</th>
                                <th class="px-4 py-3 text-center">Price</th>
                                <th class="px-4 py-3 text-center">Quantity/Hours</th>
                                <th class="px-4 py-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($groupedItems[$key] as $item): 
                            $item_sub = $item['price'] * $item['qty'];
                        ?>
                            <tr class="border-t hover:bg-gray-50">
                                <td class="px-4 py-3 text-left"><?= htmlspecialchars($item['name']) ?></td>
                                <td class="px-4 py-3 text-center"><?= number_format($item['price'], 2) ?></td>
                                <td class="px-4 py-3 text-center"><?= $item['qty'] ?></td>
                                <td class="px-4 py-3 text-right font-medium"><?= number_format($item_sub, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>

    </div>

    <!-- SUMMARY PANEL -->
<div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-100 h-fit sticky top-6">

   <!-- PROFIT CARD -->
    <div id="profitCard" class="bg-white p-4 rounded-xl shadow border border-gray-200 mb-4">
        <h3 class="text-base font-semibold text-gray-700 mb-2">Profit Summary</h3>

        <div class="flex justify-between text-gray-600 mb-1">
            <span>Profit:</span>
            <span>$<?= number_format($profit, 2) ?></span>
        </div>

        <div class="flex justify-between text-gray-600 mb-1">
            <span>Percent Margin:</span>
            <span><?= number_format($percent_margin, 2) ?>%</span>
        </div>

        <div class="flex justify-between text-gray-600 mb-1">
            <span>Net Profit:</span>
            <span><?= number_format($net_profit, 2) ?>%</span>
        </div>

        <div class="flex justify-between font-semibold text-gray-700">
            <span>Total Profit:</span>
            <span>$<?= number_format($total_profit, 2) ?></span>
        </div>
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

    <!-- SEND TO SERVICEM8 / N8N -->
    <form method="post" action="send_order.php" class="mt-6">
        <input type="hidden" name="order_id" value="<?= $order_id ?>">
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-medium transition shadow">
            Send Order to N8N
        </button>
    </form>

    <form method="post" action="send_minimal.php" class="mt-6">
        <input type="hidden" name="order_id" value="<?= $order_id ?>">
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-medium transition shadow">
            Send Order to ServiceM8
        </button>
    </form>

</div>


<?php
//-----------------------------------
// PROFIT CALCULATIONS (must run FIRST)
//-----------------------------------
$subtotal = 0;
$total_cost = 0;

foreach ($itemsRaw as $item) {
    $qty = $item['qty'] ?? 1;
    $price = $item['price'] ?? 0;

    // Default cost = 70% of price
    $cost = $item['cost'] ?? ($price * 0.7);

    $subtotal += $price * $qty;
    $total_cost += $cost * $qty;
}

$tax = round($subtotal * 0.10, 2);
$grand_total = $subtotal + $tax;

$profit = $subtotal - $total_cost;
$percent_margin = $subtotal > 0 ? ($profit / $subtotal) * 100 : 0;
$net_profit = $percent_margin - 10; // remove tax % impact
$total_profit = $profit;
?>

<?php
$content = ob_get_clean();
renderLayout("Review Order", $content, "orders");
?>
