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
    <div class="create-order-left">
        <!-- Customer Info (Name, Email, Contact, Date) -->
      <div class="bg-white p-3 rounded-xl shadow shadow border border-gray-200">
        <h5 class="text-lg font-medium text-gray-700 mb-3">Client Information</h5>
        <div class="grid grid-cols-2 gap-4">
          <input type="text" name="customer_name" placeholder="Name" class="border rounded w-full text-sm p-2" required>
          <input type="email" name="customer_email" placeholder="Email" class="border rounded w-full text-sm p-2">
          <input type="text" name="contact_number" placeholder="Phone Number" class="border rounded w-full text-sm p-2">
          <input type="date" name="appointment_date" id="appointment_date" value="<?= htmlspecialchars($selected_date ?? '') ?>" class="border rounded w-full p-2">
        </div>
      </div>
       <!-- Products -->
      <div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
        <div class="flex items-center justify-between mb-3">
          <span class="font-medium text-gray-700">Material</span>
          <input type="text" id="productSearch" placeholder="Search Product" class="border px-3 py-2 rounded-lg shadow-sm w-64">
        </div>
        <div class="overflow-y-auto max-h-64 border rounded-lg">
          <table id="productsTable" class="w-full border-collapse text-sm">
            <thead class="bg-gray-100 sticky top-0">
              <tr><th class="p-2 text-left">Name</th><th class="p-2 text-center">Price</th><th class="p-2 text-center">Qty</th><th class="p-2 text-center">Subtotal</th></tr>
            </thead>
            <tbody>
              <?php foreach($products as $p): ?>
              <tr class="border-b">
                <td class="product-name p-2"><?= htmlspecialchars($p['name']) ?></td>
                <td class="p-2 text-center"><?= number_format($p['price'], 2) ?></td>
                <td class="p-2 text-center">
                  <div class="inline-flex items-center space-x-2">
                    <button type="button" class="px-2 py-1 bg-gray-200 rounded minus-btn">-</button>
                    <input type="number" min="0" name="quantity[<?= (int)$p['id'] ?>]" value="0" class="qty-input border rounded w-16 text-center" data-price="<?= htmlspecialchars($p['price']) ?>">
                    <button type="button" class="px-2 py-1 bg-gray-200 rounded plus-btn">+</button>
                  </div>
                </td>
                <td class="subtotal p-2 text-center">0.00</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

        <!-- Split System Installation -->
      <div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
        <div class="flex items-center justify-between mb-3">
          <span class="font-medium text-gray-700">Split System Installation</span>
          <input type="text" id="splitSearch" placeholder="Search Split" class="border px-3 py-2 rounded-lg shadow-sm w-64">
        </div>
        <div class="overflow-y-auto max-h-64 border rounded-lg">
          <table id="splitTable" class="w-full border-collapse text-sm">
            <thead class="bg-gray-100 sticky top-0">
              <tr><th class="p-2 text-left">Name</th><th class="p-2 text-center">Unit Price</th><th class="p-2 text-center">Qty</th><th class="p-2 text-center">Subtotal</th></tr>
            </thead>
            <tbody>
              <?php foreach($split_installations as $s): ?>
              <tr class="border-b">
                <td class="item-name p-2"><?= htmlspecialchars($s['item_name']) ?></td>
                <td class="p-2 text-center"><?= number_format($s['unit_price'], 2) ?></td>
                <td class="p-2 text-center">
                  <div class="inline-flex items-center space-x-2">
                    <button type="button" class="px-2 py-1 bg-gray-200 rounded minus-btn">-</button>
                    <input type="number" min="0" name="split[<?= (int)$s['id'] ?>][qty]" value="0" class="split-qty border rounded w-16 text-center" data-price="<?= htmlspecialchars($s['unit_price']) ?>">
                    <button type="button" class="px-2 py-1 bg-gray-200 rounded plus-btn">+</button>
                  </div>
                </td>
                <td class="subtotal p-2 text-center">0.00</td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($split_installations)): ?>
              <tr><td colspan="4" class="p-4 text-center text-gray-500">No split items available.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

        <!-- Ducted Installations -->
      <div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
        <div class="flex items-center justify-between mb-3">
          <span class="font-medium text-gray-700">Ducted Installation</span>
        </div>
        <div class="overflow-y-auto max-h-64 border rounded-lg">
          <table id="ductedInstallationsTable" class="w-full border-collapse text-sm">
            <thead class="bg-gray-100 sticky top-0">
              <tr>
                <th class="p-2 text-left">Equipment</th>
                <th class="p-2 text-center">Type</th>
                <th class="p-2 text-center">Price</th>
                <th class="p-2 text-center">Qty</th>
                <th class="p-2 text-center">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($ducted_installations as $inst): ?>
              <tr class="border-b"
                  data-model-indoor="<?= htmlspecialchars($inst['model_name_indoor']) ?>"
                  data-model-outdoor="<?= htmlspecialchars($inst['model_name_outdoor']) ?>"
                  data-price="<?= htmlspecialchars($inst['total_cost']) ?>">
                <td class="p-2"><?= htmlspecialchars($inst['equipment_name']) ?></td>
                <td class="p-2 text-center">
                  <select name="ducted[<?= (int)$inst['id'] ?>][installation_type]" class="install-type border rounded p-1">
                    <option value="indoor">Indoor</option>
                    <option value="outdoor">Outdoor</option>
                  </select>
                </td>
                <td class="p-2 text-center"><?= number_format($inst['total_cost'],2) ?></td>
                <td class="p-2 text-center">
                  <div class="inline-flex items-center space-x-2">
                    <button type="button" class="px-2 py-1 bg-gray-200 rounded minus-btn">-</button>
                    <input type="number" min="0" name="ducted[<?= (int)$inst['id'] ?>][qty]" value="0" class="installation-qty border rounded w-16 text-center" data-price="<?= htmlspecialchars($inst['total_cost']) ?>">
                    <button type="button" class="px-2 py-1 bg-gray-200 rounded plus-btn">+</button>
                  </div>
                </td>
                <td class="installation-subtotal p-2 text-center">0.00</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

        <!-- Personnel -->
