<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// FETCH ORDERS
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$query = "SELECT * FROM orders WHERE 1";
$params = [];

if ($search !== '') {
    $query .= " AND (customer_name LIKE ? OR customer_email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter !== '') {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="p-6">

    <h1 class="text-2xl font-semibold mb-4">Orders</h1>

    <!-- SEARCH + FILTER -->
    <form class="mb-4 flex gap-3">
        <input 
            type="text" 
            name="search" 
            placeholder="Search name or email..." 
            value="<?= htmlspecialchars($search) ?>"
            class="p-2 border rounded-lg w-64"
        >

        <select name="status" class="p-2 border rounded-lg">
            <option value="">All Status</option>
            <option value="Pending"   <?= $statusFilter=="Pending"?"selected":"" ?>>Pending</option>
            <option value="Approved"  <?= $statusFilter=="Approved"?"selected":"" ?>>Approved</option>
            <option value="Completed" <?= $statusFilter=="Completed"?"selected":"" ?>>Completed</option>
        </select>

        <button class="bg-blue-600 text-white px-4 rounded-lg">Apply</button>
    </form>

    <!-- ORDERS TABLE -->
    <div class="overflow-auto border rounded-xl">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="p-3 text-left">ID</th>
                    <th class="p-3 text-left">Customer</th>
                    <th class="p-3 text-left">Email</th>
                    <th class="p-3 text-left">Amount</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr 
                    class="border-b hover:bg-gray-50 cursor-pointer order-row"
                    data-id="<?= $o['id'] ?>"
                >
                    <td class="p-3"><?= $o['id'] ?></td>
                    <td class="p-3"><?= htmlspecialchars($o['customer_name']) ?></td>
                    <td class="p-3"><?= htmlspecialchars($o['customer_email']) ?></td>
                    <td class="p-3">₱<?= number_format($o['total_amount'], 2) ?></td>
                    <td class="p-3 font-semibold"><?= htmlspecialchars($o['status']) ?></td>

                    <td class="p-3 flex gap-2">
                        <a href="edit_order.php?id=<?= $o['id'] ?>" 
                           onclick="event.stopPropagation();"
                           class="bg-green-600 text-white px-3 py-1 rounded-lg">
                            Edit
                        </a>

                        <a href="delete_order.php?id=<?= $o['id'] ?>" 
                           onclick="event.stopPropagation(); return confirm('Delete order?');"
                           class="bg-red-600 text-white px-3 py-1 rounded-lg">
                            Delete
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>

        </table>
    </div>
</div>

<!-- APPROVE POPUP -->
<div id="approvePopup" class="fixed inset-0 hidden bg-black bg-opacity-50 items-center justify-center">
    <div class="bg-white p-6 rounded-xl shadow-lg w-80 text-center">
        <h3 class="text-lg font-semibold mb-4">Approve this order?</h3>

        <div class="flex justify-center gap-3 mt-4">
            <button id="approveBtn" class="bg-green-600 text-white px-4 py-2 rounded-lg">
                Approve
            </button>
            <button id="cancelBtn" class="bg-gray-300 px-4 py-2 rounded-lg">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
let selectedOrderId = null;

// DOUBLE CLICK TO OPEN POPUP
document.querySelectorAll(".order-row").forEach(row => {
    row.addEventListener("dblclick", () => {
        selectedOrderId = row.dataset.id;
        document.getElementById("approvePopup").classList.remove("hidden");
    });
});

// CLOSE POPUP
document.getElementById("cancelBtn").onclick = () => {
    document.getElementById("approvePopup").classList.add("hidden");
};

// APPROVE ORDER
document.getElementById("approveBtn").onclick = () => {
    fetch("update_status.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({
            order_id: selectedOrderId,
            new_status: "Approved"
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert("Failed to approve order.");
        }
    });
};
</script>

<?php
$content = ob_get_clean();
renderLayout($content, "Orders");
