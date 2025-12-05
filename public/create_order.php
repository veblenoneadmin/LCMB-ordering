<?php
// create_order.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

$message = '';

// Fetch data safely
function safeFetch($pdo, $query){
    try { return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC); }
    catch(Exception $e){ return []; }
}

$products = safeFetch($pdo, "SELECT id, name, price, category FROM products ORDER BY name ASC");
$split_installations = safeFetch($pdo, "SELECT id, item_name AS name, unit_price AS price, category FROM split_installation ORDER BY item_name ASC");
$ducted_installations = safeFetch($pdo, "SELECT id, equipment_name AS name, total_cost AS price, category FROM ductedinstallations ORDER BY equipment_name ASC");
$personnel = safeFetch($pdo, "SELECT id, name, rate, category FROM personnel ORDER BY name ASC");
$equipment = safeFetch($pdo, "SELECT id, item AS name, rate, category FROM equipment ORDER BY item ASC");

function f2($v){ return number_format((float)$v, 2, '.', ''); }

// Handle POST
if($_SERVER['REQUEST_METHOD']==='POST'){
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $job_address = trim($_POST['job_address'] ?? '');
    $appointment_date = $_POST['appointment_date'] ?? null;

    $items = [];

    // PRODUCTS
    foreach($_POST['product'] ?? [] as $pid => $qty){
        $qty = intval($qty);
        if($qty > 0){
            $stmt = $pdo->prepare("SELECT price FROM products WHERE id=? LIMIT 1");
            $stmt->execute([$pid]);
            $price = (float)$stmt->fetchColumn();
            $items[] = ['item_id'=>$pid,'item_category'=>'product','installation_type'=>null,'qty'=>$qty,'price'=>$price];
        }
    }

    // SPLIT INSTALLATION
    foreach($_POST['split'] ?? [] as $sid => $qty){
        $qty = intval($qty);
        if($qty>0){
            $stmt = $pdo->prepare("SELECT unit_price FROM split_installation WHERE id=? LIMIT 1");
            $stmt->execute([$sid]);
            $price = (float)$stmt->fetchColumn();
            $items[] = ['item_id'=>$sid,'item_category'=>'split','installation_type'=>null,'qty'=>$qty,'price'=>$price];
        }
    }

    // DUCTED INSTALLATION
    foreach($_POST['ducted'] ?? [] as $did=>$data){
        $qty = intval($data['qty']??0);
        $type = $data['type'] ?? 'indoor';
        if($qty>0){
            $stmt = $pdo->prepare("SELECT total_cost FROM ductedinstallations WHERE id=? LIMIT 1");
            $stmt->execute([$did]);
            $price = (float)$stmt->fetchColumn();
            $items[] = [
                'item_id'=>$did,
                'item_category'=>'ducted',
                'installation_type'=>in_array($type,['indoor','outdoor'])?$type:'indoor',
                'qty'=>$qty,
                'price'=>$price
            ];
        }
    }

    // EQUIPMENT
    foreach($_POST['equipment'] ?? [] as $eid => $qty){
        $qty = intval($qty);
        if($qty>0){
            $stmt = $pdo->prepare("SELECT rate FROM equipment WHERE id=? LIMIT 1");
            $stmt->execute([$eid]);
            $rate = (float)$stmt->fetchColumn();
            $items[] = ['item_id'=>$eid,'item_category'=>'equipment','installation_type'=>null,'qty'=>$qty,'price'=>$rate];
        }
    }

    // OTHER EXPENSES
    $other_names = $_POST['other_expense_name'] ?? [];
    $other_amounts = $_POST['other_expense_amount'] ?? [];
    foreach($other_amounts as $i=>$amt){
        $amt = floatval($amt);
        $name = trim($other_names[$i] ?? '');
        if($amt>0){
            $items[] = [
                'item_id'=>0,
                'item_category'=>'expense',
                'installation_type'=>$name ?: 'Other expense',
                'qty'=>1,
                'price'=>$amt
            ];
        }
    }

    // PERSONNEL
    $personnel_dispatch_rows = [];
    foreach($_POST['personnel_hours'] ?? [] as $pid=>$hours_raw){
        $hours = floatval($hours_raw);
        if($hours<=0) continue;
        $stmt = $pdo->prepare("SELECT rate FROM personnel WHERE id=? LIMIT 1");
        $stmt->execute([$pid]);
        $rate = (float)$stmt->fetchColumn();
        $date = $_POST['personnel_date'][$pid] ?? $appointment_date ?? date('Y-m-d');
        $items[] = [
            'item_id'=>$pid,
            'item_category'=>'personnel',
            'installation_type'=>null,
            'qty'=>$hours,
            'price'=>$rate
        ];
        $personnel_dispatch_rows[] = ['personnel_id'=>$pid,'date'=>$date,'hours'=>$hours];
    }

    // CALCULATE TOTALS
    $subtotal=0; foreach($items as $it) $subtotal += ($it['qty']*$it['price']);
    $tax = round($subtotal*0.10,2);
    $grand_total = round($subtotal+$tax,2);
    $discount = 0.0;
    $order_number = 'ORD'.time().rand(10,99);

    // INSERT ORDER
    try{
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO orders
            (customer_name, customer_email, contact_number, job_address, appointment_date, total_amount, order_number, status, total, tax, discount, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
        ");
        $stmt->execute([$customer_name,$customer_email,$contact_number,$job_address,$appointment_date,$subtotal,$order_number,'pending',$grand_total,$tax,$discount]);
        $order_id = $pdo->lastInsertId();

        // INSERT ITEMS
        $stmt_item = $pdo->prepare("
            INSERT INTO order_items (order_id,item_id,item_category,installation_type,qty,price,created_at)
            VALUES (?,?,?,?,?,?,NOW())
        ");
        foreach($items as $it){
            $stmt_item->execute([
                $order_id,
                $it['item_id']??0,
                $it['item_category'],
                $it['installation_type']??null,
                $it['qty'],
                f2($it['price'])
            ]);
        }

        // DISPATCH ROWS
        if(!empty($personnel_dispatch_rows)){
            $stmt_dispatch = $pdo->prepare("INSERT INTO dispatch (order_id,personnel_id,date,hours,created_at) VALUES (?,?,?,?,NOW())");
            foreach($personnel_dispatch_rows as $r){
                $d = $r['date'] ?: date('Y-m-d');
                if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)) $d = date('Y-m-d');
                $stmt_dispatch->execute([$order_id,$r['personnel_id'],$d,f2($r['hours'])]);
            }
        }

        $pdo->commit();
        header("Location: review_order.php?order_id=".$order_id);
        exit;
    }catch(Exception $e){
        $pdo->rollBack();
        $message = 'Error saving order: '.$e->getMessage();
    }
}
?>

