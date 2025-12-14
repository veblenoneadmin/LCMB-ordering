<?php
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

$message = '';

// safe fetch helper
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name    = trim($_POST['customer_name'] ?? '');
    $customer_email   = trim($_POST['customer_email'] ?? '');
    $contact_number   = trim($_POST['contact_number'] ?? '');
    $job_address      = trim($_POST['job_address'] ?? '');
    $appointment_date = $_POST['appointment_date'] ?? null;

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

    // DUCTED INSTALLATIONS (with type)
    foreach ($_POST['ducted'] ?? [] as $did => $data) {
        $qty = intval($data['qty'] ?? 0);
        $type = $data['type'] ?? 'indoor';
        if ($qty > 0) {
            $type = in_array($type, ['indoor', 'outdoor']) ? $type : 'indoor';
            $stmt = $pdo->prepare("SELECT total_cost FROM ductedinstallations WHERE id=? LIMIT 1");
            $stmt->execute([$did]);
            $price = (float)$stmt->fetchColumn();
            $items[] = [
                'item_category' => 'ducted',
                'item_id' => $did,
                'installation_type' => $type,
                'qty' => $qty,
                'price' => $price,
                'description' => null
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
                'item_category' => 'equipment',
                'item_id' => $eid,
                'installation_type' => null,
                'qty' => $qty,
                'price' => $rate,
                'description' => null
            ];
        }
    }

    // OTHER EXPENSES
    // -> collect into $items array so they appear in summary and totals
    $other_names = $_POST['other_expense_name'] ?? [];
    $other_amounts = $_POST['other_expense_amount'] ?? [];
    foreach ($other_amounts as $i => $amt) {
        $amt = floatval($amt);
        $name = trim($other_names[$i] ?? '');
        if ($amt > 0) {
            $items[] = [
                'item_category' => 'expense',
                'item_id' => 0,
                'installation_type' => null,
                'qty' => 1,
                'price' => $amt,
                'description' => $name !== '' ? $name : 'Other expense'
            ];
        }
    }

    // PERSONNEL (qty=hours, also prepare dispatch rows)
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
            'item_category' => 'personnel',
            'item_id' => $pid,
            'installation_type' => null,
            'qty' => $hours,
            'price' => $rate,
            'description' => null
        ];
    }
    

    // Totals
    $subtotal = 0.0;
    foreach ($items as $it) $subtotal += $it['qty'] * $it['price'];
    $tax = round($subtotal * 0.10, 2);
    $grand_total = round($subtotal + $tax, 2);
    $discount = 0.00;
    $order_number = 'ORD' . time() . rand(10,99);

    try {
        $pdo->beginTransaction();

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
            f2($subtotal),
            $order_number,
            'pending',
            f2($grand_total),
            f2($tax),
            f2($discount)
        ]);
        $order_id = $pdo->lastInsertId();

        // Insert order_items (do NOT insert generated column line_total)
        // include description column (nullable) so other expenses' name is saved
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

       // Insert dispatch rows for personnel
