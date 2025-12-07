<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch all orders
$query = $pdo->query("
    SELECT 
        o.id,
        o.customer_name,
        o.customer_email,
        o.job_address,
        o.total_amount,
        o.status,
        o.created_at
    FROM orders o
    ORDER BY o.created_at DESC
");

$orders = $query->fetchAll(PDO::FETCH_ASSOC);

// Status options
$status_options = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
?>

<?php ob_start(); ?>

<div class="p-6">

    <h2 class="text-2xl font-semibold text-gray-800 mb-6">All Orders</h2>

    <!-- SEARCH BAR -->
    <div class="mb-4 flex justify-between items-center">
        <input id="searchOrders" type="text" 
               class="border px-4 py-2 rounded-xl w-72 shadow-sm" 
               placeholder="Search orders...">
    </div>

    <!-- ORDERS TABLE -->
    <div class="bg-white rounded-2xl shadow p-4 border border-gray-200">
        <table class="w-full border-collapse" id="ordersTable">
            <thead>
                <tr class="border-b text-left text-gray-700">
                    <th class="py-2 px-2">Order ID</th>
                    <th class="py-2 px-2">Customer</th>
                    <th class="py-2 px-2">Email</th>
                    <th class="py-2 px-2">Address</th>
                    <th class="py-2 px-2">Total</th>
                    <th class="py-2 px-2">Status</th>
                    <th class="py-2 px-2">Created</th>
                    <th class="py-2 px-2 w-40">Actions</th>
                </tr>
            </thead>
            <tbody>

            <?php foreach ($orders as $order): ?>
                <tr class="border-b hover:bg-gray-50 transition">

                    <td class="py-2 px-2 font-medium text-gray-800">
                        #<?= $order['id'] ?>
                    </td>

                    <td class="py-2 px-2"><?= htmlspecialchars($order['customer_name']) ?></td>

                    <td class="py-2 px-2"><?= htmlspecialchars($order['customer_email']) ?></td>

                    <td class="py-2 px-2"><?= htmlspecialchars($order['job_address']) ?></td>

                    <td class="py-2 px-2 font-semibold">
                        $<?= number_format($order['total_amount'], 2) ?>
                    </td>

                    <td class="py-2 px-2">
                        <form method="post" action="update_status.php">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">

                            <select name="status" class="border rounded-lg px-2 py-1 text-sm"
                                    onchange="this.form.submit()">
                                <?php foreach ($status_options as $status): ?>
                                    <option value="<?= $status ?>"
                                        <?= $status == $order['status'] ? 'selected' : '' ?>>
                                        <?= $status ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>

                    <td class="py-2 px-2 text-gray-500 text-sm">
                        <?= date("M d, Y", strtotime($order['created_at'])) ?>
                    </td>

                    <td class="py-2 px-2 space-x-2">
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

<script>
// Live Search
document.getElementById("searchOrders").addEventListener("keyup", function () {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll("#ordersTable tbody tr");

    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
});
</script>

<?php
renderLayout(ob_get_clean(), "All Orders");

?>
