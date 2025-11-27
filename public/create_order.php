<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch tables
try { $products = $pdo->query("SELECT id,name,price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $products=[]; }
try { $split_installations = $pdo->query("SELECT id,item_name AS name,unit_price AS price FROM split_installation ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $split_installations=[]; }
try { $ducted_installations = $pdo->query("SELECT id,equipment_name AS name,model_name_indoor,model_name_outdoor,total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $ducted_installations=[]; }
try { $personnel = $pdo->query("SELECT id,name,rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $personnel=[]; }
try { $equipment = $pdo->query("SELECT id,item AS name,rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $equipment=[]; }

// Handle POST
if($_SERVER['REQUEST_METHOD']==='POST'){
    $customer_name = $_POST['customer_name'] ?? '';
    $customer_email = $_POST['customer_email'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? date('Y-m-d');
    $order_number = 'ORD-'.rand(1000,9999);
    $total_amount = 0;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO orders (customer_name,customer_email,contact_number,appointment_date,order_number,total_amount) VALUES (?,?,?,?,?,0)");
        $stmt->execute([$customer_name,$customer_email,$contact_number,$appointment_date,$order_number]);
        $order_id = $pdo->lastInsertId();

        $insertItem = function($type,$items) use ($pdo,&$total_amount,$order_id){
            foreach($items as $id => $qty){
                $id=(int)$id; $qty=(int)$qty;
                if($qty>0){
                    if($type==='product') $price = $pdo->query("SELECT price FROM products WHERE id=$id")->fetchColumn();
                    if($type==='split') $price = $pdo->query("SELECT unit_price FROM split_installation WHERE id=$id")->fetchColumn();
                    if($type==='ducted') $price = $pdo->query("SELECT total_cost FROM ductedinstallations WHERE id=$id")->fetchColumn();
                    if($type==='personnel') $price = $pdo->query("SELECT rate FROM personnel WHERE id=$id")->fetchColumn();
                    if($type==='equipment') $price = $pdo->query("SELECT rate FROM equipment WHERE id=$id")->fetchColumn();
                    $line_total = $price*$qty;
                    $total_amount+=$line_total;
                    $stmtIns = $pdo->prepare("INSERT INTO order_items (order_id,item_type,item_id,qty,price) VALUES (?,?,?,?,?)");
                    $stmtIns->execute([$order_id,$type==='split'||$type==='ducted'?'installation':$type,$id,$qty,$price]);
                }
            }
        };

        $insertItem('product', $_POST['products'] ?? []);
        $insertItem('split', $_POST['split_installation'] ?? []);
        $insertItem('ducted', $_POST['ducted'] ?? []);
        $insertItem('personnel', $_POST['personnel'] ?? []);
        $insertItem('equipment', $_POST['equipment'] ?? []);

        if(!empty($_POST['other_expenses'])){
            foreach($_POST['other_expenses'] as $exp){
                $name = $exp['name'] ?? ''; $amount = (float)($exp['amount'] ?? 0);
                if($name && $amount>0){
                    $total_amount+=$amount;
                    $stmtExp = $pdo->prepare("INSERT INTO order_items (order_id,item_type,item_id,qty,price) VALUES (?,?,?,?,?)");
                    $stmtExp->execute([$order_id,'expense',0,1,$amount]);
                }
            }
        }

        $stmtUpd = $pdo->prepare("UPDATE orders SET total_amount=? WHERE id=?");
        $stmtUpd->execute([$total_amount,$order_id]);

        $pdo->commit();
        echo "<script>alert('Order saved successfully!');window.location='create_order.php';</script>";
        exit;
    } catch(Exception $e){
        $pdo->rollBack();
        echo "<script>alert('Error: ".addslashes($e->getMessage())."');</script>";
    }
}

ob_start();
?>

<form method="post" class="create-order-grid" id="orderForm">

  <!-- LEFT PANEL -->
  <div class="create-order-left">

    <!-- CLIENT INFO -->
    <div class="card">
      <h4>Client Information</h4>
      <input type="text" name="customer_name" placeholder="Name" class="input" required>
      <input type="email" name="customer_email" placeholder="Email" class="input">
      <input type="text" name="contact_number" placeholder="Phone" class="input">
      <input type="date" name="appointment_date" class="input" value="<?= date('Y-m-d') ?>">
    </div>

    <!-- PRODUCTS -->
    <div class="card">
      <h4>Material</h4>
      <div class="table-wrap">
        <table class="products-table">
          <thead><tr><th>Name</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach($products as $p): $pid=(int)$p['id']; ?>
            <tr>
              <td><?= htmlspecialchars($p['name']) ?></td>
              <td>$<?= number_format($p['price'],2) ?></td>
              <td><input type="number" min="0" value="0" class="qty-input" name="products[<?= $pid ?>]" data-price="<?= $p['price'] ?>"></td>
              <td>$<span class="row-subtotal">0.00</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- SPLIT INSTALLATION -->
    <div class="card">
      <h4>Split System Installation</h4>
      <div class="table-wrap">
        <table class="products-table">
          <thead><tr><th>Name</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach($split_installations as $s): $sid=(int)$s['id']; ?>
            <tr>
              <td><?= htmlspecialchars($s['name']) ?></td>
              <td>$<?= number_format($s['price'],2) ?></td>
              <td><input type="number" min="0" value="0" class="qty-input" name="split_installation[<?= $sid ?>]" data-price="<?= $s['price'] ?>"></td>
              <td>$<span class="row-subtotal">0.00</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- DUCTED INSTALLATION -->
    <div class="card">
      <h4>Ducted Installation</h4>
      <div class="table-wrap">
        <table class="products-table">
          <thead><tr><th>Equipment</th><th>Type</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach($ducted_installations as $d): $did=(int)$d['id']; ?>
            <tr>
              <td><?= htmlspecialchars($d['name']) ?></td>
              <td>
                <select name="ducted[<?= $did ?>][type]">
                  <option value="indoor">Indoor</option>
                  <option value="outdoor">Outdoor</option>
                </select>
              </td>
              <td>$<?= number_format($d['price'],2) ?></td>
              <td><input type="number" min="0" value="0" class="qty-input" name="ducted[<?= $did ?>][qty]" data-price="<?= $d['price'] ?>"></td>
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
      <div class="table-wrap">
        <table class="products-table">
          <thead><tr><th>Name</th><th>Rate</th><th>Hours</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach($personnel as $pers): $pid=(int)$pers['id']; ?>
            <tr>
              <td><?= htmlspecialchars($pers['name']) ?></td>
              <td>$<?= number_format($pers['rate'],2) ?></td>
              <td><input type="number" min="0" value="0" class="qty-input" name="personnel[<?= $pid ?>]" data-price="<?= $pers['rate'] ?>"></td>
              <td>$<span class="row-subtotal">0.00</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- EQUIPMENT -->
    <div class="card">
      <h4>Equipment</h4>
      <div class="table-wrap">
        <table class="products-table">
          <thead><tr><th>Item</th><th>Rate</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach($equipment as $eq): $eid=(int)$eq['id']; ?>
            <tr>
              <td><?= htmlspecialchars($eq['name']) ?></td>
              <td>$<?= number_format($eq['rate'],2) ?></td>
              <td><input type="number" min="0" value="0" class="qty-input" name="equipment[<?= $eid ?>]" data-price="<?= $eq['rate'] ?>"></td>
              <td>$<span class="row-subtotal">0.00</span></td>
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
      <button type="button" id="addExpenseBtn">Add Expense</button>
    </div>

  </div>

  <!-- RIGHT PANEL -->
  <aside class="create-order-right">
    <div class="card card-summary">
      <h4>Order Summary</h4>
      <div class="summary-list" id="orderSummary"><div class="empty-note">No items selected</div></div>
      <div class="summary-totals">
        <div>Subtotal: $<span id="subtotalDisplay">0.00</span></div>
        <div>Tax (10%): $<span id="taxDisplay">0.00</span></div>
        <div><strong>Grand Total: $<span id="grandDisplay">0.00</span></strong></div>
      </div>
      <button type="submit">Save Order</button>
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
<script>
(function(){
  function fmt(n){ return Number(n||0).toFixed(2); }
  function updateSummary(){
    let subtotal=0;
    const summary=document.getElementById('orderSummary');
    summary.innerHTML='';
    document.querySelectorAll('input.qty-input').forEach(input=>{
      const val=parseFloat(input.value)||0;
      if(val>0){
        const name=input.closest('tr').children[0].textContent;
        const price=parseFloat(input.dataset.price)||0;
        subtotal+=val*price;
        const div=document.createElement('div');
        div.textContent=`${name} x ${val} = $${fmt(val*price)}`;
        summary.appendChild(div);
      }
    });
    // Other expenses
    document.querySelectorAll('#otherExpensesContainer .other-expense-row').forEach(row=>{
      const val=parseFloat(row.querySelector('.expense-amount')?.value||0);
      subtotal+=val;
      if(val>0){
        const div=document.createElement('div');
        div.textContent=row.querySelector('.expense-name')?.value+` = $${fmt(val)}`;
        summary.appendChild(div);
      }
    });
    if(subtotal===0) summary.innerHTML='<div class="empty-note">No items selected</div>';
    document.getElementById('subtotalDisplay').textContent=fmt(subtotal);
    document.getElementById('taxDisplay').textContent=fmt(subtotal*0.1);
    document.getElementById('grandDisplay').textContent=fmt(subtotal*1.1);
  }
  document.querySelectorAll('input.qty-input').forEach(i=>i.addEventListener('input',updateSummary));
  document.getElementById('addExpenseBtn').addEventListener('click',function(){
    const div=document.createElement('div'); div.className='other-expense-row';
    div.innerHTML='<input type="text" placeholder="Name" class="expense-name"> <input type="number" placeholder="Amount" class="expense-amount"> <button type="button" class="remove-expense">x</button>';
    document.getElementById('otherExpensesContainer').appendChild(div);
    div.querySelector('.expense-amount').addEventListener('input',updateSummary);
    div.querySelector('.remove-expense').addEventListener('click',()=>{div.remove(); updateSummary();});
  });
})();
</script>

<style>
.create-order-grid{display:flex;gap:20px;}
.create-order-left{flex:1;}
.create-order-right{width:320px;}
.summary-list{max-height:300px;overflow:auto;border:1px solid #ddd;padding:5px;margin-bottom:5px;}
.row-subtotal{font-weight:bold;}
.empty-note{color:#777;text-align:center;}
</style>

<?php
$content=ob_get_clean();
renderLayout('Create Order',$content,'create_order');
?>
