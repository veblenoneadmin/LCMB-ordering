<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch all personnel
$stmt = $pdo->query("
    SELECT id, name, email, role, rate, created_at 
    FROM personnel
    ORDER BY created_at DESC
");

$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Role filter options (you may adjust)
$roles = ['Technician', 'Installer', 'Assistant'];
?>

<?php ob_start(); ?>

<div class="p-6">

    <h2 class="text-2xl font-semibold text-gray-800 mb-6">Personnel</h2>

    <!-- Search + Filter + Add + Import -->
    <div class="flex items-center gap-4 mb-4">

        <!-- SEARCH -->
        <input id="searchPersonnel"
               type="text"
               class="border px-4 py-2 rounded-xl w-80 shadow-sm"
               placeholder="Search personnel...">

        <!-- ROLE FILTER -->
        <select id="filterRole" 
                class="border px-4 py-2 rounded-xl shadow-sm w-48">
            <option value="">All Roles</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= $r ?>"><?= $r ?></option>
            <?php endforeach; ?>
        </select>

        <!-- RIGHT SIDE BUTTONS -->
        <div class="ml-auto flex gap-3">
            <button id="openAddModal"
                    class="px-4 py-2 bg-blue-600 text-white rounded-xl shadow">
                Add
            </button>

            <button class="px-4 py-2 bg-green-600 text-white rounded-xl shadow"
                    id="openImportModal">
                Import
            </button>
        </div>

    </div>

    <!-- Personnel Table -->
    <div class="bg-white rounded-2xl shadow p-4 border border-gray-200 overflow-x-auto">
        <table class="w-full border-collapse" id="personnelTable">
            <thead>
                <tr class="border-b text-left text-gray-700">
                    <th class="py-2 px-2">ID</th>
                    <th class="py-2 px-2">Name</th>
                    <th class="py-2 px-2">Email</th>
                    <th class="py-2 px-2">Role</th>
                    <th class="py-2 px-2">Rate</th>
                    <th class="py-2 px-2">Created</th>
                    <th class="py-2 px-2 w-40">Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($personnel as $p): ?>
                <tr class="border-b">

                    <td class="py-2 px-2 font-medium text-gray-800">#<?= $p['id'] ?></td>

                    <td class="py-2 px-2"><?= htmlspecialchars($p['name']) ?></td>

                    <td class="py-2 px-2"><?= htmlspecialchars($p['email']) ?></td>

                    <td class="py-2 px-2"><?= htmlspecialchars($p['role']) ?></td>

                    <td class="py-2 px-2 font-semibold">
                        ₱<?= number_format($p['rate'], 2) ?>
                    </td>

                    <td class="py-2 px-2 text-gray-500 text-sm">
                        <?= date("M d, Y", strtotime($p['created_at'])) ?>
                    </td>

                    <td class="py-2 px-2 flex gap-2">

                        <button class="px-3 py-1 bg-green-600 text-white rounded-lg text-sm shadow editBtn"
                                data-id="<?= $p['id'] ?>"
                                data-name="<?= htmlspecialchars($p['name']) ?>"
                                data-email="<?= htmlspecialchars($p['email']) ?>"
                                data-role="<?= htmlspecialchars($p['role']) ?>"
                                data-rate="<?= $p['rate'] ?>">
                            Edit
                        </button>

                        <a href="delete_personnel.php?id=<?= $p['id'] ?>"
                           onclick="return confirm('Are you sure you want to delete this personnel?');"
                           class="px-3 py-1 bg-red-600 text-white rounded-lg text-sm shadow">
                            Delete
                        </a>

                    </td>

                </tr>
            <?php endforeach; ?>

            </tbody>
        </table>
    </div>

</div>

<!-- ADD MODAL -->
<div id="addModal"
     class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">

    <div class="bg-white p-6 rounded-xl shadow-lg w-96">
        <h2 class="text-lg font-semibold mb-3">Add Personnel</h2>

        <form method="POST" action="save_personnel.php">

            <input type="text" name="name"
                   placeholder="Full name"
                   class="w-full mb-3 border p-2 rounded" required>

            <input type="email" name="email"
                   placeholder="Email address"
                   class="w-full mb-3 border p-2 rounded" required>

            <select name="role" class="w-full mb-3 border p-2 rounded" required>
                <option value="">Select Role</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>"><?= $r ?></option>
                <?php endforeach; ?>
            </select>

            <input type="number" step="0.01" name="rate"
                   placeholder="Daily rate"
                   class="w-full mb-4 border p-2 rounded" required>

            <div class="flex justify-end gap-3">
                <button type="button" id="cancelAdd" class="px-4 py-2 bg-gray-300 rounded-lg">
                    Cancel
                </button>

                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg">
                    Save
                </button>
            </div>

        </form>

    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal"
     class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">

    <div class="bg-white p-6 rounded-xl shadow-lg w-96">
        <h2 class="text-lg font-semibold mb-3">Edit Personnel</h2>

        <form method="POST" action="update_personnel.php">

            <input type="hidden" name="id" id="edit_id">

            <input type="text" name="name" id="edit_name"
                   class="w-full mb-3 border p-2 rounded" required>

            <input type="email" name="email" id="edit_email"
                   class="w-full mb-3 border p-2 rounded" required>

            <select name="role" id="edit_role" class="w-full mb-3 border p-2 rounded" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>"><?= $r ?></option>
                <?php endforeach; ?>
            </select>

            <input type="number" step="0.01" name="rate" id="edit_rate"
                   class="w-full mb-4 border p-2 rounded" required>

            <div class="flex justify-end gap-3">
                <button type="button" id="cancelEdit" class="px-4 py-2 bg-gray-300 rounded-lg">
                    Cancel
                </button>

                <button class="px-4 py-2 bg-green-600 text-white rounded-lg">
                    Update
                </button>
            </div>

        </form>
    </div>
</div>

<!-- IMPORT MODAL -->
<div id="importModal"
     class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">

    <div class="bg-white p-6 rounded-xl shadow-lg w-96">
        <h2 class="text-lg font-semibold mb-3">Import Personnel via CSV</h2>

        <form method="POST" action="import_personnel.php" enctype="multipart/form-data">

            <input type="file" name="csv_file" accept=".csv"
                   class="w-full mb-4" required>

            <div class="flex justify-end gap-3">
                <button type="button" id="cancelImport" class="px-4 py-2 bg-gray-300 rounded-lg">
                    Cancel
                </button>

                <button class="px-4 py-2 bg-green-600 text-white rounded-lg">
                    Import
                </button>
            </div>

        </form>

    </div>
</div>

<script>
// OPEN ADD MODAL
document.getElementById("openAddModal").onclick = () => {
    document.getElementById("addModal").classList.remove("hidden");
    document.getElementById("addModal").classList.add("flex");
};
document.getElementById("cancelAdd").onclick = () => {
    document.getElementById("addModal").classList.add("hidden");
};

// OPEN EDIT MODAL
document.querySelectorAll(".editBtn").forEach(btn => {
    btn.onclick = () => {
        document.getElementById("edit_id").value = btn.dataset.id;
        document.getElementById("edit_name").value = btn.dataset.name;
        document.getElementById("edit_email").value = btn.dataset.email;
        document.getElementById("edit_role").value = btn.dataset.role;
        document.getElementById("edit_rate").value = btn.dataset.rate;

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
document.getElementById("searchPersonnel").addEventListener("keyup", function () {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll("#personnelTable tbody tr");

    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
});

// FILTER ROLE
document.getElementById("filterRole").addEventListener("change", function () {
    let selected = this.value;
    let rows = document.querySelectorAll("#personnelTable tbody tr");

    rows.forEach(row => {
        let role = row.children[3].innerText.trim();
        row.style.display = (selected === "" || role === selected) ? "" : "none";
    });
});
</script>

<?php
renderLayout("Personnel", ob_get_clean(), "personnel");
?>
