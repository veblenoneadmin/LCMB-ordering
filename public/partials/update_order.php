<?php
ob_start();
require_once __DIR__ . '/../../config.php';

$order_id = $_POST['order_id'] ?? 0;
if (!$order_id) die("No order specified.");

// --- Fetch existing order ---
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) die("Order not found.");

function f2($v){ return number_format((float)$v, 2, '.', ''); }

$customer_name    = trim($_POST['customer_name'] ?? '');
$customer_email   = trim($_POST['customer_email'] ?? '');
$contact_number   = trim($_POST['contact_number'] ?? '');
$job_address      = trim($_POST['job_address'] ?? '');
$appointment_date = $_POST['appointment_date'] ?? null;

// --- Prepare items array ---
$items = [];

// PRODUCTS
foreach ($_POST['product'] ?? [] as $pid => $qty) {
    $qty = intval($qty);
    if ($qty > 0) {
        $stmt = $pdo->prepare("SELECT price FROM products WHERE id=? LIMIT 1");
        $stmt->execute([$pid]);
        $price = (float)$stmt->fetchColumn();
        $items[] = [
            'item_category' => 'product',
            'item_id' => $pid,
            'installation_type' => null,
            'qty' => $qty,
            'price' => $price,
            'description' => null
        ];
    }
}

// SPLIT INSTALLATIONS
foreach ($_POST['split'] ?? [] as $sid => $qty) {
    $qty = intval($qty);
    if ($qty > 0) {
        $stmt = $pdo->prepare("SELECT unit_price FROM split_installation WHERE id=? LIMIT 1");
        $stmt->execute([$sid]);
        $price = (float)$stmt->fetchColumn();
        $items[] = [
            'item_category' => 'split',
            'item_id' => $sid,
            'installation_type' => null,
            'qty' => $qty,
            'price' => $price,
            'description' => null
        ];
    }
}

// DUCTED INSTALLATIONS
foreach ($_POST['ducted'] ?? [] as $did => $data) {
    $qty = intval($data['qty'] ?? 0);
    $type = $data['type'] ?? 'indoor';
    if ($qty > 0) {
        $stmt = $pdo->prepare("SELECT total_cost FROM ductedinstallations WHERE id=? LIMIT 1");
        $stmt->execute([$did]);
        $price = (float)$stmt->fetchColumn();
        $type = in_array($type,['indoor','outdoor'])?$type:'indoor';
        $items[] = [
            'item_category'=>'ducted',
            'item_id'=>$did,
            'installation_type'=>$type,
            'qty'=>$qty,
            'price'=>$price,
            'description'=>null
        ];
    }
}

// EQUIPMENT
foreach ($_POST['equipment'] ?? [] as $eid => $qty) {
    $qty = intval($qty);
    if ($qty > 0) {
        $stmt = $pdo->prepare("SELECT rate FROM equipment WHERE id=? LIMIT 1");
        $stmt->execute([$eid]);
        $rate = (float)$stmt->fetchColumn();
        $items[] = [
            'item_category'=>'equipment',
            'item_id'=>$eid,
            'installation_type'=>null,
            'qty'=>$qty,
            'price'=>$rate,
            'description'=>null
        ];
    }
}

// OTHER EXPENSES
$other_names = $_POST['other_expense_name'] ?? [];
$other_amounts = $_POST['other_expense_amount'] ?? [];
foreach ($other_amounts as $i => $amt) {
    $amt = floatval($amt);
    $name = trim($other_names[$i] ?? '');
    if ($amt > 0) {
        $items[] = [
            'item_category'=>'expense',
            'item_id'=>0,
            'installation_type'=>null,
            'qty'=>1,
            'price'=>$amt,
            'description'=>$name ?: 'Other expense'
        ];
    }
}

// PERSONNEL
$personnel_dispatch_rows = [];
foreach ($_POST['personnel_hours'] ?? [] as $pid => $hours_raw) {
    $hours = floatval($hours_raw);
    if ($hours <= 0) continue;
    $stmt = $pdo->prepare("SELECT rate FROM personnel WHERE id=? LIMIT 1");
    $stmt->execute([$pid]);
    $rate = (float)$stmt->fetchColumn();
    $date = $_POST['personnel_date'][$pid] ?? $appointment_date ?? date('Y-m-d');
    $personnel_dispatch_rows[] = [
        'personnel_id' => (int)$pid,
        'date' => $date,
        'hours' => $hours
    ];
    $items[] = [
        'item_category'=>'personnel',
        'item_id'=>$pid,
        'installation_type'=>null,
        'qty'=>$hours,
        'price'=>$rate,
        'description'=>null
    ];
}

// --- Calculate totals ---
$subtotal = 0.0;
foreach($items as $it) $subtotal += $it['qty'] * $it['price'];
$tax = round($subtotal*0.10,2);
$grand_total = round($subtotal + $tax,2);
$discount = 0.00;

try {
    $pdo->beginTransaction();

    // UPDATE orders table
    $stmt = $pdo->prepare("
        UPDATE orders SET
        customer_name=?, customer_email=?, contact_number=?, job_address=?, appointment_date=?,
        total_amount=?, total=?, tax=?, discount=?, updated_at=NOW()
        WHERE id=?
    ");
    $stmt->execute([
        $customer_name,
        $customer_email,
        $contact_number,
        $job_address,
        $appointment_date ?: null,
        f2($subtotal),
        f2($grand_total),
        f2($tax),
        f2($discount),
        $order_id
    ]);

    // DELETE old items
    $pdo->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$order_id]);

    // INSERT updated items
    $stmt_item = $pdo->prepare("
        INSERT INTO order_items (order_id, item_category, item_id, installation_type, qty, price, description, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    foreach ($items as $it) {
        $stmt_item->execute([
            $order_id,
            $it['item_category'],
            $it['item_id'] ?? 0,
            $it['installation_type'] ?? null,
            $it['qty'],
            f2($it['price']),
            $it['description'] ?? null
        ]);
    }

    // DELETE old dispatch rows
    $pdo->prepare("DELETE FROM dispatch WHERE order_id=?")->execute([$order_id]);

    // INSERT updated dispatch rows
    if (!empty($personnel_dispatch_rows)) {
        $stmt_dispatch = $pdo->prepare("
            INSERT INTO dispatch (order_id, personnel_id, date, hours, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        foreach($personnel_dispatch_rows as $r){
            $d = $r['date'] ?: date('Y-m-d');
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)) $d = date('Y-m-d');
            $stmt_dispatch->execute([$order_id, $r['personnel_id'], $d, f2($r['hours'])]);
        }
    }

    $pdo->commit();
    header("Location: review_order.php?order_id=".$order_id);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error updating order: ".$e->getMessage());
}
