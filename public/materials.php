<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch all materials
$stmt = $pdo->query("
    SELECT id, sku, name, description, price, category, created_at
    FROM products
    ORDER BY created_at DESC
");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php ob_start(); ?>

<div class="p-6">

    <!-- Search + Filter + Buttons -->
    <div class="flex items-center gap-4 mb-4 justify-between">

        <div class="flex items-center gap-4">

            <!-- SEARCH -->
            <input id="searchMaterials"
                   type="text"
                   class="border px-4 py-2 rounded-xl w-80 shadow-sm"
                   placeholder="Search materials...">

        </div>

        <div class="flex gap-2">
            <button id="openAddModal"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow">
                Add
            </button>
            <button id="openImportModal"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg shadow">
                Import
            </button>
        </div>

    </div>

    <!-- Materials Table -->
    <div class="bg-white rounded-2xl shadow p-4 border border-gray-200 overflow-x-auto">
        <table class="w-full border-collapse" id="materialsTable">
            <thead>
                <tr class="border-b text-left text-gray-700">
                    <th class="py-2 px-2">SKU</th>
                    <th class="py-2 px-2">Name</th>
                    <th class="py-2 px-2">Description</th>
                    <th class="py-2 px-2">Price</th>
                    <th class="py-2 px-2">Category</th>
                    <th class="py-2 px-2">Created</th>
                    <th class="py-2 px-2 w-40">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($materials as $m): ?>
                <tr class="border-b hover:bg-gray-50 transition" data-id="<?= $m['id'] ?>">
                    <td class="py-2 px-2 font-medium text-gray-800"><?= htmlspecialchars($m['sku']) ?></td>
                    <td class="py-2 px-2"><?= htmlspecialchars($m['name']) ?></td>
                    <td class="py-2 px-2"><?= htmlspecialchars($m['description']) ?></td>
                    <td class="py-2 px-2 font-semibold">$<?= number_format($m['price'], 2) ?></td>
                    <td class="py-2 px-2"><?= htmlspecialchars($m['category']) ?></td>
                    <td class="py-2 px-2 text-gray-500 text-sm"><?= date("M d, Y", strtotime($m['created_at'])) ?></td>
                    <td class="py-2 px-2 flex gap-2">
                        <button class="edit-btn px-3 py-1 bg-green-600 text-white rounded-lg text-sm shadow" data-id="<?= $m['id'] ?>">Edit</button>
                        <button class="delete-btn px-3 py-1 bg-red-600 text-white rounded-lg text-sm shadow" data-id="<?= $m['id'] ?>">Delete</button>
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
        <h2 class="text-lg font-semibold mb-3">Add Material</h2>
        <form id="addMaterialForm">
            <div class="mb-2">
                <label class="block mb-1 font-medium">Name</label>
                <input type="text" name="name" class="material-input" placeholder="Material Name" required>
            </div>
            <div class="mb-2">
                <label class="block mb-1 font-medium">Description</label>
                <textarea name="description" class="material-input" placeholder="Description"></textarea>
            </div>
            <div class="mb-2">
                <label class="block mb-1 font-medium">Price</label>
                <input type="number" step="0.01" name="price" class="material-input" placeholder="Price" required>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="cancelAdd" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">
    <div class="bg-white p-6 rounded-xl shadow-lg w-96">
        <h2 class="text-lg font-semibold mb-3">Edit Material</h2>
        <form id="editMaterialForm">
            <input type="hidden" name="id">
            <div class="mb-2">
                <label class="block mb-1 font-medium">Name</label>
                <input type="text" name="name" class="material-input" placeholder="Material Name" required>
            </div>
            <div class="mb-2">
                <label class="block mb-1 font-medium">Description</label>
                <textarea name="description" class="material-input" placeholder="Description"></textarea>
            </div>
            <div class="mb-2">
                <label class="block mb-1 font-medium">Price</label>
                <input type="number" step="0.01" name="price" class="material-input" placeholder="Price" required>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="cancelEdit" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- IMPORT MODAL -->
<div id="importModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">
    <div class="bg-white p-6 rounded-xl shadow-lg w-96">
        <h2 class="text-lg font-semibold mb-3">Import Materials via CSV</h2>
        <form id="importForm" enctype="multipart/form-data" method="post" action="partials/import_materials.php">
            <div class="mb-4">
                <input type="file" name="csv_file" accept=".csv" required>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" id="cancelImport" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Import</button>
            </div>
        </form>
    </div>
</div>

<script>
// Open/Close Add Modal
document.getElementById("openAddModal").onclick = () => {
    document.getElementById("addModal").classList.remove("hidden");
    document.getElementById("addModal").classList.add("flex");
};
document.getElementById("cancelAdd").onclick = () => {
    document.getElementById("addModal").classList.add("hidden");
};

// Open/Close Import Modal
document.getElementById("openImportModal").onclick = () => {
    document.getElementById("importModal").classList.remove("hidden");
    document.getElementById("importModal").classList.add("flex");
};
document.getElementById("cancelImport").onclick = () => {
    document.getElementById("importModal").classList.add("hidden");
};

// Add Material AJAX
document.getElementById("addMaterialForm").addEventListener("submit", e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    fetch("partials/save_material.php", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => { if(data.success) location.reload(); else alert(data.message); });
});

// Edit Material - fill modal
document.querySelectorAll(".edit-btn").forEach(btn => {
    btn.onclick = () => {
        const row = btn.closest("tr");
        document.querySelector("#editMaterialForm [name='id']").value = row.dataset.id;
        document.querySelector("#editMaterialForm [name='name']").value = row.children[1].innerText;
        document.querySelector("#editMaterialForm [name='description']").value = row.children[2].innerText;
        document.querySelector("#editMaterialForm [name='price']").value = parseFloat(row.children[3].innerText.replace('$',''));
        const modal = document.getElementById("editModal");
        modal.classList.remove("hidden"); modal.classList.add("flex");
    };
});

// Update Material AJAX
document.getElementById("editMaterialForm").addEventListener("submit", e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    fetch("partials/update_material.php", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => { if(data.success) location.reload(); else alert(data.message); });
});

// Cancel Edit Modal
document.getElementById("cancelEdit").onclick = () => {
    document.getElementById("editModal").classList.add("hidden");
};

// Delete Material AJAX
document.querySelectorAll(".delete-btn").forEach(btn => {
    btn.onclick = () => {
        const id = btn.dataset.id;
        if(confirm("Are you sure you want to delete this material?")){
            fetch("partials/delete_material.php", {
                method: "POST",
                headers: {"Content-Type":"application/x-www-form-urlencoded"},
                body: `id=${id}`
            }).then(() => location.reload());
        }
    };
});

// Live Search
document.getElementById("searchMaterials").addEventListener("keyup", function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll("#materialsTable tbody tr").forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? "table-row" : "none";
    });
});
</script>

<?php
renderLayout("Materials", ob_get_clean(), "materials");
?>
