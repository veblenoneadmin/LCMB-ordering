<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch all personnel
$stmt = $pdo->query("
    SELECT id, name, email, role, rate, category
    FROM personnel
    ORDER BY id DESC
");
$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Role filter options
$roles = ['Technician', 'Installer', 'Assistant'];
?>

<?php ob_start(); ?>

<div class="p-6">

    <!-- Search + Filter + Buttons -->
    <div class="flex items-center gap-4 mb-4 justify-between">

        <div class="flex items-center gap-4">
            <input id="searchPersonnel" type="text"
                   class="border px-4 py-2 rounded-xl w-80 shadow-sm"
                   placeholder="Search personnel...">

            <select id="filterRole" class="border px-4 py-2 rounded-xl shadow-sm w-48">
                <option value="">All Roles</option>
                <?php foreach($roles as $r): ?>
                    <option value="<?= $r ?>"><?= $r ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex gap-2">
            <button id="openAddModal" class="px-4 py-2 bg-blue-600 text-white rounded-lg shadow">Add</button>
            <button id="openImportModal" class="px-4 py-2 bg-gray-600 text-white rounded-lg shadow">Import</button>
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
                    <th class="py-2 px-2">Category</th>
                    <th class="py-2 px-2 w-40">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($personnel as $p): ?>
                <tr class="border-b hover:bg-gray-50 transition" data-id="<?= $p['id'] ?>">
                    <td class="py-2 px-2 font-medium text-gray-800"><?= $p['id'] ?></td>
                    <td class="py-2 px-2"><?= htmlspecialchars($p['name']) ?></td>
                    <td class="py-2 px-2"><?= htmlspecialchars($p['email']) ?></td>
                    <td class="py-2 px-2"><?= htmlspecialchars($p['role']) ?></td>
                    <td class="py-2 px-2 font-semibold">₱<?= number_format($p['rate'],2) ?></td>
                    <td class="py-2 px-2"><?= htmlspecialchars($p['category']) ?></td>
                    <td class="py-2 px-2 flex gap-2">
                        <button class="edit-btn px-3 py-1 bg-green-600 text-white rounded-lg text-sm shadow">Edit</button>
                        <button class="delete-btn px-3 py-1 bg-red-600 text-white rounded-lg text-sm shadow">Delete</button>
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
        <h2 class="text-lg font-semibold mb-3">Add Personnel</h2>
        <form id="addPersonnelForm">
            <input type="text" name="name" placeholder="Full name" class="w-full mb-2 border p-2 rounded" required>
            <input type="email" name="email" placeholder="Email" class="w-full mb-2 border p-2 rounded" required>
            <select name="role" class="w-full mb-2 border p-2 rounded" required>
                <option value="">Select Role</option>
                <?php foreach($roles as $r): ?>
                    <option value="<?= $r ?>"><?= $r ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" step="0.01" name="rate" placeholder="Daily rate" class="w-full mb-2 border p-2 rounded" required>
            <input type="text" name="category" placeholder="Category" class="w-full mb-4 border p-2 rounded" required>
            <div class="flex justify-end gap-2">
                <button type="button" id="cancelAdd" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">
    <div class="bg-white p-6 rounded-xl shadow-lg w-96">
        <h2 class="text-lg font-semibold mb-3">Edit Personnel</h2>
        <form id="editPersonnelForm">
            <input type="hidden" name="id">
            <input type="text" name="name" placeholder="Full name" class="w-full mb-2 border p-2 rounded" required>
            <input type="email" name="email" placeholder="Email" class="w-full mb-2 border p-2 rounded" required>
            <select name="role" class="w-full mb-2 border p-2 rounded" required>
                <option value="">Select Role</option>
                <?php foreach($roles as $r): ?>
                    <option value="<?= $r ?>"><?= $r ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" step="0.01" name="rate" placeholder="Daily rate" class="w-full mb-2 border p-2 rounded" required>
            <input type="text" name="category" placeholder="Category" class="w-full mb-4 border p-2 rounded" required>
            <div class="flex justify-end gap-2">
                <button type="button" id="cancelEdit" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- IMPORT MODAL -->
<div id="importModal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">
    <div class="bg-white p-6 rounded-xl shadow-lg w-96">
        <h2 class="text-lg font-semibold mb-3">Import Personnel via CSV</h2>
        <form id="importForm" enctype="multipart/form-data" method="post" action="partials/import_personnel.php">
            <input type="file" name="csv_file" accept=".csv" class="w-full mb-4" required>
            <div class="flex justify-end gap-2">
                <button type="button" id="cancelImport" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg">Import</button>
            </div>
        </form>
    </div>
</div>

<script>
// Open/Close Add Modal
document.getElementById("openAddModal").onclick = () => { document.getElementById("addModal").classList.remove("hidden"); document.getElementById("addModal").classList.add("flex"); }
document.getElementById("cancelAdd").onclick = () => { document.getElementById("addModal").classList.add("hidden"); }

// Open/Close Import Modal
document.getElementById("openImportModal").onclick = () => { document.getElementById("importModal").classList.remove("hidden"); document.getElementById("importModal").classList.add("flex"); }
document.getElementById("cancelImport").onclick = () => { document.getElementById("importModal").classList.add("hidden"); }

// Add Personnel AJAX
document.getElementById("addPersonnelForm").addEventListener("submit", e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    fetch("partials/add_personnel.php", { method:"POST", body:formData })
        .then(res => res.json())
        .then(data => { if(data.success) location.reload(); else alert(data.message); });
});

// Edit button click
document.querySelectorAll(".edit-btn").forEach(btn => {
    btn.onclick = () => {
        const row = btn.closest("tr");
        const form = document.getElementById("editPersonnelForm");
        form.id.value = row.dataset.id;
        form.name.value = row.children[1].innerText;
        form.email.value = row.children[2].innerText;
        form.role.value = row.children[3].innerText;
        form.rate.value = parseFloat(row.children[4].innerText.replace('₱',''));
        form.category.value = row.children[5].innerText;
        document.getElementById("editModal").classList.remove("hidden");
        document.getElementById("editModal").classList.add("flex");
    };
});

// Update Personnel AJAX
document.getElementById("editPersonnelForm").addEventListener("submit", e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    fetch("partials/update_personnel.php",{ method:"POST", body:formData })
        .then(res=>res.json())
        .then(data=>{ if(data.success) location.reload(); else alert(data.message); });
});

// Cancel Edit Modal
document.getElementById("cancelEdit").onclick = () => { document.getElementById("editModal").classList.add("hidden"); }

// Delete Personnel AJAX
document.querySelectorAll(".delete-btn").forEach(btn=>{
    btn.onclick = () => {
        const id = btn.closest("tr").dataset.id;
        if(confirm("Are you sure you want to delete this personnel?")){
            fetch("partials/delete_personnel.php",{
                method:"POST",
                headers:{"Content-Type":"application/x-www-form-urlencoded"},
                body:`id=${id}`
            }).then(()=>location.reload());
        }
    }
});

// Live Search
document.getElementById("searchPersonnel").addEventListener("keyup", function(){
    const filter = this.value.toLowerCase();
    document.querySelectorAll("#personnelTable tbody tr").forEach(row=>{
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "table-row" : "none";
    });
});

// Filter by Role
document.getElementById("filterRole").addEventListener("change", function(){
    const val = this.value;
    document.querySelectorAll("#personnelTable tbody tr").forEach(row=>{
        const role = row.children[3].innerText;
        row.style.display = (val==="" || role===val) ? "table-row" : "none";
    });
});
</script>

<?php
renderLayout("Personnel", ob_get_clean(), "personnel");
?>
