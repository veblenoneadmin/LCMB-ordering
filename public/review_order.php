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
    $name  = '';
    $price = isset($item['price']) ? floatval($item['price']) : 0;
    $qty   = isset($item['qty']) ? intval($item['qty']) : 1;
    $cost  = isset($item['cost']) ? floatval($item['cost']) : null;
    $type  = $item['item_type'] ?? 'expense';

    switch ($type) {
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
                $type = 'split';
            } else {
                $stmtName = $pdo->prepare("SELECT equipment_name FROM ductedinstallations WHERE id=?");
                $stmtName->execute([$item['item_id']]);
                $name = $stmtName->fetchColumn() ?: 'Unknown Ducted Installation';
                $name .= " (" . ($item['installation_type'] ?? '') . ")";
                $type = 'ducted';
            }
            break;

        case 'personnel':
            $stmtName = $pdo->prepare("SELECT name, rate FROM personnel WHERE id=? OR technician_uuid=?");
            $stmtName->execute([$item['item_id'], $item['item_id']]);
            $row = $stmtName->fetch(PDO::FETCH_ASSOC);
            $name  = $row['name'] ?? 'Unknown Personnel';
            $price = isset($row['rate']) ? floatval($row['rate']) : 0;
            break;

        case 'equipment':
            $stmtName = $pdo->prepare("SELECT item FROM equipment WHERE id=?");
            $stmtName->execute([$item['item_id']]);
            $name = $stmtName->fetchColumn() ?: 'Unknown Equipment';
            break;

        case 'expense':
        default:
            $name = $item['name'] ?? 'Other Expense';
            break;
    }

    if ($cost === null || $cost == 0) {
        $cost = $price * 0.70;
    }

    $normalized = [
        'name'  => $name,
        'price' => $price,
        'qty'   => $qty,
        'cost'  => $cost
    ] + $item;

    if (!isset($groupedItems[$type])) {
        $type = 'expense';
    }

    $groupedItems[$type][] = $normalized;
}

// ==========================
// CALCULATE TOTALS
// ==========================
$subtotal = 0;
$total_cost = 0;

foreach ($groupedItems as $type => $items) {
    foreach ($items as $item) {
        $subtotal   += ($item['price'] ?? 0) * ($item['qty'] ?? 1);
        $total_cost += ($item['cost'] ?? 0) * ($item['qty'] ?? 1);
    }
}

$tax         = round($subtotal * 0.10, 2);
$grand_total = $subtotal + $tax;

$profit         = $subtotal - $total_cost;
$percent_margin = $subtotal > 0 ? ($profit / $subtotal) * 100 : 0;
$net_profit     = $subtotal > 0 ? (($profit - $tax) / $subtotal) * 100 : 0;
$total_profit   = $profit;

ob_start();
?>

<!-- HTML CONTENT -->
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
        <?php
        $titles = [
            'products' => 'Ordered Products',
            'split'    => 'Split Installations',
            'ducted'   => 'Ducted Installations',
            'personnel'=> 'Personnel',
            'equipment'=> 'Equipment',
            'expense'  => 'Other Expenses'
        ];
        ?>
        <?php foreach ($titles as $key => $title): ?>
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
                                    $item_sub = ($item['price'] ?? 0) * ($item['qty'] ?? 1);
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

        <div id="rightPanel" class="bg-white p-6 rounded-2xl shadow border border-gray-200 h-auto max-h-[80vh] flex flex-col">
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

        <!-- Buttons -->
        <button type="button" 
            id="openEmailModal" 
            class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl font-medium transition shadow mt-4"
            data-client-email="<?= htmlspecialchars($order['customer_email'] ?? '') ?>"
            data-order-id="<?= $order_id ?>"
            data-customer-name="<?= htmlspecialchars($order['customer_name'] ?? '') ?>"
            data-grand-total="<?= number_format($grand_total, 2) ?>">
            Send to Email
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

<!-- EMAIL MODAL -->
<div id="emailModal" class="fixed inset-0 bg-black bg-opacity-30 backdrop-blur-sm hidden flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-3xl shadow-2xl w-96 max-w-full mx-2 transform transition-all duration-300 ease-out scale-95 opacity-0" id="emailModalContent">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Send Email</h2>
        <form method="post" id="emailForm">
            <input type="email" id="emailInput" name="recipient" required>
            <input type="text" id="customerNameInput" name="customer_name" readonly>
            <input type="text" id="totalInput" name="total" readonly>
            <input type="hidden" id="orderIdInput" name="order_id">


            <div class="mb-3">
                <label class="block text-gray-600 font-medium mb-1">To:</label>
                <input type="email" id="recipientField" class="w-full border rounded-xl p-2" required>
            </div>
            <div class="mb-3">
                <label class="block text-gray-600 font-medium mb-1">Subject:</label>
                <input type="text" id="subjectField" class="w-full border rounded-xl p-2" required>
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
    const closeBtn = document.getElementById('closeEmailModal');

    const emailInput = document.getElementById('emailInput');
    const customerNameInput = document.getElementById('customerNameInput');
    const totalInput = document.getElementById('totalInput');
    const orderIdInput = document.getElementById('orderIdInput');

    const subjectField = document.getElementById('subjectField');

    // Open modal
    openBtn.addEventListener('click', () => {
        emailInput.value = openBtn.getAttribute('data-customer-email') || '';
        customerNameInput.value = openBtn.getAttribute('data-customer-name') || '';
        totalInput.value = openBtn.getAttribute('data-total') || '';
        orderIdInput.value = openBtn.getAttribute('data-order-id') || '';
        subjectField.value = `Order #${orderIdInput.value} Details`;

        modal.classList.remove('hidden'); // Show modal
        setTimeout(() => modal.classList.add('opacity-100'), 10);
    });

    // Close modal
    closeBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
        modal.classList.remove('opacity-100');
    });

    // Submit form
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
            modal.classList.add('hidden');
            modal.classList.remove('opacity-100');
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