<!-- REST OF YOUR HTML & JS stays the same as your working create_order.php -->




<?php ob_start(); ?>
<?php if($message): ?>
<div class="alert" style="padding:10px;background:#fee;border:1px solid #fbb;margin-bottom:12px;">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<form method="post" class="create-order-grid" id="orderForm" novalidate>
<div class="flex-1 flex flex-col gap-6">

<!-- CLIENT INFO -->
<div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
  <h5 class="text-lg font-medium text-gray-700 mb-4">Client Information</h5>
  <div class="grid grid-cols-2 gap-6">
    <div class="relative">
      <input type="text" name="customer_name" id="customer_name" placeholder=" " class="material-input" required>
      <label for="customer_name">Name</label>
    </div>
    <div class="relative">
      <input type="email" name="customer_email" id="customer_email" placeholder=" " class="material-input">
      <label for="customer_email">Email</label>
    </div>
    <div class="relative">
      <input type="text" name="contact_number" id="contact_number" placeholder=" " class="material-input">
      <label for="contact_number">Phone</label>
    </div>
    <div class="relative">
      <input type="text" name="job_address" id="job_address" placeholder=" " class="material-input">
      <label for="job_address">Address</label>
    </div>
    <div class="relative">
      <input type="date" name="appointment_date" id="appointment_date" value="<?= date('Y-m-d') ?>" class="material-input">
      <label for="appointment_date">Appointment Date</label>
    </div>
  </div>
</div>

<!-- PRODUCTS TABLE -->
<?php
function render_table($items,$input_name_prefix,$qty_class='qty-input',$price_field='price'){
?>
<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
<div class="flex items-center justify-between mb-3">
<span class="font-medium text-gray-700"><?= $items[0]['category'] ?? 'Items' ?></span>
<input type="text" class="search-input" placeholder="Search..." >
</div>
<div class="overflow-y-auto max-h-64 border rounded-lg">
<table class="products-table w-full border-collapse text-sm">
<thead class="bg-gray-100 sticky top-0"><tr><th>Name</th><th class="text-center">Price</th><th class="text-center">Qty</th><th class="text-center">Subtotal</th></tr></thead>
<tbody>
<?php foreach($items as $i): $id=(int)$i['id']; ?>
<tr class="border-b">
<td class="product-name p-2"><?= htmlspecialchars($i['name']) ?></td>
<td class="p-2 text-center">$<span class="prod-price"><?= number_format($i[$price_field],2) ?></span></td>
<td class="p-2 text-center">
<div class="qty-wrapper">
<button type="button" class="qtbn minus">-</button>
<input type="number" min="0" value="0" name="<?= $input_name_prefix ?>[<?= $id ?>]" class="<?= $qty_class ?>" data-price="<?= htmlspecialchars($i[$price_field]) ?>">
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
<?php
}
render_table($products,'product');
render_table($split_installations,'split','qty-input split-qty','price');
render_table($ducted_installations,'ducted','qty-input ducted-qty','price');
render_table($equipment,'equipment','qty-input equip-input','rate');
?>

