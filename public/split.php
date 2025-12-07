<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch all split installations
$stmt = $pdo->query("
    SELECT id, item_name, unit_price, quantity, category
    FROM split_installation
    ORDER BY id DESC
");
$installations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php ob_start(); ?>

<div class="p-6">

    <h2 class="text-2xl font-semibold text-gray-800 mb-6">Split Installation</h2>

    <!-- Search + Add + Import -->
    <div class="flex items-center gap-4 mb-4">

        <!-- SEARCH -->
        <input id="searchInstallations"
               type="text"
               class="border px-4 py-2 rounded-xl w-80 shadow-sm"
               placeholder="Search items...">

        <!-- RIGHT SIDE BUTTONS -->
        <div class="ml-auto flex gap-3">
            <button id="openAddModal"
                    class="px-4 py-2 bg-blue-600 text-white rounded-xl shadow">
                Add
            </button>

            <button id="openImportModal"
                    class="px-4 py-2 bg-green-600 text-white rounded-xl shadow">
                Import
            </button>
        </div>

    </div>

    <!-- Installations Table -->
    <div class="bg-white rounded-2xl shadow p-4 border border-gray-200 overflow-x-auto">
        <table class="w-full border-collapse" id="installationsTable">
            <thead>
                <tr class="border-b text-left text-gray-700">
                    <th class="py-2 px-2">ID</th>
                    <th class="py-2 px-2">Item Name</th>
                    <th class="py-2 px-2">Unit Price</th>
                    <th class="py-2 px-2">Quantity</th>
                    <th class="py-2 px-2">Category</th>
                    <th class="py-2 px-2 w-40">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installations as $i): ?>
                    <tr class="border-b" data-id="<?= $i['id'] ?>">

                        <td class="py-2 px-2 font-medium text-gray-800">#<?= $i['id'] ?></td>

                        <td class="py-2 px-2"><?= htmlspecialchars($i['item_name']) ?></td>

                        <td class="py-2 px-2 font-semibold">
                            ₱<?= number_format($i['unit_price'], 2) ?>
                        </td>

                        <td class="py-2 px-2"><?= htmlspecialchars($i['quantity']) ?></td>

                        <td class="py-2 px-2"><?= htmlspecialchars($i['category']) ?></td>

                        <td class="py-2 px-2 flex gap-2">

                            <button class="px-3 py-1 bg-green-600 text-white rounded-lg text-sm shadow editBtn"
                                    data-id="<?= $i['id'] ?>"
                                    data-item="<?= htmlspecialchars($i['item_name']) ?>"
                                    data-price="<?= $i['unit_price'] ?>"
                                    data-quantity="<?= $i['quantity'] ?>"
                                    data-category="<?= htmlspecialchars($i['category']) ?>">
                                Edit
                            </button>

                            <button class="px-3 py-1 bg-red-600 text-white rounded-lg text-sm shadow deleteBtn"
                                    data-id="<?= $i['id'] ?>">
                                Delete
                            </button>

                        </td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- ADD MODAL -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">
    <div class="bg-white p-6 rounded-xl shadow-lg w-96">
        <h2 class="text-lg font-semibold mb-3">Add Item</h2>

        <form id="addForm" method="POST" action="partials/add_split_installation.php">
            <input type="text" name="item_name" placeholder="Item Name" class="w-full mb-3 border p-2 rounded" required>
            <input type="number" step="0.01" name="unit_price" placeholder="Unit Price" class="w-full mb-3 border p-2 rounded" required>
            <input type="number" name="quantity" placeholder="Quantity" class="w-full mb-3 border p-2 rounded" required>
            <input type="text" name="category" placeholder="Category" class="w-full mb-4 border p-2 rounded" required>

            <div class="flex justify-end gap-3">
                <button type="button" id="cancelAdd" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">
    <div class="bg-white p-6 rounded-xl shadow-lg w-96">
        <h2 class="text-lg font-semibold mb-3">Edit Item</h2>

        <form id="editForm" method="POST" action="partials/edit_split_installation.php">
            <input type="hidden" name="id" id="edit_id">
            <input type="text" name="item_name" id="edit_item" class="w-full mb-3 border p-2 rounded" required>
            <input type="number" step="0.01" name="unit_price" id="edit_price" class="w-full mb-3 border p-2 rounded" required>
            <input type="number" name="quantity" id="edit_quantity" class="w-full mb-3 border p-2 rounded" required>
            <input type="text" name="category" id="edit_category" class="w-full mb-4 border p-2 rounded" required>

            <div class="flex justify-end gap-3">
                <button type="button" id="cancelEdit" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- IMPORT MODAL -->
<div id="importModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">
    <div class="bg-white p-6 rounded-xl shadow-lg w-96">
        <h2 class="text-lg font-semibold mb-3">Import via CSV</h2>

        <form id="importForm" method="POST" action="partials/import_split_installation.php" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" class="w-full mb-4" required>

            <div class="flex justify-end gap-3">
                <button type="button" id="cancelImport" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg">Import</button>
            </div>
        </form>
    </div>
</div>

<script>
// ADD MODAL
document.getElementById("openAddModal").onclick = () => {
    document.getElementById("addModal").classList.remove("hidden");
    document.getElementById("addModal").classList.add("flex");
};
document.getElementById("cancelAdd").onclick = () => {
    document.getElementById("addModal").classList.add("hidden");
};

// EDIT MODAL
document.querySelectorAll(".editBtn").forEach(btn => {
    btn.onclick = () => {
        document.getElementById("edit_id").value = btn.dataset.id;
        document.getElementById("edit_item").value = btn.dataset.item;
        document.getElementById("edit_price").value = btn.dataset.price;
        document.getElementById("edit_quantity").value = btn.dataset.quantity;
        document.getElementById("edit_category").value = btn.dataset.category;

        document.getElementById("editModal").classList.remove("hidden");
        document.getElementById("editModal").classList.add("flex");
    };
});
document.getElementById("cancelEdit").onclick = () => {
    document.getElementById("editModal").classList.add("hidden");
};

// IMPORT MODAL
document.getElementById("openImportModal").onclick = () => {
    document.getElementById("importModal").classList.remove("hidden");
    document.getElementById("importModal").classList.add("flex");
};
document.getElementById("cancelImport").onclick = () => {
    document.getElementById("importModal").classList.add("hidden");
};

// SEARCH
document.getElementById("searchInstallations").addEventListener("keyup", function () {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll("#installationsTable tbody tr");
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
});

// DELETE BUTTON
document.querySelectorAll(".deleteBtn").forEach(btn => {
    btn.onclick = () => {
        if(confirm("Are you sure you want to delete this item?")) {
            const id = btn.dataset.id;
            fetch("partials/delete_split_installation.php", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: `id=${id}`
            }).then(() => location.reload());
        }
    };
});
</script>

<?php
renderLayout("Split Installation", ob_get_clean(), "split_installation");
?>
