<?php
// create_order.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Check DB connection
if (!isset($pdo) || !$pdo instanceof PDO) {
    ob_start();
    echo '<div style="padding:20px; background:#fee; border:1px solid #fbb; border-radius:8px;">';
    echo '<h3>Database connection missing</h3>';
    echo '<p>Please make sure <code>$pdo</code> is created in config.php</p>';
    echo '</div>';
    $content = ob_get_clean();
    renderLayout('Create Order', $content, 'create_order');
    exit;
}

// Load data safely
try {
    $products = $pdo->query("SELECT id, name, price FROM `products` ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $products = []; }

try {
    $split_installations = $pdo->query("SELECT id, item_name AS name, unit_price AS price FROM `split installation` ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $split_installations = []; }

try {
    $ducted_installations = $pdo->query("SELECT id, equipment_name AS name, model_name_indoor, model_name_outdoor, total_cost AS price FROM `ductedinstallations` ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $ducted_installations = []; }

try {
    $personnel = $pdo->query("SELECT id, name, rate FROM `personnel` ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $personnel = []; }

try {
    $equipment = $pdo->query("SELECT id, item AS name, rate FROM `equipment` ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $equipment = []; }

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Insert order
        $stmt = $pdo->prepare("INSERT INTO `orders` (customer_name, customer_email, contact_number, appointment_date, created_at) VALUES (?,?,?,?,NOW())");
        $stmt->execute([
            $_POST['customer_name'] ?? '',
            $_POST['customer_email'] ?? '',
            $_POST['contact_number'] ?? '',
            $_POST['appointment_date'] ?? date('Y-m-d')
        ]);
        $order_id = $pdo->lastInsertId();

        // Insert order_items - products
        if (!empty($_POST['quantity'])) {
            $stmt = $pdo->prepare("INSERT INTO `order_items` (order_id, item_type, item_id, qty, price) VALUES (?,?,?,?,?)");
            foreach ($_POST['quantity'] as $pid => $qty) {
                $qty = (int)$qty;
                if ($qty <= 0) continue;
                $product = $pdo->query("SELECT price FROM `products` WHERE id=" . (int)$pid)->fetch(PDO::FETCH_ASSOC);
                $price = $product['price'] ?? 0;
                $stmt->execute([$order_id,'product',$pid,$qty,$price]);
            }
        }

        // Split installations
        if (!empty($_POST['split'])) {
            $stmt = $pdo->prepare("INSERT INTO `order_items` (order_id, item_type, item_id, qty, price) VALUES (?,?,?,?,?)");
            foreach ($_POST['split'] as $sid => $data) {
                $qty = (int)($data['qty'] ?? 0);
                if ($qty <= 0) continue;
                $row = $pdo->query("SELECT unit_price FROM `split installation` WHERE id=".(int)$sid)->fetch(PDO::FETCH_ASSOC);
                $price = $row['unit_price'] ?? 0;
                $stmt->execute([$order_id,'installation',$sid,$qty,$price]);
            }
        }

        // Ducted installations
        if (!empty($_POST['ducted'])) {
            $stmt = $pdo->prepare("INSERT INTO `order_items` (order_id, item_type, item_id, installation_type, qty, price) VALUES (?,?,?,?,?,?)");
            foreach ($_POST['ducted'] as $did => $data) {
                $qty = (int)($data['qty'] ?? 0);
                if ($qty <= 0) continue;
                $row = $pdo->query("SELECT total_cost FROM `ductedinstallations` WHERE id=".(int)$did)->fetch(PDO::FETCH_ASSOC);
                $price = $row['total_cost'] ?? 0;
                $install_type = $data['installation_type'] ?? null;
                $stmt->execute([$order_id,'installation',$did,$install_type,$qty,$price]);
            }
        }

        // Personnel
        if (!empty($_POST['personnel_hours'])) {
            $stmt = $pdo->prepare("INSERT INTO `order_items` (order_id, item_type, item_id, qty, price) VALUES (?,?,?,?,?)");
            foreach ($_POST['personnel_hours'] as $pid => $hours) {
                $hours = (float)$hours;
                if ($hours <= 0) continue;
                $row = $pdo->query("SELECT rate FROM `personnel` WHERE id=".(int)$pid)->fetch(PDO::FETCH_ASSOC);
                $rate = $row['rate'] ?? 0;
                $stmt->execute([$order_id,'personnel',$pid,$hours,$rate]);
            }
        }

        // Equipment
        if (!empty($_POST['equipment_qty'])) {
            $stmt = $pdo->prepare("INSERT INTO `order_items` (order_id, item_type, item_id, qty, price) VALUES (?,?,?,?,?)");
            foreach ($_POST['equipment_qty'] as $eid => $qty) {
                $qty = (int)$qty;
                if ($qty <= 0) continue;
                $row = $pdo->query("SELECT rate FROM `equipment` WHERE id=".(int)$eid)->fetch(PDO::FETCH_ASSOC);
                $price = $row['rate'] ?? 0;
                $stmt->execute([$order_id,'equipment',$eid,$qty,$price]);
            }
        }

        // Other expenses
        if (!empty($_POST['other_expenses'])) {
            $stmt = $pdo->prepare("INSERT INTO `order_items` (order_id, item_type, item_id, qty, price) VALUES (?,?,?,?,?)");
            foreach ($_POST['other_expenses'] as $exp) {
                $name = trim($exp['name'] ?? '');
                $amount = (float)($exp['amount'] ?? 0);
                if ($amount <= 0) continue;
                // We store other expenses as item_type 'other', item_id 0
                $stmt->execute([$order_id,'other',0,1,$amount]);
            }
        }

        $pdo->commit();

        // Redirect to review page
        header("Location: review_order.php?id=".$order_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error saving order: " . $e->getMessage();
    }
}

// Render page content (your existing HTML/JS here)
ob_start();
include __DIR__ . '/create_order_form_html.php'; // You can split form/JS into a separate file if you like
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
