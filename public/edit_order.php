<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if ($order_id <= 0) die("<h2 style='color:red;padding:20px;'>Invalid order id</h2>");

// fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) die("<h2 style='color:red;padding:20px;'>❌ Order not found or has been deleted.</h2>");

// fetch order_items
$stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$order_id]);
$itemsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// helper to load lists (for add-new rows)
$products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$split_installations = $pdo->query("SELECT id, item_name, unit_price FROM split_installation ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$ducted_list = $pdo->query("SELECT id, equipment_name, total_cost FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$personnel_list = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipment_list = $pdo->query("SELECT id, item, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC);

// Group existing items
$grouped = [
    'product' => [],
    'split' => [],
    'ducted' => [],
    'personnel' => [],
    'equipment' => [],
    'expense' => []
];

foreach ($itemsRaw as $it) {
    $cat = strtolower(trim($it['item_category'] ?? 'expense'));
    $item = $it;

    // fallback price/name logic: prefer order_items stored price then lookup
    switch ($cat) {
        case 'product':
            $s = $pdo->prepare("SELECT name, price FROM products WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['name'] ?? 'Product';
            $item['price'] = isset($it['price']) && $it['price']>0 ? $it['price'] : ($row['price'] ?? 0);
            break;
        case 'split':
            $s = $pdo->prepare("SELECT item_name, unit_price FROM split_installation WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['item_name'] ?? 'Split Installation';
            $item['price'] = isset($it['price']) && $it['price']>0 ? $it['price'] : ($row['unit_price'] ?? 0);
            break;
        case 'ducted':
            $s = $pdo->prepare("SELECT equipment_name, total_cost FROM ductedinstallations WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['equipment_name'] ?? 'Ducted Installation';
            if (!empty($it['installation_type'])) $item['name'] .= ' (' . htmlspecialchars($it['installation_type']) . ')';
            $item['price'] = isset($it['price']) && $it['price']>0 ? $it['price'] : ($row['total_cost'] ?? 0);
            break;
        case 'personnel':
            $s = $pdo->prepare("SELECT name, rate FROM personnel WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['name'] ?? 'Personnel';
            $item['price'] = isset($it['price']) && $it['price']>0 ? $it['price'] : ($row['rate'] ?? 0);
            // fetch dispatch date/hours from dispatch table if present
            $d = $pdo->prepare("SELECT date, hours FROM dispatch WHERE order_id=? AND personnel_id=? LIMIT 1");
            $d->execute([$order_id, $it['item_id']]);
            $dr = $d->fetch(PDO::FETCH_ASSOC);
            if ($dr) {
                $item['dispatch_date'] = $dr['date'];
                $item['dispatch_hours'] = $dr['hours'];
            } else {
                $item['dispatch_date'] = '';
                $item['dispatch_hours'] = $it['qty'];
            }
            break;
        case 'equipment':
            $s = $pdo->prepare("SELECT item, rate FROM equipment WHERE id=? LIMIT 1");
            $s->execute([$it['item_id']]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            $item['name'] = $row['item'] ?? 'Equipment';
            $item['price'] = isset($it['price']) && $it['price']>0 ? $it['price'] : ($row['rate'] ?? 0);
            break;
        default:
            $item['name'] = $it['installation_type'] ?? ($it['description'] ?? 'Other Expense');
            $item['price'] = isset($it['price']) ? $it['price'] : 0;
            break;
    }

    $item['qty'] = $item['qty'] ?? 1;
    $item['line_total'] = ($item['line_total'] ?? ($item['qty'] * $item['price']));

    if (!isset($grouped[$cat])) $grouped['expense'][] = $item;
    else $grouped[$cat][] = $item;
}

// Calculate totals
$subtotal = 0;
foreach ($grouped as $g) {
    foreach ($g as $it) $subtotal += ($it['line_total'] ?? 0);
}
$tax = round($subtotal * 0.10, 2);
$grand_total = round($subtotal + $tax, 2);

ob_start();
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<form method="post" action="save_edit_order.php" id="editOrderForm">
<input type="hidden" name="order_id" value="<?= $order_id ?>">

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100 flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-semibold text-gray-800">Edit Order #<?= htmlspecialchars($order_id) ?></h2>
        <p class="text-gray-500 text-sm mt-1">Verify and edit all order details before saving.</p>
      </div>
      <a href="review_order.php?order_id=<?= $order_id ?>" class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700">Back to Review</a>
    </div>

    <!-- CLIENT INFO -->
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
      <h3 class="text-lg font-semibold mb-4 text-gray-700">Client Information</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="text-gray-500 text-sm font-medium">Customer Name</label>
          <input type="text" name="customer_name" value="<?= htmlspecialchars($order['customer_name']) ?>" class="mt-1 p-3 bg-gray-50 rounded-xl border w-full">
        </div>
        <div>
          <label class="text-gray-500 text-sm font-medium">Order Date</label>
          <input type="date" name="appointment_date" value="<?= htmlspecialchars($order['appointment_date']) ?>" class="mt-1 p-3 bg-gray-50 rounded-xl border w-full">
        </div>
        <div>
          <label class="text-gray-500 text-sm font-medium">Email</label>
          <input type="email" name="customer_email" value="<?= htmlspecialchars($order['customer_email']) ?>" class="mt-1 p-3 bg-gray-50 rounded-xl border w-full">
        </div>
        <div>
          <label class="text-gray-500 text-sm font-medium">Phone</label>
          <input type="text" name="contact_number" value="<?= htmlspecialchars($order['contact_number']) ?>" class="mt-1 p-3 bg-gray-50 rounded-xl border w-full">
        </div>
        <div class="md:col-span-2">
          <label class="text-gray-500 text-sm font-medium">Address</label>
          <input type="text" name="job_address" value="<?= htmlspecialchars($order['job_address']) ?>" class="mt-1 p-3 bg-gray-50 rounded-xl border w-full">
        </div>
      </div>
    </div>

    <!-- EXISTING ITEMS (editable) -->
    <?php
    $titles = [
      'product' => 'Ordered Products',
      'split' => 'Split Installations',
      'ducted' => 'Ducted Installations',
      'personnel' => 'Personnel',
      'equipment' => 'Equipment',
      'expense' => 'Other Expenses'
    ];
    foreach ($titles as $key => $title):
      if (!empty($grouped[$key])):
    ?>
      <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
        <h3 class="text-lg font-semibold mb-4 text-gray-700"><?= $title ?></h3>
        <div class="overflow-auto rounded-xl border border-gray-200">
          <table class="w-full text-sm">
            <thead class="bg-gray-100 text-gray-700">
              <tr>
                <th class="px-4 py-3 text-left">Item</th>
                <th class="px-4 py-3 text-center">Price</th>
                <th class="px-4 py-3 text-center">Qty / Hours</th>
                <?php if ($key === 'personnel'): ?><th class="px-4 py-3 text-center">Date</th><?php endif; ?>
                <th class="px-4 py-3 text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($grouped[$key] as $it): ?>
                <tr class="border-t hover:bg-gray-50">
                  <td class="px-4 py-3 text-left"><?= htmlspecialchars($it['name']) ?></td>

                  <!-- price and qty inputs reference order_items.id -->
                  <td class="px-4 py-3 text-center">
                    <input type="number" step="0.01" name="price[<?= $it['id'] ?>]" value="<?= number_format($it['price'],2,'.','') ?>" class="w-28 border rounded px-2 py-1 text-center existing-price" data-id="<?= $it['id'] ?>">
                  </td>

                  <td class="px-4 py-3 text-center">
                    <input type="number" step="0.01" min="0" name="qty[<?= $it['id'] ?>]" value="<?= $it['qty'] ?>" class="w-20 border rounded px-2 py-1 text-center existing-qty" data-id="<?= $it['id'] ?>">
                  </td>

                  <?php if ($key === 'personnel'): ?>
                    <td class="px-4 py-3 text-center">
                      <input type="text" name="personnel_date[<?= $it['id'] ?>]" value="<?= htmlspecialchars($it['dispatch_date'] ?? '') ?>" class="personnel-date-input w-36 border rounded px-2 py-1 text-center" placeholder="YYYY-MM-DD">
                    </td>
                  <?php endif; ?>

                  <td class="px-4 py-3 text-right font-medium existing-line-total" data-id="<?= $it['id'] ?>"><?= number_format($it['line_total'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php
      endif;
    endforeach;
    ?>

    <!-- ===== ADD NEW ITEMS AREA ===== -->

    <!-- Add Products -->
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
      <div class="flex justify-between items-center mb-3">
        <h3 class="text-lg font-semibold text-gray-700">Add Products</h3>
        <button type="button" class="qbtn add-product-row">Add Product</button>
      </div>

      <div id="newProductsContainer"></div>

      <!-- template -->
      <template id="productRowTpl">
        <div class="flex gap-2 items-center mb-2 product-row">
          <input list="productList" class="w-1/2 border rounded px-2 py-1 product-select" placeholder="Search product by name..." />
          <datalist id="productList">
            <?php foreach($products as $p): ?>
              <option data-price="<?= htmlspecialchars($p['price']) ?>" value="<?= htmlspecialchars($p['id'].'|'.$p['name']) ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </datalist>
          <input type="number" step="0.01" name="new_product_price[]" placeholder="Price" class="w-24 border rounded px-2 py-1">
          <input type="number" step="1" name="new_product_qty[]" value="1" class="w-20 border rounded px-2 py-1">
          <button type="button" class="qbtn remove-row">Remove</button>
          <input type="hidden" name="new_product_id[]" class="new_product_id">
        </div>
      </template>
    </div>

    <!-- Add Split -->
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
      <div class="flex justify-between items-center mb-3">
        <h3 class="text-lg font-semibold text-gray-700">Add Split Installations</h3>
        <button type="button" class="qbtn add-split-row">Add Split</button>
      </div>

      <div id="newSplitContainer"></div>

      <template id="splitRowTpl">
        <div class="flex gap-2 items-center mb-2 split-row">
          <input list="splitList" class="w-1/2 border rounded px-2 py-1 split-select" placeholder="Search split system..." />
          <datalist id="splitList">
            <?php foreach($split_installations as $s): ?>
              <option data-price="<?= htmlspecialchars($s['unit_price']) ?>" value="<?= htmlspecialchars($s['id'].'|'.$s['item_name']) ?>"><?= htmlspecialchars($s['item_name']) ?></option>
            <?php endforeach; ?>
          </datalist>
          <input type="number" step="0.01" name="new_split_price[]" placeholder="Price" class="w-24 border rounded px-2 py-1">
          <input type="number" step="1" name="new_split_qty[]" value="1" class="w-20 border rounded px-2 py-1">
          <button type="button" class="qbtn remove-row">Remove</button>
          <input type="hidden" name="new_split_id[]" class="new_split_id">
        </div>
      </template>
    </div>

    <!-- Add Ducted -->
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
      <div class="flex justify-between items-center mb-3">
        <h3 class="text-lg font-semibold text-gray-700">Add Ducted Installations</h3>
        <button type="button" class="qbtn add-ducted-row">Add Ducted</button>
      </div>

      <div id="newDuctedContainer"></div>

      <template id="ductedRowTpl">
        <div class="flex gap-2 items-center mb-2 ducted-row">
          <input list="ductedList" class="w-1/2 border rounded px-2 py-1 ducted-select" placeholder="Search ducted..." />
          <datalist id="ductedList">
            <?php foreach($ducted_list as $d): ?>
              <option data-price="<?= htmlspecialchars($d['total_cost']) ?>" value="<?= htmlspecialchars($d['id'].'|'.$d['equipment_name']) ?>"><?= htmlspecialchars($d['equipment_name']) ?></option>
            <?php endforeach; ?>
          </datalist>
          <select name="new_ducted_type[]" class="w-28 border rounded px-2 py-1">
            <option value="indoor">Indoor</option>
            <option value="outdoor">Outdoor</option>
          </select>
          <input type="number" step="0.01" name="new_ducted_price[]" placeholder="Price" class="w-24 border rounded px-2 py-1">
          <input type="number" step="1" name="new_ducted_qty[]" value="1" class="w-20 border rounded px-2 py-1">
          <button type="button" class="qbtn remove-row">Remove</button>
          <input type="hidden" name="new_ducted_id[]" class="new_ducted_id">
        </div>
      </template>
    </div>

    <!-- Add Personnel -->
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
      <div class="flex justify-between items-center mb-3">
        <h3 class="text-lg font-semibold text-gray-700">Add Personnel</h3>
        <button type="button" class="qbtn add-personnel-row">Add Personnel</button>
      </div>

      <div id="newPersonnelContainer"></div>

      <template id="personnelRowTpl">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-center mb-2 personnel-row">
          <input list="personnelList" class="col-span-2 border rounded px-2 py-1 personnel-select" placeholder="Search personnel...">
          <datalist id="personnelList">
            <?php foreach($personnel_list as $p): ?>
              <option data-rate="<?= htmlspecialchars($p['rate']) ?>" value="<?= htmlspecialchars($p['id'].'|'.$p['name']) ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </datalist>

          <input type="date" name="new_personnel_date[]" class="border rounded px-2 py-1">
          <input type="time" name="new_personnel_start[]" class="border rounded px-2 py-1">
          <input type="time" name="new_personnel_end[]" class="border rounded px-2 py-1">
          <input type="number" step="0.01" name="new_personnel_rate[]" placeholder="Rate" class="w-24 border rounded px-2 py-1">
          <input type="hidden" name="new_personnel_id[]" class="new_personnel_id">
          <button type="button" class="qbtn remove-row col-span-full md:col-auto">Remove</button>
        </div>
      </template>
    </div>

    <!-- Add Equipment -->
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
      <div class="flex justify-between items-center mb-3">
        <h3 class="text-lg font-semibold text-gray-700">Add Equipment</h3>
        <button type="button" class="qbtn add-equipment-row">Add Equipment</button>
      </div>

      <div id="newEquipmentContainer"></div>

      <template id="equipmentRowTpl">
        <div class="flex gap-2 items-center mb-2 equipment-row">
          <input list="equipmentList" class="w-1/2 border rounded px-2 py-1 equipment-select" placeholder="Search equipment...">
          <datalist id="equipmentList">
            <?php foreach($equipment_list as $e): ?>
              <option data-rate="<?= htmlspecialchars($e['rate']) ?>" value="<?= htmlspecialchars($e['id'].'|'.$e['item']) ?>"><?= htmlspecialchars($e['item']) ?></option>
            <?php endforeach; ?>
          </datalist>
          <input type="number" step="0.01" name="new_equipment_price[]" placeholder="Price" class="w-24 border rounded px-2 py-1">
          <input type="number" step="1" name="new_equipment_qty[]" value="1" class="w-20 border rounded px-2 py-1">
          <button type="button" class="qbtn remove-row">Remove</button>
          <input type="hidden" name="new_equipment_id[]" class="new_equipment_id">
        </div>
      </template>
    </div>

    <!-- Add Expense -->
    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-100">
      <div class="flex justify-between items-center mb-3">
        <h3 class="text-lg font-semibold text-gray-700">Add Expense</h3>
        <button type="button" class="qbtn add-expense-row">Add Expense</button>
      </div>

      <div id="newExpenseContainer"></div>

      <template id="expenseRowTpl">
        <div class="flex gap-2 items-center mb-2 expense-row">
          <input type="text" name="new_expense_name[]" placeholder="Expense name" class="w-1/2 border rounded px-2 py-1">
          <input type="number" step="0.01" name="new_expense_price[]" placeholder="Price" class="w-24 border rounded px-2 py-1">
          <button type="button" class="qbtn remove-row">Remove</button>
        </div>
      </template>
    </div>

  </div>

  <!-- RIGHT SUMMARY -->
  <div class="flex flex-col w-80 gap-4">
    <div class="bg-white p-6 rounded-2xl shadow border border-gray-200 h-auto max-h-[80vh] flex flex-col">
      <div class="flex justify-between text-gray-700">
        <span>Subtotal</span>
        <span id="subtotalDisplay"><?= number_format($subtotal,2) ?></span>
      </div>
      <div class="flex justify-between text-gray-700">
        <span>Tax (10%)</span>
        <span id="taxDisplay"><?= number_format($tax,2) ?></span>
      </div>
      <div class="flex justify-between font-semibold text-gray-900 text-base border-t pt-3">
        <span>Grand Total</span>
        <span id="grandDisplay"><?= number_format($grand_total,2) ?></span>
      </div>

      <div class="mt-4">
        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl font-medium transition shadow">
          Save Changes
        </button>
      </div>
    </div>
  </div>
</div>
</form>

<script>
/* Utility */
const parseFloatSafe = v => parseFloat(v) || 0;
const fmt = n => Number(n||0).toFixed(2);

/* Recalc existing line totals and summary */
function recalcFromExisting() {
  let subtotal = 0;
  document.querySelectorAll('.existing-price').forEach(inp=>{
    const id = inp.dataset.id;
    const price = parseFloatSafe(inp.value);
    const qty = parseFloatSafe(document.querySelector('.existing-qty[data-id="'+id+'"]').value);
    const line = price * qty;
    const el = document.querySelector('.existing-line-total[data-id="'+id+'"]');
    if (el) el.innerText = fmt(line);
    subtotal += line;
  });

  // include new rows' subtotal
  document.querySelectorAll('.product-row, .split-row, .ducted-row, .personnel-row, .equipment-row, .expense-row').forEach(row=>{
    let price = 0, qty = 0;
    const pr = row.querySelector('input[name^="new_product_price"], input[name^="new_split_price"], input[name^="new_ducted_price"], input[name^="new_equipment_price"], input[name^="new_personnel_rate"], input[name^="new_expense_price"]');
    if (pr) price = parseFloatSafe(pr.value);
    const q = row.querySelector('input[name^="new_product_qty"], input[name^="new_split_qty"], input[name^="new_ducted_qty"], input[name^="new_equipment_qty"]');
    if (q) qty = parseFloatSafe(q.value);
    // personnel rows compute hours based on start/end
    if (row.querySelector('input[name^="new_personnel_start"]')) {
      const start = row.querySelector('input[name^="new_personnel_start"]').value;
      const end = row.querySelector('input[name^="new_personnel_end"]').value;
      if (start && end) {
        const s = new Date('1970-01-01T'+start+':00');
        const e = new Date('1970-01-01T'+end+':00');
        let hours = (e - s) / 3600000;
        if (hours < 0) hours = 0;
        qty = hours;
      }
    }
    subtotal += price * qty;
  });

  const tax = subtotal * 0.10;
  const grand = subtotal + tax;
  document.getElementById('subtotalDisplay').innerText = fmt(subtotal);
  document.getElementById('taxDisplay').innerText = fmt(tax);
  document.getElementById('grandDisplay').innerText = fmt(grand);
}

/* Wire up existing inputs */
document.querySelectorAll('.existing-price, .existing-qty').forEach(inp => {
  inp.addEventListener('input', recalcFromExisting);
});

/* Remove row */
document.addEventListener('click', function(e){
  if (e.target.matches('.remove-row')) {
    e.target.closest('.product-row, .split-row, .ducted-row, .personnel-row, .equipment-row, .expense-row').remove();
    recalcFromExisting();
  }
});

/* Add row helpers (cloning template) */
function attachSelectBehavior(container) {
  // whenever a list input is changed, parse the value "id|name" and set hidden id and default price
  container.querySelectorAll('input[list]').forEach(inp=>{
    inp.addEventListener('input', ()=>{
      const val = inp.value || '';
      const hidden = inp.closest('.product-row, .split-row, .ducted-row, .personnel-row, .equipment-row')?.querySelector('input[type="hidden"]');
      if (!hidden) return;
      // expected format "id|name" because datalist option value set that way
      if (val.includes('|')) {
        const parts = val.split('|');
        hidden.value = parts[0];
        // try to extract price/rate from matching datalist option (not all browsers support dataset on datalist matched option,
        // but we used option value = id|name and also included data-price attributes. We'll parse through datalist options.)
        const listId = inp.getAttribute('list');
        const list = document.getElementById(listId);
        if (list) {
          const opt = [...list.options].find(o => o.value === val);
          if (opt) {
            const p = opt.dataset.price || opt.dataset.rate || opt.dataset.total_cost || opt.dataset.unit_price;
            const priceInput = inp.closest('.product-row, .split-row, .ducted-row, .personnel-row, .equipment-row')?.querySelector('input[type="number"]');
            if (p && priceInput) priceInput.value = p;
          }
        }
      }
      recalcFromExisting();
    });
  });
}

/* Add product row */
document.querySelectorAll('.add-product-row').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const tpl = document.getElementById('productRowTpl');
    const clone = tpl.content.cloneNode(true);
    document.getElementById('newProductsContainer').appendChild(clone);
    attachSelectBehavior(document.getElementById('newProductsContainer'));
  });
});

/* Add split row */
document.querySelectorAll('.add-split-row').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const tpl = document.getElementById('splitRowTpl');
    const clone = tpl.content.cloneNode(true);
    document.getElementById('newSplitContainer').appendChild(clone);
    attachSelectBehavior(document.getElementById('newSplitContainer'));
  });
});

/* Add ducted row */
document.querySelectorAll('.add-ducted-row').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const tpl = document.getElementById('ductedRowTpl');
    const clone = tpl.content.cloneNode(true);
    document.getElementById('newDuctedContainer').appendChild(clone);
    attachSelectBehavior(document.getElementById('newDuctedContainer'));
  });
});

