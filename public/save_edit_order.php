<?php
require_once __DIR__ . '/../config.php';

// helper
function f2($v){ return number_format((float)$v, 2, '.', ''); }

$order_id = intval($_POST['order_id'] ?? 0);
if (!$order_id) die("No order specified.");

// fetch order to ensure exists
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) die("Order not found.");

// collect order-level fields
$customer_name    = trim($_POST['customer_name'] ?? '');
$customer_email   = trim($_POST['customer_email'] ?? '');
$contact_number   = trim($_POST['contact_number'] ?? '');
$job_address      = trim($_POST['job_address'] ?? '');
$appointment_date = $_POST['appointment_date'] ?? null;

$items = []; // items to insert (will delete old and reinsert)
$dispatch_rows = []; // for personnel dispatch recreate

try {
    $pdo->beginTransaction();

    // 1) Update orders basic info (we'll update totals later)
    $stmt = $pdo->prepare("UPDATE orders SET customer_name=?, customer_email=?, contact_number=?, job_address=?, appointment_date=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([$customer_name, $customer_email, $contact_number, $job_address, $appointment_date ?: null, $order_id]);

    // 2) Existing items (those that came from order_items are submitted as arrays price[id], qty[id], personnel_date[id] possibly)
    $prices = $_POST['price'] ?? [];
    $qtys = $_POST['qty'] ?? [];

    foreach ($qtys as $order_item_id => $q) {
        $q = floatval($q);
        $p = isset($prices[$order_item_id]) ? floatval($prices[$order_item_id]) : 0;
        if ($q <= 0) continue; // skip zero quantity (removes item)
        // retrieve the original row to get category / item_id / installation_type / description
        $orig = $pdo->prepare("SELECT * FROM order_items WHERE id=? LIMIT 1");
        $orig->execute([$order_item_id]);
        $o = $orig->fetch(PDO::FETCH_ASSOC);
        if (!$o) continue;
        $item_category = $o['item_category'] ?? 'expense';
        $item_id = $o['item_id'] ?? 0;
        $installation_type = $o['installation_type'] ?? null;
        $description = $o['description'] ?? null;

        $items[] = [
            'item_category' => $item_category,
            'item_id' => $item_id,
            'installation_type' => $installation_type,
            'qty' => $q,
            'price' => $p,
            'description' => $description
        ];

        // if personnel, capture dispatch row using personnel_date[order_item_id] or fallback to appointment_date
        if ($item_category === 'personnel') {
            $date = $_POST['personnel_date'][$order_item_id] ?? $appointment_date ?? date('Y-m-d');
            $hours = $q;
            // basic normalize date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) $date = date('Y-m-d');
            $dispatch_rows[] = [
                'personnel_id' => (int)$item_id,
                'date' => $date,
                'hours' => f2($hours)
            ];
        }
    }

    // 3) New products (arrays)
    // new_product_id[] (hidden ids), new_product_qty[], new_product_price[]
    foreach ($_POST['new_product_id'] ?? [] as $i => $v) {
        $id = intval($v);
        $qty = floatval($_POST['new_product_qty'][$i] ?? 0);
        $price = floatval($_POST['new_product_price'][$i] ?? 0);
        if ($id <= 0 || $qty <= 0) continue;
        $items[] = [
            'item_category' => 'product',
            'item_id' => $id,
            'installation_type' => null,
            'qty' => $qty,
            'price' => $price,
            'description' => null
        ];
    }

    // 4) New split
    foreach ($_POST['new_split_id'] ?? [] as $i => $v) {
        $id = intval($v);
        $qty = floatval($_POST['new_split_qty'][$i] ?? 0);
        $price = floatval($_POST['new_split_price'][$i] ?? 0);
        if ($id <= 0 || $qty <= 0) continue;
        $items[] = [
            'item_category' => 'split',
            'item_id' => $id,
            'installation_type' => null,
            'qty' => $qty,
            'price' => $price,
            'description' => null
        ];
    }

    // 5) New ducted (with type)
    foreach ($_POST['new_ducted_id'] ?? [] as $i => $v) {
        $id = intval($v);
        $qty = floatval($_POST['new_ducted_qty'][$i] ?? 0);
        $price = floatval($_POST['new_ducted_price'][$i] ?? 0);
        $type = $_POST['new_ducted_type'][$i] ?? null;
        if ($id <= 0 || $qty <= 0) continue;
        $items[] = [
            'item_category' => 'ducted',
            'item_id' => $id,
            'installation_type' => $type,
            'qty' => $qty,
            'price' => $price,
            'description' => null
        ];
    }

    // 6) New equipment
    foreach ($_POST['new_equipment_id'] ?? [] as $i => $v) {
        $id = intval($v);
        $qty = floatval($_POST['new_equipment_qty'][$i] ?? 0);
        $price = floatval($_POST['new_equipment_price'][$i] ?? 0);
        if ($id <= 0 || $qty <= 0) continue;
        $items[] = [
            'item_category' => 'equipment',
            'item_id' => $id,
            'installation_type' => null,
            'qty' => $qty,
            'price' => $price,
            'description' => null
        ];
    }

    // 7) New personnel (with date + start/end -> compute hours)
    foreach ($_POST['new_personnel_id'] ?? [] as $i => $v) {
        $id = intval($v);
        $date = $_POST['new_personnel_date'][$i] ?? ($appointment_date ?? date('Y-m-d'));
        $start = $_POST['new_personnel_start'][$i] ?? '';
        $end = $_POST['new_personnel_end'][$i] ?? '';
        $rate = floatval($_POST['new_personnel_rate'][$i] ?? 0);

        // compute hours from start/end (HH:MM)
        $hours = 0;
        if ($start && $end) {
            $s = strtotime("1970-01-01 $start");
            $e = strtotime("1970-01-01 $end");
            $hours = max(0, ($e - $s) / 3600);
        }
        // if hours is zero allow explicit qty? (we didn't provide qty field for new personnel) - skip if zero
        if ($id <= 0 || $hours <= 0) continue;

        $items[] = [
            'item_category' => 'personnel',
            'item_id' => $id,
            'installation_type' => null,
            'qty' => $hours,
            'price' => $rate,
            'description' => null
        ];

        $dispatch_rows[] = [
            'personnel_id' => $id,
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) ? $date : date('Y-m-d'),
            'hours' => f2($hours)
        ];
    }

    // 8) New expenses
    foreach ($_POST['new_expense_name'] ?? [] as $i => $name) {
        $amt = floatval($_POST['new_expense_price'][$i] ?? 0);
        $n = trim($name);
        if ($amt <= 0) continue;
        $items[] = [
            'item_category' => 'expense',
            'item_id' => 0,
            'installation_type' => $n ?: 'Other expense',
            'qty' => 1,
            'price' => $amt,
            'description' => $n ?: null
        ];
    }

    // 9) Delete old order_items & dispatch rows for this order (we'll recreate)
    $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
    $pdo->prepare("DELETE FROM dispatch WHERE order_id = ?")->execute([$order_id]);

    // 10) Insert new order_items
    $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, item_category, item_id, installation_type, qty, price, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
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

    // 11) Insert dispatch rows (personnel)
    if (!empty($dispatch_rows)) {
        $stmt_disp = $pdo->prepare("INSERT INTO dispatch (order_id, personnel_id, date, hours, created_at) VALUES (?, ?, ?, ?, NOW())");
        foreach ($dispatch_rows as $r) {
            $d = $r['date'] ?: date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)) $d = date('Y-m-d');
            $stmt_disp->execute([$order_id, $r['personnel_id'], $d, f2($r['hours'])]);
        }
    }

    // 12) Recalculate totals and update orders row
    $subtotal = 0;
    foreach ($items as $it) $subtotal += ($it['qty'] * $it['price']);
    $tax = round($subtotal * 0.10, 2);
    $grand = round($subtotal + $tax, 2);
    $discount = 0.00;

    $stmt = $pdo->prepare("UPDATE orders SET total_amount = ?, total = ?, tax = ?, discount = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([ f2($subtotal), f2($grand), f2($tax), f2($discount), $order_id ]);

    $pdo->commit();
    header("Location: review_order.php?order_id=" . $order_id);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error saving order: " . $e->getMessage());
}
