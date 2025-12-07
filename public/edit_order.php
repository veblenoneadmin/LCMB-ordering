<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Get the order ID
$order_id = $_GET['order_id'] ?? 0;

// Fetch order details
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id=?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch order items
$products = $pdo->prepare("SELECT * FROM order_items WHERE order_id=? AND type='product'");
$products->execute([$order_id]);
$products = $products->fetchAll(PDO::FETCH_ASSOC);

$split = $pdo->prepare("SELECT * FROM order_items WHERE order_id=? AND type='split'");
$split->execute([$order_id]);
$split = $split->fetchAll(PDO::FETCH_ASSOC);

$ducted = $pdo->prepare("SELECT * FROM order_items WHERE order_id=? AND type='ducted'");
$ducted->execute([$order_id]);
$ducted = $ducted->fetchAll(PDO::FETCH_ASSOC);

$personnel = $pdo->prepare("SELECT * FROM order_personnel WHERE order_id=?");
$personnel->execute([$order_id]);
$personnel = $personnel->fetchAll(PDO::FETCH_ASSOC);

$expenses = $pdo->prepare("SELECT * FROM order_expenses WHERE order_id=?");
$expenses->execute([$order_id]);
$expenses = $expenses->fetchAll(PDO::FETCH_ASSOC);
?>

<?php ob_start(); ?>

<div class="p-6">

<h2 class="text-2xl font-semibold text-gray-800 mb-6">Edit Order #<?= $order['id'] ?></h2>

