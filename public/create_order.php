<?php
// create_order.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch data if not already fetched
try { $products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $products=[]; }
try { $split_installations = $pdo->query("SELECT id, item_name AS name, unit_price AS price FROM `split_installation` ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $split_installations=[]; }
try { $ducted_installations = $pdo->query("SELECT id, equipment_name AS name, model_name_indoor, model_name_outdoor, total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $ducted_installations=[]; }
try { $personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $personnel=[]; }
try { $equipment = $pdo->query("SELECT id, item AS name, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $equipment=[]; }

// Capture POST if needed
if($_SERVER['REQUEST_METHOD']==='POST'){
    // Your backend processing
}

ob_start();
?>

<!-- MAIN GRID -->
<form method="post" class="create-order-grid" id="orderForm" novalidate>

  <!-- LEFT PANEL -->
  <div class="create-order-left">

    <!-- Client Info -->
    <div class="card">
      <h4>Client Information</h4>
      <div class="client-grid">
        <input type="text" name="customer_name" class="input" placeholder="Name" required>
        <input type="email" name="customer_email" class="input" placeholder="Email">
        <input type="text" name="contact_number" class="input" placeholder="Phone">
        <input type="date" name="appointment_date" class="input" value="<?= date('Y-m-d') ?>">
      </div>
    </div>

    <!-- PRODUCTS TABLE -->
    <div class="card">
      <h4>Material</h4>
      <input id="productSearch" class="search-input" placeholder="Search products...">
      <div class="table-wrap">
        <table class="products-table" id="productsTable">
          <thead>
            <tr><th>Name</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr>
          </thead>
          <tbody>
          <?php foreach($products as $p): 
            $pid = (int)$p['id'];
            $price = number_format((float)$p['price'],2,'.',''); ?>
            <tr data-product-id="<?= $pid ?>">
              <td><?= htmlspecialchars($p['name']) ?></td>
              <td>$<span class="prod-price"><?= $price ?></span></td>
              <td>
                <div class="qty-box">
                  <button type="button" class="qbtn minus">-</button>
                  <input type="number" min="0" value="0" class="qty-input" data-price="<?= htmlspecialchars($p['price']) ?>">
                  <button type="button" class="qbtn plus">+</button>
                </div>
              </td>
              <td>$<span class="row-subtotal">0.00</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- SPLIT INSTALLATIONS -->
    <div class="card">
      <h4>Split System Installation</h4>
      <input id="splitSearch" class="search-input" placeholder="Search split systems...">
      <div class="table-wrap">
        <table class="products-table" id="splitTable">
          <thead>
            <tr><th>Name</th><th>Unit Price</th><th>Qty</th><th>Subtotal</th></tr>
          </thead>
          <tbody>
          <?php foreach($split_installations as $s):
            $sid=(int)$s['id']; $sprice=number_format((float)$s['price'],2,'.',''); ?>
            <tr data-split-id="<?= $sid ?>">
              <td><?= htmlspecialchars($s['name']) ?></td>
              <td>$<span class="split-price"><?= $sprice ?></span></td>
              <td>
                <div class="qty-box">
                  <button type="button" class="qbtn split-minus">-</button>
                  <input type="number" min="0" value="0" class="qty-input split-qty" data-price="<?= htmlspecialchars($s['price']) ?>">
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

    <!-- DUCTED INSTALLATIONS -->
    <div class="card">
      <h4>Ducted Installation</h4>
      <div class="table-wrap">
        <table class="products-table" id="ductedTable">
          <thead>
            <tr><th>Equipment</th><th>Type</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr>
          </thead>
          <tbody>
          <?php foreach($ducted_installations as $d):
            $did=(int)$d['id']; $dprice=number_format((float)$d['price'],2,'.',''); ?>
            <tr data-ducted-id="<?= $did ?>" data-model-indoor="<?= htmlspecialchars($d['model_name_indoor']) ?>" data-model-outdoor="<?= htmlspecialchars($d['model_name_outdoor']) ?>">
              <td><?= htmlspecialchars($d['name']) ?></td>
              <td>
                <select class="installation-type input" name="ducted[<?= $did ?>][type]">
                  <option value="indoor">Indoor</option>
                  <option value="outdoor">Outdoor</option>
                </select>
              </td>
              <td>$<span class="ducted-price"><?= $dprice ?></span></td>
              <td>
                <div class="qty-box">
                  <button type="button" class="qbtn ducted-minus">-</button>
                  <input type="number" min="0" value="0" class="qty-input installation-qty" data-price="<?= htmlspecialchars($d['price']) ?>">
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
    <div class="card">
      <h4>Personnel</h4>
      <input id="personnelSearch" class="search-input" placeholder="Search personnel...">
      <div class="table-wrap">
        <table class="products-table" id="personnelTable">
          <thead><tr><th>Name</th><th>Rate</th><th>Hours</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach($personnel as $pers):
            $prid=(int)$pers['id']; ?>
            <tr data-personnel-id="<?= $prid ?>" data-rate="<?= htmlspecialchars($pers['rate']) ?>">
              <td><?= htmlspecialchars($pers['name']) ?></td>
              <td>$<span class="pers-rate"><?= number_format((float)$pers['rate'],2,'.','') ?></span></td>
              <td>
                <div class="qty-box">
                  <button type="button" class="qbtn hour-minus">-</button>
                  <input type="number" min="0" value="0" class="qty-input hour-input" data-rate="<?= htmlspecialchars($pers['rate']) ?>">
                  <button type="button" class="qbtn hour-plus">+</button>
                </div>
              </td>
              <td>$<span class="pers-subtotal">0.00</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- EQUIPMENT -->
    <div class="card">
      <h4>Equipment</h4>
      <input id="equipmentSearch" class="search-input" placeholder="Search equipment...">
      <div class="table-wrap">
        <table class="products-table" id="equipmentTable">
          <thead><tr><th>Item</th><th>Rate</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach($equipment as $eq):
            $eid=(int)$eq['id']; ?>
            <tr data-equip-id="<?= $eid ?>" data-rate="<?= htmlspecialchars($eq['rate']) ?>">
              <td><?= htmlspecialchars($eq['name']) ?></td>
              <td>$<span class="equip-rate"><?= number_format((float)$eq['rate'],2,'.','') ?></span></td>
              <td>
                <div class="qty-box">
                  <button type="button" class="qbtn equip-minus">-</button>
                  <input type="number" min="0" value="0" class="qty-input equip-input">
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
    <div class="card">
      <h4>Other Expenses</h4>
      <div id="otherExpensesContainer"></div>
      <button type="button" class="qbtn" id="addExpenseBtn">Add</button>
    </div>

  </div> <!-- END LEFT PANEL -->

  <!-- RIGHT PANEL (SUMMARY) -->
  <aside class="create-order-right">
    <div class="card card-summary">
      <h4 class="card-title">Order Summary</h4>
      <div class="summary-list" id="orderSummary"><div class="empty-note">No items selected.</div></div>
      <div class="summary-totals">
        <div class="flex justify-between"><span>Subtotal:</span><span>$<span id="subtotalDisplay">0.00</span></span></div>
        <div class="flex justify-between"><span>Tax (10%):</span><span>$<span id="taxDisplay">0.00</span></span></div>
        <div class="flex justify-between border-t"><strong>Grand Total:</strong><strong>$<span id="grandDisplay">0.00</span></strong></div>
      </div>
      <button type="submit" class="input">Save Order</button>
    </div>
  </aside>

</form>

<!-- Minimal CSS for summary -->
<style>
.create-order-grid{display:flex;gap:20px;}
.create-order-left{flex:1;}
.create-order-right{width:320px;}
.summary-list{max-height:300px;overflow:auto;}
.summary-item{display:flex;justify-content:space-between;padding:4px 0;}
.empty-note{color:#7e8796;font-size:13px;text-align:center;padding:12px 0;}
</style>

<!-- JS for live summary -->
<script>
(function(){
  function fmt(n){ return Number(n||0).toFixed(2); }
  function updateSummary(){
    let subtotal=0;
    const summaryEl=document.getElementById('orderSummary');
    summaryEl.innerHTML='';

    const allRows = document.querySelectorAll('input.qty-input');
    allRows.forEach(input=>{
      const row=input.closest('tr');
      const val=parseFloat(input.value)||0;
      if(val>0){
        let name=row.querySelector('td')?.textContent||'';
        let price=parseFloat(input.dataset.price)||parseFloat(row.dataset.rate)||0;
        if(row.querySelector('.hour-input')) name+=` (${val} hr)`;
        subtotal+=price*val;
        const div=document.createElement('div');
        div.className='summary-item';
        div.innerHTML=`<span>${name} x ${val}</span><span>$${fmt(price*val)}</span>`;
        summaryEl.appendChild(div);
      }
    });

    if(subtotal===0) summaryEl.innerHTML='<div class="empty-note">No items selected.</div>';
    document.getElementById('subtotalDisplay').textContent=fmt(subtotal);
    document.getElementById('taxDisplay').textContent=fmt(subtotal*0.1);
    document.getElementById('grandDisplay').textContent=fmt(subtotal*1.1);
  }

  document.querySelectorAll('input.qty-input').forEach(input=>{
    input.addEventListener('input',updateSummary);
    const row=input.closest('tr');
    row.querySelectorAll('.qbtn').forEach(btn=>{
      btn.addEventListener('click',()=>{
        let val=parseInt(input.value)||0;
        if(btn.classList.contains('plus')||btn.classList.contains('split-plus')||btn.classList.contains('ducted-plus')||btn.classList.contains('hour-plus')||btn.classList.contains('equip-plus')) val++;
        if(btn.classList.contains('minus')||btn.classList.contains('split-minus')||btn.classList.contains('ducted-minus')||btn.classList.contains('hour-minus')||btn.classList.contains('equip-minus')) val=Math.max(0,val-1);
        input.value=val;
        updateSummary();
      });
    });
  });

  // Other Expenses
  document.getElementById('addExpenseBtn').addEventListener('click',function(){
    const row=document.createElement('div');
    row.className='other-expense-row';
    row.style.display='flex'; row.style.gap='8px'; row.style.marginBottom='8px';
    row.innerHTML='<input type="text" placeholder="Name" class="input expense-name" style="flex:1;">'+
                  '<input type="number" placeholder="Amount" class="input expense-amount" style="width:110px;">'+
                  '<button type="button" class="qbtn remove-expense">x</button>';
    document.getElementById('otherExpensesContainer').appendChild(row);
    row.querySelector('.expense-amount').addEventListener('input',updateSummary);
    row.querySelector('.remove-expense').addEventListener('click',()=>{ row.remove(); updateSummary(); });
  });

  updateSummary();
})();
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
