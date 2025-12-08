<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if ($order_id <= 0) die("<h2 style='color:red;padding:20px;'>Invalid order id</h2>");

// fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) die("<h2 style='color:red;padding:20px;'>❌ Order not found or has been deleted.</h2>");

// fetch order_items
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$order_id]);
$itemsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// helper lists for add-new rows
$products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$split_installations = $pdo->query("SELECT id, item_name, unit_price FROM split_installation ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$ducted_list = $pdo->query("SELECT id, equipment_name, total_cost FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$personnel_list = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipment_list = $pdo->query("SELECT id, item, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC);

// Group existing items
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
            $item['price'] = isset($it['price']) && $it['price']>0 ? $it['price'] : ($row['price'] ?? 0);
            break;
        case 'split':
            $s = $pdo->prepare("SELECT item_name, unit_price FROM split_installation WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['item_name'] ?? 'Split Installation';
            $item['price'] = isset($it['price']) && $it['price']>0 ? $it['price'] : ($row['unit_price'] ?? 0);
            break;
        case 'ducted':
            $s = $pdo->prepare("SELECT equipment_name, total_cost FROM ductedinstallations WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['equipment_name'] ?? 'Ducted Installation';
            if (!empty($it['installation_type'])) $item['name'] .= ' (' . htmlspecialchars($it['installation_type']) . ')';
            $item['price'] = isset($it['price']) && $it['price']>0 ? $it['price'] : ($row['total_cost'] ?? 0);
            break;
        case 'personnel':
            $s = $pdo->prepare("SELECT name, rate FROM personnel WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['name'] ?? 'Personnel';
            $item['price'] = isset($it['price']) && $it['price']>0 ? $it['price'] : ($row['rate'] ?? 0);
            $d = $pdo->prepare("SELECT date, hours FROM dispatch WHERE order_id=? AND personnel_id=? LIMIT 1");
            $d->execute([$order_id, $it['item_id']]);
            $dr = $d->fetch(PDO::FETCH_ASSOC);
            $item['dispatch_date'] = $dr['date'] ?? '';
            $item['dispatch_hours'] = $dr['hours'] ?? $it['qty'];
            break;
        case 'equipment':
            $s = $pdo->prepare("SELECT item, rate FROM equipment WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['item'] ?? 'Equipment';
            $item['price'] = isset($it['price']) && $it['price']>0 ? $it['price'] : ($row['rate'] ?? 0);
            break;
        default:
            $item['name'] = $it['installation_type'] ?? ($it['description'] ?? 'Other Expense');
            $item['price'] = $it['price'] ?? 0;
            break;
    }

    $item['qty'] = $item['qty'] ?? 1;
    $item['line_total'] = $item['line_total'] ?? ($item['qty'] * $item['price']);

    if (!isset($grouped[$cat])) $grouped['expense'][] = $item;
    else $grouped[$cat][] = $item;
}

// Calculate totals
$subtotal = 0;
foreach ($grouped as $g) {
    foreach ($g as $it) $subtotal += ($it['line_total'] ?? 0);
}
$tax = round($subtotal * 0.10, 2);
$grand_total = round($subtotal + $tax, 2);

ob_start();
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<form method="post" action="save_edit_order.php" id="editOrderForm">
<input type="hidden" name="order_id" value="<?= $order_id ?>">

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-6">
    <!-- HEADER + CLIENT INFO (unchanged) -->
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100 flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-semibold text-gray-800">Edit Order #<?= htmlspecialchars($order_id) ?></h2>
        <p class="text-gray-500 text-sm mt-1">Verify and edit all order details before saving.</p>
      </div>
      <a href="review_order.php?order_id=<?= $order_id ?>" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700">Back to Review</a>
    </div>

    <!-- CLIENT INFO -->
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
        <div>
          <label class="text-gray-500 text-sm font-medium">Email</label>
          <input type="email" name="customer_email" value="<?= htmlspecialchars($order['customer_email']) ?>" class="mt-1 p-3 bg-gray-50 rounded-xl border w-full">
        </div>
        <div>
          <label class="text-gray-500 text-sm font-medium">Phone</label>
          <input type="text" name="contact_number" value="<?= htmlspecialchars($order['contact_number']) ?>" class="mt-1 p-3 bg-gray-50 rounded-xl border w-full">
        </div>
        <div class="md:col-span-2">
          <label class="text-gray-500 text-sm font-medium">Address</label>
          <input type="text" name="job_address" value="<?= htmlspecialchars($order['job_address']) ?>" class="mt-1 p-3 bg-gray-50 rounded-xl border w-full">
        </div>
      </div>
    </div>

    <!-- EXISTING ITEMS -->
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
                <th class="px-4 py-3 text-center">Qty / Hours</th>
                <?php if ($key === 'personnel'): ?><th class="px-4 py-3 text-center">Date</th><?php endif; ?>
                <th class="px-4 py-3 text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($grouped[$key] as $it): ?>
                <tr class="border-t hover:bg-gray-50">
                  <td class="px-4 py-3 text-left"><?= htmlspecialchars($it['name']) ?></td>
                  <td class="px-4 py-3 text-center">
                    <input type="number" step="0.01" name="price[<?= $it['id'] ?>]" value="<?= number_format($it['price'],2,'.','') ?>" class="w-28 border rounded px-2 py-1 text-center existing-price" data-id="<?= $it['id'] ?>">
                  </td>
                  <td class="px-4 py-3 text-center">
                    <input type="number" step="0.01" min="0" name="qty[<?= $it['id'] ?>]" value="<?= $it['qty'] ?>" class="w-20 border rounded px-2 py-1 text-center existing-qty" data-id="<?= $it['id'] ?>">
                  </td>
                  <?php if ($key === 'personnel'): ?>
                    <td class="px-4 py-3 text-center">
                      <input type="text" name="personnel_date[<?= $it['id'] ?>]" value="<?= htmlspecialchars($it['dispatch_date'] ?? '') ?>" class="personnel-date-input w-36 border rounded px-2 py-1 text-center" placeholder="YYYY-MM-DD">
                    </td>
                  <?php endif; ?>
                  <td class="px-4 py-3 text-right font-medium existing-line-total" data-id="<?= $it['id'] ?>"><?= number_format($it['line_total'], 2) ?></td>
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

    <!-- ===== ADD NEW ITEMS AREA ===== -->

    <!-- Add Product, Split, Ducted, Personnel, Equipment, Expense sections -->
    <!-- SAME AS IN YOUR PROVIDED TEMPLATE, INCLUDING THE UPDATED DUCTED ROW TEMPLATE -->
    <!-- Make sure each has class `.product-row`, `.split-row`, `.ducted-row`, etc. -->
    <!-- See my previous message for the full templates -->

  </div>

  <!-- RIGHT SUMMARY -->
  <div class="flex flex-col w-80 gap-4">
    <div class="bg-white p-6 rounded-2xl shadow border border-gray-200 h-auto max-h-[80vh] flex flex-col">
      <div class="flex justify-between text-gray-700">
        <span>Subtotal</span>
        <span id="subtotalDisplay"><?= number_format($subtotal,2) ?></span>
      </div>
      <div class="flex justify-between text-gray-700">
        <span>Tax (10%)</span>
        <span id="taxDisplay"><?= number_format($tax,2) ?></span>
      </div>
      <div class="flex justify-between font-semibold text-gray-900 text-base border-t pt-3">
        <span>Grand Total</span>
        <span id="grandDisplay"><?= number_format($grand_total,2) ?></span>
      </div>
      <div class="mt-4">
        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl font-medium transition shadow">
          Save Changes
        </button>
      </div>
    </div>
  </div>
</div>
</form>

<script>
// JS: recalc, add/remove rows, attachSelectBehavior, flatpickr, etc.
// (Use the same JS I provided in the previous message, fully supporting ducted rows)
</script>

<style>
.qbtn { background:#e6eef8;padding:6px 10px;border-radius:8px;border:1px solid #cfe0f8;cursor:pointer }
</style>

<?php
$content = ob_get_clean();
renderLayout("Edit Order", $content, "orders");
?>
