<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer = $_POST['customer_name'] ?? '';
    $order_date = $_POST['order_date'] ?? date('Y-m-d');
    $items = $_POST['items'] ?? [];

    if ($customer && !empty($items)) {
        try {
            // Insert order
            $stmt = $pdo->prepare("INSERT INTO orders (customer_name, order_date) VALUES (?, ?)");
            $stmt->execute([$customer, $order_date]);
            $order_id = $pdo->lastInsertId();

            // Insert order items
            $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, item_name, price, quantity) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmtItem->execute([
                    $order_id,
                    $item['name'] ?? '',
                    $item['price'] ?? 0,
                    $item['qty'] ?? 0
                ]);
            }

            // Redirect to review page
            header("Location: review_order.php?order_id=$order_id");
            exit();
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "Customer name, order date, and items are required.";
    }
}

ob_start();
?>

<?php if (!empty($error)): ?>
    <div class="p-2 bg-red-200 text-red-800 rounded mb-4"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" class="space-y-4">
    <div>
        <label class="font-medium">Customer Name</label>
        <input type="text" name="customer_name" class="border rounded px-2 py-1 w-full" required>
    </div>

    <div>
        <label class="font-medium">Order Date</label>
        <input type="date" name="order_date" class="border rounded px-2 py-1 w-full" required value="<?= date('Y-m-d') ?>">
    </div>

    <div id="itemsContainer">
        <div class="flex gap-2 mb-2">
            <input type="text" name="items[0][name]" placeholder="Item Name" class="border rounded px-2 py-1 flex-1" required>
            <input type="number" name="items[0][price]" placeholder="Price" class="border rounded px-2 py-1 w-24" required>
            <input type="number" name="items[0][qty]" placeholder="Qty" class="border rounded px-2 py-1 w-16" required>
        </div>
    </div>

    <button type="button" id="addItemBtn" class="px-3 py-1 bg-blue-600 text-white rounded">Add Item</button>
    <button type="submit" class="px-3 py-1 bg-green-600 text-white rounded">Save Order</button>
</form>

<script>
let itemIndex = 1;
document.getElementById("addItemBtn").onclick = () => {
    const container = document.getElementById("itemsContainer");
    const div = document.createElement("div");
    div.classList.add("flex","gap-2","mb-2");
    div.innerHTML = `
        <input type="text" name="items[${itemIndex}][name]" placeholder="Item Name" class="border rounded px-2 py-1 flex-1" required>
        <input type="number" name="items[${itemIndex}][price]" placeholder="Price" class="border rounded px-2 py-1 w-24" required>
        <input type="number" name="items[${itemIndex}][qty]" placeholder="Qty" class="border rounded px-2 py-1 w-16" required>
    `;
    container.appendChild(div);
    itemIndex++;
};
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
