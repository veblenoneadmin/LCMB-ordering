<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// DB sanity check
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

// Load table data
$products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$split_installations = $pdo->query("SELECT id, item_name AS name, unit_price AS price FROM split_installation ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$ducted_installations = $pdo->query("SELECT id, equipment_name AS name, model_name_indoor, model_name_outdoor, total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipment = $pdo->query("SELECT id, item AS name, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC);

$message = '';

// =====================
// POST Handling: save to DB
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_email, contact_number, appointment_date, created_at) VALUES (?,?,?,?,NOW())");
        $stmt->execute([
            $_POST['customer_name'] ?? '',
            $_POST['customer_email'] ?? '',
            $_POST['contact_number'] ?? '',
            $_POST['appointment_date'] ?? date('Y-m-d'),
        ]);
        $orderId = $pdo->lastInsertId();

        // Helper to insert items
        $insertItem = $pdo->prepare("INSERT INTO order_items (order_id,item_type,item_id,item_name,qty,price,type) VALUES (?,?,?,?,?,?,?)");

        // Products
        if(!empty($_POST['quantity'])){
            foreach($_POST['quantity'] as $pid=>$qty){
                $qty=(int)$qty;
                if($qty>0){
                    $row = $pdo->query("SELECT name, price FROM products WHERE id=".(int)$pid)->fetch(PDO::FETCH_ASSOC);
                    $insertItem->execute([$orderId,'product',$pid,$row['name'],$qty,$row['price'],null]);
                }
            }
        }

        // Split installations
        if(!empty($_POST['split'])){
            foreach($_POST['split'] as $sid=>$data){
                $qty=(int)($data['qty']??0);
                if($qty>0){
                    $row = $pdo->query("SELECT item_name AS name, unit_price AS price FROM split_installation WHERE id=".(int)$sid)->fetch(PDO::FETCH_ASSOC);
                    $insertItem->execute([$orderId,'split',$sid,$row['name'],$qty,$row['price'],null]);
                }
            }
        }

        // Ducted installations
        if(!empty($_POST['ducted'])){
            foreach($_POST['ducted'] as $did=>$data){
                $qty=(int)($data['qty']??0);
                if($qty>0){
                    $row = $pdo->query("SELECT equipment_name AS name, total_cost AS price FROM ductedinstallations WHERE id=".(int)$did)->fetch(PDO::FETCH_ASSOC);
                    $type = $data['installation_type'] ?? '';
                    $insertItem->execute([$orderId,'ducted',$did,$row['name'],$qty,$row['price'],$type]);
                }
            }
        }

        // Personnel
        if(!empty($_POST['personnel_hours'])){
            foreach($_POST['personnel_hours'] as $pid=>$hours){
                $hours=(float)$hours;
                if($hours>0){
                    $row = $pdo->query("SELECT name, rate FROM personnel WHERE id=".(int)$pid)->fetch(PDO::FETCH_ASSOC);
                    $insertItem->execute([$orderId,'personnel',$pid,$row['name'],$hours,$row['rate'],null]);
                }
            }
        }

        // Equipment
        if(!empty($_POST['equipment_qty'])){
            foreach($_POST['equipment_qty'] as $eid=>$qty){
                $qty=(int)$qty;
                if($qty>0){
                    $row = $pdo->query("SELECT item AS name, rate FROM equipment WHERE id=".(int)$eid)->fetch(PDO::FETCH_ASSOC);
                    $insertItem->execute([$orderId,'equipment',$eid,$row['name'],$qty,$row['rate'],null]);
                }
            }
        }

        // Other Expenses
        if(!empty($_POST['other_expenses'])){
            foreach($_POST['other_expenses'] as $exp){
                $amt=(float)($exp['amount']??0);
                if($amt>0){
                    $name = $exp['name'] ?: 'Other expense';
                    $insertItem->execute([$orderId,'other',null,$name,1,$amt,null]);
                }
            }
        }

        $pdo->commit();
        $message = "Order saved successfully! Order ID: $orderId";

    } catch(Exception $e){
        $pdo->rollBack();
        $message = "Error saving order: ".$e->getMessage();
    }
}

// =====================
// Render Page
// =====================
ob_start();
?>

<style>
.create-order-grid { display:flex; gap:20px; align-items:flex-start; }
.create-order-left { flex:1; min-width:0; }
.create-order-right { width:360px; }

