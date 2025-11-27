<?php
session_start();
require_once __DIR__ . '/../config.php'; // your PDO $pdo connection

// Helper: generate unique order number
function generateOrderNumber() {
    return 'ORD-' . date('YmdHis');
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect order info
    $customerName = $_POST['customer_name'] ?? '';
    $customerEmail = $_POST['customer_email'] ?? '';
    $contactNumber = $_POST['contact_number'] ?? '';
    $appointmentDate = $_POST['appointment_date'] ?? null;

    // Items array from form
    $items = $_POST['items'] ?? [];

    if (empty($customerName) || empty($items)) {
        $errors[] = "Customer name and at least one item are required.";
    } else {
        try {
            $pdo->beginTransaction();

            // Calculate totals
            $totalAmount = 0;
            foreach ($items as $item) {
                $lineTotal = floatval($item['qty']) * floatval($item['price']);
                $totalAmount += $lineTotal;
            }

            // Insert order
            $orderNumber = generateOrderNumber();
            $stmt = $pdo->prepare("
                INSERT INTO orders (customer_name, customer_email, contact_number, appointment_date, total_amount, order_number)
                VALUES (:name, :email, :contact, :appointment, :total, :order_number)
            ");
            $stmt->execute([
                ':name' => $customerName,
                ':email' => $customerEmail,
                ':contact' => $contactNumber,
                ':appointment' => $appointmentDate,
                ':total' => $totalAmount,
                ':order_number' => $orderNumber
            ]);

            $orderId = $pdo->lastInsertId();

            // Insert order items
            $stmtItem = $pdo->prepare("
                INSERT INTO order_items (order_id, item_type, item_id, qty, price, installation_type)
                VALUES (:order_id, :item_type, :item_id, :qty, :price, :installation_type)
            ");

            foreach ($items as $item) {
                $stmtItem->execute([
                    ':order_id' => $orderId,
                    ':item_type' => $item['type'],
                    ':item_id' => $item['id'],
                    ':qty' => $item['qty'],
                    ':price' => $item['price'],
                    ':installation_type' => $item['installation_type'] ?? null
                ]);
            }

            $pdo->commit();
            $success = "Order saved successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Error saving order: " . $e->getMessage();
        }
    }
}

// Fetch recent orders for table
$orders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

// Fetch products, personnel, installations for right panel selection
$products = $pdo->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);
$personnel = $pdo->query("SELECT * FROM personnel")->fetchAll(PDO::FETCH_ASSOC);
$installations = $pdo->query("SELECT * FROM split_installation")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Order</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        body { display: flex; min-height: 100vh; }
        #sidebar { width: 220px; background: #343a40; color: #fff; padding: 15px; }
        #sidebar a { color: #fff; text-decoration: none; display: block; margin: 10px 0; }
        #content { flex: 1; padding: 20px; }
        #right-panel { width: 300px; background: #f8f9fa; padding: 15px; }
    </style>
</head>
<body>

<div id="sidebar">
    <h4>Dashboard</h4>
    <a href="#">Orders</a>
    <a href="#">Products</a>
    <a href="#">Personnel</a>
</div>

<div id="content">
    <h2>Create Order</h2>

    <?php if($errors): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="post" id="orderForm">
        <div class="mb-3">
            <label>Customer Name</label>
            <input type="text" name="customer_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Customer Email</label>
            <input type="email" name="customer_email" class="form-control">
        </div>
        <div class="mb-3">
            <label>Contact Number</label>
            <input type="text" name="contact_number" class="form-control">
        </div>
        <div class="mb-3">
            <label>Appointment Date</label>
            <input type="date" name="appointment_date" class="form-control">
        </div>

        <h5>Items</h5>
        <table class="table table-bordered" id="itemsTable">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Remove</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <button type="button" class="btn btn-secondary" id="addItemBtn">Add Item</button>
        <button type="submit" class="btn btn-primary mt-3">Save Order</button>
    </form>

    <h3 class="mt-5">Recent Orders</h3>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Status</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($orders as $order): ?>
            <tr>
                <td><?= htmlspecialchars($order['order_number']) ?></td>
                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                <td><?= number_format($order['total_amount'], 2) ?></td>
                <td><?= htmlspecialchars($order['status']) ?></td>
                <td><?= $order['created_at'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="right-panel">
    <h5>Available Items</h5>
    <h6>Products</h6>
    <ul>
        <?php foreach($products as $p): ?>
            <li data-type="product" data-id="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>">
                <?= htmlspecialchars($p['name']) ?> - <?= number_format($p['price'], 2) ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <h6>Personnel</h6>
    <ul>
        <?php foreach($personnel as $p): ?>
            <li data-type="personnel" data-id="<?= $p['id'] ?>" data-price="<?= $p['rate'] ?>">
                <?= htmlspecialchars($p['name']) ?> - <?= number_format($p['rate'], 2) ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <h6>Installations</h6>
    <ul>
        <?php foreach($installations as $i): ?>
            <li data-type="installation" data-id="<?= $i['id'] ?>" data-price="<?= $i['unit_price'] ?>">
                <?= htmlspecialchars($i['item_name']) ?> - <?= number_format($i['unit_price'], 2) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<script>
    const itemsTable = document.querySelector('#itemsTable tbody');
    const addItemBtn = document.getElementById('addItemBtn');
    const rightPanelItems = document.querySelectorAll('#right-panel li');

    rightPanelItems.forEach(li => {
        li.addEventListener('click', () => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <input type="hidden" name="items[][type]" value="${li.dataset.type}">
                    ${li.dataset.type}
                </td>
                <td>
                    <input type="hidden" name="items[][id]" value="${li.dataset.id}">
                    ${li.textContent}
                </td>
                <td><input type="number" name="items[][qty]" value="1" min="1" class="form-control"></td>
                <td><input type="number" name="items[][price]" value="${li.dataset.price}" step="0.01" class="form-control"></td>
                <td><button type="button" class="btn btn-danger btn-sm removeItem">X</button></td>
            `;
            itemsTable.appendChild(row);
        });
    });

    itemsTable.addEventListener('click', e => {
        if(e.target.classList.contains('removeItem')) {
            e.target.closest('tr').remove();
        }
    });
</script>

</body>
</html>
