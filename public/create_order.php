<?php
// create_order.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch all data safely
try {
    $products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
}

try {
    $split_installations = $pdo->query("SELECT id, item_name AS name, unit_price AS price FROM split_installation ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $split_installations = [];
}

try {
    $ducted_installations = $pdo->query("SELECT id, equipment_name AS name, model_name_indoor, model_name_outdoor, total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ducted_installations = [];
}

try {
    $personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $personnel = [];
}

try {
    $equipment = $pdo->query("SELECT id, item AS name, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $equipment = [];
}

$message = '';

function f2($v) { return number_format((float)$v, 2, '.', ''); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name    = trim($_POST['customer_name'] ?? '');
    $customer_email   = trim($_POST['customer_email'] ?? null);
    $contact_number   = trim($_POST['contact_number'] ?? null);
    $job_address      = trim($_POST['job_address'] ?? null);
    $appointment_date = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;

    $items = [];
    $personnel_dispatch_rows = [];

    // -----------------------
    // PRODUCTS
    // -----------------------
    foreach ($_POST['product'] ?? [] as $pid => $qty) {
        $qty = intval($qty);
        if ($qty <= 0) continue;

        $stmt = $pdo->prepare("SELECT name, price, category FROM products WHERE id=? LIMIT 1");
        $stmt->execute([$pid]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) continue;

        $items[] = [
            'item_id' => $pid,
            'item_type' => $p['category'] === 'personnel' ? 'personnel' : 'product',
            'category' => $p['category'] ?? 'product',
            'installation_type' => null,
            'qty' => $qty,
            'price' => (float)$p['price'],
            'model' => null,
        ];
    }

    // -----------------------
    // SPLIT INSTALLATIONS
    // -----------------------
    foreach ($_POST['split'] ?? [] as $sid => $qty) {
        $qty = intval($qty);
        if ($qty <= 0) continue;

        $stmt = $pdo->prepare("SELECT item_name AS name, unit_price AS price, category FROM split_installation WHERE id=? LIMIT 1");
        $stmt->execute([$sid]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$s) continue;

        $items[] = [
            'item_id' => $sid,
            'item_type' => 'installation',
            'category' => $s['category'] ?? 'split',
            'installation_type' => null,
            'qty' => $qty,
            'price' => (float)$s['price'],
            'model' => null,
        ];
    }

    // -----------------------
    // DUCTED INSTALLATIONS
    // -----------------------
    foreach ($_POST['ducted'] ?? [] as $did => $data) {
        $qty = intval($data['qty'] ?? 0);
        $type = strtolower($data['type'] ?? 'indoor');
        if ($qty <= 0) continue;

        $stmt = $pdo->prepare("SELECT equipment_name, model_name_indoor, model_name_outdoor, total_cost, category FROM ductedinstallations WHERE id=? LIMIT 1");
        $stmt->execute([$did]);
        $d = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$d) continue;

        $selected_model = ($type === 'indoor') ? $d['model_name_indoor'] : $d['model_name_outdoor'];

        $items[] = [
            'item_id' => $did,
            'item_type' => 'installation',
            'category' => $d['category'] ?? 'ducted',
            'installation_type' => in_array($type, ['indoor','outdoor']) ? $type : 'indoor',
            'qty' => $qty,
            'price' => (float)$d['total_cost'],
            'model' => $selected_model,
        ];
    }

    // -----------------------
    // EQUIPMENT
    // -----------------------
    foreach ($_POST['equipment'] ?? [] as $eid => $qty) {
        $qty = intval($qty);
        if ($qty <= 0) continue;

        $stmt = $pdo->prepare("SELECT item AS name, rate AS price, category FROM equipment WHERE id=? LIMIT 1");
        $stmt->execute([$eid]);
        $e = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$e) continue;

        $items[] = [
            'item_id' => $eid,
            'item_type' => $e['category'] === 'personnel' ? 'personnel' : 'product',
            'category' => $e['category'] ?? 'equipment',
            'installation_type' => null,
            'qty' => $qty,
            'price' => (float)$e['price'],
            'model' => null,
        ];
    }

    // -----------------------
    // PERSONNEL
    // -----------------------
    foreach ($_POST['personnel_hours'] ?? [] as $pid => $hours_raw) {
        $hours = floatval($hours_raw);
        if ($hours <= 0) continue;

        $stmt = $pdo->prepare("SELECT rate, category FROM personnel WHERE id=? LIMIT 1");
        $stmt->execute([$pid]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) continue;

        $date = $_POST['personnel_date'][$pid] ?? $appointment_date ?? date('Y-m-d');

        $items[] = [
            'item_id' => $pid,
            'item_type' => 'personnel',
            'category' => $p['category'] ?? 'personnel',
            'installation_type' => null,
            'qty' => $hours,
            'price' => (float)$p['rate'],
            'model' => null,
            'date' => $date,
        ];

        $personnel_dispatch_rows[] = [
            'personnel_id' => $pid,
            'date' => $date,
            'hours' => $hours,
        ];
    }

    // -----------------------
    // OTHER EXPENSES
    // -----------------------
    $other_names   = $_POST['other_expense_name'] ?? [];
    $other_amounts = $_POST['other_expense_amount'] ?? [];
    foreach ($other_amounts as $i => $amt) {
        $amt = floatval($amt);
        $name = trim($other_names[$i] ?? '');
        if ($amt <= 0) continue;

        $items[] = [
            'item_id' => 0,
            'item_type' => 'product',
            'category' => 'other',
            'installation_type' => 'split', // placeholder to satisfy ENUM
            'qty' => 1,
            'price' => $amt,
            'model' => $name ?: 'Other Expense',
        ];
    }

    // -----------------------
    // CALCULATE TOTALS
    // -----------------------
    $subtotal = 0.0;
    foreach ($items as $it) {
        $subtotal += ((float)$it['qty'] * (float)$it['price']);
    }
    $tax = round($subtotal * 0.10, 2);
    $grand_total = round($subtotal + $tax, 2);
    $discount = 0.00;
    $order_number = 'ORD' . time() . rand(10,99);

    try {
        $pdo->beginTransaction();

        // INSERT ORDER
        $stmt = $pdo->prepare("
            INSERT INTO orders
            (customer_name, customer_email, contact_number, job_address, appointment_date, total_amount, order_number, status, total, tax, discount, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
        ");
        $stmt->execute([
            $customer_name,
            $customer_email,
            $contact_number,
            $job_address,
            $appointment_date !== null ? $appointment_date : null,
            number_format($subtotal,2,'.',''),
            $order_number,
            'pending',
            number_format($grand_total,2,'.',''),
            number_format($tax,2,'.',''),
            number_format($discount,2,'.','')
        ]);
        $order_id = $pdo->lastInsertId();

        // INSERT ORDER ITEMS
        $stmt_item = $pdo->prepare("
            INSERT INTO order_items (order_id,item_type,item_id,installation_type,qty,price,model,category,created_at)
            VALUES (?,?,?,?,?,?,?,?,NOW())
        ");
        foreach ($items as $it) {
            $stmt_item->execute([
                $order_id,
                $it['item_type'],
                $it['item_id'] ?? null,
                $it['installation_type'] ?? null,
                $it['qty'],
                number_format($it['price'],2,'.',''),
                $it['model'] ?? null,
                $it['category'] ?? null,
            ]);
        }

        // INSERT DISPATCH rows for personnel
        if (!empty($personnel_dispatch_rows)) {
            $stmt_dispatch = $pdo->prepare("
                INSERT INTO dispatch (order_id, personnel_id, date, hours, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            foreach ($personnel_dispatch_rows as $r) {
                $d = $r['date'] ?: date('Y-m-d');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $d = date('Y-m-d');
                $stmt_dispatch->execute([$order_id, $r['personnel_id'], $d, number_format($r['hours'],2,'.','')]);
            }
        }

        $pdo->commit();
        header("Location: review_order.php?order_id=" . $order_id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error saving order: ' . $e->getMessage();
    }
}
 // end POST

// Render form
ob_start();
?>

<?php if ($message): ?>
    <div class="alert" style="padding:10px;background:#fee;border:1px solid #fbb;margin-bottom:12px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

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
<?php foreach ($products as $p): $pid = (int)$p['id']; ?>
<tr class="border-b">
<td class="product-name p-2"><?= htmlspecialchars($p['name']) ?></td>
<td class="p-2 text-center">$<span class="prod-price"><?= number_format($p['price'],2) ?></span></td>
<td class="p-2 text-center">
<div class="qty-wrapper">
<button type="button" class="qtbn minus">-</button>
<input type="number" min="0" value="0" name="product[<?= $pid ?>]" class="qty-input" data-price="<?= htmlspecialchars($p['price']) ?>">
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

<!-- SPLIT INSTALLATION -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
<div class="flex items-center justify-between mb-3">
<span class="font-medium text-gray-700">Split System Installation</span>
<input id="splitSearch" class="search-input" placeholder="Search split systems..." >
</div>
<div class="overflow-y-auto max-h-64 border rounded-lg">
<table id="splitTable" class="products-table w-full border-collapse text-sm">
<thead><tr><th>Name</th><th>Unit Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
<tbody>
<?php foreach ($split_installations as $s): $sid = (int)$s['id']; ?>
<tr>
<td><?= htmlspecialchars($s['name']) ?></td>
<td>$<span class="split-price"><?= number_format($s['price'],2) ?></span></td>
<td>
<div class="qty-box">
<button type="button" class="qbtn split-minus">-</button>
<input type="number" min="0" value="0" name="split[<?= $sid ?>]" class="qty-input split-qty" data-price="<?= htmlspecialchars($s['price']) ?>">
<button type="button" class="qbtn split-plus">+</button>
</div>
</td>
<td>$<span class="row-subtotal">0.00</span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<!-- DUCTED INSTALLATION -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
<div class="flex items-center justify-between mb-3">
<span class="font-medium text-gray-700">Ducted Installation</span>
</div>
<div class="overflow-y-auto max-h-64 border rounded-lg">
<table class="products-table w-full border-collapse text-sm">
<thead><tr><th>Equipment</th><th>Type</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
<tbody>
<?php foreach ($ducted_installations as $d): $did = (int)$d['id']; ?>
<tr>
<td><?= htmlspecialchars($d['name']) ?></td>
<td>
<select name="ducted[<?= $did ?>][type]" class="input installation-type">
<option value="indoor">Indoor</option>
<option value="outdoor">Outdoor</option>
</select>
</td>
<td>$<span class="ducted-price"><?= number_format($d['price'],2) ?></span></td>
<td>
<div class="qty-box">
<button type="button" class="qbtn ducted-minus">-</button>
<input type="number" min="0" value="0" name="ducted[<?= $did ?>][qty]" class="qty-input installation-qty" data-price="<?= htmlspecialchars($d['price']) ?>">
<button type="button" class="qbtn ducted-plus">+</button>
</div>
</td>
<td>$<span class="row-subtotal">0.00</span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<!-- PERSONNEL TABLE -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Personnel</span>
    <input id="personnelSearch" class="search-input" placeholder="Search personnel...">
  </div>
  <div class="overflow-y-auto max-h-64 border rounded-lg">
    <table class="personnel-table w-full border-collapse text-sm">
      <thead class="bg-gray-100 sticky top-0">
        <tr>
          <th class="p-2 text-left">Name</th>
          <th class="p-2 text-center">Rate</th>
          <th class="p-2 text-center">Date</th>
          <th class="p-2 text-center">Hours</th>
          <th class="p-2 text-center">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($personnel as $p): $pid = (int)$p['id']; ?>
        <tr data-id="<?= $pid ?>" class="border-b text-center">
          <td class="p-2 text-left"><?= htmlspecialchars($p['name']) ?></td>
          <td class="p-2 text-center pers-rate"><?= number_format($p['rate'],2) ?></td>
          <td class="p-2 text-center">
            <input type="date" name="personnel_date[<?= $pid ?>]" class="personnel-date w-full text-center" placeholder="YYYY-MM-DD">
          </td>
          <td class="p-2 text-center">
            <div class="qty-wrapper">
                <button type="button" class="qtbn hour-minus">-</button>
                <input type="number" min="0" value="0"
                       name="personnel_hours[<?= $pid ?>]"
                       class="qty-input pers-hours" data-rate="<?= htmlspecialchars($p['rate']) ?>">
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

<!-- EQUIPMENT -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
<div class="flex items-center justify-between mb-3">
<span class="font-medium text-gray-700">Equipment</span>
<input id="equipmentSearch" class="search-input" placeholder="Search equipment...">
</div>
<div class="overflow-y-auto max-h-64 border rounded-lg">
<table class="products-table w-full border-collapse text-sm">
<thead><tr><th>Item</th><th>Rate</th><th>Qty</th><th>Subtotal</th></tr></thead>
<tbody>
<?php foreach ($equipment as $e): $eid = (int)$e['id']; ?>
<tr>
<td><?= htmlspecialchars($e['name']) ?></td>
<td class="equip-rate"><?= number_format($e['rate'],2) ?></td>
<td>
<div class="qty-box">
<button type="button" class="qbtn equip-minus">-</button>
<input type="number" min="0" value="0" name="equipment[<?= $eid ?>]" class="qty-input equip-input" data-rate="<?= htmlspecialchars($e['rate']) ?>">
<button type="button" class="qbtn equip-plus">+</button>
</div>
</td>
<td>$<span class="equip-subtotal">0.00</span></td>
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

<!-- RIGHT PANEL SUMMARY -->
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

<!-- CSS -->
<style>
.create-order-grid { display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap; }
.create-order-right { position:sticky; top:24px; align-self:flex-start; }
.products-table td, .products-table th { vertical-align: middle; }
.empty-note { color:#7e8796; font-size:13px; text-align:center; padding:12px 0; }
.qty-box input.qty-input { width:72px; text-align:center; }
@media (max-width:1000px){
  .create-order-grid{flex-direction:column;}
  .create-order-right{width:100%;}
}
</style>

<!-- JS -->
<script>
document.addEventListener("DOMContentLoaded", function(){

  function parseFloatSafe(v){ return parseFloat(v)||0; }
  function fmt(n){ return Number(n||0).toFixed(2); }

  function updateRowSubtotal(row){
      if(!row) return;
      const persInput = row.querySelector(".pers-hours");
      if(persInput){
          const rate = parseFloatSafe(persInput.dataset.rate);
          const hours = parseFloatSafe(persInput.value);
          const persSub = row.querySelector(".pers-subtotal");
          if(persSub) persSub.textContent = (hours * rate).toFixed(2);
          return;
      }

      const input = row.querySelector(".qty-input");
      if(!input) return;
      const price = parseFloatSafe(input.dataset.price || input.dataset.rate);
      const qty = parseFloatSafe(input.value);
      const subtotalEl = row.querySelector(".row-subtotal, .equip-subtotal");
      if(subtotalEl) subtotalEl.textContent = (qty*price).toFixed(2);
  }

  function updateSummary(){
      let subtotal=0;
      document.querySelectorAll("tr").forEach(row=>{
          const subEl = row.querySelector(".row-subtotal, .pers-subtotal, .equip-subtotal");
          if(subEl) subtotal += parseFloatSafe(subEl.textContent);
      });

      // Include Other Expenses in subtotal
      document.querySelectorAll("input[name='other_expense_amount[]']").forEach((inp,i) => {
          const val = parseFloatSafe(inp.value);
          subtotal += val;
      });

      const tax = subtotal * 0.10;
      const grand = subtotal + tax;
      document.getElementById("subtotalDisplay").textContent = fmt(subtotal);
      document.getElementById("taxDisplay").textContent = fmt(tax);
      document.getElementById("grandDisplay").textContent = fmt(grand);

      // update orderSummary panel
      const summaryEl = document.getElementById('orderSummary');
      summaryEl.innerHTML = '';

      // Add normal items
      document.querySelectorAll("tr").forEach(row=>{
          const name = row.querySelector('td')?.textContent?.trim();
          const subEl = row.querySelector(".row-subtotal, .pers-subtotal, .equip-subtotal");
          if(name && subEl && parseFloatSafe(subEl.textContent) > 0){
              let qty = '';
              if(row.querySelector('.pers-hours')) qty = (row.querySelector('.pers-hours').value || '0') + ' hr';
              else {
                  const q = row.querySelector('.qty-input');
                  if(q) qty = q.value || '0';
              }
              const div = document.createElement('div');
              div.className = 'summary-item flex justify-between py-1';
              div.innerHTML = `<span style="color:#374151">${name}${qty?(' x '+qty):''}</span><span style="color:#111827">$${fmt(parseFloatSafe(subEl.textContent))}</span>`;
              summaryEl.appendChild(div);
          }
      });

      // Add Other Expenses
      document.querySelectorAll("input[name='other_expense_amount[]']").forEach((inp,i)=>{
          const val = parseFloatSafe(inp.value);
          if(val <= 0) return;
          const name = (document.querySelectorAll("input[name='other_expense_name[]']")[i]?.value || 'Other Expense').trim();
          const div = document.createElement('div');
          div.className = 'summary-item flex justify-between py-1';
          div.innerHTML = `<span style="color:#374151">${name}</span><span style="color:#111827">$${fmt(val)}</span>`;
          summaryEl.appendChild(div);
      });

      if(summaryEl.innerHTML.trim() === '') summaryEl.innerHTML = '<div class="empty-note">No items selected.</div>';
  }

  document.querySelectorAll(".qbtn, .qtbn").forEach(btn => {
      btn.addEventListener("click", () => {
          const input = btn.closest("td, div").querySelector("input");
          if (!input) return;
          let val = parseFloat(input.value) || 0;
          if (btn.classList.contains("plus") || btn.classList.contains("split-plus") || btn.classList.contains("ducted-plus") || btn.classList.contains("equip-plus") || btn.classList.contains("hour-plus")) val++;
          else if (btn.classList.contains("minus") || btn.classList.contains("split-minus") || btn.classList.contains("ducted-minus") || btn.classList.contains("equip-minus") || btn.classList.contains("hour-minus")) val = Math.max(0, val - 1);
          input.value = val;
          const row = input.closest("tr");
          updateRowSubtotal(row);
          updateSummary();
      });
  });

  document.querySelectorAll(".qty-input").forEach(input=>{
      input.addEventListener('input', ()=>{
          const row = input.closest("tr");
          updateRowSubtotal(row);
          updateSummary();
      });
  });

  document.getElementById("addExpenseBtn").addEventListener("click", function(){
      const container = document.getElementById("otherExpensesContainer");
      const div = document.createElement("div");
      div.className = "flex gap-2 mb-2";
      div.innerHTML = `
          <input type="text" name="other_expense_name[]" placeholder="Expense Name" class="input flex-1">
          <input type="number" name="other_expense_amount[]" value="0" class="input other-exp-amount w-24" step="0.01" min="0">
          <button type="button" class="qbtn remove-expense">X</button>
      `;
      container.appendChild(div);

      const amountInput = div.querySelector(".other-exp-amount");
      const removeBtn = div.querySelector(".remove-expense");

      amountInput.addEventListener("input", updateSummary);
      removeBtn.addEventListener("click", ()=>{ div.remove(); updateSummary(); });

      updateSummary();
  });

  function simpleSearch(inputId, tableSelector, cellSelector){
    const input=document.getElementById(inputId); if(!input) return;
    input.addEventListener("input", ()=>{
      const q=input.value.trim().toLowerCase();
      document.querySelectorAll(tableSelector+" tbody tr").forEach(row=>{
        const text=(row.querySelector(cellSelector)?.textContent||'').toLowerCase();
        row.style.display = text.includes(q)?'':'none';
      });
    });
  }
  simpleSearch("productSearch",".products-table","td.product-name");
  simpleSearch("splitSearch","#splitTable","td");
  simpleSearch("personnelSearch",".personnel-table","td");
  simpleSearch("equipmentSearch",".products-table","td");

  document.querySelectorAll("tr").forEach(row => updateRowSubtotal(row));
  updateSummary();
});

</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>