/* Add personnel row */
document.querySelectorAll('.add-personnel-row').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const tpl = document.getElementById('personnelRowTpl');
    const clone = tpl.content.cloneNode(true);
    document.getElementById('newPersonnelContainer').appendChild(clone);
    attachSelectBehavior(document.getElementById('newPersonnelContainer'));
    // initialise flatpickr on date inputs if desired
  });
});

/* Add equipment row */
document.querySelectorAll('.add-equipment-row').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const tpl = document.getElementById('equipmentRowTpl');
    const clone = tpl.content.cloneNode(true);
    document.getElementById('newEquipmentContainer').appendChild(clone);
    attachSelectBehavior(document.getElementById('newEquipmentContainer'));
  });
});

/* Add expense row */
document.querySelectorAll('.add-expense-row').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const tpl = document.getElementById('expenseRowTpl');
    const clone = tpl.content.cloneNode(true);
    document.getElementById('newExpenseContainer').appendChild(clone);
    recalcFromExisting();
  });
});

/* Hook inputs inside newly added rows (delegation) */
document.addEventListener('input', function(e){
  if (e.target.matches('input[name^="new_"], input[name^="new_product_price"], input[name^="new_product_qty"], input[name^="new_split_price"], input[name^="new_split_qty"], input[name^="new_ducted_price"], input[name^="new_ducted_qty"], input[name^="new_equipment_price"], input[name^="new_equipment_qty"], input[name^="new_personnel_rate"], input[name^="new_personnel_start"], input[name^="new_personnel_end"]')) {
    recalcFromExisting();
  }
});