<!-- PERSONNEL -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
<div class="flex items-center justify-between mb-3">
<span class="font-medium text-gray-700">Personnel</span>
<input type="text" id="personnelSearch" class="search-input" placeholder="Search personnel...">
</div>
<div class="overflow-y-auto max-h-64 border rounded-lg">
<table class="personnel-table w-full border-collapse text-sm">
<thead class="bg-gray-100 sticky top-0"><tr><th>Name</th><th>Rate</th><th>Date</th><th>Hours</th><th>Subtotal</th></tr></thead>
<tbody>
<?php foreach($personnel as $p): $pid=(int)$p['id']; ?>
<tr data-id="<?= $pid ?>" class="border-b text-center">
<td class="p-2 text-left"><?= htmlspecialchars($p['name']) ?></td>
<td class="p-2 text-center pers-rate"><?= number_format($p['rate'],2) ?></td>
<td class="p-2 text-center">
<input type="text" name="personnel_date[<?= $pid ?>]" class="personnel-date w-full text-center" data-personnel-id="<?= $pid ?>" placeholder="YYYY-MM-DD">
</td>
<td class="p-2 text-center">
<div class="qty-wrapper">
<button type="button" class="qtbn hour-minus">-</button>
<input type="number" min="0" value="0" name="personnel_hours[<?= $pid ?>]" class="qty-input pers-hours" data-rate="<?= htmlspecialchars($p['rate']) ?>">
<button type="button" class="qtbn hour-plus">+</button>
</div>
</td>
<td class="p-2">$<span class="pers-subtotal">0.00</span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<!-- OTHER EXPENSES -->
<div class="bg-white p-4 rounded-xl shadow mb-4">
<span class="font-medium text-gray-700 mb-2">Other Expenses</span>
<div id="otherExpensesContainer"></div>
<button type="button" class="qbtn" id="addExpenseBtn">Add</button>
</div>

</div> <!-- END LEFT PANEL -->

<!-- RIGHT PANEL -->
<div class="create-order-right" style="width:360px;">
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

<style>
.create-order-grid {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    flex-wrap: wrap; /* keeps responsiveness */
}

.create-order-right {
    position: sticky;
    top: 24px;
    width: 360px;       /* fixed width for right panel */
    flex-shrink: 0;     /* prevent shrinking */
    align-self: flex-start;
}

