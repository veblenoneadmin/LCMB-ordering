<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch all orders
$stmt = $pdo->query("
    SELECT 
        o.id,
        o.customer_name,
        o.customer_email,
        o.total_amount,
        o.status,
        o.created_at
    FROM orders o
    ORDER BY o.created_at DESC
");

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Status filter options
$status_options = ['Pending', 'Approved', 'Completed'];
?>

<?php ob_start(); ?>

<div class="p-6">

    <h2 class="text-2xl font-semibold text-gray-800 mb-6">All Orders</h2>

    <!-- Search + Filter -->
    <div class="flex items-center gap-4 mb-4">

        <!-- SEARCH -->
        <input id="searchOrders"
               type="text"
               class="border px-4 py-2 rounded-xl w-80 shadow-sm"
               placeholder="Search orders...">

        <!-- FILTER STATUS -->
        <select id="filterStatus" 
                class="border px-4 py-2 rounded-xl shadow-sm w-48">
            <option value="">All Status</option>
            <?php foreach ($status_options as $status): ?>
                <option value="<?= $status ?>"><?= $status ?></option>
            <?php endforeach; ?>
        </select>

    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-2xl shadow p-4 border border-gray-200 overflow-x-auto">
        <table class="w-full border-collapse" id="ordersTable">
            <thead>
                <tr class="border-b text-left text-gray-700">
                    <th class="py-2 px-2">Order ID</th>
                    <th class="py-2 px-2">Customer</th>
                    <th class="py-2 px-2">Email</th>
                    <th class="py-2 px-2">Total</th>
                    <th class="py-2 px-2">Status</th>
                    <th class="py-2 px-2">Created</th>
                    <th class="py-2 px-2 w-40">Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($orders as $order): ?>
                <tr class="border-b hover:bg-gray-50 transition cursor-pointer order-row"
                    data-id="<?= $order['id'] ?>"
                    data-status="<?= htmlspecialchars($order['status']) ?>">

                    <td class="py-2 px-2 font-medium text-gray-800">#<?= $order['id'] ?></td>

                    <td class="py-2 px-2"><?= htmlspecialchars($order['customer_name']) ?></td>

                    <td class="py-2 px-2"><?= htmlspecialchars($order['customer_email']) ?></td>

                    <td class="py-2 px-2 font-semibold">
                        $<?= number_format($order['total_amount'], 2) ?>
                    </td>

                    <td class="py-2 px-2 font-medium text-gray-700">
                        <?= $order['status'] ?>
                    </td>

                    <td class="py-2 px-2 text-gray-500 text-sm">
                        <?= date("M d, Y", strtotime($order['created_at'])) ?>
                    </td>

                    <td class="py-2 px-2 flex gap-2">
                        <a href="review_order.php?order_id=<?= $order['id'] ?>"
                           class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm shadow">
                            View
                        </a>

                        <a href="edit_order.php?order_id=<?= $order['id'] ?>"
                           class="px-3 py-1 bg-green-600 text-white rounded-lg text-sm shadow">
                            Edit
                        </a>

                        <a href="delete_order.php?order_id=<?= $order['id'] ?>"
                           onclick="return confirm('Are you sure you want to delete this order?');"
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

<!-- APPROVAL MODAL -->
<div id="approveModal"
     class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">

    <div class="bg-white p-6 rounded-xl shadow-lg w-80">
        <h2 class="text-lg font-semibold mb-3">Approve Order?</h2>

        <p class="text-gray-600 mb-4">Do you want to approve this order?</p>

        <div class="flex justify-end gap-3">
            <button id="cancelApprove"
                    class="px-4 py-2 bg-gray-300 rounded-lg">
                Cancel
            </button>

            <button id="confirmApprove"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg">
                Approve
            </button>
        </div>
    </div>
</div>

<script>
let selectedOrderId = null;

// DOUBLE CLICK → OPEN MODAL
document.querySelectorAll(".order-row").forEach(row => {
    row.addEventListener("dblclick", function () {
        selectedOrderId = this.dataset.id;

        const modal = document.getElementById("approveModal");
        modal.classList.remove("hidden");
        modal.classList.add("flex");
    });
});

// CANCEL BUTTON
document.getElementById("cancelApprove").addEventListener("click", () => {
    const modal = document.getElementById("approveModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
});

// APPROVE BUTTON → AJAX CALL
document.getElementById("confirmApprove").addEventListener("click", () => {

    fetch("update_status.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: `order_id=${selectedOrderId}&status=Approved`
    })
    .then(res => location.reload());
});

// SEARCH FUNCTION
document.getElementById("searchOrders").addEventListener("keyup", function () {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll("#ordersTable tbody tr");

    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? "table-row" : "none";
    });
});

// STATUS FILTER
document.getElementById("filterStatus").addEventListener("change", function () {
    let selected = this.value.toLowerCase();
    let rows = document.querySelectorAll("#ordersTable tbody tr");

    rows.forEach(row => {
        let status = row.getAttribute("data-status").toLowerCase();
        row.style.display = (selected === "" || status === selected) ? "table-row" : "none";
    });
});
</script>

<?php
renderLayout("All Orders", ob_get_clean(), "orders");
?>
