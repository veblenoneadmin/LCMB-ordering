<?php
session_start();
require_once __DIR__ . '/../config.php'; // Database connection

// Generate a unique order number
function generateOrderNumber($pdo) {
    do {
        $number = 'ORD-' . rand(1000, 9999);
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ?");
        $stmt->execute([$number]);
    } while ($stmt->rowCount() > 0);
    return $number;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = $_POST['customer_name'] ?? '';
    $customer_email = $_POST['customer_email'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';

    $order_number = generateOrderNumber($pdo);
    $total_amount = 0;

    // Begin transaction
    $pdo->beginTransaction();
    try {
        // Insert order
        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_email, contact_number, appointment_date, order_number, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$customer_name, $customer_email, $contact_number, $appointment_date, $order_number, 0]);
        $order_id = $pdo->lastInsertId();

        // Insert selected products
        if (!empty($_POST['products'])) {
            foreach ($_POST['products'] as $product_id => $qty) {
                $stmtProd = $pdo->prepare("SELECT price FROM products WHERE id = ?");
                $stmtProd->execute([$product_id]);
                $price = $stmtProd->fetchColumn();
                $line_total = $price * $qty;
                $total_amount += $line_total;

                $stmtInsert = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, qty, price) VALUES (?, 'product', ?, ?, ?)");
                $stmtInsert->execute([$order_id, $product_id, $qty, $price]);
            }
        }

        // Insert selected personnel
        if (!empty($_POST['personnel'])) {
            foreach ($_POST['personnel'] as $person_id => $qty) {
                $stmtPers = $pdo->prepare("SELECT rate FROM personnel WHERE id = ?");
                $stmtPers->execute([$person_id]);
                $price = $stmtPers->fetchColumn();
                $line_total = $price * $qty;
                $total_amount += $line_total;

                $stmtInsert = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, qty, price) VALUES (?, 'personnel', ?, ?, ?)");
                $stmtInsert->execute([$order_id, $person_id, $qty, $price]);
            }
        }

        // Insert selected split installations
        if (!empty($_POST['split_installation'])) {
            foreach ($_POST['split_installation'] as $install_id => $qty) {
                $stmtInst = $pdo->prepare("SELECT unit_price FROM split_installation WHERE id = ?");
                $stmtInst->execute([$install_id]);
                $price = $stmtInst->fetchColumn();
                $line_total = $price * $qty;
                $total_amount += $line_total;

                $stmtInsert = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, qty, price) VALUES (?, 'installation', ?, ?, ?)");
                $stmtInsert->execute([$order_id, $install_id, $qty, $price]);
            }
        }

        // Insert selected ducted installations
        if (!empty($_POST['ductedinstallations'])) {
            foreach ($_POST['ductedinstallations'] as $duct_id => $qty) {
                $stmtDuct = $pdo->prepare("SELECT total_cost FROM ductedinstallations WHERE id = ?");
                $stmtDuct->execute([$duct_id]);
                $price = $stmtDuct->fetchColumn();
                $line_total = $price * $qty;
                $total_amount += $line_total;

                $stmtInsert = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, qty, price) VALUES (?, 'installation', ?, ?, ?)");
                $stmtInsert->execute([$order_id, $duct_id, $qty, $price]);
            }
        }

        // Insert selected equipment
        if (!empty($_POST['equipment'])) {
            foreach ($_POST['equipment'] as $equip_id => $qty) {
                $stmtEquip = $pdo->prepare("SELECT rate FROM equipment WHERE id = ?");
                $stmtEquip->execute([$equip_id]);
                $price = $stmtEquip->fetchColumn();
                $line_total = $price * $qty;
                $total_amount += $line_total;

                $stmtInsert = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, qty, price) VALUES (?, 'personnel', ?, ?, ?)");
                $stmtInsert->execute([$order_id, $equip_id, $qty, $price]);
            }
        }

        // Update order with total_amount
        $stmtUpdate = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
        $stmtUpdate->execute([$total_amount, $order_id]);

        $pdo->commit();
        $success = "Order created successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving order: " . $e->getMessage();
    }
}

// Fetch tables for display
$products = $pdo->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);
$personnel = $pdo->query("SELECT * FROM personnel")->fetchAll(PDO::FETCH_ASSOC);
$split_installation = $pdo->query("SELECT * FROM split_installation")->fetchAll(PDO::FETCH_ASSOC);
$ductedinstallations = $pdo->query("SELECT * FROM ductedinstallations")->fetchAll(PDO::FETCH_ASSOC);
$equipment = $pdo->query("SELECT * FROM equipment")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Order</title>
    <link href="assets/css/material-dashboard.css" rel="stylesheet">
