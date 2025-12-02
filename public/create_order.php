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

/**
 * Helper: compute hours from H:i strings, handle wrap-midnight
 * returns decimal hours (float)
 */
function compute_hours_from_times(?string $startTime, ?string $endTime): float {
    if (!$startTime || !$endTime) return 0.0;
    // expected format "HH:MM"
    $s = DateTime::createFromFormat('H:i', $startTime);
    $e = DateTime::createFromFormat('H:i', $endTime);
    if (!$s || !$e) return 0.0;
    $diff = $s->diff($e);
    $hours = $diff->h + ($diff->i / 60.0);
    // if end is before start, treat as wrap-midnight
    if ($diff->invert === 1) {
        // invert flag means start > end, compute as 24 - hours
        $hours = 24 - $hours;
    }
    return round((float)$hours, 2);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    // Collect customer data
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? null);
    $contact_number = trim($_POST['contact_number'] ?? null);
    $job_address = trim($_POST['job_address'] ?? null);
    $appointment_date = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;

    // Build items array (normalized for insertion)
    $items = [];

     // PRODUCTS
    foreach($_POST['product'] ?? [] as $pid => $qty){
        $qty = intval($qty);
        if($qty>0){
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

    // SPLIT INSTALLATIONS (ENUM-safe)
    foreach($_POST['split'] ?? [] as $sid => $qty){
        $qty = intval($qty);
        if($qty>0){
            $stmt = $pdo->prepare("SELECT unit_price FROM split_installation WHERE id=? LIMIT 1");
            $stmt->execute([$sid]);
            $price = (float)$stmt->fetchColumn();
            $items[] = [
                'item_type' => 'installation',
                'item_id' => $sid,
                'installation_type' => null, // safe for ENUM
                'qty' => $qty,
                'price' => f2($price)
            ];
        }
    }

    // DUCTED INSTALLATIONS (must be ENUM: indoor/outdoor)
    foreach($_POST['ducted'] ?? [] as $did => $data){
        $qty = intval($data['qty'] ?? 0);
        $type = ($data['type'] ?? 'indoor');
        if($qty>0){
            $stmt = $pdo->prepare("SELECT total_cost FROM ductedinstallations WHERE id=? LIMIT 1");
            $stmt->execute([$did]);
            $price = (float)$stmt->fetchColumn();
            $items[] = [
                'item_type' => 'installation',
                'item_id' => $did,
                'installation_type' => in_array($type,['indoor','outdoor']) ? $type : 'indoor',
                'qty' => $qty,
                'price' => f2($price)
            ];
        }
    }

    // PERSONNEL
    // We expect hidden numeric hours inputs named personnel[ID] (set by JS from time_start/time_end)
    foreach($_POST['personnel'] ?? [] as $pid => $hours){
        $hours = floatval($hours);
        if($hours>0){
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
        if($qty>0){
            $stmt = $pdo->prepare("SELECT rate FROM equipment WHERE id=? LIMIT 1");
            $stmt->execute([$eid]);
            $rate = (float)$stmt->fetchColumn();
            $items[] = [
                'item_type' => 'product', // treat as product
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
    foreach($other_amounts as $i=>$amt){
        $amt = floatval($amt);
        $name = trim($other_names[$i] ?? '');
        if($amt>0){
            $items[] = [
                'item_type' => 'product',
                'item_id' => 0,
                'installation_type' => $name ?: 'Other expense',
                'qty' => 1,
                'price' => f2($amt)
            ];
        }
    }

    // Calculate totals
    $subtotal = 0.0;
    foreach($items as $it){
        $subtotal += ((float)$it['qty']) * ((float)$it['price']);
    }
    $tax = round($subtotal * 0.10, 2); // 10% tax
    $grand_total = round($subtotal + $tax, 2);
    $discount = 0.00;

    // Prepare order_number (unique)
    $order_number = 'ORD' . time() . rand(10,99);

    try{
        $pdo->beginTransaction();

        // Insert into orders table.
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

        // Insert each order item
        $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, installation_type, qty, price, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        foreach($items as $it){
            // ensure item_id is integer or NULL
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

        // Save dispatch schedule (separate table)
        // Expect arrays:
        // personnel_date[pid], time_start[pid], time_end[pid]
        if (!empty($_POST['personnel_date']) && is_array($_POST['personnel_date'])) {
            $stmt_dispatch = $pdo->prepare("INSERT INTO dispatch (order_id, personnel_id, date, time_start, time_end, hours) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($_POST['personnel_date'] as $pid => $date) {
                $pid = (int)$pid;
                $date = trim($date);
                $time_start = $_POST['time_start'][$pid] ?? null;
                $time_end   = $_POST['time_end'][$pid] ?? null;

                if ($date && $time_start && $time_end) {
                    $hours = compute_hours_from_times($time_start, $time_end);
                    // only insert if hours > 0
                    if ($hours > 0) {
                        $stmt_dispatch->execute([$order_id, $pid, $date, $time_start, $time_end, f2($hours)]);
                    }
                }
            }
        }

        $pdo->commit();
        // redirect to review page or show message
        header("Location: review_order.php?order_id=" . $order_id);
        exit;
    } catch(Exception $e){
        $pdo->rollBack();
        $message = 'Error saving order: '.$e->getMessage();
    }
}

ob_start();
?>

<?php if($message): ?>
<div class="alert" style="padding:10px;background:#fee;border:1px solid #fbb;margin-bottom:12px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" class="create-order-grid" id="orderForm" novalidate>
    <div class="flex-1 flex flex-col gap-6">
        <!-- Client Info -->
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
                    <?php foreach($split_installations as $s): $sid=(int)$s['id']; ?>
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
                    <?php foreach($ducted_installations as $d): $did=(int)$d['id']; ?>
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

        <!-- PERSONNEL -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
    <div class="flex items-center justify-between mb-3">
        <span class="font-medium text-gray-700">Personnel</span>
        <input id="personnelSearch" class="search-input" placeholder="Search personnel..." >
    </div>

    <div class="overflow-y-auto max-h-64 border rounded-lg">
        <table class="products-table w-full border-collapse text-sm">
            <thead class="bg-gray-100 sticky top-0">
                <tr>
                    <th class="p-2 text-left">Name</th>
                    <th class="p-2 text-center">Rate</th>
                    <th class="p-2 text-center">Time Start</th>
                    <th class="p-2 text-center">Time End</th>
                    <th class="p-2 text-center">Subtotal</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($personnel as $p): $pid=(int)$p['id']; ?>
                <tr class="personnel-row cursor-pointer hover:bg-gray-50" data-id="<?= $pid ?>">
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td>$<span class="pers-rate"><?= number_format($p['rate'],2) ?></span></td>
                    <td>
                        <input type="time"
                               name="time_start[<?= $pid ?>]"
                               class="time-start w-full border p-1 rounded"
                               data-pid="<?= $pid ?>">
                    </td>
                    <td>
                        <input type="time"
                               name="time_end[<?= $pid ?>]"
                               class="time-end w-full border p-1 rounded"
                               data-pid="<?= $pid ?>">
                    </td>
                    <td>$<span class="pers-subtotal" id="subtotal-<?= $pid ?>">0.00</span></td>

                    <!-- Hidden hours input so existing JS works (name preserved) -->
                    <input type="hidden" name="personnel[<?= $pid ?>]" value="0" class="qty-input hour-input personnel-hours-input" data-rate="<?= htmlspecialchars($p['rate']) ?>">
                </tr>

                <!-- Date row -->
                <tr>
                    <td colspan="5" class="bg-gray-50 p-3">
                        <label class="text-xs text-gray-500">Select Date</label>
                        <input type="date"
                               name="personnel_date[<?= $pid ?>]"
                               class="w-full border p-2 rounded">
                    </td>
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
                    <?php foreach($equipment as $e): $eid=(int)$e['id']; ?>
                        <tr>
                            <td><?= htmlspecialchars($e['name']) ?></td>
                            <td>$<span class="equip-rate"><?= number_format($e['rate'],2) ?></span></td>
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
    </div>

    <!-- RIGHT PANEL WRAPPER -->
    <div class="create-order-right" style="width:360px;">
        <!-- HIDDEN PROFIT CARD (required so layout does not error) -->
<div id="profitCard" class="hidden bg-white p-4 rounded-xl shadow border border-gray-200 mb-4">
    <h3 class="text-base font-semibold text-gray-700 mb-2">Profit Summary</h3>

    <div class="flex justify-between text-gray-600 mb-1">
        <span>Profit:</span><span>$<span id="profitDisplay">0.00</span></span>
    </div>

    <div class="flex justify-between text-gray-600 mb-1">
        <span>Percent Margin:</span><span><span id="profitMarginDisplay">0.00</span>%</span>
    </div>

    <div class="flex justify-between text-gray-600 mb-1">
        <span>Net Profit:</span><span><span id="netProfitDisplay">0.00</span>%</span>
    </div>

    <div class="flex justify-between font-semibold text-gray-700">
        <span>Total Profit:</span><span>$<span id="totalProfitDisplay">0.00</span></span>
    </div>
</div>

        <!-- SUMMARY CARD -->
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

<!-- small css to preserve sidebar layout/sticky behaviour -->
<style>
.create-order-grid { display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap; }
.create-order-right { position:sticky; top:24px; align-self:flex-start; }
.products-table td, .products-table th { vertical-align: middle; }
.empty-note { color:#7e8796; font-size:13px; text-align:center; padding:12px 0; }
.qty-box input.qty-input { width:72px; text-align:center; }
@media (max-width:1000px){
  .create-order-grid{flex-direction:column;}
  .create-order-right{position:relative; width:100%;}
}
</style>

<!-- Keep your JS logic for summary + plus/minus etc. -->
<script>
(function(){
  function fmt(n){return Number(n||0).toFixed(2);}

  function updateSummary(){
    let subtotal=0;
    const summaryEl=document.getElementById('orderSummary');
    summaryEl.innerHTML='';
    const allRows = document.querySelectorAll('input.qty-input');
    allRows.forEach(input=>{
      const row = input.closest('tr');
      const val = parseFloat(input.value) || 0;
      if(val > 0){
        let name = (row?.querySelector('td')?.textContent || '').trim();
        let price = parseFloat(input.dataset.price || input.dataset.rate) || 0;
        // If personnel hidden hour-input exists treat as personnel line
        if(row && row.querySelector('.personnel-hours-input')) {
          const hours = val;
          const lineTotal = price * hours;
          subtotal += lineTotal;
          const div = document.createElement('div');
          div.className='summary-item';
          div.innerHTML=`<span>${name} x ${hours} hr</span><span>$${fmt(lineTotal)}</span>`;
          summaryEl.appendChild(div);
        } else {
          const lineTotal = price * val;
          subtotal += lineTotal;
          const div = document.createElement('div');
          div.className='summary-item';
          div.innerHTML=`<span>${name} x ${val}</span><span>$${fmt(lineTotal)}</span>`;
          summaryEl.appendChild(div);
        }
      }
    });

    // Other expenses
    document.querySelectorAll('.other-expense-row').forEach(r=>{
      const name = r.querySelector('.expense-name')?.value || 'Other expense';
      const amt = parseFloat(r.querySelector('.expense-amount')?.value) || 0;
      if(amt>0){
        subtotal += amt;
        const div = document.createElement('div');
        div.className = 'summary-item';
        div.innerHTML = `<span>${name}</span><span>$${fmt(amt)}</span>`;
        summaryEl.appendChild(div);
      }
    });

    if(subtotal === 0){
      summaryEl.innerHTML = '<div class="empty-note">No items selected.</div>';
    }
    const tax = subtotal * 0.10;
    const grand = subtotal + tax;
    document.getElementById('subtotalDisplay').textContent = fmt(subtotal);
    document.getElementById('taxDisplay').textContent = fmt(tax);
    document.getElementById('grandDisplay').textContent = fmt(grand);
  }

  // wire plus/minus and inputs for numeric qty inputs
  document.querySelectorAll('input.qty-input').forEach(input=>{
    input.addEventListener('input', updateSummary);
    const row = input.closest('tr');
    if(row){
      row.querySelectorAll('.qbtn, .qtbn').forEach(btn=>{
        btn.addEventListener('click', ()=> {
          let val = parseFloat(input.value) || 0;
          if(btn.classList.contains('plus') || btn.classList.contains('split-plus') || btn.classList.contains('ducted-plus') || btn.classList.contains('hour-plus') || btn.classList.contains('equip-plus')) {
            val++;
          } else if(btn.classList.contains('minus') || btn.classList.contains('split-minus') || btn.classList.contains('ducted-minus') || btn.classList.contains('hour-minus') || btn.classList.contains('equip-minus')) {
            val = Math.max(0, val-1);
          }
          input.value = val;
          updateSummary();
        });
      });
    }
  });

  // personnel row toggle (keeps behavior similar to before, but we don't require click to open time inputs)
  document.querySelectorAll(".personnel-row").forEach(row => {
    row.addEventListener("click", function(e) {
        // ignore clicks on inputs/buttons
        if (e.target.tagName === "INPUT" || e.target.tagName === "BUTTON" || e.target.tagName === "SELECT") return;
        const id = this.dataset.id;
        // find the next date row and toggle highlight (not hiding fields now)
        const dateRow = this.parentElement.querySelector(`input[name='personnel_date[${id}]']`);
        if(dateRow) {
          // focus date as a simple visual interaction
          dateRow.focus();
        }
    });
  });

  // time inputs: compute hours and update hidden hour-input & subtotal cell
  function computeHoursFromStrings(start, end) {
    if(!start || !end) return 0;
    // create Date objects on epoch day
    const s = new Date(`1970-01-01T${start}:00`);
    const e = new Date(`1970-01-01T${end}:00`);
    let diffMs = e - s;
    if (diffMs < 0) diffMs += 24 * 3600 * 1000; // wrap midnight
    const hours = diffMs / (1000 * 60 * 60);
    return Math.round(hours * 100) / 100;
  }

  document.querySelectorAll('.time-start, .time-end').forEach(input=>{
    input.addEventListener('change', function(){
      const pid = this.dataset.pid;
      const start = document.querySelector(`input[name="time_start[${pid}]"]`).value;
      const end = document.querySelector(`input[name="time_end[${pid}]"]`).value;
      const rateEl = document.querySelector(`.personnel-row[data-id='${pid}'] .pers-rate`);
      const rate = rateEl ? parseFloat(rateEl.textContent) : 0;

      const hours = computeHoursFromStrings(start, end);
      // update hidden hour input (name preserved so server receives personnel[pid])
      const hiddenHourInput = document.querySelector(`input[name="personnel[${pid}]"]`);
      if(hiddenHourInput){
        hiddenHourInput.value = hours.toFixed(2);
        // update subtotal cell
        const subtotalEl = document.getElementById(`subtotal-${pid}`);
        if(subtotalEl){
          subtotalEl.textContent = (hours * rate).toFixed(2);
        }
        updateSummary();
      }
    });
  });

  // Add expense row handler
  document.getElementById('addExpenseBtn').addEventListener('click', function(){
    const row = document.createElement('div');
    row.className = 'other-expense-row';
    row.style.display='flex'; row.style.gap='8px'; row.style.marginBottom='8px';
    row.innerHTML = '<input type="text" placeholder="Name" name="other_expense_name[]" class="input expense-name" style="flex:1;">' +
                    '<input type="number" placeholder="Amount" name="other_expense_amount[]" class="input expense-amount" style="width:110px;">' +
                    '<button type="button" class="qbtn remove-expense">x</button>';
    document.getElementById('otherExpensesContainer').appendChild(row);
    row.querySelector('.expense-amount').addEventListener('input', updateSummary);
    row.querySelector('.remove-expense').addEventListener('click', ()=>{ row.remove(); updateSummary(); });
  });

  // initial summary
  updateSummary();

  // small live search filters (product/split/personnel/equipment)
  function simpleSearch(inputId, tableSelector, cellSelector){
    const input = document.getElementById(inputId);
    if(!input) return;
    input.addEventListener('input', ()=> {
      const q = input.value.trim().toLowerCase();
      document.querySelectorAll(tableSelector + ' tbody tr').forEach(row=>{
        const text = (row.querySelector(cellSelector)?.textContent || '').toLowerCase();
        row.style.display = text.indexOf(q) === -1 ? 'none' : '';
      });
    });
  }
  simpleSearch('productSearch', '.products-table', 'td.product-name');
  simpleSearch('splitSearch', '#splitTable', 'td');
  simpleSearch('personnelSearch', '.products-table', 'td');
  simpleSearch('equipmentSearch', '.products-table', 'td');

  // (optional) profit card logic left inactive because server computes profit/reporting on review page

})();
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
