<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch data safely
$products = $pdo->query("SELECT id,name,price,category FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$split_installations = $pdo->query("SELECT id,item_name AS name,unit_price AS price,category FROM split_installation ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$ducted_installations = $pdo->query("SELECT id,equipment_name AS name,total_cost AS price,category FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$personnel = $pdo->query("SELECT id,name,rate,category FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipment = $pdo->query("SELECT id,item AS name,rate,category FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC);

$message = '';
function f2($v){ return number_format((float)$v,2,'.',''); }

if($_SERVER['REQUEST_METHOD']==='POST'){
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $job_address = trim($_POST['job_address'] ?? '');
    $appointment_date = $_POST['appointment_date'] ?: null;

    $items = [];

    // PRODUCTS
    foreach($_POST['product'] ?? [] as $pid=>$qty){
        $qty=intval($qty);
        if($qty>0){
            $price = (float)$pdo->query("SELECT price FROM products WHERE id=".intval($pid))->fetchColumn();
            $items[]=['item_type'=>'product','item_id'=>$pid,'installation_type'=>null,'qty'=>$qty,'price'=>$price];
        }
    }

    // SPLIT INSTALLATIONS
    foreach($_POST['split'] ?? [] as $sid=>$qty){
        $qty=intval($qty);
        if($qty>0){
            $price = (float)$pdo->query("SELECT unit_price FROM split_installation WHERE id=".intval($sid))->fetchColumn();
            $items[]=['item_type'=>'installation','item_id'=>$sid,'installation_type'=>null,'qty'=>$qty,'price'=>$price];
        }
    }

    // DUCTED INSTALLATIONS
    foreach($_POST['ducted'] ?? [] as $did=>$data){
        $qty=intval($data['qty'] ?? 0);
        $type=$data['type'] ?? 'indoor';
        if($qty>0){
            $price=(float)$pdo->query("SELECT total_cost FROM ductedinstallations WHERE id=".intval($did))->fetchColumn();
            $items[]=[
                'item_type'=>'installation',
                'item_id'=>$did,
                'installation_type'=>in_array($type,['indoor','outdoor'])?$type:'indoor',
                'qty'=>$qty,
                'price'=>$price
            ];
        }
    }

    // EQUIPMENT
    foreach($_POST['equipment'] ?? [] as $eid=>$qty){
        $qty=intval($qty);
        if($qty>0){
            $price=(float)$pdo->query("SELECT rate FROM equipment WHERE id=".intval($eid))->fetchColumn();
            $items[]=['item_type'=>'equipment','item_id'=>$eid,'installation_type'=>null,'qty'=>$qty,'price'=>$price];
        }
    }

    // OTHER EXPENSES
    $other_names=$_POST['other_expense_name'] ?? [];
    $other_amounts=$_POST['other_expense_amount'] ?? [];
    foreach($other_amounts as $i=>$amt){
        $amt=floatval($amt);
        $name=trim($other_names[$i] ?? '');
        if($amt>0){
            $items[]=['item_type'=>'expense','item_id'=>0,'installation_type'=>$name ?: 'Other Expense','qty'=>1,'price'=>$amt];
        }
    }

    // PERSONNEL
    $personnel_dispatch=[];
    foreach($_POST['personnel_hours'] ?? [] as $pid=>$hours){
        $hours=floatval($hours);
        if($hours<=0) continue;
        $rate=(float)$pdo->query("SELECT rate FROM personnel WHERE id=".intval($pid))->fetchColumn();
        $date=$_POST['personnel_date'][$pid] ?? $appointment_date ?? date('Y-m-d');
        $items[]=['item_type'=>'personnel','item_id'=>$pid,'installation_type'=>null,'qty'=>$hours,'price'=>$rate];
        $personnel_dispatch[]=['personnel_id'=>$pid,'date'=>$date,'hours'=>$hours];
    }

    $subtotal=0;
    foreach($items as $it) $subtotal+=((float)$it['qty']*(float)$it['price']);
    $tax=round($subtotal*0.10,2);
    $grand_total=round($subtotal+$tax,2);
    $discount=0.00;
    $order_number='ORD'.time().rand(10,99);

    try{
        $pdo->beginTransaction();
        $stmt=$pdo->prepare("INSERT INTO orders (customer_name,customer_email,contact_number,job_address,appointment_date,total_amount,order_number,status,total,tax,discount,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([$customer_name,$customer_email,$contact_number,$job_address,$appointment_date ?: null,f2($subtotal),$order_number,'pending',f2($grand_total),f2($tax),f2($discount)]);
        $order_id=$pdo->lastInsertId();

        $stmt_item=$pdo->prepare("INSERT INTO order_items (order_id,item_type,item_id,installation_type,qty,price,created_at) VALUES (?,?,?,?,?,?,NOW())");
        foreach($items as $it){
            $stmt_item->execute([$order_id,$it['item_type'],$it['item_id'],$it['installation_type'],$it['qty'],f2($it['price'])]);
        }

        // Dispatch
        if(!empty($personnel_dispatch)){
            $stmt_disp=$pdo->prepare("INSERT INTO dispatch (order_id,personnel_id,date,hours,created_at) VALUES (?,?,?,?,NOW())");
            foreach($personnel_dispatch as $d){
                $stmt_disp->execute([$order_id,$d['personnel_id'],$d['date'],f2($d['hours'])]);
            }
        }

        $pdo->commit();
        header("Location: review_order.php?order_id=".$order_id);
        exit;
    }catch(Exception $e){
        $pdo->rollBack();
        $message='Error saving order: '.$e->getMessage();
    }
}
ob_start();
?>

<!-- HTML form (Products table example, all tables same style) -->
<form method="post" class="create-order-grid" novalidate>
<div class="flex-1 flex flex-col gap-6">

<!-- CLIENT INFO -->
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
<?php function render_table($title,$items,$type='product',$show_type=false){ ?>
<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
<div class="flex items-center justify-between mb-3">
<span class="font-medium text-gray-700"><?= $title ?></span>
<input class="search-input" placeholder="Search..." oninput="searchTable(this)">
</div>
<div class="overflow-y-auto max-h-64 border rounded-lg">
<table class="products-table w-full border-collapse text-sm">
<thead class="bg-gray-100 sticky top-0">
<tr>
<th class="p-2 text-left">Name</th>
<?php if($show_type) echo '<th class="p-2 text-left">Type</th>'; ?>
<th class="p-2 text-center">Price</th>
<th class="p-2 text-center">Qty</th>
<th class="p-2 text-center">Subtotal</th>
</tr>
</thead>
<tbody>
<?php foreach($items as $i): $id=(int)$i['id']; ?>
<tr class="border-b">
<td class="product-name p-2"><?= htmlspecialchars($i['name']) ?></td>
<?php if($show_type) echo '<td class="p-2">'.htmlspecialchars($i['category'] ?? '').'</td>'; ?>
<td class="p-2 text-center">$<span class="prod-price"><?= f2($i['price'] ?? $i['rate']) ?></span></td>
<td class="p-2 text-center">
<div class="qty-wrapper">
<button type="button" class="qtbn minus">-</button>
<input type="number" min="0" value="0" name="<?= $type ?>[<?= $id ?>]" class="qty-input" data-price="<?= htmlspecialchars($i['price'] ?? $i['rate']) ?>">
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
<?php } ?>

<?php render_table('Material',$products,'product'); ?>
<?php render_table('Split System Installation',$split_installations,'split'); ?>
<?php render_table('Ducted Installation',$ducted_installations,'ducted',true); ?>
<?php render_table('Personnel',$personnel,'personnel',true); ?>
<?php render_table('Equipment',$equipment,'equipment'); ?>

<!-- Other Expenses -->
<div class="bg-white p-4 rounded-xl shadow mb-4">
<span class="font-medium text-gray-700 mb-2">Other Expenses</span>
<div id="otherExpensesContainer"></div>
<button type="button" class="qbtn" id="addExpenseBtn">Add</button>
</div>

</div> <!-- END LEFT PANEL -->

<!-- RIGHT PANEL -->
<div class="create-order-right" style="width:360px;">
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

<!-- CSS -->
<style>
.create-order-grid { display:flex; gap:20px; align-items:flex-start; flex-wrap:nowrap; }
.create-order-right { position:sticky; top:24px; width:360px; flex-shrink:0; align-self:flex-start; }
.products-table td, .products-table th { vertical-align: middle; }
.empty-note { color:#7e8796; font-size:13px; text-align:center; padding:12px 0; }
.qty-box input.qty-input { width:72px; text-align:center; }
@media(max-width:1000px){
.create-order-grid{flex-direction:column;}
.create-order-right{width:100%;}
}
</style>

<!-- JS for quantity, subtotal, summary, and disable booked dates -->
<script>
document.addEventListener("DOMContentLoaded",function(){
  function parseFloatSafe(v){return parseFloat(v)||0;}
  function fmt(n){return Number(n||0).toFixed(2);}

  function updateRowSubtotal(row){
      if(!row) return;
      const persInput=row.querySelector(".pers-hours");
      if(persInput){
          const rate=parseFloatSafe(persInput.dataset.rate);
          const hours=parseFloatSafe(persInput.value);
          const persSub=row.querySelector(".pers-subtotal");
          if(persSub) persSub.textContent=(hours*rate).toFixed(2);
          return;
      }
      const input=row.querySelector(".qty-input");
      if(!input) return;
      const price=parseFloatSafe(input.dataset.price || input.dataset.rate);
      const qty=parseFloatSafe(input.value);
      const subtotalEl=row.querySelector(".row-subtotal, .equip-subtotal");
      if(subtotalEl) subtotalEl.textContent=(qty*price).toFixed(2);
  }

  function updateSummary(){
      let subtotal=0;
      document.querySelectorAll("tr").forEach(row=>{
          const subEl=row.querySelector(".row-subtotal, .pers-subtotal, .equip-subtotal");
          if(subEl) subtotal+=parseFloatSafe(subEl.textContent);
      });
      document.querySelectorAll("input[name='other_expense_amount[]']").forEach(inp=>subtotal+=parseFloatSafe(inp.value));
      const tax=subtotal*0.10;
      const grand=subtotal+tax;
      document.getElementById("subtotalDisplay").textContent=fmt(subtotal);
      document.getElementById("taxDisplay").textContent=fmt(tax);
      document.getElementById("grandDisplay").textContent=fmt(grand);

      const summaryEl=document.getElementById('orderSummary');
      summaryEl.innerHTML='';
      document.querySelectorAll("tr").forEach(row=>{
        const name=row.querySelector('td')?.textContent?.trim();
        const subEl=row.querySelector(".row-subtotal, .pers-subtotal, .equip-subtotal");
        if(name && subEl && parseFloatSafe(subEl.textContent)>0){
            let qty='';
            if(row.querySelector('.pers-hours')) qty=(row.querySelector('.pers-hours').value||'0')+' hr';
            else { const q=row.querySelector('.qty-input'); if(q) qty=q.value||'0'; }
            const div=document.createElement('div');
            div.className='summary-item flex justify-between py-1';
            div.innerHTML=`<span style="color:#374151">${name}${qty?(' x '+qty):''}</span><span style="color:#111827">$${fmt(parseFloatSafe(subEl.textContent))}</span>`;
            summaryEl.appendChild(div);
        }
      });
      if(summaryEl.innerHTML.trim()==='') summaryEl.innerHTML='<div class="empty-note">No items selected.</div>';
  }

  document.querySelectorAll(".qbtn, .qtbn").forEach(btn=>{
      btn.addEventListener("click",()=>{
          const input=btn.closest("td, div").querySelector("input");
          if(!input) return;
          let val=parseFloat(input.value)||0;
          if(btn.classList.contains("plus")||btn.classList.contains("split-plus")||btn.classList.contains("ducted-plus")||btn.classList.contains("equip-plus")||btn.classList.contains("hour-plus")) val++;
          else if(btn.classList.contains("minus")||btn.classList.contains("split-minus")||btn.classList.contains("ducted-minus")||btn.classList.contains("equip-minus")||btn.classList.contains("hour-minus")) val=Math.max(0,val-1);
          input.value=val;
          updateRowSubtotal(input.closest("tr"));
          updateSummary();
      });
  });

  document.querySelectorAll(".qty-input").forEach(input=>{
      input.addEventListener('input',()=>{ updateRowSubtotal(input.closest("tr")); updateSummary(); });
  });

// Other Expenses
document.getElementById("addExpenseBtn").addEventListener("click",function(){
    const container=document.getElementById("otherExpensesContainer");
    const div=document.createElement("div");
    div.className="flex gap-2 mb-2";
    div.innerHTML=`
        <input type="text" name="other_expense_name[]" placeholder="Expense Name" class="input flex-1">
        <input type="number" name="other_expense_amount[]" placeholder="Amount" class="input w-24" min="0" step="0.01">
        <button type="button" class="qbtn remove-expense">Remove</button>
    `;
    container.appendChild(div);

    div.querySelector(".remove-expense").addEventListener("click",function(){
        div.remove();
        updateSummary();
    });

    div.querySelector('input[name="other_expense_amount[]"]').addEventListener("input", updateSummary);
});

// Update summary for other expenses input
document.querySelectorAll('input[name="other_expense_amount[]"]').forEach(inp=>inp.addEventListener("input", updateSummary));

// --------------------------
// Disable booked dates in personnel
// --------------------------
fetch('/fetch_dispatch.php')
.then(res=>res.json())
.then(data=>{
    const bookedDatesByPersonnel={};
    data.forEach(d=>{
        const pid = d.personnel; // assuming personnel_name
        const date = d.date || d.day; // adjust to actual fetch
        if(!bookedDatesByPersonnel[pid]) bookedDatesByPersonnel[pid]=[];
        bookedDatesByPersonnel[pid].push(date);
    });

    document.querySelectorAll(".personnel-date").forEach(input=>{
        const pid=input.dataset.personnelId;
        if(!pid) return;
        const booked = bookedDatesByPersonnel[pid] || [];
        input.addEventListener("input",function(){
            if(booked.includes(this.value)){
                alert("Personnel already booked on this date!");
                this.value='';
            }
        });
    });
});

updateSummary();
});
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>

