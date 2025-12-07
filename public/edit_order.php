<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if ($order_id <= 0) die("<h2 style='color:red;padding:20px;'>Invalid order ID</h2>");

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) die("<h2 style='color:red;padding:20px;'>❌ Order not found</h2>");

// Fetch order items
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
$stmt->execute([$order_id]);
$itemsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by category
$grouped = [
    'product' => [],
    'split' => [],
    'ducted' => [],
    'personnel' => [],
    'equipment' => [],
    'expense' => []
];

foreach ($itemsRaw as $it) {
    $cat = strtolower(trim($it['item_category'] ?? 'expense'));
    $item = $it;

    switch ($cat) {
        case 'product':
            $s = $pdo->prepare("SELECT name, price FROM products WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['name'] ?? 'Product';
            $item['price'] = $row['price'] ?? $it['price'];
            break;

        case 'split':
            $s = $pdo->prepare("SELECT item_name, unit_price FROM split_installation WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['item_name'] ?? 'Split Installation';
            $item['price'] = $row['unit_price'] ?? $it['price'];
            break;

        case 'ducted':
            $s = $pdo->prepare("SELECT equipment_name, unit_price FROM ductedinstallations WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['equipment_name'] ?? 'Ducted Installation';
            $item['price'] = $row['unit_price'] ?? $it['price'];
            if (!empty($it['installation_type'])) $item['name'] .= ' (' . htmlspecialchars($it['installation_type']) . ')';
            break;

        case 'personnel':
            $s = $pdo->prepare("SELECT name, rate FROM personnel WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['name'] ?? 'Personnel';
            $item['price'] = isset($it['price']) && $it['price'] > 0 ? $it['price'] : ($row['rate'] ?? 0);
            break;

        case 'equipment':
            $s = $pdo->prepare("SELECT item FROM equipment WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $item['name'] = $s->fetchColumn() ?: 'Equipment';
            break;

        default:
            $item['name'] = $it['installation_type'] ?? ($it['name'] ?? 'Other Expense');
            $item['price'] = $it['price'] ?? 0;
            break;
    }

    $item['qty'] = $it['qty'] ?? 1;
    $item['line_total'] = $item['qty'] * ($item['price'] ?? 0);

    if (!isset($grouped[$cat])) $grouped['expense'][] = $item;
    else $grouped[$cat][] = $item;
}

// Calculate totals
$subtotal = 0;
foreach ($grouped as $g) {
    foreach ($g as $it) $subtotal += $it['line_total'];
}
$tax = round($subtotal * 0.10, 2);
$grand_total = round($subtotal + $tax, 2);
$profit = 0; $percent_margin = 0; $net_profit = 0; $total_profit = 0;

ob_start();
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100 flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-semibold text-gray-800">Edit Order #<?= htmlspecialchars($order_id) ?></h2>
        <p class="text-gray-500 text-sm mt-1">Verify all order details before updating.</p>
      </div>
      <a href="review_order.php?order_id=<?= $order_id ?>" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-xl font-medium transition">Review Order</a>
    </div>

    <!-- Client Info -->
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
      <h3 class="text-lg font-semibold mb-4 text-gray-700">Client Information</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="text-gray-500 text-sm font-medium">Customer Name</label>
          <input type="text" name="customer_name" value="<?= htmlspecialchars($order['customer_name']) ?>" class="mt-1 p-3 bg-gray-50 rounded-xl border w-full">
        </div>
        <div>
          <label class="text-gray-500 text-sm font-medium">Order Date</label>
          <input type="date" name="appointment_date" value="<?= htmlspecialchars($order['appointment_date']) ?>" class="mt-1 p-3 bg-gray-50 rounded-xl border w-full">
        </div>
      </div>
    </div>

    <!-- Order Items -->
    <?php
    $titles = [
      'product' => 'Ordered Products',
      'split' => 'Split Installations',
      'ducted' => 'Ducted Installations',
      'personnel' => 'Personnel',
      'equipment' => 'Equipment',
      'expense' => 'Other Expenses'
    ];
    foreach ($titles as $key => $title):
      if (!empty($grouped[$key])):
    ?>
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
              <?php foreach ($grouped[$key] as $it): ?>
                <tr class="border-t hover:bg-gray-50">
                  <td class="px-4 py-3 text-left"><?= htmlspecialchars($it['name']) ?></td>
                  <td class="px-4 py-3 text-center"><?= number_format($it['price'], 2) ?></td>
                  <td class="px-4 py-3 text-center">
                    <input type="number" min="0" value="<?= $it['qty'] ?>" class="w-20 border rounded-xl p-1 text-center qty-input" data-price="<?= htmlspecialchars($it['price']) ?>" data-category="<?= $key ?>">
                  </td>
                  <td class="px-4 py-3 text-right font-medium line-total"><?= number_format($it['line_total'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php
      endif;
    endforeach;
    ?>
  </div>

  <!-- SUMMARY CARD -->
  <div class="flex flex-col w-80 gap-4">
      <div class="bg-white p-6 rounded-2xl shadow-lg border border-gray-100 h-fit sticky top-6 mb-4">
          <h3 class="text-base font-semibold text-gray-700 mb-3">Profit Summary</h3>
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
              <span>$<?= number_format($net_profit, 2) ?></span>
          </div>
          <div class="border-t my-2"></div>
          <div class="flex justify-between font-semibold text-gray-700">
              <span>Total Profit:</span>
              <span>$<?= number_format($total_profit, 2) ?></span>
          </div>
      </div>

      <div class="bg-white p-6 rounded-2xl shadow border border-gray-200 h-auto max-h-[80vh] flex flex-col">
          <div class="flex justify-between text-gray-700">
              <span>Subtotal</span>
              <span class="subtotal"><?= number_format($subtotal, 2) ?></span>
          </div>
          <div class="flex justify-between text-gray-700">
              <span>Tax (10%)</span>
              <span class="tax"><?= number_format($tax, 2) ?></span>
          </div>
          <div class="flex justify-between font-semibold text-gray-900 text-base border-t pt-3">
              <span>Grand Total</span>
              <span class="grand-total"><?= number_format($grand_total, 2) ?></span>
          </div>

          <div class="border-t mt-4 pt-4 flex flex-col gap-3">
              <button id="updateOrderBtn" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl font-medium transition shadow">Update Order</button>
          </div>
      </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function recalcTotals() {
        let subtotal = 0;
        document.querySelectorAll('input.qty-input').forEach(input => {
            const price = parseFloat(input.dataset.price) || 0;
            const qty = parseFloat(input.value) || 0;
            const lineTotal = price * qty;
            input.closest('tr').querySelector('.line-total').innerText = lineTotal.toFixed(2);
            subtotal += lineTotal;
        });
        const tax = subtotal * 0.10;
        const grandTotal = subtotal + tax;
        document.querySelector('.subtotal').innerText = subtotal.toFixed(2);
        document.querySelector('.tax').innerText = tax.toFixed(2);
        document.querySelector('.grand-total').innerText = grandTotal.toFixed(2);
    }

    document.querySelectorAll('input.qty-input').forEach(input => {
        input.addEventListener('input', recalcTotals);
    });

    document.getElementById('updateOrderBtn').addEventListener('click', function() {
        // collect updated quantities
        const updates = [];
        document.querySelectorAll('input.qty-input').forEach(input => {
            updates.push({
                category: input.dataset.category,
                qty: input.value,
                row_index: Array.from(document.querySelectorAll('input.qty-input')).indexOf(input)
            });
        });
        alert('Order update feature not yet implemented in backend!');
        console.log(updates);
    });
});
</script>

<?php
$content = ob_get_clean();
renderLayout("Edit Order", $content, "orders");
?>
