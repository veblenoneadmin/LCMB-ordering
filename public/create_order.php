<?php
// create_order.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch all data
try { $products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $products=[]; }
try { $split_installations = $pdo->query("SELECT id, item_name AS name, unit_price AS price FROM split_installation ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $split_installations=[]; }
try { $ducted_installations = $pdo->query("SELECT id, equipment_name AS name, model_name_indoor, model_name_outdoor, total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $ducted_installations=[]; }
try { $personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $personnel=[]; }
try { $equipment = $pdo->query("SELECT id, item AS name, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $equipment=[]; }

$message = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $customer_name = $_POST['customer_name'] ?? '';
    $customer_email = $_POST['customer_email'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? null;

    // Collect all items
    $items = [];

    // Products
    foreach($_POST['product'] ?? [] as $pid => $qty){
        if($qty>0){
            $stmt = $pdo->prepare("SELECT price FROM products WHERE id=?");
            $stmt->execute([$pid]);
            $price = (float)$stmt->fetchColumn();
            $items[] = ['type'=>'product','id'=>$pid,'qty'=>$qty,'price'=>$price];
        }
    }

    // Split Installation
    foreach($_POST['split'] ?? [] as $sid => $qty){
        if($qty>0){
            $stmt = $pdo->prepare("SELECT unit_price FROM split_installation WHERE id=?");
            $stmt->execute([$sid]);
            $price = (float)$stmt->fetchColumn();
            $items[] = ['type'=>'installation','id'=>$sid,'installation_type'=>'split','qty'=>$qty,'price'=>$price];
        }
    }

    // Ducted Installation
    foreach($_POST['ducted'] ?? [] as $did => $data){
        $qty = intval($data['qty'] ?? 0);
        $type = $data['type'] ?? 'indoor';
        if($qty>0){
            $stmt = $pdo->prepare("SELECT total_cost FROM ductedinstallations WHERE id=?");
            $stmt->execute([$did]);
            $price = (float)$stmt->fetchColumn();
            $items[] = ['type'=>'installation','id'=>$did,'installation_type'=>$type,'qty'=>$qty,'price'=>$price];
        }
    }

    // Personnel
    foreach($_POST['personnel'] ?? [] as $pid => $hours){
        if($hours>0){
            $stmt = $pdo->prepare("SELECT rate FROM personnel WHERE id=?");
            $stmt->execute([$pid]);
            $rate = (float)$stmt->fetchColumn();
            $items[] = ['type'=>'personnel','id'=>$pid,'qty'=>$hours,'price'=>$rate];
        }
    }

    // Equipment
    foreach($_POST['equipment'] ?? [] as $eid => $qty){
        if($qty>0){
            $stmt = $pdo->prepare("SELECT rate FROM equipment WHERE id=?");
            $stmt->execute([$eid]);
            $price = (float)$stmt->fetchColumn();
            $items[] = ['type'=>'equipment','id'=>$eid,'qty'=>$qty,'price'=>$price];
        }
    }

    // Other Expenses
    foreach($_POST['other_expense_name'] ?? [] as $idx => $name){
        $amount = floatval($_POST['other_expense_amount'][$idx] ?? 0);
        if($amount>0){
            $items[] = ['type'=>'expense','name'=>$name,'qty'=>1,'price'=>$amount];
        }
    }

    $total_amount = 0;
    foreach($items as $it) $total_amount += $it['qty']*$it['price'];

    // Generate unique order number
    $order_number = 'ORD'.time();

    try{
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_email, contact_number, appointment_date, total_amount, order_number, status) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$customer_name,$customer_email,$contact_number,$appointment_date,$total_amount,$order_number,'pending']);
        $order_id = $pdo->lastInsertId();

        $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, installation_type, qty, price) VALUES (?,?,?,?,?,?)");
        foreach($items as $it){
            $stmt_item->execute([
                $order_id,
                $it['type'],
                $it['id'] ?? null,
                $it['installation_type'] ?? null,
                $it['qty'],
                $it['price']
            ]);
        }
        $pdo->commit();
        $message = 'Order saved successfully!';
    }catch(Exception $e){
        $pdo->rollBack();
        $message = 'Error saving order: '.$e->getMessage();
    }
}

ob_start();
?>

<?php if($message): ?>
<div class="alert"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" class="create-order-grid" id="orderForm" novalidate>
    <div class="flex-1 flex flex-col gap-6">
        <!-- Client Info -->
        <div class="bg-white p-3 rounded-xl shadow shadow border border-gray-200">
            <h5 class="text-lg font-medium text-gray-700 mb-3">Client Information</h5>
            <div class="grid grid-cols-2 gap-4">
                <input type="text" name="customer_name" class="input" placeholder="Name" class="border rounded w-full text-sm p-2" required>
                <input type="email" name="customer_email" class="input" placeholder="Email" class="border rounded w-full text-sm p-2">
                <input type="text" name="contact_number" class="input" placeholder="Phone" class="border rounded w-full text-sm p-2">
                <input type="date" name="appointment_date" class="input" value="<?= date('Y-m-d') ?>" class="border rounded w-full p-2">
            </div>
        </div>

        <!-- PRODUCTS TABLE -->
        <div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
            <div class="flex items-center justify-between mb-3">
          <span class="font-medium text-gray-700">Material</span>
            <input id="productSearch" class="search-input" placeholder="Search products..." class="border px-3 py-2 rounded-lg shadow-sm w-64">
            </div>
            <div class="overflow-y-auto max-h-64 border rounded-lg">
                <table class="products-table" class="w-full border-collapse text-sm">
                     <thead class="bg-gray-100 sticky top-0">
              <tr><th class="p-2 text-left">Name</th><th class="p-2 text-center">Price</th><th class="p-2 text-center">Qty</th><th class="p-2 text-center">Subtotal</th></tr>
            </thead>
            <tbody>
                    <?php foreach($products as $p): $pid=(int)$p['id']; ?>
                        <tr class="border-b">
                            <td class="product-name p-2"><?= htmlspecialchars($p['name']) ?></td>
                            <td class="p-2 text-center">$<span class="prod-price"><?= number_format($p['price'],2) ?></span></td>
                            <td class="p-2 text-center">
                                <div class="inline-flex items-center space-x-2">
                                    <button type="button" class="px-2 py-1 bg-gray-200 rounded qtbn minus">-</button>
                                    <input type="number" min="0" value="0" name="product[<?= $pid ?>]" class="qty-input" data-price="<?= htmlspecialchars($p['price']) ?>">
                                    <button type="button" class="px-2 py-1 bg-gray-200 rounded qtbn plus">+</button>
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
        <div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Split System Installation</span>
            <input id="splitSearch" class="search-input" placeholder="Search split systems..." class="border px-3 py-2 rounded-lg shadow-sm w-64">
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
        <div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
        <div class="flex items-center justify-between mb-3">
          <span class="font-medium text-gray-700">Ducted Installation</span>
          <input id="splitSearch" class="search-input" placeholder="Search split systems..." class="border px-3 py-2 rounded-lg shadow-sm w-64">
        </div>

        <div class="overflow-y-auto max-h-64 border rounded-lg">
                <table class="products-table">
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
        <div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
        <div class="flex items-center justify-between mb-3">
            <span class="font-medium text-gray-700">Personnel</span>
            <input id="personnelSearch" class="search-input" placeholder="Search personnel...">
            <div>

            <div class="overflow-y-auto max-h-64 border rounded-lg">
                <table class="products-table">
                    <thead class="bg-gray-100 sticky top-0">
        <tr>
          <th class="p-2 text-left">Name</th>
          <th class="p-2 text-center">Rate</th>
          <th class="p-2 text-center">Hours</th>
          <th class="p-2 text-center">Subtotal</th>
        </tr>
      </thead>
                    <tbody>
                    <?php foreach($personnel as $p): $pid=(int)$p['id']; ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td>$<span class="pers-rate"><?= number_format($p['rate'],2) ?></span></td>
                            <td>
                                <div class="qty-box">
                                    <button type="button" class="qbtn hour-minus">-</button>
                                    <input type="number" min="0" value="0" name="personnel[<?= $pid ?>]" class="qty-input hour-input" data-rate="<?= htmlspecialchars($p['rate']) ?>">
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
        <div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
        <div class="flex items-center justify-between mb-3">
        <span class="font-medium text-gray-700">Equipment</span>
            <input id="equipmentSearch" class="search-input" placeholder="Search equipment...">
            <div class="table-wrap">
                <table class="products-table">
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
        <div class="bg-white p-4 rounded-xl shadow flex flex-col mb-4">
        <span class="font-medium text-gray-700 mb-2">Other Expenses</span>
            <h4>Other Expenses</h4>
            <div id="otherExpensesContainer"></div>
            <button type="button" class="qbtn" id="addExpenseBtn">Add</button>
        </div>
    </div>

    <!-- RIGHT PANEL WRAPPER -->
    <div class="w-80 flex flex-col gap-4">

    <!-- PROFIT CARD -->
    <div id="profitCard" class="bg-white p-4 rounded-xl shadow border border-gray-200">
        <h3 class="text-base font-semibold text-gray-700 mb-2">Profit Summary</h3>

        <div class="flex justify-between text-gray-600 mb-1">
            <span>Profit:</span>
            <span>$<span id="profitDisplay">0.00</span></span>
        </div>

        <div class="flex justify-between text-gray-600 mb-1">
            <span>Percent Margin:</span>
            <span><span id="profitMarginDisplay">0.00</span>%</span>
        </div>

        <div class="flex justify-between text-gray-600 mb-1">
            <span>Net Profit:</span>
            <span><span id="netProfitDisplay">0.00</span>%</span>
        </div>

        <div class="flex justify-between font-semibold text-gray-700">
            <span>Total Profit:</span>
            <span>$<span id="totalProfitDisplay">0.00</span></span>
        </div>
    </div>

    <!-- SUMMARY CARD -->
    <div id="rightPanel" class="bg-white p-6 rounded-2xl shadow border border-gray-200 h-auto max-h-[80vh] flex flex-col">

    <!-- ITEM LIST -->
        <div id="orderSummary" class="flex-1 overflow-y-auto mb-4">
            <span style="color:#777;">No items selected.</span>
        </div>
        <!-- TOTALS -->
        <hr class="mb-3">

        <p class="text-base font-medium text-gray-600 flex justify-between mb-1">
            <span>Subtotal:</span>
            <span>$<span id="subtotalDisplay">0.00</span></span>
        </p>

        <p class="text-base font-medium text-gray-600 flex justify-between mb-1">
            <span>Tax:</span>
            <span>$<span id="taxDisplay">0.00</span></span>
        </p>

        <p class="text-xl font-semibold flex justify-between text-blue-700 mb-4">
            <span>Grand Total:</span>
            <span>$<span id="grandDisplay">0.00</span></span>
        </p>

        <button type="submit" class= "w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 text-lg">
            Save Order
        </button>
    </div>

</div>
  </div>
</form>

<script>
(function(){
  function fmt(n){return Number(n||0).toFixed(2);}
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
        let price=parseFloat(input.dataset.price)||parseFloat(input.dataset.rate)||0;
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
        input.value=val; updateSummary();
      });
    });
  });

  document.getElementById('addExpenseBtn').addEventListener('click',function(){
    const row=document.createElement('div');
    row.className='other-expense-row';
    row.style.display='flex'; row.style.gap='8px'; row.style.marginBottom='8px';
    row.innerHTML='<input type="text" placeholder="Name" name="other_expense_name[]" class="input expense-name" style="flex:1;">'+
                  '<input type="number" placeholder="Amount" name="other_expense_amount[]" class="input expense-amount" style="width:110px;">'+
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