/* Save: before submit, ensure datalist-chosen values are parsed into hidden IDs */
document.getElementById('editOrderForm').addEventListener('submit', function(e){
  // for each input with list attribute, parse its current value into hidden id field if we stored as "id|name"
  document.querySelectorAll('input[list]').forEach(inp=>{
    const val = inp.value || '';
    const hidden = inp.closest('.product-row, .split-row, .ducted-row, .personnel-row, .equipment-row')?.querySelector('input[type="hidden"]');
    if (!hidden) return;
    if (val.includes('|')) {
      hidden.value = val.split('|')[0];
    } else {
      // if user typed name only, try to find option by text
      const list = document.getElementById(inp.getAttribute('list'));
      if (list) {
        const match = [...list.options].find(o => o.text === val || o.value.endsWith('|'+val));
        if (match) hidden.value = (match.value.includes('|')?match.value.split('|')[0]:match.value);
      }
    }
  });

  // nothing else to do; server will handle inserted arrays
});

/* initialise simple flatpickr on personnel_date inputs (existing ones) */
flatpickr(".personnel-date-input", { dateFormat: "Y-m-d" });

recalcFromExisting();
</script>

<style>
.qbtn { background:#e6eef8;padding:6px 10px;border-radius:8px;border:1px solid #cfe0f8;cursor:pointer }
</style>

<?php
$content = ob_get_clean();
renderLayout("Edit Order", $content, "orders");
