<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if ($order_id <= 0) die("<h2 style='color:red;padding:20px;'>Invalid order id</h2>");

// fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) die("<h2 style='color:red;padding:20px;'>❌ Order not found or has been deleted.</h2>");

// fetch order_items
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$order_id]);
$itemsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// group by item_category
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

    // resolve display name & price override if needed
    switch ($cat) {
        case 'product':
            $s = $pdo->prepare("SELECT name FROM products WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $item['name'] = $s->fetchColumn() ?: ($it['installation_type'] ?: 'Product');
            break;

        case 'split':
            $s = $pdo->prepare("SELECT item_name FROM split_installation WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $item['name'] = $s->fetchColumn() ?: ($it['installation_type'] ?: 'Split Installation');
            break;

        case 'ducted':
            $s = $pdo->prepare("SELECT equipment_name FROM ductedinstallations WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $item['name'] = $s->fetchColumn() ?: 'Ducted Installation';
            // include installation_type in display if present
            if (!empty($it['installation_type'])) {
                $item['name'] .= ' (' . htmlspecialchars($it['installation_type']) . ')';
            }
            break;

        case 'personnel':
            $s = $pdo->prepare("SELECT name, rate FROM personnel WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['name'] ?? 'Personnel';
            // prefer stored price if present, otherwise use rate
            $item['price'] = isset($it['price']) && $it['price'] > 0 ? $it['price'] : ($row['rate'] ?? $it['price']);
            break;

        case 'equipment':
            $s = $pdo->prepare("SELECT item FROM equipment WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $item['name'] = $s->fetchColumn() ?: 'Equipment';
            break;

        default: // expense
            // for expense we stored the expense name in installation_type
            $item['name'] = $it['installation_type'] ?: ($it['name'] ?? 'Other Expense');
            break;
    }

    // ensure qty/price/line_total numeric
    $item['qty'] = $item['qty'] ?? 1;
    $item['price'] = isset($item['price']) ? floatval($item['price']) : 0.0;
    $item['line_total'] = isset($item['line_total']) ? floatval($item['line_total']) : ($item['qty'] * $item['price']);

    // assign
    if (!isset($grouped[$cat])) $grouped['expense'][] = $item;
    else $grouped[$cat][] = $item;
}

// totals
$subtotal = 0; $total_cost = 0;
foreach ($grouped as $g) {
    foreach ($g as $it) {
        $subtotal += ($it['line_total'] ?? 0);
    }
}
$tax = round($subtotal * 0.10, 2);
$grand_total = round($subtotal + $tax, 2);
$profit = 0; $percent_margin = 0; $net_profit = 0; $total_profit = 0;

ob_start();
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
      <h2 class="text-2xl font-semibold text-gray-800">Review Order #<?= htmlspecialchars($order_id) ?></h2>
      <p class="text-gray-500 text-sm mt-1">Verify all order details before sending.</p>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
      <h3 class="text-lg font-semibold mb-4 text-gray-700">Client Information</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="text-gray-500 text-sm font-medium">Customer Name</label>
          <div class="mt-1 p-3 bg-gray-50 rounded-xl text-gray-800 border"><?= htmlspecialchars($order['customer_name'] ?? '') ?></div>
        </div>
        <div>
          <label class="text-gray-500 text-sm font-medium">Order Date</label>
          <div class="mt-1 p-3 bg-gray-50 rounded-xl text-gray-800 border"><?= htmlspecialchars($order['appointment_date'] ?? '') ?></div>
        </div>
      </div>
    </div>

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
                  <td class="px-4 py-3 text-center"><?= $it['qty'] ?></td>
                  <td class="px-4 py-3 text-right font-medium"><?= number_format($it['line_total'], 2) ?></td>
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

    <!-- Right PANEL -->

<!-- PROFIT CARD -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200 w-full">
    <h3 class="text-lg font-semibold text-gray-700 mb-4">Profit</h3>

    <div class="space-y-3">
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Total Items Cost</span>
            <span class="font-medium text-gray-900">₱<?= number_format($total_items_cost, 2) ?></span>
        </div>

        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Technician Cost</span>
            <span class="font-medium text-gray-900">₱<?= number_format($total_technician_cost, 2) ?></span>
        </div>

        <div class="border-t my-2"></div>

        <div class="flex justify-between text-base font-semibold">
            <span class="text-gray-700">Profit</span>
            <span class="text-green-600">₱<?= number_format($profit, 2) ?></span>
        </div>
    </div>
</div>


    <!-- SUMMARY CARD -->
<div id="rightPanel" class="bg-white p-6 rounded-2xl shadow-lg border border-gray-200 h-fit sticky top-6">
    <h3 class="text-base font-semibold text-gray-700 mb-3">Order Summary</h3>

    <div class="flex justify-between text-gray-700 mb-1">
        <span>Subtotal</span>
        <span>$<?= number_format($subtotal, 2) ?></span>
    </div>

    <div class="flex justify-between text-gray-700 mb-1">
        <span>Tax (10%)</span>
        <span>$<?= number_format($tax, 2) ?></span>
    </div>

    <div class="flex justify-between font-semibold text-gray-900 text-base border-t pt-3 mt-3">
        <span>Grand Total</span>
        <span>$<?= number_format($grand_total, 2) ?></span>
    </div>
    <!-- Buttons -->
                <button type="button" 
                        id="openEmailModal"
                        data-order-id="<?= $order_id ?>"
                        data-customer-email="<?= htmlspecialchars($order['customer_email'] ?? '') ?>"
                        data-customer-name="<?= htmlspecialchars($order['customer_name'] ?? '') ?>"
                        data-total="<?= number_format($grand_total, 2) ?>"
                        class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl font-medium transition shadow mt-4">
                    Send Order via Email
                </button>

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

                
            </div>
        </div>
    </div>
</div>

<!-- EMAIL MODAL -->
<div id="emailModal" class="fixed inset-0 bg-black bg-opacity-30 backdrop-blur-sm hidden flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="bg-white p-6 rounded-3xl shadow-2xl w-96 max-w-full mx-2 transform transition-all duration-300 ease-out scale-95 opacity-0" id="emailModalContent">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Send Order Email</h2>
        <form id="emailForm">
            <input type="hidden" id="orderIdInput" name="order_id">
            <div class="mb-3">
                <label class="block text-gray-600 font-medium mb-1">To (Email)</label>
                <input type="email" id="emailInput" name="recipient" class="w-full border rounded-xl p-2" required>
            </div>
            <div class="mb-3">
                <label class="block text-gray-600 font-medium mb-1">Customer Name</label>
                <input type="text" id="customerNameInput" name="customer_name" class="w-full border rounded-xl p-2" readonly>
            </div>
            <div class="mb-3">
                <label class="block text-gray-600 font-medium mb-1">Total</label>
                <input type="text" id="totalInput" name="total" class="w-full border rounded-xl p-2" readonly>
            </div>
            <div class="mb-3">
                <label class="block text-gray-600 font-medium mb-1">Subject</label>
                <input type="text" id="subjectField" name="subject" class="w-full border rounded-xl p-2" required>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="closeEmailModal" class="px-4 py-2 rounded-lg bg-gray-300 hover:bg-gray-400">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">Send Email</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const openBtn = document.getElementById('openEmailModal');
    const modal = document.getElementById('emailModal');
    const modalContent = document.getElementById('emailModalContent');
    const closeBtn = document.getElementById('closeEmailModal');

    const emailInput = document.getElementById('emailInput');
    const customerNameInput = document.getElementById('customerNameInput');
    const totalInput = document.getElementById('totalInput');
    const orderIdInput = document.getElementById('orderIdInput');
    const subjectField = document.getElementById('subjectField');

    openBtn.addEventListener('click', () => {
        emailInput.value = openBtn.getAttribute('data-customer-email') || '';
        customerNameInput.value = openBtn.getAttribute('data-customer-name') || '';
        totalInput.value = openBtn.getAttribute('data-total') || '';
        orderIdInput.value = openBtn.getAttribute('data-order-id') || '';
        subjectField.value = `Order #${orderIdInput.value} Details`;

        modal.classList.remove('hidden');
        setTimeout(() => modalContent.classList.add('opacity-100', 'scale-100'), 10);
    });

    closeBtn.addEventListener('click', () => {
        modalContent.classList.remove('opacity-100', 'scale-100');
        setTimeout(() => modal.classList.add('hidden'), 300);
    });

    document.getElementById('emailForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const payload = {
            order_id: orderIdInput.value,
            customer_email: emailInput.value,
            customer_name: customerNameInput.value,
            total: totalInput.value
        };

        fetch('https://primary-s0q-production.up.railway.app/webhook/send-order-email', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(() => {
            alert('Email request sent to n8n successfully!');
            modalContent.classList.remove('opacity-100', 'scale-100');
            setTimeout(() => modal.classList.add('hidden'), 300);
        })
        .catch(err => {
            console.error(err);
            alert('Failed to send email request.');
        });
    });
});
</script>

<?php
$content = ob_get_clean();
renderLayout("Review Order", $content, "orders");
?>
