<?php
// create_order.php

require_once __DIR__ . '/../config.php'; // adjust path if needed
require_once __DIR__ . '/layout.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Helper function to fetch table data safely
function fetchAllSafe(PDO $pdo, string $query): array {
    try {
        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
        return [];
    }
}

// Load selectable items
$products = fetchAllSafe($pdo, "SELECT id, name, price FROM products ORDER BY name ASC");
$split_installations = fetchAllSafe($pdo, "SELECT id, item_name AS name, unit_price AS price FROM split_installations ORDER BY item_name ASC");
$ducted_installations = fetchAllSafe($pdo, "SELECT id, equipment_name AS name, model_name_indoor, model_name_outdoor, total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC");
$personnel = fetchAllSafe($pdo, "SELECT id, name, rate FROM personnel ORDER BY name ASC");
$equipment = fetchAllSafe($pdo, "SELECT id, item AS name, rate FROM equipment ORDER BY item ASC");

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Insert into orders
        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_email, contact_number, appointment_date, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $_POST['customer_name'] ?? '',
            $_POST['customer_email'] ?? '',
            $_POST['contact_number'] ?? '',
            $_POST['appointment_date'] ?? date('Y-m-d')
        ]);

        $order_id = $pdo->lastInsertId();

        // Helper to insert into order_items
        $insertItem = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, installation_type, qty, price) VALUES (?, ?, ?, ?, ?, ?)");

        // Products
        foreach ($_POST['quantity'] ?? [] as $pid => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) {
                $price = 0;
                foreach ($products as $p) { if ($p['id'] == $pid) { $price = $p['price']; break; } }
                $insertItem->execute([$order_id, 'product', $pid, null, $qty, $price]);
            }
        }

        // Split Installations
        foreach ($_POST['split'] ?? [] as $sid => $info) {
            $qty = (int)($info['qty'] ?? 0);
            if ($qty > 0) {
                $price = 0;
                foreach ($split_installations as $s) { if ($s['id'] == $sid) { $price = $s['price']; break; } }
                $insertItem->execute([$order_id, 'installation', $sid, null, $qty, $price]);
            }
        }

        // Ducted Installations
        foreach ($_POST['ducted'] ?? [] as $did => $info) {
            $qty = (int)($info['qty'] ?? 0);
            if ($qty > 0) {
                $installation_type = $info['installation_type'] ?? null;
                $price = 0;
                foreach ($ducted_installations as $d) { if ($d['id'] == $did) { $price = $d['price']; break; } }
                $insertItem->execute([$order_id, 'installation', $did, $installation_type, $qty, $price]);
            }
        }

        // Personnel
        foreach ($_POST['personnel_hours'] ?? [] as $pid => $hours) {
            $hours = (float)$hours;
            if ($hours > 0) {
                $rate = 0;
                foreach ($personnel as $p) { if ($p['id'] == $pid) { $rate = $p['rate']; break; } }
                $insertItem->execute([$order_id, 'personnel', $pid, null, $hours, $rate]);
            }
        }

        // Equipment
        foreach ($_POST['equipment_qty'] ?? [] as $eid => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) {
                $rate = 0;
                foreach ($equipment as $e) { if ($e['id'] == $eid) { $rate = $e['rate']; break; } }
                $insertItem->execute([$order_id, 'product', $eid, null, $qty, $rate]);
            }
        }

        // Other Expenses
        foreach ($_POST['other_expenses'] ?? [] as $exp) {
            $name = trim($exp['name'] ?? 'Other');
            $amount = (float)($exp['amount'] ?? 0);
            if ($amount > 0) {
                $insertItem->execute([$order_id, 'product', 0, $name, 1, $amount]);
            }
        }

        $pdo->commit();

        // Redirect to review page
        header("Location: review_order.php?id=$order_id");
        exit;

    } catch (\Exception $e) {
        $pdo->rollBack();
        $message = "Error saving order: " . $e->getMessage();
    }
}

// Render layout
ob_start();
?>
<?php if ($message): ?>
    <div style="padding:10px;background:#fee;border:1px solid #f00;margin:10px 0;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" id="orderForm">
    <h3>Client Info</h3>
    <input type="text" name="customer_name" placeholder="Name" required><br>
    <input type="email" name="customer_email" placeholder="Email"><br>
    <input type="text" name="contact_number" placeholder="Phone"><br>
    <input type="date" name="appointment_date" value="<?= date('Y-m-d') ?>"><br><br>

    <h3>Products</h3>
    <?php foreach ($products as $p): ?>
        <label><?= htmlspecialchars($p['name']) ?> ($<?= number_format($p['price'],2) ?>)</label>
        <input type="number" min="0" name="quantity[<?= $p['id'] ?>]" value="0"><br>
    <?php endforeach; ?>

    <h3>Split Installations</h3>
    <?php foreach ($split_installations as $s): ?>
        <label><?= htmlspecialchars($s['name']) ?> ($<?= number_format($s['price'],2) ?>)</label>
        <input type="number" min="0" name="split[<?= $s['id'] ?>][qty]" value="0"><br>
    <?php endforeach; ?>

    <h3>Ducted Installations</h3>
    <?php foreach ($ducted_installations as $d): ?>
        <label><?= htmlspecialchars($d['name']) ?> ($<?= number_format($d['price'],2) ?>)</label>
        <select name="ducted[<?= $d['id'] ?>][installation_type]">
            <option value="indoor">Indoor</option>
            <option value="outdoor">Outdoor</option>
        </select>
        <input type="number" min="0" name="ducted[<?= $d['id'] ?>][qty]" value="0"><br>
    <?php endforeach; ?>

    <h3>Personnel</h3>
    <?php foreach ($personnel as $p): ?>
        <label><?= htmlspecialchars($p['name']) ?> ($<?= number_format($p['rate'],2) ?>/hr)</label>
        <input type="number" min="0" step="0.1" name="personnel_hours[<?= $p['id'] ?>]" value="0"><br>
    <?php endforeach; ?>

    <h3>Equipment</h3>
    <?php foreach ($equipment as $e): ?>
        <label><?= htmlspecialchars($e['name']) ?> ($<?= number_format($e['rate'],2) ?>)</label>
        <input type="number" min="0" name="equipment_qty[<?= $e['id'] ?>]" value="0"><br>
    <?php endforeach; ?>

    <h3>Other Expenses</h3>
    <div id="otherExpensesContainer"></div>
    <button type="button" id="addExpenseBtn">Add Expense</button><br><br>

    <button type="submit">Save Order</button>
</form>

<script>
document.getElementById('addExpenseBtn').addEventListener('click', function(){
    const container = document.getElementById('otherExpensesContainer');
    const row = document.createElement('div');
    row.innerHTML = '<input type="text" name="other_expenses[][name]" placeholder="Expense Name"> ' +
                    '<input type="number" step="0.01" min="0" name="other_expenses[][amount]" placeholder="Amount">';
    container.appendChild(row);
});
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