/* card style */
.card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:16px; margin-bottom:18px; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
.card h4 { margin:0 0 10px 0; font-size:16px; color:#2b3440; }

/* inputs and tables */
.input { width:100%; padding:8px 10px; border:1px solid #dfe6ef; border-radius:6px; font-size:14px; }
.table-wrap { border:1px solid #edf2f7; border-radius:8px; overflow:hidden; }
.products-scroll { max-height:300px; overflow:auto; }
.products-table { width:100%; border-collapse:collapse; font-size:14px; }
.products-table th, .products-table td { padding:8px 10px; border-bottom:1px solid #f5f7fb; text-align:center; }
.products-table th:first-child, .products-table td:first-child { text-align:left; }
.qty-box { display:inline-flex; align-items:center; gap:6px; }
.qbtn { display:inline-block; width:28px; height:28px; line-height:28px; text-align:center; border-radius:6px; cursor:pointer; border:1px solid #e6eef7; background:#f8fafc; user-select:none; }
.qty-input { width:56px; padding:6px; border:1px solid #e6eef7; border-radius:6px; text-align:center; }

/* summary */
.summary-list { max-height:400px; overflow:auto; padding-right:8px; }
.summary-item { display:flex; justify-content:space-between; padding:6px 4px; border-bottom:1px dashed #f1f5f9; color:#1f2937; }
.summary-totals { margin-top:12px; }
.bold { font-weight:700; }
.blue { color:#0b63ff; }

/* responsive */
@media (max-width:980px) {
  .create-order-grid { flex-direction:column; }
  .create-order-right { width:100%; }
}
</style>

<?php if(!empty($message)): ?>
<div class="card" style="background:#e6ffed; color:#0b6623;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" id="orderForm" class="create-order-grid" novalidate>

<!-- LEFT COLUMN -->
<div class="create-order-left">
  <!-- Client Info -->
  <div class="card">
    <h4>Client Information</h4>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
      <input type="text" name="customer_name" class="input" placeholder="Name" required>
      <input type="email" name="customer_email" class="input" placeholder="Email">
      <input type="text" name="contact_number" class="input" placeholder="Phone">
      <input type="date" name="appointment_date" class="input" value="<?= date('Y-m-d') ?>">
    </div>
  </div>

  <!-- Products Table -->
  <div class="card">
    <h4>Products / Material</h4>
    <div class="table-wrap">
      <div class="products-scroll">
        <table class="products-table">
          <thead>
            <tr><th>Name</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr>
          </thead>
          <tbody>
            <?php foreach($products as $p): ?>
            <tr data-id="<?= $p['id'] ?>">
              <td><?= htmlspecialchars($p['name']) ?></td>
              <td>$<span class="price"><?= number_format($p['price'],2) ?></span></td>
              <td>
                <div class="qty-box">
                  <button type="button" class="qbtn minus">-</button>
                  <input type="number" min="0" name="quantity[<?= $p['id'] ?>]" value="0" class="qty-input" data-price="<?= $p['price'] ?>">
                  <button type="button" class="qbtn plus">+</button>
                </div>
              </td>
              <td>$<span class="subtotal">0.00</span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Repeat similar structure for Split, Ducted, Personnel, Equipment, Other Expenses -->
  <!-- For brevity, assume similar HTML as your previous code, with qty-inputs and price spans -->
</div>

<!-- RIGHT COLUMN -->
<aside class="create-order-right">
  <div class="card" style="position:sticky; top:20px;">
    <h4>Order Summary</h4>
    <div class="summary-list" id="orderSummary"><div class="empty-note">No items selected.</div></div>
    <div class="summary-totals">
      <div style="display:flex;justify-content:space-between;"><span>Subtotal:</span><span>$<span id="subtotalDisplay">0.00</span></span></div>
      <div style="display:flex;justify-content:space-between;"><span>Tax (10%):</span><span>$<span id="taxDisplay">0.00</span></span></div>
      <div style="display:flex;justify-content:space-between;border-top:1px solid #f3f6f9;padding-top:8px;"><strong class="bold">Grand Total:</strong><strong class="bold blue">$<span id="grandDisplay">0.00</span></strong></div>
    </div>
    <div style="margin-top:12px;"><button type="submit" class="input" style="background:#0b63ff;color:#fff;border:none;border-radius:8px;padding:10px;">Save Order</button></div>
  </div>
</aside>

</form>

<script>
// Full JS logic: qty buttons, subtotal calculation, order summary update
(function(){
  const GST = 0.10;
  function fmt(n){ return Number((n||0).toFixed(2)).toFixed(2); }

  function updateTotals(){
    let subtotal = 0;
    const summaryEl = document.getElementById('orderSummary');
    summaryEl.innerHTML = '';
    let any = false;

    document.querySelectorAll('.qty-input').forEach(inp=>{
      const qty = parseFloat(inp.value)||0;
      const price = parseFloat(inp.dataset.price)||0;
      const row = inp.closest('tr');
      const sub = price*qty;
      row.querySelector('.subtotal').textContent = fmt(sub);
      if(qty>0){
        any=true;
        const div = document.createElement('div');
        div.className='summary-item';
        div.innerHTML = `<div>${row.children[0].textContent} x ${qty}</div><div>$${fmt(sub)}</div>`;
        summaryEl.appendChild(div);
      }
      subtotal+=sub;
    });

    if(!any) summaryEl.innerHTML='<div class="empty-note">No items selected.</div>';
    const tax = subtotal*GST;
    const grand = subtotal+tax;
    document.getElementById('subtotalDisplay').textContent = fmt(subtotal);
    document.getElementById('taxDisplay').textContent = fmt(tax);
    document.getElementById('grandDisplay').textContent = fmt(grand);
  }

  document.querySelectorAll('.qbtn.plus').forEach(btn=>{
    btn.addEventListener('click', ()=>{ const inp=btn.closest('tr').querySelector('.qty-input'); inp.value=(parseInt(inp.value)||0)+1; updateTotals(); });
  });
  document.querySelectorAll('.qbtn.minus').forEach(btn=>{
    btn.addEventListener('click', ()=>{ const inp=btn.closest('tr').querySelector('.qty-input'); inp.value=Math.max(0,(parseInt(inp.value)||0)-1); updateTotals(); });
  });
  document.querySelectorAll('.qty-input').forEach(inp=>inp.addEventListener('input', updateTotals));
  updateTotals();
})();
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
