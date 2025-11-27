<?php
// create_order.php
// Drop this file into your project. It expects config.php + layout.php (renderLayout) to exist.

require_once __DIR__ . '/../config.php'; // config.php in root
require_once __DIR__ . '/layout.php';

// Simple DB sanity check
if (!isset($pdo) || !$pdo instanceof PDO) {
    // Render a minimal error page using layout if PDO missing
    ob_start();
    echo '<div style="padding:20px; background:#fee; border:1px solid #fbb; border-radius:8px;">';
    echo '<h3>Database connection missing</h3>';
    echo '<p>Please make sure <code>$pdo</code> is created in config.php</p>';
    echo '</div>';
    $content = ob_get_clean();
    renderLayout('Create Order', $content, 'create_order');
    exit;
}

// If arrays are not provided by some other include, attempt to load them
try {
    if (!isset($products)) {
        $products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $products = [];
}

try {
    if (!isset($split_installations)) {
        // try possible names
        $cands = ['split_system_installation','split_installations','split_systems','split_installation'];
        $found = null;
        foreach ($cands as $t) {
            $r = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn();
            if ($r) { $found = $t; break; }
        }
        if ($found) {
            $split_installations = $pdo->query("SELECT id, item_name AS name, unit_price AS price FROM `$found` ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $split_installations = [];
        }
    }
} catch (Exception $e) {
    $split_installations = [];
}

try {
    if (!isset($ducted_installations)) {
        $ducted_installations = $pdo->query("SELECT id, equipment_name AS name, model_name_indoor, model_name_outdoor, total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $ducted_installations = [];
}

try {
    if (!isset($personnel)) {
        $personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $personnel = [];
}

try {
    if (!isset($equipment)) {
        $equipment = $pdo->query("SELECT id, item AS name, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $equipment = [];
}

// If the page receives POST, you can rely on your existing backend logic (this form posts fields that match)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Optional: show a simple message if you want to debug quickly
    // $message = "Received order POST - check server-side processing.";
    // But we leave heavy processing to your existing backend code you shared earlier.
}

// Render the page content
ob_start();
?>
<style>
/* Page-specific CSS (keeps things simple, neutral and similar to your screenshot) */
.create-order-grid { display:flex; gap:20px; align-items:flex-start; }
.create-order-left { flex:1; min-width:0; }
.create-order-right { width:320px; }

.card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:16px; margin-bottom:18px; box-shadow:0 1px 2px rgba(16,24,40,0.03); }
.card h4 { margin:0 0 10px 0; font-size:16px; color:#2b3440; }

/* client inputs */
.client-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.input { width:100%; padding:10px 12px; border:1px solid #dfe6ef; border-radius:6px; font-size:14px; }

/* tables */
.table-wrap { border:1px solid #edf2f7; border-radius:8px; overflow:hidden; }
.table-header { padding:12px 14px; border-bottom:1px solid #f1f5f9; background:#fafbfc; font-weight:600; color:#344054; }
.products-scroll { max-height:300px; overflow:auto; }
.products-table { width:100%; border-collapse:collapse; font-size:14px; }
.products-table th, .products-table td { padding:10px 12px; border-bottom:1px solid #f5f7fb; text-align:center; }
.products-table th:first-child, .products-table td:first-child { text-align:left; }
.qty-box { display:inline-flex; align-items:center; gap:6px; }
.qbtn { display:inline-block; width:28px; height:28px; line-height:28px; text-align:center; border-radius:6px; cursor:pointer; border:1px solid #e6eef7; background:#f8fafc; user-select:none; }
.qty-input { width:56px; padding:6px; border:1px solid #e6eef7; border-radius:6px; text-align:center; }

/* summary */
.summary-list { max-height:240px; overflow:auto; padding-right:8px; }
.summary-item { display:flex; justify-content:space-between; padding:8px 4px; border-bottom:1px dashed #f1f5f9; color:#1f2937; }
.summary-totals { margin-top:12px; }
.bold { font-weight:700; }
.blue { color:#0b63ff; }

/* other small helpers */
.search-input { padding:8px 10px; width:100%; border:1px solid #e6eef7; border-radius:8px; margin-bottom:10px; }
.small-muted { color:#64748b; font-size:13px; }
.empty-note { color:#7e8796; font-size:14px; text-align:center; padding:20px 0; }

/* responsive */
@media (max-width:980px) {
  .create-order-grid { flex-direction:column; }
  .create-order-right { width:100%; }
}
</style>

<?php if (!empty($message ?? '')): ?>
  <div class="card">
    <div style="color:#c53030;"><?= htmlspecialchars($message) ?></div>
  </div>
<?php endif; ?>

<form method="post" id="orderForm" class="create-order-grid" novalidate>

  <!-- LEFT COLUMN -->
  <div class="create-order-left">

    <!-- Client Info -->
    <div class="card">
      <h4>Client Information</h4>
      <div class="client-grid">
        <input type="text" name="customer_name" class="input" placeholder="Name" required>
        <input type="email" name="customer_email" class="input" placeholder="Email">
        <input type="text" name="contact_number" class="input" placeholder="Phone">
        <input type="date" id="appointment_date" name="appointment_date" class="input" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
      </div>
    </div>

    <!-- Material / Products -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <h4 style="margin:0">Material</h4>
        <input id="productSearch" class="search-input" placeholder="Search product...">
      </div>

      <div class="table-wrap" style="margin-top:12px;">
        <div class="table-header">Name &nbsp;&nbsp;&nbsp; | &nbsp; Price &nbsp; | &nbsp; Qty &nbsp; | &nbsp; Subtotal</div>
        <div class="products-scroll">
          <table class="products-table" id="productsTable" aria-describedby="products">
            <thead>
              <tr>
                <th>Name</th>
                <th style="width:120px;">Price</th>
                <th style="width:160px;">Qty</th>
                <th style="width:120px;">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($products)): ?>
                <tr><td colspan="4" class="empty-note">No products found.</td></tr>
              <?php else: ?>
                <?php foreach ($products as $p): 
                  $pid = (int)$p['id'];
                  $price = number_format((float)$p['price'], 2, '.', '');
                ?>
                <tr data-product-id="<?= $pid ?>">
                  <td class="prod-name"><?= htmlspecialchars($p['name']) ?></td>
                  <td>$<span class="prod-price"><?= $price ?></span></td>
                  <td>
                    <div class="qty-box">
                      <button type="button" class="qbtn minus" aria-label="minus">-</button>
                      <input type="number" min="0" name="quantity[<?= $pid ?>]" value="0" class="qty-input" data-price="<?= htmlspecialchars($p['price']) ?>">
                      <button type="button" class="qbtn plus" aria-label="plus">+</button>
                    </div>
                  </td>
                  <td>$<span class="row-subtotal">0.00</span></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Split Installations -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <h4 style="margin:0">Split System Installation</h4>
        <input id="splitSearch" class="search-input" placeholder="Search split...">
      </div>

      <div class="table-wrap" style="margin-top:12px;">
        <div class="products-scroll">
          <table class="products-table" id="splitTable">
            <thead>
              <tr><th>Name</th><th style="width:120px;">Unit Price</th><th style="width:160px;">Qty</th><th style="width:120px;">Subtotal</th></tr>
            </thead>
            <tbody>
              <?php if (empty($split_installations)): ?>
                <tr><td colspan="4" class="empty-note">No split installations available.</td></tr>
              <?php else: ?>
                <?php foreach ($split_installations as $s): 
                  $sid = (int)$s['id'];
                  $sprice = number_format((float)$s['price'], 2, '.', '');
                ?>
                <tr data-split-id="<?= $sid ?>">
                  <td class="item-name"><?= htmlspecialchars($s['name']) ?></td>
                  <td>$<span class="split-price"><?= $sprice ?></span></td>
                  <td>
                    <div class="qty-box">
                      <button type="button" class="qbtn split-minus">-</button>
                      <input type="number" min="0" name="split[<?= $sid ?>][qty]" value="0" class="qty-input split-qty" data-price="<?= htmlspecialchars($s['price']) ?>">
                      <button type="button" class="qbtn split-plus">+</button>
                    </div>
                  </td>
                  <td>$<span class="row-subtotal">0.00</span></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Ducted Installations -->
    <div class="card">
      <h4>Ducted Installation</h4>
      <div class="table-wrap" style="margin-top:12px;">
        <div class="products-scroll">
          <table class="products-table" id="ductedTable">
            <thead>
              <tr><th>Equipment</th><th>Type</th><th style="width:120px;">Price</th><th style="width:160px;">Qty</th><th style="width:120px;">Subtotal</th></tr>
            </thead>
            <tbody>
              <?php if (empty($ducted_installations)): ?>
                <tr><td colspan="5" class="empty-note">No ducted installations available.</td></tr>
              <?php else: ?>
                <?php foreach ($ducted_installations as $d): 
                  $did = (int)$d['id'];
                  $dprice = number_format((float)$d['price'], 2, '.', '');
                ?>
                <tr data-ducted-id="<?= $did ?>" data-model-indoor="<?= htmlspecialchars($d['model_name_indoor'] ?? '') ?>" data-model-outdoor="<?= htmlspecialchars($d['model_name_outdoor'] ?? '') ?>">
                  <td><?= htmlspecialchars($d['name']) ?></td>
                  <td>
                    <select name="ducted[<?= $did ?>][installation_type]" class="installation-type input" style="padding:6px;">
                      <option value="indoor">indoor</option>
                      <option value="outdoor">outdoor</option>
                    </select>
                  </td>
                  <td>$<span class="ducted-price"><?= $dprice ?></span></td>
                  <td>
                    <div class="qty-box">
                      <button type="button" class="qbtn ducted-minus">-</button>
                      <input type="number" min="0" name="ducted[<?= $did ?>][qty]" value="0" class="qty-input installation-qty" data-price="<?= htmlspecialchars($d['price']) ?>">
                      <button type="button" class="qbtn ducted-plus">+</button>
                    </div>
                  </td>
                  <td>$<span class="row-subtotal">0.00</span></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Personnel -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <h4 style="margin:0">Personnel</h4>
        <input id="personnelSearch" class="search-input" placeholder="Search personnel...">
      </div>

      <div class="table-wrap" style="margin-top:12px;">
        <div class="products-scroll">
          <table class="products-table" id="personnelTable">
            <thead>
              <tr><th>Name</th><th style="width:120px;">Rate</th><th style="width:160px;">Hours</th><th style="width:120px;">Subtotal</th></tr>
            </thead>
            <tbody>
              <?php if (empty($personnel)): ?>
                <tr><td colspan="4" class="empty-note">No personnel found.</td></tr>
              <?php else: ?>
                <?php foreach ($personnel as $pers): 
                  $prid = (int)$pers['id'];
                ?>
                <tr data-personnel-id="<?= $prid ?>" data-rate="<?= htmlspecialchars($pers['rate']) ?>">
                  <td class="pers-name"><?= htmlspecialchars($pers['name']) ?></td>
                  <td>$<span class="pers-rate"><?= number_format((float)$pers['rate'],2,'.','') ?></span></td>
                  <td>
                    <div class="qty-box">
                      <button type="button" class="qbtn hour-minus">-</button>
                      <input type="number" min="0" name="personnel_hours[<?= $prid ?>]" value="0" class="qty-input hour-input" data-rate="<?= htmlspecialchars($pers['rate']) ?>">
                      <button type="button" class="qbtn hour-plus">+</button>
                    </div>
                  </td>
                  <td>$<span class="pers-subtotal">0.00</span></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Equipment -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <h4 style="margin:0">Equipment</h4>
        <input id="equipmentSearch" class="search-input" placeholder="Search equipment...">
      </div>

      <div class="table-wrap" style="margin-top:12px;">
        <div class="products-scroll">
          <table class="products-table" id="equipmentTable">
            <thead>
              <tr><th>Item</th><th style="width:120px;">Rate</th><th style="width:160px;">Qty</th><th style="width:120px;">Subtotal</th></tr>
            </thead>
            <tbody>
              <?php if (empty($equipment)): ?>
                <tr><td colspan="4" class="empty-note">No equipment found.</td></tr>
              <?php else: ?>
                <?php foreach ($equipment as $eq): $eid=(int)$eq['id']; ?>
                <tr data-equip-id="<?= $eid ?>" data-rate="<?= htmlspecialchars($eq['rate']) ?>">
                  <td class="equip-name"><?= htmlspecialchars($eq['name']) ?></td>
                  <td>$<span class="equip-rate"><?= number_format((float)$eq['rate'],2,'.','') ?></span></td>
                  <td>
                    <div class="qty-box">
                      <button type="button" class="qbtn equip-minus">-</button>
                      <input type="number" min="0" name="equipment_qty[<?= $eid ?>]" value="0" class="qty-input equip-input">
                      <button type="button" class="qbtn equip-plus">+</button>
                    </div>
                  </td>
                  <td>$<span class="equip-subtotal">0.00</span></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Other Expenses (dynamic rows) -->
    <div class="card">
      <h4>Other Expenses</h4>
      <div id="otherExpensesContainer"></div>
      <div style="display:flex;justify-content:flex-end;margin-top:10px;">
        <button type="button" id="addExpenseBtn" class="qbtn" style="padding:8px 12px;">Add</button>
      </div>
    </div>

  </div> <!-- END LEFT COLUMN -->


  <!-- RIGHT COLUMN (summary) -->
  <aside class="create-order-right">
    <div class="card" style="position:sticky; top:24px;">
      <h4>Order Summary</h4>

      <div class="summary-list" id="orderSummary">
        <div class="empty-note">No items selected.</div>
      </div>

      <div class="summary-totals">
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><span class="small-muted">Subtotal:</span><span>$<span id="subtotalDisplay">0.00</span></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;"><span class="small-muted">Tax (10%):</span><span>$<span id="taxDisplay">0.00</span></span></div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:1px solid #f3f6f9;"><strong class="bold">Grand Total:</strong><strong class="bold blue">$<span id="grandDisplay">0.00</span></strong></div>
      </div>

      <div style="margin-top:12px;">
        <button type="submit" class="input" style="background:#0b63ff;color:#fff;border:none;cursor:pointer;border-radius:8px;padding:12px;">Save Order</button>
      </div>
    </div>
  </aside>

</form>

<!-- Vanilla JS: client-side interactions + totals -->
<script>
(function(){
  const GST = 0.10;

  // Helpers
  function fmt(n){ return Number((n||0).toFixed(2)).toFixed(2); }

  // Update per-row subtotal for a given row element
  function updateRowSubtotal(row) {
    const priceEl = row.querySelector('.prod-price, .split-price, .ducted-price, .pers-rate, .equip-rate');
    let price = 0;
    if (priceEl) price = parseFloat(priceEl.textContent.replace(/[^0-9.-]+/g,"")) || 0;

    // For personnel rows, rate is on data-rate and hours input uses data-rate instead
    if (row.dataset.rate) {
      price = parseFloat(row.dataset.rate) || price;
    }

    // find input qty or hour
    const qtyInput = row.querySelector('input.qty-input');
    let qty = qtyInput ? (parseFloat(qtyInput.value) || 0) : 0;

    // For personnel use hours * rate
    if (row.querySelector('.hour-input')) {
      qty = parseFloat(row.querySelector('.hour-input').value) || 0;
      price = parseFloat(row.dataset.rate) || price;
    }

    const subtotal = price * qty;
    const cell = row.querySelector('.row-subtotal, .pers-subtotal, .equip-subtotal');
    if (cell) cell.textContent = fmt(subtotal);
    return subtotal;
  }

  // MAIN TOTAL update
  function updateTotal() {
    let subtotal = 0;
    const summaryParts = [];

    // products
    document.querySelectorAll('#productsTable tbody tr[data-product-id]').forEach(row => {
      const price = parseFloat(row.querySelector('input.qty-input').dataset.price) || parseFloat(row.querySelector('.prod-price')?.textContent) || 0;
      const qty = parseInt(row.querySelector('input.qty-input').value) || 0;
      const sub = price * qty;
      row.querySelector('.row-subtotal').textContent = fmt(sub);
      if (qty > 0) summaryParts.push({name: row.querySelector('.prod-name').textContent.trim(), qty, sub});
      subtotal += sub;
    });

    // split
    document.querySelectorAll('#splitTable tbody tr[data-split-id]').forEach(row => {
      const price = parseFloat(row.querySelector('.split-qty')?.dataset.price) || parseFloat(row.querySelector('.split-price')?.textContent) || 0;
      const qty = parseInt(row.querySelector('.split-qty').value) || 0;
      const sub = price * qty;
      row.querySelector('.row-subtotal').textContent = fmt(sub);
      if (qty > 0) summaryParts.push({name: row.querySelector('.item-name').textContent.trim(), qty, sub});
      subtotal += sub;
    });

    // ducted
    document.querySelectorAll('#ductedTable tbody tr[data-ducted-id]').forEach(row => {
      const price = parseFloat(row.querySelector('.installation-qty')?.dataset.price) || parseFloat(row.querySelector('.ducted-price')?.textContent) || 0;
      const qty = parseInt(row.querySelector('.installation-qty').value) || 0;
      const type = row.querySelector('.installation-type')?.value || '';
      const modelIndoor = row.dataset.modelIndoor || row.dataset.modelIndoor;
      const modelOutdoor = row.dataset.modelOutdoor || row.dataset.modelOutdoor;
      const model = type === 'outdoor' ? (row.dataset.modelOutdoor || '') : (row.dataset.modelIndoor || '');
      const label = (model ? model : row.querySelector('td').textContent.trim()) + (type ? ' ('+type+')' : '');
      const sub = price * qty;
      row.querySelector('.row-subtotal').textContent = fmt(sub);
      if (qty > 0) summaryParts.push({name: label, qty, sub});
      subtotal += sub;
    });

    // personnel (hours)
    document.querySelectorAll('#personnelTable tbody tr[data-personnel-id]').forEach(row => {
      const rate = parseFloat(row.dataset.rate) || parseFloat(row.querySelector('.pers-rate')?.textContent) || 0;
      const hours = parseFloat(row.querySelector('.hour-input').value) || 0;
      const sub = rate * hours;
      row.querySelector('.pers-subtotal').textContent = fmt(sub);
      if (hours > 0) summaryParts.push({name: row.querySelector('.pers-name').textContent.trim() + ' ('+hours+' hr)', qty: hours, sub});
      subtotal += sub;
    });

    // equipment
    document.querySelectorAll('#equipmentTable tbody tr[data-equip-id]').forEach(row => {
      const rate = parseFloat(row.dataset.rate) || parseFloat(row.querySelector('.equip-rate')?.textContent) || 0;
      const qty = parseInt(row.querySelector('.equip-input').value) || 0;
      const sub = rate * qty;
      row.querySelector('.equip-subtotal').textContent = fmt(sub);
      if (qty > 0) summaryParts.push({name: row.querySelector('.equip-name').textContent.trim(), qty, sub});
      subtotal += sub;
    });

    // other expenses
    document.querySelectorAll('.other-expense-row').forEach(row => {
      const name = row.querySelector('.expense-name').value.trim();
      const amt = parseFloat(row.querySelector('.expense-amount').value) || 0;
      if (amt > 0) summaryParts.push({name: name || 'Other expense', qty: 1, sub: amt});
      subtotal += amt;
    });

    // render summary
    const summaryEl = document.getElementById('orderSummary');
    if (summaryParts.length === 0) {
      summaryEl.innerHTML = '<div class="empty-note">No items selected.</div>';
    } else {
      summaryEl.innerHTML = '';
      summaryParts.forEach(it => {
        const div = document.createElement('div');
        div.className = 'summary-item';
        div.innerHTML = '<div>' + escapeHtml(it.name) + (it.qty ? ' x ' + it.qty : '') + '</div><div>$' + fmt(it.sub) + '</div>';
        summaryEl.appendChild(div);
      });
    }

    // totals
    const tax = subtotal * GST;
    const grand = subtotal + tax;
    document.getElementById('subtotalDisplay').textContent = fmt(subtotal);
    document.getElementById('taxDisplay').textContent = fmt(tax);
    document.getElementById('grandDisplay').textContent = fmt(grand);
  }

  // escape helper
  function escapeHtml(s){ return String(s).replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }

  // Generic plus/minus handlers for many lists
  function wireQtyButtons(rootSelector, plusSel, minusSel, inputSel) {
    document.querySelectorAll(rootSelector + ' ' + plusSel).forEach(btn => {
      btn.addEventListener('click', () => {
        const row = btn.closest('tr');
        const input = row.querySelector(inputSel);
        input.value = Math.max(0, (parseInt(input.value)||0) + 1);
        updateTotal();
      });
    });
    document.querySelectorAll(rootSelector + ' ' + minusSel).forEach(btn => {
      btn.addEventListener('click', () => {
        const row = btn.closest('tr');
        const input = row.querySelector(inputSel);
        input.value = Math.max(0, (parseInt(input.value)||0) - 1);
        updateTotal();
      });
    });
    // input changes
    document.querySelectorAll(rootSelector + ' ' + inputSel).forEach(inp => {
      inp.addEventListener('input', () => {
        if (inp.value === '' || isNaN(inp.value)) inp.value = 0;
        updateTotal();
      });
    });
  }

  // wire for each table
  wireQtyButtons('#productsTable', '.plus', '.minus', '.qty-input');
  wireQtyButtons('#splitTable', '.split-plus', '.split-minus', '.split-qty');
  wireQtyButtons('#ductedTable', '.ducted-plus', '.ducted-minus', '.installation-qty');
  wireQtyButtons('#personnelTable', '.hour-plus', '.hour-minus', '.hour-input');
  wireQtyButtons('#equipmentTable', '.equip-plus', '.equip-minus', '.equip-input');

  // other expenses dynamic rows
  const otherContainer = document.getElementById('otherExpensesContainer');
  document.getElementById('addExpenseBtn').addEventListener('click', function(){
    const row = document.createElement('div');
    row.className = 'other-expense-row';
    row.style.display='flex';
    row.style.gap='8px';
    row.style.marginBottom='8px';
    row.innerHTML =
      '<input type="text" name="other_expenses[][name]" placeholder="Name" class="input expense-name" style="flex:1;padding:8px;">' +
      '<input type="number" name="other_expenses[][amount]" step="0.01" min="0" placeholder="Amount" class="input expense-amount" style="width:110px;padding:8px;">' +
      '<button type="button" class="qbtn remove-expense">x</button>';
    otherContainer.appendChild(row);

    row.querySelector('.expense-amount').addEventListener('input', updateTotal);
    row.querySelector('.remove-expense').addEventListener('click', function(){ row.remove(); updateTotal(); });
  });

  // search filters
  function simpleSearch(inputId, tableId, rowSelector, cellSelector) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('input', function(){
      const q = input.value.trim().toLowerCase();
      document.querySelectorAll(tableId + ' ' + rowSelector).forEach(row=>{
        const text = (row.querySelector(cellSelector)?.textContent || '').toLowerCase();
        row.style.display = text.indexOf(q) === -1 ? 'none' : '';
      });
    });
  }
  simpleSearch('productSearch','#productsTable','tbody tr','td.prod-name');
  simpleSearch('splitSearch','#splitTable','tbody tr','td.item-name');
  simpleSearch('personnelSearch','#personnelTable','tbody tr','td.pers-name');
  simpleSearch('equipmentSearch','#equipmentTable','tbody tr','td.equip-name');

  // initial update
  updateTotal();

})();
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