if (!empty($personnel_dispatch_rows)) {
    $stmt_dispatch = $pdo->prepare("
        INSERT INTO dispatch (order_id, personnel_id, date, hours, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    foreach ($personnel_dispatch_rows as $r) {
        $d = $r['date'] ?: date('Y-m-d'); // fallback
        $stmt_dispatch->execute([
            $order_id,
            $r['personnel_id'],
            $d,
            f2($r['hours'])
        ]);
    }
}



        $pdo->commit();
        header("Location: review_order.php?order_id=" . $order_id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error saving order: ' . $e->getMessage();
    }
} // end POST

// RENDER FORM (kept your HTML structure intact)
ob_start();
?>

<?php if ($message): ?>
    <div class="alert" style="padding:10px;background:#fee;border:1px solid #fbb;margin-bottom:12px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" class="create-order-grid" id="orderForm" novalidate>
<div class="flex-1 flex flex-col gap-6">

<!-- CLIENT INFO -->
<div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
  <h5 class="text-lg font-medium text-gray-700 mb-4">Client Information</h5>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    
    <!-- Name -->
    <div class="relative">
      <input type="text" name="customer_name" id="customer_name" placeholder=" " 
             class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition" required>
      <label for="customer_name" 
             class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">
        Name
      </label>
    </div>

    <!-- Email -->
    <div class="relative">
      <input type="email" name="customer_email" id="customer_email" placeholder=" " 
             class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
      <label for="customer_email" 
             class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">
        Email
      </label>
    </div>

    <!-- Phone -->
    <div class="relative">
      <input type="text" name="contact_number" id="contact_number" placeholder=" " 
             class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
      <label for="contact_number" 
             class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">
        Phone
      </label>
    </div>

    <!-- Address -->
    <div class="relative">
      <input type="text" name="job_address" id="job_address" placeholder=" " 
             class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
      <label for="job_address" 
             class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">
        Address
      </label>
    </div>

    <!-- Appointment Date -->
    <div class="relative">
      <input type="date" name="appointment_date" id="appointment_date" value="<?= date('Y-m-d') ?>" 
             class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
      <label for="appointment_date" 
             class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">
        Appointment Date
      </label>
    </div>

  </div>
</div>



<!-- PRODUCTS TABLE -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
<div class="flex items-center justify-between mb-3">
<span class="font-medium text-gray-700">Products</span>
<input id="productSearch" class="search-input" placeholder="Search products..." >
</div>
<div class="overflow-y-auto max-h-64 border rounded-lg">
<table class="products-table w-full border-collapse text-sm">
<thead class="bg-gray-100 sticky top-0"><tr><th>Name</th><th class="p-2 text-center">Price</th><th class="p-2 text-center">Qty</th><th class="p-2 text-center">Subtotal</th></tr></thead>
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

<!-- DUCTED INSTALLATIONS -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Ducted Installations</span>
    <input type="text" class="search-input" placeholder="Search..." >
  </div>
  <div class="overflow-y-auto max-h-64 border rounded-lg">
    <table class="w-full text-sm border-collapse">
      <thead class="bg-gray-100 sticky top-0">
        <tr>
          <th>Name</th>
          <th class="text-center">Price</th>
          <th class="text-center">Qty</th>
          <th class="text-center">Type</th>
          <th class="text-center">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($ducted_installations as $d): $did=(int)$d['id']; ?>
        <tr>
          <td><?= htmlspecialchars($d['name']) ?></td>
          <td class="text-center">$<?= number_format($d['price'],2) ?></td>
          <td class="text-center">
            <div class="qty-wrapper">
              <button type="button" class="qtbn ducted-minus">-</button>
              <input type="number" min="0" value="0" name="ducted[<?= $did ?>][qty]" class="qty-input ducted-qty" data-price="<?= htmlspecialchars($d['price']) ?>">
              <button type="button" class="qtbn ducted-plus">+</button>
            </div>
          </td>
          <td class="text-center">
            <select name="ducted[<?= $did ?>][type]" class="installation-type">
              <option value="indoor">Indoor</option>
              <option value="outdoor">Outdoor</option>
            </select>
          </td>
          <td class="text-center">$<span class="row-subtotal">0.00</span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- SPLIT INSTALLATIONS -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Split Installations</span>
    <input id="splitSearch" class="search-input" placeholder="Search split systems..." >
  </div>
  <div class="overflow-y-auto max-h-64 border rounded-lg">
    <table id="splitTable" class="products-table w-full border-collapse text-sm">
      <thead><tr><th>Name</th><th class="text-center">Unit Price</th><th class="text-center">Qty</th><th class="text-center">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($split_installations as $s): $sid=(int)$s['id']; ?>
        <tr>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td class="text-center">$<?= number_format($s['price'],2) ?></td>
          <td class="text-center">
            <div class="qty-wrapper">
              <button type="button" class="qtbn split-minus">-</button>
              <input type="number" min="0" value="0" name="split[<?= $sid ?>]" class="qty-input split-qty" data-price="<?= htmlspecialchars($s['price']) ?>">
              <button type="button" class="qtbn split-plus">+</button>
            </div>
          </td>
          <td class="text-center">$<span class="row-subtotal">0.00</span></td>
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
<thead><tr><th>Item</th><th class="text-center">Rate</th><th class="text-center">Qty</th><th class="text-center">Subtotal</th></tr></thead>
<tbody>
<?php foreach ($equipment as $e): $eid=(int)$e['id']; ?>
<tr>
<td><?= htmlspecialchars($e['name']) ?></td>
<td class="text-center">$<?= number_format($e['rate'],2) ?></td>
<td class="text-center">
<div class="qty-wrapper">
<button type="button" class="qtbn equip-minus">-</button>
<input type="number" min="0" value="0" name="equipment[<?= $eid ?>]" class="qty-input equip-input" data-price="<?= htmlspecialchars($e['rate']) ?>">
<button type="button" class="qtbn equip-plus">+</button>
</div>
</td>
<td class="text-center">$<span class="row-subtotal">0.00</span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

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

<style>
.create-order-grid {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    flex-wrap: wrap;
}
.create-order-right {
    position: sticky;
    top: 24px;
    width: 360px;
    flex-shrink: 0;
    align-self: flex-start;
}
.products-table td, .products-table th { vertical-align: middle; }
.empty-note { color:#7e8796; font-size:13px; text-align:center; padding:12px 0; }
.qty-wrapper input { width:72px; text-align:center; }
@media (max-width:1000px){
  .create-order-grid{flex-direction:column;}
  .create-order-right{width:100%;}
}
</style>


<script>
document.addEventListener("DOMContentLoaded", function(){

  function parseFloatSafe(v){ return parseFloat(v) || 0; }
  function fmt(n){ return Number(n||0).toFixed(2); }

  function updateRowSubtotal(row){
      if(!row) return;

      // Personnel
      const persInput = row.querySelector(".pers-hours");
      if(persInput){
          const rate = parseFloatSafe(persInput.dataset.rate);
          const hours = parseFloatSafe(persInput.value);
          const sub = row.querySelector(".pers-subtotal");
          if(sub) sub.textContent = fmt(hours * rate);
          return;
      }

      // Products / split / ducted / equipment
      const input = row.querySelector(".qty-input");
      if(!input) return;
      const price = parseFloatSafe(input.dataset.price);
      const qty = parseFloatSafe(input.value);
      const sub = row.querySelector(".row-subtotal");
      if(sub) sub.textContent = fmt(price * qty);
  }

  function updateSummary(){
      let subtotal = 0;

      document.querySelectorAll(".row-subtotal, .pers-subtotal").forEach(el => {
          subtotal += parseFloatSafe(el.textContent);
      });

      document.querySelectorAll("input[name='other_expense_amount[]']").forEach(inp => {
          subtotal += parseFloatSafe(inp.value);
      });

      const tax = subtotal * 0.10;
      const grand = subtotal + tax;

      document.getElementById("subtotalDisplay").textContent = fmt(subtotal);
      document.getElementById("taxDisplay").textContent = fmt(tax);
      document.getElementById("grandDisplay").textContent = fmt(grand);

      const summaryEl = document.getElementById("orderSummary");
      summaryEl.innerHTML = "";

      document.querySelectorAll("tr").forEach(row => {
          const name = row.querySelector("td")?.textContent?.trim();
          const subEl = row.querySelector(".row-subtotal, .pers-subtotal");
          if(!name || !subEl) return;

          const amount = parseFloatSafe(subEl.textContent);
          if(amount <= 0) return;

          let qty = "";
          if(row.querySelector(".pers-hours")){
              qty = row.querySelector(".pers-hours").value + " hr";
          } else if(row.querySelector(".qty-input")){
              qty = row.querySelector(".qty-input").value;
          }

          const div = document.createElement("div");
          div.className = "summary-item flex justify-between py-1";
          div.innerHTML =
              `<span>${name}${qty ? " x " + qty : ""}</span>
               <span>$${fmt(amount)}</span>`;
          summaryEl.appendChild(div);
      });

      if(summaryEl.innerHTML.trim() === "")
          summaryEl.innerHTML = "<div class='empty-note'>No items selected.</div>";
  }

  /* ==================================
     FIX: ALL qtbn + / - BUTTONS
     ================================== */
  document.querySelectorAll(".qtbn").forEach(btn => {
      btn.addEventListener("click", () => {

          const wrapper = btn.closest(".qty-wrapper");
          if (!wrapper) return;

          const input = wrapper.querySelector("input");
          if (!input) return;

          let val = parseFloat(input.value) || 0;

          if (
              btn.classList.contains("plus") ||
              btn.classList.contains("split-plus") ||
              btn.classList.contains("ducted-plus") ||
              btn.classList.contains("equip-plus") ||
              btn.classList.contains("hour-plus")
          ) {
              val++;
          }
          else if (
              btn.classList.contains("minus") ||
              btn.classList.contains("split-minus") ||
              btn.classList.contains("ducted-minus") ||
              btn.classList.contains("equip-minus") ||
              btn.classList.contains("hour-minus")
          ) {
              val = Math.max(0, val - 1);
          } else {
              return;
          }

          input.value = val;

          updateRowSubtotal(input.closest("tr"));
          updateSummary();
      });
  });

  /* ==================================
     DIRECT INPUT CHANGES
     ================================== */
  document.querySelectorAll(".qty-input, .pers-hours").forEach(input => {
      input.addEventListener("input", () => {
          updateRowSubtotal(input.closest("tr"));
          updateSummary();
      });
  });

  /* ==================================
     FIX: PERSONNEL DATE PICKER
     ================================== */
  if (typeof flatpickr !== "undefined") {
      document.querySelectorAll(".personnel-date").forEach(input => {
          input.type = "text"; // important
          flatpickr(input, {
              dateFormat: "Y-m-d",
              allowInput: true
          });
      });
  }

  /* ==================================
     OTHER EXPENSES
     ================================== */
  document.getElementById("addExpenseBtn")?.addEventListener("click", function(){
      const container = document.getElementById("otherExpensesContainer");
      const div = document.createElement("div");
      div.className = "flex gap-2 mb-2";
      div.innerHTML = `
        <input type="text" name="other_expense_name[]" placeholder="Expense Name" class="input flex-1">
        <input type="number" name="other_expense_amount[]" value="0" step="0.01" min="0" class="input w-28">
        <button type="button" class="qbtn remove-expense">Remove</button>
      `;
      container.appendChild(div);

      div.querySelector(".remove-expense").addEventListener("click", () => {
          div.remove();
          updateSummary();
      });

      div.querySelector("input[name='other_expense_amount[]']").addEventListener("input", updateSummary);
      div.querySelector("input[name='other_expense_name[]']").addEventListener("input", updateSummary);
  });

  /* ==================================
     INITIAL CALC
     ================================== */
  document.querySelectorAll("tr").forEach(row => updateRowSubtotal(row));
  updateSummary();

});
</script>



<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