<!-- PRODUCTS TABLE -->
<div class="mb-6 bg-white p-4 rounded-2xl shadow border border-gray-200">
    <div class="flex items-center justify-between mb-2">
        <h3 class="font-semibold text-gray-700">Products</h3>
        <button id="addProductBtn" class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm shadow">Add Product</button>
    </div>
    <table class="w-full border-collapse" id="productsTable">
        <thead>
            <tr class="border-b text-left text-gray-700">
                <th>Name</th><th>Qty</th><th>Price</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($products as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= $p['quantity'] ?></td>
                <td><?= number_format($p['price'],2) ?></td>
                <td class="flex gap-2">
                    <button type="button" class="removeRowBtn px-2 py-1 bg-red-500 text-white rounded">Remove</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- SPLIT TABLE -->
<div class="mb-6 bg-white p-4 rounded-2xl shadow border border-gray-200">
    <div class="flex items-center justify-between mb-2">
        <h3 class="font-semibold text-gray-700">Split Installations</h3>
        <button id="addSplitBtn" class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm shadow">Add Split</button>
    </div>
    <table class="w-full border-collapse" id="splitTable">
        <thead>
            <tr class="border-b text-left text-gray-700">
                <th>Name</th><th>Qty</th><th>Capacity</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($split as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><?= $s['quantity'] ?></td>
                <td><?= htmlspecialchars($s['capacity']) ?></td>
                <td class="flex gap-2">
                    <button type="button" class="removeRowBtn px-2 py-1 bg-red-500 text-white rounded">Remove</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- DUCTED TABLE -->
<div class="mb-6 bg-white p-4 rounded-2xl shadow border border-gray-200">
    <div class="flex items-center justify-between mb-2">
        <h3 class="font-semibold text-gray-700">Ducted Installations</h3>
        <button id="addDuctedBtn" class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm shadow">Add Ducted</button>
    </div>
    <table class="w-full border-collapse" id="ductedTable">
        <thead>
            <tr class="border-b text-left text-gray-700">
                <th>Name</th><th>Qty</th><th>Type</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($ducted as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['name']) ?></td>
                <td><?= $d['quantity'] ?></td>
                <td><?= htmlspecialchars($d['installation_type']) ?></td>
                <td class="flex gap-2">
                    <button type="button" class="removeRowBtn px-2 py-1 bg-red-500 text-white rounded">Remove</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- PERSONNEL TABLE -->
<div class="mb-6 bg-white p-4 rounded-2xl shadow border border-gray-200">
    <div class="flex items-center justify-between mb-2">
        <h3 class="font-semibold text-gray-700">Personnel</h3>
        <button id="addPersonnelBtn" class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm shadow">Add Personnel</button>
    </div>
    <table class="w-full border-collapse" id="personnelTable">
        <thead>
            <tr class="border-b text-left text-gray-700">
                <th>Name</th><th>Role</th><th>Rate</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($personnel as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['role']) ?></td>
                <td><?= number_format($p['rate'],2) ?></td>
                <td class="flex gap-2">
                    <button type="button" class="removeRowBtn px-2 py-1 bg-red-500 text-white rounded">Remove</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- EXPENSES TABLE -->
<div class="mb-6 bg-white p-4 rounded-2xl shadow border border-gray-200">
    <div class="flex items-center justify-between mb-2">
        <h3 class="font-semibold text-gray-700">Expenses</h3>
        <button id="addExpenseBtn" class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm shadow">Add Expense</button>
    </div>
    <table class="w-full border-collapse" id="expensesTable">
        <thead>
            <tr class="border-b text-left text-gray-700">
                <th>Item</th><th>Amount</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($expenses as $e): ?>
            <tr>
                <td><?= htmlspecialchars($e['item']) ?></td>
                <td><?= number_format($e['amount'],2) ?></td>
                <td class="flex gap-2">
                    <button type="button" class="removeRowBtn px-2 py-1 bg-red-500 text-white rounded">Remove</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</div>

<script>
// Helper function to add rows
function addRow(tableId, columnsHtml) {
    const tableBody = document.querySelector(`#${tableId} tbody`);
    const newRow = document.createElement("tr");
    newRow.innerHTML = columnsHtml;
    tableBody.appendChild(newRow);

    const removeBtn = newRow.querySelector(".removeRowBtn");
    if (removeBtn) removeBtn.onclick = () => newRow.remove();
}

// Add buttons
document.getElementById("addProductBtn").onclick = () => addRow("productsTable", `
    <td><input type="text" name="product_name[]" class="border p-1 rounded w-full"></td>
    <td><input type="number" name="quantity[]" class="border p-1 rounded w-20"></td>
    <td><input type="number" name="price[]" class="border p-1 rounded w-28"></td>
    <td class="flex gap-2"><button type="button" class="removeRowBtn px-2 py-1 bg-red-500 text-white rounded">Remove</button></td>
`);
document.getElementById("addSplitBtn").onclick = () => addRow("splitTable", `
    <td><input type="text" name="split_name[]" class="border p-1 rounded w-full"></td>
    <td><input type="number" name="split_qty[]" class="border p-1 rounded w-20"></td>
    <td><input type="text" name="split_capacity[]" class="border p-1 rounded w-28"></td>
    <td class="flex gap-2"><button type="button" class="removeRowBtn px-2 py-1 bg-red-500 text-white rounded">Remove</button></td>
`);
document.getElementById("addDuctedBtn").onclick = () => addRow("ductedTable", `
    <td><input type="text" name="ducted_name[]" class="border p-1 rounded w-full"></td>
    <td><input type="number" name="ducted_qty[]" class="border p-1 rounded w-20"></td>
    <td><input type="text" name="ducted_type[]" class="border p-1 rounded w-28"></td>
    <td class="flex gap-2"><button type="button" class="removeRowBtn px-2 py-1 bg-red-500 text-white rounded">Remove</button></td>
`);
document.getElementById("addPersonnelBtn").onclick = () => addRow("personnelTable", `
    <td><input type="text" name="personnel_name[]" class="border p-1 rounded w-full"></td>
    <td><input type="text" name="personnel_role[]" class="border p-1 rounded w-full"></td>
    <td><input type="number" name="personnel_rate[]" class="border p-1 rounded w-28"></td>
    <td class="flex gap-2"><button type="button" class="removeRowBtn px-2 py-1 bg-red-500 text-white rounded">Remove</button></td>
`);
document.getElementById("addExpenseBtn").onclick = () => addRow("expensesTable", `
    <td><input type="text" name="expense_item[]" class="border p-1 rounded w-full"></td>
    <td><input type="number" name="expense_amount[]" class="border p-1 rounded w-28"></td>
    <td class="flex gap-2"><button type="button" class="removeRowBtn px-2 py-1 bg-red-500 text-white rounded">Remove</button></td>
`);
</script>

<?php
renderLayout("Edit Order #".$order_id, ob_get_clean(), "orders");
?>
