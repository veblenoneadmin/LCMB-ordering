<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch orders
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

// Status list for filtering
$status_options = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
?>

<?php ob_start(); ?>

<style>
/* Popup backdrop */
#approvePopup {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.55);
    justify-content: center;
    align-items: center;
    z-index: 2000;
}
#approvePopup .popup-box {
    background: white;
    padding: 25px;
    border-radius: 16px;
    width: 350px;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}
</style>

<div class="p-6">

    <h2 class="text-2xl font-semibold text-gray-800 mb-6">All Orders</h2>

    <!-- Search + Filter -->
    <div class="flex items-center gap-4 mb-4">
        <input 
            id="searchOrders" 
            class="border px-4 py-2 rounded-xl w-80 shadow-sm"
            placeholder="Search orders..."
        >

        <select id="filterStatus" class="border px-4 py-2 rounded-xl shadow-sm w-48">
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
                <tr 
                    class="border-b hover:bg-gray-50 transition cursor-pointer order-row"
                    data-id="<?= $order['id'] ?>"
                    data-status="<?= $order['status'] ?>"
                >
                    <td class="py-2 px-2 font-medium text-gray-800">#<?= $order['id'] ?></td>
                    <td class="py-2 px-2"><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td class="py-2 px-2"><?= htmlspecialchars($order['customer_email']) ?></td>
                    <td class="py-2 px-2 font-semibold">$<?= number_format($order['total_amount'], 2) ?></td>
                    <td class="py-2 px-2"><?= $order['status'] ?></td>
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

<!-- Pop-up Approve Modal -->
<div id="approvePopup">
    <div class="popup-box">
        <h3 class="text-lg font-semibold mb-4">Approve Order?</h3>

        <div class="flex justify-center gap-4">
            <button id="cancelApprove" 
                    class="px-4 py-2 bg-gray-300 rounded-lg shadow">
                Cancel
            </button>

            <a id="approveBtn" 
               class="px-4 py-2 bg-green-600 text-white rounded-lg shadow">
               Approve
            </a>
        </div>
    </div>
</div>

<script>
let popup = document.getElementById("approvePopup");
let approveBtn = document.getElementById("approveBtn");
let cancelBtn = document.getElementById("cancelApprove");

// DOUBLE CLICK ROW = OPEN POPUP
document.querySelectorAll(".order-row").forEach(row => {
    row.addEventListener("dblclick", function() {
        let id = this.dataset.id;
        approveBtn.href = "approve_order.php?order_id=" + id;
        popup.style.display = "flex";
    });
});

// CLOSE POPUP
cancelBtn.onclick = () => popup.style.display = "none";

// SEARCH
document.getElementById("searchOrders").addEventListener("keyup", function () {
    let filter = this.value.toLowerCase();
    document.querySelectorAll("#ordersTable tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
});

// STATUS FILTER
document.getElementById("filterStatus").addEventListener("change", function () {
    let selected = this.value;
    document.querySelectorAll("#ordersTable tbody tr").forEach(row => {
        let status = row.getAttribute("data-status");
        row.style.display = (selected === "" || status === selected) ? "" : "none";
    });
});
</script>

<?php
renderLayout("All Orders", ob_get_clean(), "orders");
?>