@media (max-width: 1000px) {
    .create-order-grid {
        flex-direction: column;
    }
    .create-order-right {
        width: 100%;    /* full width on mobile */
        position: relative; /* remove sticky on small screens */
    }
}
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- JS for quantity, subtotal, summary, and Flatpickr for personnel -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    function parseFloatSafe(v) { return parseFloat(v) || 0; }
    function fmt(n) { return Number(n || 0).toFixed(2); }

    function updateRowSubtotal(row) {
        if (!row) return;

        // Personnel
        const persInput = row.querySelector(".pers-hours");
        if (persInput) {
            const rate = parseFloatSafe(persInput.dataset.rate);
            const hours = parseFloatSafe(persInput.value);
            const persSub = row.querySelector(".pers-subtotal");
            if (persSub) persSub.textContent = (hours * rate).toFixed(2);
            return;
        }

        // Products / Installations / Equipment
        const input = row.querySelector(".qty-input");
        if (!input) return;
        const price = parseFloatSafe(input.dataset.price || input.dataset.rate);
        const qty = parseFloatSafe(input.value);
        const subtotalEl = row.querySelector(".row-subtotal, .equip-subtotal");
        if (subtotalEl) subtotalEl.textContent = (qty * price).toFixed(2);
    }

    function updateSummary() {
        let subtotal = 0;
        const summaryEl = document.getElementById('orderSummary');
        summaryEl.innerHTML = '';

        // 1️⃣ Products, installations, equipment, personnel
        document.querySelectorAll("tr").forEach(row => {
            const name = row.querySelector('td')?.textContent?.trim();
            const subEl = row.querySelector(".row-subtotal, .pers-subtotal, .equip-subtotal");
            if (name && subEl && parseFloatSafe(subEl.textContent) > 0) {
                let qtyStr = '';
                if (row.querySelector('.pers-hours')) {
                    qtyStr = (row.querySelector('.pers-hours').value || '0') + ' hr';
                } else {
                    const q = row.querySelector('.qty-input');
                    if (q) qtyStr = q.value || '0';
                }
                const div = document.createElement('div');
                div.className = 'summary-item flex justify-between py-1';
                div.innerHTML = `<span style="color:#374151">${name}${qtyStr ? (' x ' + qtyStr) : ''}</span><span style="color:#111827">$${fmt(parseFloatSafe(subEl.textContent))}</span>`;
                summaryEl.appendChild(div);
                subtotal += parseFloatSafe(subEl.textContent);
            }
        });

        // 2️⃣ Other Expenses
        document.querySelectorAll("#otherExpensesContainer > div").forEach(div => {
            const name = div.querySelector('input[name="other_expense_name[]"]').value || "Other Expense";
            const amt = parseFloatSafe(div.querySelector('input[name="other_expense_amount[]"]').value);
            if (amt > 0) {
                const summaryItem = document.createElement('div');
                summaryItem.className = 'summary-item flex justify-between py-1';
                summaryItem.innerHTML = `<span style="color:#374151">${name}</span><span style="color:#111827">$${fmt(amt)}</span>`;
                summaryEl.appendChild(summaryItem);
                subtotal += amt;
            }
        });

        // Totals
        const tax = subtotal * 0.10;
        const grand = subtotal + tax;
        document.getElementById("subtotalDisplay").textContent = fmt(subtotal);
        document.getElementById("taxDisplay").textContent = fmt(tax);
        document.getElementById("grandDisplay").textContent = fmt(grand);

        if (summaryEl.innerHTML.trim() === '') summaryEl.innerHTML = '<div class="empty-note">No items selected.</div>';
    }

    // 3️⃣ Quantity buttons
    document.querySelectorAll(".qbtn, .qtbn").forEach(btn => {
        btn.addEventListener("click", () => {
            const input = btn.closest("td, div").querySelector("input");
            if (!input) return;
            let val = parseFloat(input.value) || 0;
            if (btn.classList.contains("plus") || btn.classList.contains("split-plus") || btn.classList.contains("ducted-plus") || btn.classList.contains("equip-plus") || btn.classList.contains("hour-plus")) val++;
            else if (btn.classList.contains("minus") || btn.classList.contains("split-minus") || btn.classList.contains("ducted-minus") || btn.classList.contains("equip-minus") || btn.classList.contains("hour-minus")) val = Math.max(0, val - 1);
            input.value = val;
            updateRowSubtotal(input.closest("tr"));
            updateSummary();
        });
    });

    // 4️⃣ Manual input changes
    document.querySelectorAll(".qty-input").forEach(input => {
        input.addEventListener('input', () => { updateRowSubtotal(input.closest("tr")); updateSummary(); });
    });

    // 5️⃣ Other Expenses Add/Remove
    document.getElementById("addExpenseBtn").addEventListener("click", function () {
        const container = document.getElementById("otherExpensesContainer");
        const div = document.createElement("div");
        div.className = "flex gap-2 mb-2";
        div.innerHTML = `
            <input type="text" name="other_expense_name[]" placeholder="Expense Name" class="input flex-1">
            <input type="number" name="other_expense_amount[]" placeholder="Amount" class="input w-24" min="0" step="0.01">
            <button type="button" class="qbtn remove-expense">Remove</button>
        `;
        container.appendChild(div);

        div.querySelector(".remove-expense").addEventListener("click", function () { div.remove(); updateSummary(); });
        div.querySelector('input[name="other_expense_amount[]"]').addEventListener("input", updateSummary);
    });
    
    // 6️⃣ Flatpicker 
 document.querySelectorAll('.personnel-date').forEach(input => {
    flatpickr(input, {
        dateFormat: "Y-m-d",
        allowInput: false,
        onOpen: function(selectedDates, dateStr, instance) {
            const pid = input.dataset.personnelId;

            fetch('fetch_personnel_booked.php?personnel_id=' + pid)
                .then(res => res.json())
                .then(booked => {
                    console.log("BOOKED FOR PID " + pid, booked);

                    instance.set('disable', booked);
                });
        }
    });
});



  updateSummary();
});
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
