<?php
require_once __DIR__ . '/../config.php';

// Get order ID
$order_id = $_GET['order_id'] ?? 0;

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("❌ Order not found or has been deleted.");
}

// Fetch order items
$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$order_id]);
$items = $itemStmt->fetchAll();

// Fetch ducted installation options
$ductStmt = $pdo->query("SELECT * FROM ductedinstallation ORDER BY category ASC");
$ductItems = $ductStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Order</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

<style>
.input-field {
    border: 1px solid #ccc;
    padding: 8px 12px;
    border-radius: 8px;
    width: 100%;
}
</style>
</head>

<body class="bg-gray-100 p-6">

<div class="max-w-6xl mx-auto bg-white p-6 rounded-xl shadow">

    <h2 class="text-2xl font-semibold text-gray-700 mb-4">
        Edit Order #<?= htmlspecialchars($order_id) ?>
    </h2>

    <!-- ORDER FIELDS -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <input type="text" value="<?= htmlspecialchars($order['customer_name']) ?>" class="input-field">
        <input type="text" value="<?= htmlspecialchars($order['customer_email']) ?>" class="input-field">
        <input type="text" value="<?= htmlspecialchars($order['job_address']) ?>" class="input-field">
        <input type="number" value="<?= htmlspecialchars($order['total_amount']) ?>" class="input-field">
    </div>

    <!-- ITEMS CARD -->
    <div class="bg-gray-50 p-4 rounded-xl shadow border border-gray-200 mb-6">

        <div class="flex justify-between items-center mb-3">
            <h3 class="text-lg font-medium text-gray-700">Order Items</h3>

            <!-- ADD ITEM BUTTON OUTSIDE TABLE BUT INSIDE CARD -->
            <button onclick="addItem()"
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                + Add Item
            </button>
        </div>

        <!-- ITEMS TABLE -->
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b">
                    <th class="p-2">Indoor Model</th>
                    <th class="p-2">Outdoor Model</th>
                    <th class="p-2">Equipment</th>
                    <th class="p-2">Qty</th>
                    <th class="p-2">Unit Cost</th>
                    <th class="p-2">Total</th>
                    <th class="p-2"></th>
                </tr>
            </thead>

            <tbody id="itemsTable">

                <?php foreach ($items as $it): ?>
                <tr class="border-b">
                    <td class="p-2"><?= htmlspecialchars($it['model_name_indoor']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($it['model_name_outdoor']) ?></td>
                    <td class="p-2"><?= htmlspecialchars($it['equipment_name']) ?></td>
                    <td class="p-2"><?= $it['quantity'] ?></td>
                    <td class="p-2"><?= number_format($it['total_cost'],2) ?></td>
                    <td class="p-2"><?= number_format($it['total'],2) ?></td>
                    <td class="p-2">
                        <button onclick="this.closest('tr').remove()"
                                class="text-red-600">Remove</button>
                    </td>
                </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

    </div>

    <!-- SUMMARY PANEL -->
    <div class="bg-white p-4 rounded-xl shadow border border-gray-200">
        <h3 class="text-lg font-medium text-gray-700 mb-3">Summary</h3>

        <div class="grid grid-cols-2 gap-4">
            <div>Total Items: <span id="summaryItems">0</span></div>
            <div>Total Amount: ₱<span id="summaryAmount">0.00</span></div>
        </div>
    </div>
</div>


<script>
// Add new item row
function addItem() {
    const row = `
        <tr class="border-b">
            <td class="p-2">
                <select class="input-field">
                    <option value="">Select Indoor</option>
                    <?php foreach ($ductItems as $d): ?>
                    <option><?= htmlspecialchars($d['model_name_indoor']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>

            <td class="p-2">
                <select class="input-field">
                    <option value="">Select Outdoor</option>
                    <?php foreach ($ductItems as $d): ?>
                    <option><?= htmlspecialchars($d['model_name_outdoor']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>

            <td class="p-2">
                <input type="text" class="input-field" placeholder="Equipment">
            </td>

            <td class="p-2">
                <input type="number" class="input-field qty" value="1" oninput="updateSummary()">
            </td>

            <td class="p-2">
                <input type="number" class="input-field cost" value="0" oninput="updateSummary()">
            </td>

            <td class="p-2 total">0.00</td>

            <td class="p-2">
                <button onclick="this.closest('tr').remove(); updateSummary();"
                    class="text-red-600">Remove</button>
            </td>
        </tr>
    `;
    document.getElementById("itemsTable").insertAdjacentHTML('beforeend', row);
    updateSummary();
}

// Update totals
function updateSummary() {
    let items = document.querySelectorAll("#itemsTable tr");
    let totalItems = 0;
    let totalAmount = 0;

    items.forEach(row => {
        const qty = parseFloat(row.querySelector(".qty")?.value || 0);
        const cost = parseFloat(row.querySelector(".cost")?.value || 0);
        const total = qty * cost;

        row.querySelector(".total").innerText = total.toFixed(2);

        totalItems += qty;
        totalAmount += total;
    });

    document.getElementById("summaryItems").innerText = totalItems;
    document.getElementById("summaryAmount").innerText = totalAmount.toFixed(2);
}

updateSummary();
</script>

</body>
</html>
