<?php
// Enable errors for debugging on Railway
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php'; // DB connection ($pdo)
require_once __DIR__ . '/layout.php';

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetchColumn();
}

// Load data safely
$products = [];
$split_installations = [];
$ducted_installations = [];
$personnel = [];
$equipment = [];

try {
    if (tableExists($pdo, 'products')) {
        $products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    if (tableExists($pdo, 'split installation')) {
        $split_installations = $pdo->query("SELECT id, item_name AS name, unit_price AS price FROM `split installation` ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    if (tableExists($pdo, 'ductedinstallations')) {
        $ducted_installations = $pdo->query("SELECT id, equipment_name AS name, model_name_indoor, model_name_outdoor, total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    if (tableExists($pdo, 'personnel')) {
        $personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    if (tableExists($pdo, 'equipment')) {
        $equipment = $pdo->query("SELECT id, item AS name, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "Error loading data: " . $e->getMessage();
}

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Insert order
        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_email, contact_number, appointment_date, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $_POST['customer_name'] ?? '',
            $_POST['customer_email'] ?? '',
            $_POST['contact_number'] ?? '',
            $_POST['appointment_date'] ?? date('Y-m-d'),
        ]);
        $order_id = $pdo->lastInsertId();

        // Insert products
        if (!empty($_POST['quantity'])) {
            $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, qty, price) VALUES (?, 'product', ?, ?, ?)");
            foreach ($_POST['quantity'] as $pid => $qty) {
                $qty = (int)$qty;
                if ($qty > 0) {
                    $price = 0;
                    foreach ($products as $p) if ($p['id'] == $pid) $price = $p['price'];
                    $stmtItem->execute([$order_id, $pid, $qty, $price]);
                }
            }
        }

        // Insert split installations
        if (!empty($_POST['split'])) {
            $stmtSplit = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, qty, price) VALUES (?, 'installation', ?, ?, ?)");
            foreach ($_POST['split'] as $sid => $data) {
                $qty = (int)($data['qty'] ?? 0);
                if ($qty > 0) {
                    $price = 0;
                    foreach ($split_installations as $s) if ($s['id'] == $sid) $price = $s['price'];
                    $stmtSplit->execute([$order_id, $sid, $qty, $price]);
                }
            }
        }

        // Insert other items similarly (ducted, personnel, equipment)...

        $pdo->commit();

        header("Location: review_order.php?id=" . $order_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving order: " . $e->getMessage();
    }
}

// Render layout
ob_start();
if (!empty($error)) echo '<div style="color:red;margin:12px;">' . htmlspecialchars($error) . '</div>';
require __DIR__ . '/partials/order_form.php'; // Keep your HTML/JS from earlier in a separate file
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