</head>
<body class="g-sidenav-show bg-gray-200">
<?php include 'sidebar.php'; ?>
<main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
<?php include 'topbar.php'; ?>

<div class="container-fluid py-4">
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Customer Information</h6>
                    </div>
                    <div class="card-body">
                        <input class="form-control mb-2" name="customer_name" placeholder="Customer Name" required>
                        <input class="form-control mb-2" name="customer_email" placeholder="Customer Email">
                        <input class="form-control mb-2" name="contact_number" placeholder="Contact Number">
                        <input type="date" class="form-control mb-2" name="appointment_date">
                    </div>
                </div>

                <!-- Products Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Products</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr><th>Select</th><th>Name</th><th>Price</th><th>Qty</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td><input type="checkbox" name="products[<?= $p['id'] ?>]"></td>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td><?= number_format($p['price'],2) ?></td>
                                        <td><input type="number" name="products[<?= $p['id'] ?>]" value="1" min="1" class="form-control"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Repeat similar tables for personnel, split_installation, ductedinstallations, equipment -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6>Personnel</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead><tr><th>Select</th><th>Name</th><th>Role</th><th>Rate</th><th>Qty</th></tr></thead>
                            <tbody>
                                <?php foreach ($personnel as $per): ?>
                                    <tr>
                                        <td><input type="checkbox" name="personnel[<?= $per['id'] ?>]"></td>
                                        <td><?= htmlspecialchars($per['name']) ?></td>
                                        <td><?= htmlspecialchars($per['role']) ?></td>
                                        <td><?= number_format($per['rate'],2) ?></td>
                                        <td><input type="number" name="personnel[<?= $per['id'] ?>]" value="1" min="1" class="form-control"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h6>Split Installation</h6></div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead><tr><th>Select</th><th>Item</th><th>Unit Price</th><th>Qty</th></tr></thead>
                            <tbody>
                                <?php foreach ($split_installation as $si): ?>
                                    <tr>
                                        <td><input type="checkbox" name="split_installation[<?= $si['id'] ?>]"></td>
                                        <td><?= htmlspecialchars($si['item_name']) ?></td>
                                        <td><?= number_format($si['unit_price'],2) ?></td>
                                        <td><input type="number" name="split_installation[<?= $si['id'] ?>]" value="1" min="1" class="form-control"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h6>Ducted Installations</h6></div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead><tr><th>Select</th><th>Equipment</th><th>Total Cost</th><th>Qty</th></tr></thead>
                            <tbody>
                                <?php foreach ($ductedinstallations as $di): ?>
                                    <tr>
                                        <td><input type="checkbox" name="ductedinstallations[<?= $di['id'] ?>]"></td>
                                        <td><?= htmlspecialchars($di['equipment_name']) ?></td>
                                        <td><?= number_format($di['total_cost'],2) ?></td>
                                        <td><input type="number" name="ductedinstallations[<?= $di['id'] ?>]" value="1" min="1" class="form-control"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h6>Equipment</h6></div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead><tr><th>Select</th><th>Item</th><th>Rate</th><th>Qty</th></tr></thead>
                            <tbody>
                                <?php foreach ($equipment as $eq): ?>
                                    <tr>
                                        <td><input type="checkbox" name="equipment[<?= $eq['id'] ?>]"></td>
                                        <td><?= htmlspecialchars($eq['item']) ?></td>
                                        <td><?= number_format($eq['rate'],2) ?></td>
                                        <td><input type="number" name="equipment[<?= $eq['id'] ?>]" value="1" min="1" class="form-control"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <button class="btn btn-primary mb-4" type="submit">Create Order</button>
            </div>

            <!-- RIGHT PANEL (SUMMARY) -->
  <aside class="create-order-right">
    <div class="card card-summary">
      <h4 class="card-title">Order Summary</h4>
      <div class="summary-list" id="orderSummary"><div class="empty-note">No items selected.</div></div>
      <div class="summary-totals">
        <div class="flex justify-between"><span>Subtotal:</span><span>$<span id="subtotalDisplay">0.00</span></span></div>
        <div class="flex justify-between"><span>Tax (10%):</span><span>$<span id="taxDisplay">0.00</span></span></div>
        <div class="flex justify-between border-t"><strong>Grand Total:</strong><strong>$<span id="grandDisplay">0.00</span></strong></div>
      </div>
      <button type="submit" class="input">Save Order</button>
    </div>
  </aside>
    </form>
</div>
</main>
<?php include 'footer.php'; ?>
<script src="assets/js/material-dashboard.min.js"></script>
</body>
</html>
