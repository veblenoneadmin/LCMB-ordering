<?php
// create_order.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch all data (safe queries)
try { $products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $products=[]; }
try { $split_installations = $pdo->query("SELECT id, item_name AS name, unit_price AS price FROM split_installation ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $split_installations=[]; }
try { $ducted_installations = $pdo->query("SELECT id, equipment_name AS name, model_name_indoor, model_name_outdoor, total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $ducted_installations=[]; }
try { $personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $personnel=[]; }
try { $equipment = $pdo->query("SELECT id, item AS name, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $equipment=[]; }

$message = '';

/**
 * Helper: format float for DB (2 decimals)
 */
function f2($v){ return number_format((float)$v, 2, '.', ''); }

if($_SERVER['REQUEST_METHOD']==='POST'){
    // Collect customer data
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? null);
    $contact_number = trim($_POST['contact_number'] ?? null);
    $job_address = trim($_POST['job_address'] ?? null);
    $appointment_date = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;

    // Build items array
    $items = [];

    // PRODUCTS
    foreach($_POST['product'] ?? [] as $pid => $qty){
        $qty = intval($qty);
        if($qty > 0){
            $stmt = $pdo->prepare("SELECT price FROM products WHERE id=? LIMIT 1");
            $stmt->execute([$pid]);
            $price = (float)$stmt->fetchColumn();
            $items[] = [
                'item_type' => 'product',
                'item_id' => $pid,
                'installation_type' => null,
                'qty' => $qty,
                'price' => f2($price)
            ];
        }
    }

    // SPLIT INSTALLATIONS
    foreach($_POST['split'] ?? [] as $sid => $qty){
        $qty = intval($qty);
        if($qty > 0){
            $stmt = $pdo->prepare("SELECT unit_price FROM split_installation WHERE id=? LIMIT 1");
            $stmt->execute([$sid]);
            $price = (float)$stmt->fetchColumn();
            $items[] = [
                'item_type' => 'installation',
                'item_id' => $sid,
                'installation_type' => 'split',
                'qty' => $qty,
                'price' => f2($price)
            ];
        }
    }

    // DUCTED INSTALLATIONS
    foreach($_POST['ducted'] ?? [] as $did => $data){
        $qty = intval($data['qty'] ?? 0);
        $type = ($data['type'] ?? 'indoor') ?: 'indoor';
        if($qty > 0){
            $stmt = $pdo->prepare("SELECT total_cost FROM ductedinstallations WHERE id=? LIMIT 1");
            $stmt->execute([$did]);
            $price = (float)$stmt->fetchColumn();
            $items[] = [
                'item_type' => 'installation',
                'item_id' => $did,
                'installation_type' => $type,
                'qty' => $qty,
                'price' => f2($price)
            ];
        }
    }

    // PERSONNEL
    foreach($_POST['personnel'] ?? [] as $pid => $hours){
        $hours = floatval($hours);
        if($hours > 0){
            $stmt = $pdo->prepare("SELECT rate FROM personnel WHERE id=? LIMIT 1");
            $stmt->execute([$pid]);
            $rate = (float)$stmt->fetchColumn();
            $line_price = $rate * $hours;
            $items[] = [
                'item_type' => 'personnel',
                'item_id' => $pid,
                'installation_type' => null,
                'qty' => 1,
                'price' => f2($line_price)
            ];
        }
    }

    // EQUIPMENT
    foreach($_POST['equipment'] ?? [] as $eid => $qty){
        $qty = intval($qty);
        if($qty > 0){
            $stmt = $pdo->prepare("SELECT rate FROM equipment WHERE id=? LIMIT 1");
            $stmt->execute([$eid]);
            $rate = (float)$stmt->fetchColumn();
            $items[] = [
                'item_type' => 'equipment', // FIXED
                'item_id' => $eid,
                'installation_type' => null,
                'qty' => $qty,
                'price' => f2($rate)
            ];
        }
    }

    // OTHER EXPENSES
    $other_names = $_POST['other_expense_name'] ?? [];
    $other_amounts = $_POST['other_expense_amount'] ?? [];
    foreach($other_amounts as $i => $amt){
        $amt = floatval($amt);
        $name = trim($other_names[$i] ?? '');
        if($amt > 0){
            $items[] = [
                'item_type' => 'expense', // FIXED
                'item_id' => 0,
                'installation_type' => $name ?: 'Other expense',
                'qty' => 1,
                'price' => f2($amt)
            ];
        }
    }

    // Totals
    $subtotal = 0.0;
    foreach($items as $it){
        $subtotal += ((float)$it['qty']) * ((float)$it['price']);
    }
    $tax = round($subtotal * 0.10, 2);
    $grand_total = round($subtotal + $tax, 2);
    $discount = 0.00;
    $order_number = 'ORD' . time() . rand(10,99);

    try{
        $pdo->beginTransaction();

        // INSERT ORDER
        $stmt = $pdo->prepare(
            "INSERT INTO orders (customer_name, customer_email, contact_number, job_address, appointment_date, total_amount, order_number, status, total, tax, discount, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $customer_name,
            $customer_email,
            $contact_number,
            $job_address,
            $appointment_date,
            f2($subtotal),
            $order_number,
            'pending',
            f2($grand_total),
            f2($tax),
            f2($discount)
        ]);

        $order_id = $pdo->lastInsertId();

        // INSERT ORDER ITEMS
        $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, installation_type, qty, price, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        foreach($items as $it){
            $item_id = isset($it['item_id']) ? (int)$it['item_id'] : null;
            $installation_type = $it['installation_type'] ?? null;
            $qty = (int)$it['qty'];
            $price = f2($it['price']);
            $stmt_item->execute([
                $order_id,
                $it['item_type'],
                $item_id,
                $installation_type,
                $qty,
                $price
            ]);
        }

        $pdo->commit();
        header("Location: review_order.php?order_id=" . $order_id);
        exit;

    } catch(Exception $e){
        $pdo->rollBack();
        $message = 'Error saving order: '.$e->getMessage();
    }
}

ob_start();
?>

<!-- MESSAGE -->
<?php if($message): ?>
<div class="alert" style="padding:10px;background:#fee;border:1px solid #fbb;margin-bottom:12px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- FORM -->
<form method="post" class="create-order-grid" id="orderForm" novalidate>
    <div class="flex-1 flex flex-col gap-6">
        <!-- CLIENT INFO -->
        <div class="bg-white p-3 rounded-xl shadow border border-gray-200">
            <h5 class="text-lg font-medium text-gray-700 mb-3">Client Information</h5>
            <div class="grid grid-cols-2 gap-4">
                <input type="text" name="customer_name" placeholder="Name" class="input" required>
                <input type="email" name="customer_email" placeholder="Email" class="input">
                <input type="text" name="contact_number" placeholder="Phone" class="input">
                <input type="text" name="job_address" placeholder="Address" class="input">
                <input type="date" name="appointment_date" value="<?= date('Y-m-d') ?>" class="input">
            </div>
        </div>

        <!-- PRODUCTS TABLE -->
        <div class="bg-white p-4 rounded-xl shadow border border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <span class="font-medium text-gray-700">Material</span>
                <input id="productSearch" class="search-input" placeholder="Search products..." >
            </div>
            <div class="overflow-y-auto max-h-64 border rounded-lg">
                <table class="products-table w-full border-collapse text-sm">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr><th class="p-2 text-left">Name</th><th class="p-2 text-center">Price</th><th class="p-2 text-center">Qty</th><th class="p-2 text-center">Subtotal</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach($products as $p): $pid=(int)$p['id']; ?>
                        <tr class="border-b">
                            <td class="product-name p-2"><?= htmlspecialchars($p['name']) ?></td>
                            <td class="p-2 text-center">$<span class="prod-price"><?= number_format($p['price'],2) ?></span></td>
                            <td class="p-2 text-center">
                                <div class="qty-wrapper">
                                    <button type="button" class="qtbn minus">-</button>
                                    <input type="number" min="0" value="0" name="product[<?= $pid ?>]" 
                                           class="qty-input" data-price="<?= htmlspecialchars($p['price']) ?>">
                                    <button type="button" class="qtbn plus">+</button>
                                </div>
                            </td>
                            <td class="subtotal p-2 text-center">$<span class="row-subtotal">0.00</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SPLIT / DUCTED / PERSONNEL / EQUIPMENT / EXPENSES -->
        <!-- Keep your existing blocks from previous create_order.php (unchanged) -->

    </div>

    <!-- RIGHT PANEL -->
    <div class="create-order-right" style="width:360px;">
      
        <!-- SUMMARY -->
        <div id="rightPanel" class="bg-white p-6 rounded-2xl shadow border border-gray-200 h-auto max-h-[80vh] flex flex-col">
            <div id="orderSummary" class="flex-1 overflow-y-auto mb-4"><div class="empty-note">No items selected.</div></div>
            <hr class="mb-3">
            <p class="text-base font-medium text-gray-600 flex justify-between mb-1"><span>Subtotal:</span><span>$<span id="subtotalDisplay">0.00</span></span></p>
            <p class="text-base font-medium text-gray-600 flex justify-between mb-1"><span>Tax:</span><span>$<span id="taxDisplay">0.00</span></span></p>
            <p class="text-xl font-semibold flex justify-between text-blue-700 mb-4"><span>Grand Total:</span><span>$<span id="grandDisplay">0.00</span></span></p>
            <button type="submit" class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 text-lg">Save Order</button>
        </div>
    </div>
</form>

<!-- CSS and JS (same as previous code) -->

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
