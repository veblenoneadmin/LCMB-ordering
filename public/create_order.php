<?php
// create_order.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch data
try { $products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $products=[]; }
try { $split_installations = $pdo->query("SELECT id, item_name AS name, unit_price AS price FROM split_installation ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $split_installations=[]; }
try { $ducted_installations = $pdo->query("SELECT id, equipment_name AS name, model_name_indoor, model_name_outdoor, total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $ducted_installations=[]; }
try { $personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $personnel=[]; }
try { $equipment = $pdo->query("SELECT id, item AS name, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $equipment=[]; }

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Basic client info
        $customer_name = $_POST['customer_name'] ?? '';
        $customer_email = $_POST['customer_email'] ?? null;
        $contact_number = $_POST['contact_number'] ?? null;
        $appointment_date = $_POST['appointment_date'] ?? null;
        $subtotal = floatval($_POST['subtotal'] ?? 0);
        $tax = floatval($_POST['tax'] ?? 0);
        $grand_total = floatval($_POST['grand_total'] ?? 0);

        // Generate unique order number
        $order_number = 'ORD-' . time();

        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_email, contact_number, appointment_date, total_amount, order_number, status, total, tax, discount) VALUES (:name,:email,:phone,:date,:total,:ordernum,'pending',:total,:tax,0)");
        $stmt->execute([
            ':name' => $customer_name,
            ':email' => $customer_email,
            ':phone' => $contact_number,
            ':date' => $appointment_date,
            ':total' => $grand_total,
            ':ordernum' => $order_number,
            ':tax' => $tax
        ]);

        $order_id = $pdo->lastInsertId();

        // Insert order items (products)
        foreach ($products as $p) {
            $pid = $p['id'];
            $qty = intval($_POST['product'][$pid] ?? 0);
            if ($qty > 0) {
                $price = floatval($p['price']);
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id,item_type,item_id,qty,price) VALUES (:orderid,'product',:itemid,:qty,:price)");
                $stmt->execute([':orderid'=>$order_id,':itemid'=>$pid,':qty'=>$qty,':price'=>$price]);
            }
        }

        // Split Installations
        foreach ($split_installations as $s) {
            $sid = $s['id'];
            $qty = intval($_POST['split'][$sid] ?? 0);
            if ($qty > 0) {
                $price = floatval($s['price']);
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id,item_type,item_id,qty,price) VALUES (:orderid,'installation',:itemid,:qty,:price)");
                $stmt->execute([':orderid'=>$order_id,':itemid'=>$sid,':qty'=>$qty,':price'=>$price]);
            }
        }

        // Ducted Installations
        foreach ($ducted_installations as $d) {
            $did = $d['id'];
            $qty = intval($_POST['ducted'][$did]['qty'] ?? 0);
            $type = $_POST['ducted'][$did]['type'] ?? 'indoor';
            if ($qty > 0) {
                $price = floatval($d['price']);
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id,item_type,item_id,installation_type,qty,price) VALUES (:orderid,'installation',:itemid,:type,:qty,:price)");
                $stmt->execute([':orderid'=>$order_id,':itemid'=>$did,':type'=>$type,':qty'=>$qty,':price'=>$price]);
            }
        }

        // Personnel
        foreach ($personnel as $p) {
            $pid = $p['id'];
            $hours = intval($_POST['personnel'][$pid] ?? 0);
            if ($hours > 0) {
                $price = floatval($p['rate']);
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id,item_type,item_id,qty,price) VALUES (:orderid,'personnel',:itemid,:qty,:price)");
                $stmt->execute([':orderid'=>$order_id,':itemid'=>$pid,':qty'=>$hours,':price'=>$price]);
            }
        }

        // Equipment
        foreach ($equipment as $e) {
            $eid = $e['id'];
            $qty = intval($_POST['equipment'][$eid] ?? 0);
            if ($qty > 0) {
                $price = floatval($e['rate']);
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id,item_type,item_id,qty,price) VALUES (:orderid,'equipment',:itemid,:qty,:price)");
                $stmt->execute([':orderid'=>$order_id,':itemid'=>$eid,':qty'=>$qty,':price'=>$price]);
            }
        }

        $pdo->commit();
        $success_msg = "Order saved successfully!";
    } catch(Exception $e){
        $pdo->rollBack();
        $error_msg = "Error saving order: ".$e->getMessage();
    }
}

ob_start();
?>

