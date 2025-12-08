<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

$order_id = $_GET['order_id'] ?? 0;

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    renderLayout("Order Not Found", "<p class='p-4 text-red-600'>Order not found or deleted.</p>");
    exit;
}

// Fetch items
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");

$itemStmt->execute([$order_id]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// Group items
$groups = [
    "product" => [],
    "ducted" => [],
    "split" => [],
    "persons" => [],
    "equipments" => [],
    "additional" => [],
];

foreach ($items as $item) {
    $groups[$item["item_category"]][] = $item;


}

// Calculate summary totals
$total_products = array_sum(array_column($groups["product"], 'subtotal'));
$total_ducted   = array_sum(array_column($groups["ducted"], 'subtotal'));
$total_split    = array_sum(array_column($groups["split"], 'subtotal'));
$total_personnel= array_sum(array_column($groups["persons"], 'subtotal'));
$total_equip    = array_sum(array_column($groups["equipments"], 'subtotal'));
$total_additional = array_sum(array_column($groups["additional"], 'subtotal'));

$grand_total =
    $total_products +
    $total_ducted +
    $total_split +
    $total_personnel +
    $total_equip +
    $total_additional;

ob_start();
?>

<div class="grid grid-cols-12 gap-4">

    <!-- LEFT: ORDER ITEMS -->
    <div class="col-span-8 space-y-6">

        <!-- CATEGORY BLOCK -->
        <?php
        function renderCategoryBlock($title, $catKey, $items) {
            ?>
            <div class="bg-white p-4 rounded-xl shadow border border-gray-200">

                <!-- Header + Add Button -->
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold text-gray-700"><?= $title ?></h2>
                    <button 
                        class="addBtn text-sm px-3 py-1 rounded bg-blue-500 text-white"
                        data-target="<?= $catKey ?>FormWrap">
                        Add
                    </button>
                </div>

                <!-- Table -->
                <table class="w-full text-sm">
                    <thead>
                    <tr class="text-left border-b">
                        <th>Name</th>
                        <th width="80">Qty</th>
                        <th width="100">Unit</th>
                        <th width="100">Subtotal</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($items)): ?>
                        <?php foreach ($items as $i): ?>
                            <tr class="border-b">
                                <td><?= htmlspecialchars($i["item_name"]) ?></td>
                                <td><?= $i["quantity"] ?></td>
                                <td><?= number_format($i["unit_price"], 2) ?></td>
                                <td><?= number_format($i["subtotal"], 2) ?></td>
                            </tr>
                        <?php endforeach ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-gray-400 p-2">No items</td></tr>
                    <?php endif ?>
                    </tbody>
                </table>

                <!-- Add Form -->
                <div id="<?= $catKey ?>FormWrap" class="hidden mt-3">
                    <form class="addItemForm space-y-2" data-category="<?= $catKey ?>">
                        <input type="hidden" name="order_id" value="<?= $GLOBALS['order_id'] ?>">

                        <input name="item_name" class="w-full border p-2 rounded" placeholder="Item name" required>

                        <div class="grid grid-cols-3 gap-2">
                            <input name="quantity" type="number" class="border p-2 rounded" placeholder="Qty" required>
                            <input name="unit_price" type="number" step="0.01" class="border p-2 rounded" placeholder="Unit Price" required>
                            <input name="installation_type" class="border p-2 rounded" placeholder="Installation (optional)">
                        </div>

                        <button class="bg-green-500 text-white px-3 py-2 rounded text-sm">Save Item</button>
                    </form>
                </div>

            </div>
            <?php
        }

        renderCategoryBlock("Products", "product", $groups["product"]);
        renderCategoryBlock("Ducted Installation", "ducted", $groups["ducted"]);
        renderCategoryBlock("Split Installation", "split", $groups["split"]);
        renderCategoryBlock("Personnel", "persons", $groups["persons"]);
        renderCategoryBlock("Equipment", "equipments", $groups["equipments"]);
        renderCategoryBlock("Other Expense", "additional", $groups["additional"]);
        ?>

    </div>

    <!-- RIGHT: SUMMARY PANEL -->
    <div class="col-span-4">
        <div class="bg-white p-4 rounded-xl shadow border border-gray-200 sticky top-4">

            <h3 class="text-lg font-semibold text-gray-700 mb-3">Order Summary</h3>

            <div class="space-y-1 text-sm">
                <p class="flex justify-between">
                    <span>Products</span>
                    <span><?= number_format($total_products, 2) ?></span>
                </p>
                <p class="flex justify-between">
                    <span>Ducted Installation</span>
                    <span><?= number_format($total_ducted, 2) ?></span>
                </p>
                <p class="flex justify-between">
                    <span>Split Installation</span>
                    <span><?= number_format($total_split, 2) ?></span>
                </p>
                <p class="flex justify-between">
                    <span>Personnel</span>
                    <span><?= number_format($total_personnel, 2) ?></span>
                </p>
                <p class="flex justify-between">
                    <span>Equipment</span>
                    <span><?= number_format($total_equip, 2) ?></span>
                </p>
                <p class="flex justify-between">
                    <span>Other Expense</span>
                    <span><?= number_format($total_additional, 2) ?></span>
                </p>

                <p class="font-bold text-lg border-t pt-2 flex justify-between">
                    <span>Total</span>
                    <span><?= number_format($grand_total, 2) ?></span>
                </p>
            </div>

        </div>
    </div>

</div>


<script>
// Toggle add forms
document.querySelectorAll(".addBtn").forEach(btn => {
    btn.onclick = () => {
        const target = document.getElementById(btn.dataset.target);
        target.classList.toggle("hidden");
    };
});

// AJAX add item
document.querySelectorAll(".addItemForm").forEach(form => {
    form.onsubmit = e => {
        e.preventDefault();
        const data = new FormData(form);
        data.append("item_category", form.dataset.category);
        fetch("partials/add_item.php", { method: "POST", body: data })
            .then(r => r.json())
            .then(res => {
                if (res.success) location.reload();
                else alert(res.message);
            });
    };
});
</script>

<?php
$content = ob_get_clean();
renderLayout("Edit Order", $content);