<div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Personnel</span>
    <input type="text" id="personnelSearch" placeholder="Search..." class="border px-3 py-2 rounded-lg shadow-sm w-64">
  </div>

  <div class="overflow-y-auto max-h-64 border rounded-lg">
    <table id="personnelTable" class="w-full border-collapse text-sm">
      <thead class="bg-gray-100 sticky top-0">
        <tr>
          <th class="p-2 text-left">Name</th>
          <th class="p-2 text-center">Rate</th>
          <th class="p-2 text-center">Hours</th>
          <th class="p-2 text-center">Subtotal</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach($personnel as $pers):
          $isBooked = in_array($pers['id'], $booked_personnel_ids);
        ?>
        <tr class="border-b <?= $isBooked ? 'bg-red-50 opacity-80' : '' ?>"
            data-personnel-id="<?= (int)$pers['id'] ?>"
            data-rate="<?= htmlspecialchars($pers['rate']) ?>">

          <td class="pers-name p-2"><?= htmlspecialchars($pers['name']) ?></td>
          <td class="p-2 text-center"><?= number_format($pers['rate'], 2) ?></td>

          <td class="p-2 text-center">
            <?php if(!$isBooked): ?>
            <div class="flex items-center justify-center gap-2">
              <button type="button" class="hour-minus bg-gray-200 px-2 rounded">–</button>
              <input type="number" name="personnel_hours[<?= (int)$pers['id'] ?>]"
                     class="hour-input w-12 text-center border rounded"
                     value="0" min="0">
              <button type="button" class="hour-plus bg-gray-200 px-2 rounded">+</button>
            </div>
            <?php else: ?>
              <span class="text-red-400 text-xs">Booked</span>
            <?php endif; ?>
          </td>

          <td class="pers-subtotal p-2 text-center">0.00</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

        <!-- Equipment -->
<div class="bg-white p-4 rounded-xl shadow flex flex-col shadow border border-gray-200">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Equipment</span>
    <input type="text" id="equipmentSearch" placeholder="Search..." class="border px-3 py-2 rounded-lg shadow-sm w-64">
  </div>

  <div class="overflow-y-auto max-h-64 border rounded-lg">
    <table id="equipmentTable" class="w-full border-collapse text-sm">
      <thead class="bg-gray-100 sticky top-0">
        <tr>
          <th class="p-2 text-left">Item</th>
          <th class="p-2 text-center">Rate</th>
          <th class="p-2 text-center">Qty</th>
          <th class="p-2 text-center">Subtotal</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach($equipment as $equip): ?>
        <tr class="border-b"
            data-equip-id="<?= (int)$equip['id'] ?>"
            data-rate="<?= htmlspecialchars($equip['rate']) ?>">

          <td class="equip-name p-2"><?= htmlspecialchars($equip['item']) ?></td>
          <td class="p-2 text-center"><?= number_format($equip['rate'], 2) ?></td>

          <td class="p-2 text-center">
            <div class="flex items-center justify-center gap-2">
              <button type="button" class="equip-minus bg-gray-200 px-2 rounded">–</button>
              <input type="number"
                     name="equipment_qty[<?= (int)$equip['id'] ?>]"
                     class="equip-input w-12 text-center border rounded"
                     value="0" min="0">
              <button type="button" class="equip-plus bg-gray-200 px-2 rounded">+</button>
            </div>
          </td>

          <td class="equip-subtotal p-2 text-center">0.00</td>
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
    </div>

    <!-- RIGHT PANEL -->
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