<!-- MAIN GRID -->
<form method="post" class="create-order-grid" id="orderForm" novalidate>

  <!-- LEFT PANEL -->
  <div class="create-order-left">

    <!-- CLIENT INFO -->
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
      <div class="table-wrap">
        <table class="products-table">
          <thead><tr><th>Name</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach($products as $p):
            $pid=(int)$p['id']; $price=number_format((float)$p['price'],2,'.',''); ?>
            <tr>
              <td><?= htmlspecialchars($p['name']) ?></td>
              <td>$<span class="prod-price"><?= $price ?></span></td>
              <td><input type="number" min="0" value="0" class="qty-input" name="product[<?= $pid ?>]" data-price="<?= $p['price'] ?>"></td>
              <td>$<span class="row-subtotal">0.00</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- SPLIT INSTALLATION TABLE -->
    <div class="card">
      <h4>Split Installation</h4>
      <div class="table-wrap">
        <table class="products-table">
          <thead><tr><th>Name</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach($split_installations as $s):
            $sid=(int)$s['id']; ?>
            <tr>
              <td><?= htmlspecialchars($s['name']) ?></td>
              <td>$<span class="split-price"><?= number_format((float)$s['price'],2,'.','') ?></span></td>
              <td><input type="number" min="0" value="0" class="qty-input" name="split[<?= $sid ?>]" data-price="<?= $s['price'] ?>"></td>
              <td>$<span class="row-subtotal">0.00</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- DUCTED INSTALLATION TABLE -->
    <div class="card">
      <h4>Ducted Installation</h4>
      <div class="table-wrap">
        <table class="products-table">
          <thead><tr><th>Equipment</th><th>Type</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach($ducted_installations as $d):
            $did=(int)$d['id']; ?>
            <tr>
              <td><?= htmlspecialchars($d['name']) ?></td>
              <td>
                <select name="ducted[<?= $did ?>][type]" class="input">
                  <option value="indoor">Indoor</option>
                  <option value="outdoor">Outdoor</option>
                </select>
              </td>
              <td>$<span class="ducted-price"><?= number_format((float)$d['price'],2,'.','') ?></span></td>
              <td><input type="number" min="0" value="0" class="qty-input" name="ducted[<?= $did ?>][qty]" data-price="<?= $d['price'] ?>"></td>
              <td>$<span class="row-subtotal">0.00</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PERSONNEL TABLE -->
    <div class="card">
      <h4>Personnel</h4>
      <div class="table-wrap">
        <table class="products-table">
          <thead><tr><th>Name</th><th>Rate</th><th>Hours</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach($personnel as $p):
            $pid=(int)$p['id']; ?>
            <tr>
              <td><?= htmlspecialchars($p['name']) ?></td>
              <td>$<span class="pers-rate"><?= number_format((float)$p['rate'],2,'.','') ?></span></td>
              <td><input type="number" min="0" value="0" class="qty-input" name="personnel[<?= $pid ?>]" data-price="<?= $p['rate'] ?>"></td>
              <td>$<span class="row-subtotal">0.00</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- EQUIPMENT TABLE -->
    <div class="card">
      <h4>Equipment</h4>
      <div class="table-wrap">
        <table class="products-table">
          <thead><tr><th>Item</th><th>Rate</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach($equipment as $e):
            $eid=(int)$e['id']; ?>
            <tr>
              <td><?= htmlspecialchars($e['name']) ?></td>
              <td>$<span class="equip-rate"><?= number_format((float)$e['rate'],2,'.','') ?></span></td>
              <td><input type="number" min="0" value="0" class="qty-input" name="equipment[<?= $eid ?>]" data-price="<?= $e['rate'] ?>"></td>
              <td>$<span class="row-subtotal">0.00</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div> <!-- END LEFT PANEL -->

  <!-- RIGHT PANEL -->
  <aside class="create-order-right">
    <div class="card card-summary">
      <h4>Order Summary</h4>
      <div id="orderSummary"><div class="empty-note">No items selected.</div></div>
      <div class="summary-totals">
        <div class="flex justify-between"><span>Subtotal:</span><span>$<span id="subtotalDisplay">0.00</span></span></div>
        <div class="flex justify-between"><span>Tax (10%):</span><span>$<span id="taxDisplay">0.00</span></span></div>
        <div class="flex justify-between border-t"><strong>Grand Total:</strong><strong>$<span id="grandDisplay">0.00</span></strong></div>
      </div>
      <button type="submit" class="input">Save Order</button>
    </div>
  </aside>

</form>

<style>
.create-order-grid{display:flex;gap:20px;}
.create-order-left{flex:1;}
.create-order-right{width:320px;}
.card{padding:16px;margin-bottom:20px;background:#fff;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.1);}
.products-table{width:100%;border-collapse:collapse;}
.products-table th, .products-table td{border:1px solid #ddd;padding:6px;}
.summary-list{max-height:300px;overflow:auto;}
.empty-note{color:#7e8796;font-size:13px;text-align:center;padding:12px 0;}
.summary-totals{margin-top:12px;}
.flex{display:flex;justify-content:space-between;}
.border-t{border-top:1px solid #ddd;padding-top:6px;margin-top:6px;}
</style>

<script>
(function(){
  function fmt(n){return Number(n||0).toFixed(2);}
  function updateSummary(){
    let subtotal=0;
    const summaryEl=document.getElementById('orderSummary');
    summaryEl.innerHTML='';
    const rows=document.querySelectorAll('.create-order-left .products-table tbody tr');
    rows.forEach(r=>{
      const input=r.querySelector('input.qty-input');
      if(!input) return;
      const val=parseFloat(input.value)||0;
      if(val>0){
        let name=r.cells[0].textContent;
        let price=parseFloat(input.dataset.price)||0;
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
  });

  updateSummary();
})();
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
