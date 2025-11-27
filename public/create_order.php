<?php
// create_order.php
require_once __DIR__ . '/../config.php'; // config.php in root
require_once __DIR__ . '/layout.php';

$message = '';

// Simple DB sanity check
if (!isset($pdo) || !$pdo instanceof PDO) {
    ob_start();
    echo '<div style="padding:20px; background:#fee; border:1px solid #fbb; border-radius:8px;">';
    echo '<h3>Database connection missing</h3>';
    echo '<p>Please make sure <code>$pdo</code> is created in config.php</p>';
    echo '</div>';
    $content = ob_get_clean();
    renderLayout('Create Order', $content, 'create_order');
    exit;
}

// Load tables if not already set
try { $products = $products ?? $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $products = []; }
try { $split_installations = $split_installations ?? $pdo->query("SELECT id, item_name AS name, unit_price AS price FROM `split_installation` ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $split_installations = []; }
try { $ducted_installations = $ducted_installations ?? $pdo->query("SELECT id, equipment_name AS name, model_name_indoor, model_name_outdoor, total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $ducted_installations = []; }
try { $personnel = $personnel ?? $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $personnel = []; }
try { $equipment = $equipment ?? $pdo->query("SELECT id, item AS name, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $equipment = []; }

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Insert order
        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_email, contact_number, appointment_date, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $_POST['customer_name'] ?? '',
            $_POST['customer_email'] ?? '',
            $_POST['contact_number'] ?? '',
            $_POST['appointment_date'] ?? date('Y-m-d')
        ]);
        $order_id = $pdo->lastInsertId();

        $insertItem = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, installation_type, qty, price) VALUES (?, ?, ?, ?, ?, ?)");

        // Products
        foreach ($_POST['quantity'] ?? [] as $pid => $qty) {
            $qty = (int)$qty;
            if ($qty>0) {
                $price = 0;
                foreach($products as $p){ if($p['id']==$pid){$price=$p['price'];break;} }
                $insertItem->execute([$order_id,'product',$pid,null,$qty,$price]);
            }
        }

        // Split Installations
        foreach ($_POST['split'] ?? [] as $sid => $info) {
            $qty = (int)($info['qty'] ?? 0);
            if ($qty>0) {
                $price = 0;
                foreach($split_installations as $s){ if($s['id']==$sid){$price=$s['price'];break;} }
                $insertItem->execute([$order_id,'split_installation',$sid,null,$qty,$price]);
            }
        }

        // Ducted Installations
        foreach ($_POST['ducted'] ?? [] as $did => $info) {
            $qty = (int)($info['qty'] ?? 0);
            $installation_type = $info['installation_type'] ?? null;
            if ($qty>0) {
                $price = 0;
                foreach($ducted_installations as $d){ if($d['id']==$did){$price=$d['price'];break;} }
                $insertItem->execute([$order_id,'ducted_installation',$did,$installation_type,$qty,$price]);
            }
        }

        // Personnel
        foreach ($_POST['personnel_hours'] ?? [] as $pid => $hours) {
            $hours = (float)$hours;
            if ($hours>0) {
                $rate = 0;
                foreach($personnel as $p){ if($p['id']==$pid){$rate=$p['rate'];break;} }
                $insertItem->execute([$order_id,'personnel',$pid,null,$hours,$rate]);
            }
        }

        // Equipment
        foreach ($_POST['equipment_qty'] ?? [] as $eid => $qty) {
            $qty = (int)$qty;
            if ($qty>0) {
                $rate = 0;
                foreach($equipment as $e){ if($e['id']==$eid){$rate=$e['rate'];break;} }
                $insertItem->execute([$order_id,'equipment',$eid,null,$qty,$rate]);
            }
        }

        // Other Expenses
        foreach ($_POST['other_expenses']['amount'] ?? [] as $i => $amt) {
            $amt = (float)$amt;
            $name = $_POST['other_expenses']['name'][$i] ?? 'Other expense';
            if($amt>0) $insertItem->execute([$order_id,'other_expense',null,null,1,$amt]);
        }

        $pdo->commit();
        $message = "Order saved successfully! ID: $order_id";
    } catch(Exception $e) {
        $pdo->rollBack();
        $message = "Error saving order: ".$e->getMessage();
    }
}

// Render the page content
ob_start();
?>

<style>
/* Your existing CSS for layout, tables, summary, buttons, inputs */
.create-order-grid { display:flex; gap:20px; align-items:flex-start; }
.create-order-left { flex:1; min-width:0; }
.create-order-right { width:320px; }
.card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:16px; margin-bottom:18px; box-shadow:0 1px 2px rgba(16,24,40,0.03); }
.card h4 { margin:0 0 10px 0; font-size:16px; color:#2b3440; }
.client-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.input { width:100%; padding:10px 12px; border:1px solid #dfe6ef; border-radius:6px; font-size:14px; }
.table-wrap { border:1px solid #edf2f7; border-radius:8px; overflow:hidden; }
.table-header { padding:12px 14px; border-bottom:1px solid #f1f5f9; background:#fafbfc; font-weight:600; color:#344054; }
.products-scroll { max-height:300px; overflow:auto; }
.products-table { width:100%; border-collapse:collapse; font-size:14px; }
.products-table th, .products-table td { padding:10px 12px; border-bottom:1px solid #f5f7fb; text-align:center; }
.products-table th:first-child, .products-table td:first-child { text-align:left; }
.qty-box { display:inline-flex; align-items:center; gap:6px; }
.qbtn { display:inline-block; width:28px; height:28px; line-height:28px; text-align:center; border-radius:6px; cursor:pointer; border:1px solid #e6eef7; background:#f8fafc; user-select:none; }
.qty-input { width:56px; padding:6px; border:1px solid #e6eef7; border-radius:6px; text-align:center; }
.summary-list { max-height:240px; overflow:auto; padding-right:8px; }
.summary-item { display:flex; justify-content:space-between; padding:8px 4px; border-bottom:1px dashed #f1f5f9; color:#1f2937; }
.summary-totals { margin-top:12px; }
.bold { font-weight:700; }
.blue { color:#0b63ff; }
.search-input { padding:8px 10px; width:30%; border:1px solid #e6eef7; border-radius:8px; margin-bottom:10px; }
.small-muted { color:#64748b; font-size:13px; }
.empty-note { color:#7e8796; font-size:14px; text-align:center; padding:20px 0; }
@media (max-width:980px) { .create-order-grid { flex-direction:column; } .create-order-right { width:100%; } }
</style>

<?php if (!empty($message)): ?>
  <div class="card">
    <div style="color:green;"><?= htmlspecialchars($message) ?></div>
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
        <div class="products-scroll">
          <table class="products-table" id="productsTable" aria-describedby="products">
            <thead>
              <tr>
                <th>Name</th><th style="width:120px;">Price</th><th style="width:160px;">Qty</th><th style="width:120px;">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($products)): ?>
                <tr><td colspan="4" class="empty-note">No products found.</td></tr>
              <?php else: foreach($products as $p): $pid=(int)$p['id']; $price=number_format((float)$p['price'],2,'.',''); ?>
                <tr data-product-id="<?= $pid ?>">
                  <td class="prod-name"><?= htmlspecialchars($p['name']) ?></td>
                  <td>$<span class="prod-price"><?= $price ?></span></td>
                  <td><div class="qty-box"><button type="button" class="qbtn minus">-</button><input type="number" min="0" name="quantity[<?= $pid ?>]" value="0" class="qty-input" data-price="<?= htmlspecialchars($p['price']) ?>"><button type="button" class="qbtn plus">+</button></div></td>
                  <td>$<span class="row-subtotal">0.00</span></td>
                </tr>
              <?php endforeach; endif; ?>
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
            <thead><tr><th>Name</th><th style="width:120px;">Unit Price</th><th style="width:160px;">Qty</th><th style="width:120px;">Subtotal</th></tr></thead>
            <tbody>
              <?php if(empty($split_installations)): ?>
                <tr><td colspan="4" class="empty-note">No split installations available.</td></tr>
              <?php else: foreach($split_installations as $s): $sid=(int)$s['id']; $sprice=number_format((float)$s['price'],2,'.',''); ?>
                <tr data-split-id="<?= $sid ?>">
                  <td class="item-name"><?= htmlspecialchars($s['name']) ?></td>
                  <td>$<span class="split-price"><?= $sprice ?></span></td>
                  <td><div class="qty-box"><button type="button" class="qbtn split-minus">-</button><input type="number" min="0" name="split[<?= $sid ?>][qty]" value="0" class="qty-input split-qty" data-price="<?= htmlspecialchars($s['price']) ?>"><button type="button" class="qbtn split-plus">+</button></div></td>
                  <td>$<span class="row-subtotal">0.00</span></td>
                </tr>
              <?php endforeach; endif; ?>
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
            <thead><tr><th>Equipment</th><th>Type</th><th style="width:120px;">Price</th><th style="width:160px;">Qty</th><th style="width:120px;">Subtotal</th></tr></thead>
            <tbody>
              <?php if(empty($ducted_installations)): ?>
                <tr><td colspan="5" class="empty-note">No ducted installations available.</td></tr>
              <?php else: foreach($ducted_installations as $d): $did=(int)$d['id']; $dprice=number_format((float)$d['price'],2,'.',''); ?>
                <tr data-ducted-id="<?= $did ?>" data-model-indoor="<?= htmlspecialchars($d['model_name_indoor'] ?? '') ?>" data-model-outdoor="<?= htmlspecialchars($d['model_name_outdoor'] ?? '') ?>">
                  <td><?= htmlspecialchars($d['name']) ?></td>
                  <td>
                    <select name="ducted[<?= $did ?>][installation_type]" class="installation-type input" style="padding:6px;">
                      <option value="indoor">indoor</option>
                      <option value="outdoor">outdoor</option>
                    </select>
                  </td>
                  <td>$<span class="ducted-price"><?= $dprice ?></span></td>
                  <td><div class="qty-box"><button type="button" class="qbtn ducted-minus">-</button><input type="number" min="0" name="ducted[<?= $did ?>][qty]" value="0" class="qty-input installation-qty" data-price="<?= htmlspecialchars($d['price']) ?>"><button type="button" class="qbtn ducted-plus">+</button></div></td>
                  <td>$<span class="row-subtotal">0.00</span></td>
                </tr>
              <?php endforeach; endif; ?>
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
            <thead><tr><th>Name</th><th style="width:120px;">Rate</th><th style="width:160px;">Hours</th><th style="width:120px;">Subtotal</th></tr></thead>
            <tbody>
              <?php if(empty($personnel)): ?>
                <tr><td colspan="4" class="empty-note">No personnel found.</td></tr>
              <?php else: foreach($personnel as $pers): $prid=(int)$pers['id']; ?>
                <tr data-personnel-id="<?= $prid ?>" data-rate="<?= htmlspecialchars($pers['rate']) ?>">
                  <td class="pers-name"><?= htmlspecialchars($pers['name']) ?></td>
                  <td>$<span class="pers-rate"><?= number_format((float)$pers['rate'],2,'.','') ?></span></td>
                  <td><div class="qty-box"><button type="button" class="qbtn hour-minus">-</button><input type="number" min="0" name="personnel_hours[<?= $prid ?>]" value="0" class="qty-input hour-input" data-rate="<?= htmlspecialchars($pers['rate']) ?>"><button type="button" class="qbtn hour-plus">+</button></div></td>
                  <td>$<span class="pers-subtotal">0.00</span></td>
                </tr>
              <?php endforeach; endif; ?>
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
            <thead><tr><th>Item</th><th style="width:120px;">Rate</th><th style="width:160px;">Qty</th><th style="width:120px;">Subtotal</th></tr></thead>
            <tbody>
              <?php if(empty($equipment)): ?>
                <tr><td colspan="4" class="empty-note">No equipment found.</td></tr>
              <?php else: foreach($equipment as $eq): $eid=(int)$eq['id']; ?>
                <tr data-equipment-id="<?= $eid ?>">
                  <td><?= htmlspecialchars($eq['name']) ?></td>
                  <td>$<span class="eq-rate"><?= number_format((float)$eq['rate'],2,'.','') ?></span></td>
                  <td><div class="qty-box"><button type="button" class="qbtn eq-minus">-</button><input type="number" min="0" name="equipment_qty[<?= $eid ?>]" value="0" class="qty-input eq-qty" data-rate="<?= htmlspecialchars($eq['rate']) ?>"><button type="button" class="qbtn eq-plus">+</button></div></td>
                  <td>$<span class="eq-subtotal">0.00</span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Other Expenses -->
    <div class="card">
      <h4>Other Expenses</h4>
      <div id="otherExpensesContainer">
        <div style="display:flex; gap:8px; margin-bottom:6px;">
          <input type="text" name="other_expenses[name][]" class="input" placeholder="Name">
          <input type="number" min="0" step="0.01" name="other_expenses[amount][]" class="input" placeholder="Amount">
        </div>
      </div>
      <button type="button" id="addOtherExpense" class="qbtn" style="margin-top:6px;">+</button>
    </div>

  </div>

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

<script>
// JS to handle qty increment/decrement, subtotal, and summary calculation
document.querySelectorAll('.qbtn').forEach(btn=>{
    btn.addEventListener('click',function(){
        const parent=this.parentElement;
        const input=parent.querySelector('input');
        if(!input) return;
        let val=parseFloat(input.value)||0;
        if(this.classList.contains('plus') || this.classList.contains('split-plus') || this.classList.contains('ducted-plus') || this.classList.contains('hour-plus') || this.classList.contains('eq-plus')) val+=1;
        if(this.classList.contains('minus') || this.classList.contains('split-minus') || this.classList.contains('ducted-minus') || this.classList.contains('hour-minus') || this.classList.contains('eq-minus')) val=Math.max(0,val-1);
        input.value=val;
        updateSummary();
    });
});
document.querySelectorAll('input.qty-input, input.hour-input, input.eq-qty').forEach(input=>{
    input.addEventListener('input',updateSummary);
});
function updateSummary(){
    let totalProducts=0, totalSplit=0, totalDucted=0, totalPersonnel=0, totalEquipment=0, totalOther=0;
    // Products
    document.querySelectorAll('#productsTable tbody tr').forEach(tr=>{
        const input=tr.querySelector('input');
        const price=parseFloat(input?.dataset.price)||0;
        const qty=parseFloat(input?.value)||0;
        const subtotal=price*qty;
        tr.querySelector('.row-subtotal').textContent=subtotal.toFixed(2);
        totalProducts+=subtotal;
    });
    // Split
    document.querySelectorAll('#splitTable tbody tr').forEach(tr=>{
        const input=tr.querySelector('input');
        const price=parseFloat(input?.dataset.price)||0;
        const qty=parseFloat(input?.value)||0;
        const subtotal=price*qty;
        tr.querySelector('.row-subtotal').textContent=subtotal.toFixed(2);
        totalSplit+=subtotal;
    });
    // Ducted
    document.querySelectorAll('#ductedTable tbody tr').forEach(tr=>{
        const input=tr.querySelector('input');
        const price=parseFloat(input?.dataset.price)||0;
        const qty=parseFloat(input?.value)||0;
        const subtotal=price*qty;
        tr.querySelector('.row-subtotal').textContent=subtotal.toFixed(2);
        totalDucted+=subtotal;
    });
    // Personnel
    document.querySelectorAll('#personnelTable tbody tr').forEach(tr=>{
        const input=tr.querySelector('input');
        const rate=parseFloat(input?.dataset.rate)||0;
        const hours=parseFloat(input?.value)||0;
        const subtotal=rate*hours;
        tr.querySelector('.pers-subtotal').textContent=subtotal.toFixed(2);
        totalPersonnel+=subtotal;
    });
    // Equipment
    document.querySelectorAll('#equipmentTable tbody tr').forEach(tr=>{
        const input=tr.querySelector('input');
        const rate=parseFloat(input?.dataset.rate)||0;
        const qty=parseFloat(input?.value)||0;
        const subtotal=rate*qty;
        tr.querySelector('.eq-subtotal').textContent=subtotal.toFixed(2);
        totalEquipment+=subtotal;
    });
    // Other Expenses
    document.querySelectorAll('#otherExpensesContainer input[name="other_expenses[amount][]"]').forEach(input=>{
        const amt=parseFloat(input.value)||0;
        totalOther+=amt;
    });
    document.getElementById('totalProducts').textContent='$'+totalProducts.toFixed(2);
    document.getElementById('totalSplit').textContent='$'+totalSplit.toFixed(2);
    document.getElementById('totalDucted').textContent='$'+totalDucted.toFixed(2);
    document.getElementById('totalPersonnel').textContent='$'+totalPersonnel.toFixed(2);
    document.getElementById('totalEquipment').textContent='$'+totalEquipment.toFixed(2);
    document.getElementById('totalOther').textContent='$'+totalOther.toFixed(2);
    document.getElementById('grandTotal').textContent=(totalProducts+totalSplit+totalDucted+totalPersonnel+totalEquipment+totalOther).toFixed(2);
}

// Add other expense row
document.getElementById('addOtherExpense').addEventListener('click',function(){
    const container=document.getElementById('otherExpensesContainer');
    const div=document.createElement('div');
    div.style.display='flex'; div.style.gap='8px'; div.style.marginBottom='6px';
    div.innerHTML=`<input type="text" name="other_expenses[name][]" class="input" placeholder="Name"><input type="number" min="0" step="0.01" name="other_expenses[amount][]" class="input" placeholder="Amount">`;
    container.appendChild(div);
});
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
