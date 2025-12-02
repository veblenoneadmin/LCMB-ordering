<?php
// create_order.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

// Fetch all data safely
try { $products = $pdo->query("SELECT id, name, price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $products=[]; }
try { $split_installations = $pdo->query("SELECT id, item_name AS name, unit_price AS price FROM split_installation ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $split_installations=[]; }
try { $ducted_installations = $pdo->query("SELECT id, equipment_name AS name, model_name_indoor, model_name_outdoor, total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $ducted_installations=[]; }
try { $personnel = $pdo->query("SELECT id, name, rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $personnel=[]; }
try { $equipment = $pdo->query("SELECT id, item AS name, rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){ $equipment=[]; }

$message = '';

function f2($v){ return number_format((float)$v,2,'.',''); }

if($_SERVER['REQUEST_METHOD']==='POST'){
    // Collect customer data
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? null);
    $contact_number = trim($_POST['contact_number'] ?? null);
    $job_address = trim($_POST['job_address'] ?? null);
    $appointment_date = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;

    $items = [];

    // PRODUCTS
    foreach($_POST['product'] ?? [] as $pid => $qty){
        $qty=intval($qty);
        if($qty>0){
            $stmt=$pdo->prepare("SELECT price FROM products WHERE id=? LIMIT 1");
            $stmt->execute([$pid]);
            $price = (float)$stmt->fetchColumn();
            $items[]=['item_type'=>'product','item_id'=>$pid,'installation_type'=>null,'qty'=>$qty,'price'=>f2($price)];
        }
    }

    // SPLIT INSTALLATIONS
    foreach($_POST['split'] ?? [] as $sid => $qty){
        $qty=intval($qty);
        if($qty>0){
            $stmt=$pdo->prepare("SELECT unit_price FROM split_installation WHERE id=? LIMIT 1");
            $stmt->execute([$sid]);
            $price=(float)$stmt->fetchColumn();
            $items[]=['item_type'=>'installation','item_id'=>$sid,'installation_type'=>null,'qty'=>$qty,'price'=>f2($price)];
        }
    }

    // DUCTED INSTALLATIONS
    foreach($_POST['ducted'] ?? [] as $did=>$data){
        $qty=intval($data['qty'] ?? 0);
        $type=$data['type'] ?? 'indoor';
        if($qty>0){
            $stmt=$pdo->prepare("SELECT total_cost FROM ductedinstallations WHERE id=? LIMIT 1");
            $stmt->execute([$did]);
            $price=(float)$stmt->fetchColumn();
            $items[]=['item_type'=>'installation','item_id'=>$did,'installation_type'=>in_array($type,['indoor','outdoor'])?$type:'indoor','qty'=>$qty,'price'=>f2($price)];
        }
    }

    // EQUIPMENT
    foreach($_POST['equipment'] ?? [] as $eid => $qty){
        $qty=intval($qty);
        if($qty>0){
            $stmt=$pdo->prepare("SELECT rate FROM equipment WHERE id=? LIMIT 1");
            $stmt->execute([$eid]);
            $rate=(float)$stmt->fetchColumn();
            $items[]=['item_type'=>'product','item_id'=>$eid,'installation_type'=>null,'qty'=>$qty,'price'=>f2($rate)];
        }
    }

    // OTHER EXPENSES
    $other_names=$_POST['other_expense_name'] ?? [];
    $other_amounts=$_POST['other_expense_amount'] ?? [];
    foreach($other_amounts as $i=>$amt){
        $amt=floatval($amt);
        $name=trim($other_names[$i] ?? '');
        if($amt>0){
            $items[]=['item_type'=>'product','item_id'=>0,'installation_type'=>$name ?: 'Other expense','qty'=>1,'price'=>f2($amt)];
        }
    }

    $subtotal=0.0;
    foreach($items as $it) $subtotal+=((float)$it['qty'])*((float)$it['price']);
    $tax=round($subtotal*0.10,2);
    $grand_total=round($subtotal+$tax,2);
    $discount=0.00;
    $order_number='ORD'.time().rand(10,99);

    try{
        $pdo->beginTransaction();

        // Insert order
        $stmt=$pdo->prepare("INSERT INTO orders (customer_name, customer_email, contact_number, job_address, appointment_date, total_amount, order_number, status, total, tax, discount, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$customer_name,$customer_email,$contact_number,$job_address,$appointment_date,f2($subtotal),$order_number,'pending',f2($grand_total),f2($tax),f2($discount)]);
        $order_id=$pdo->lastInsertId();

        // Insert order items
        $stmt_item=$pdo->prepare("INSERT INTO order_items (order_id, item_type, item_id, installation_type, qty, price, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        foreach($items as $it){
            $stmt_item->execute([$order_id,$it['item_type'],isset($it['item_id'])?(int)$it['item_id']:null,$it['installation_type'] ?? null,(int)$it['qty'],f2($it['price'])]);
        }

        // Insert dispatch table (personnel)
        $stmt_dispatch=$pdo->prepare("INSERT INTO dispatch (order_id, personnel_id, date, time_start, time_end, hours, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        foreach($_POST['personnel'] ?? [] as $pid => $_){
            $date = $_POST['personnel_date'][$pid] ?? null;
            $start = $_POST['personnel_start'][$pid] ?? null;
            $end = $_POST['personnel_end'][$pid] ?? null;
            if($date && $start && $end){
                $tstart=strtotime($start);
                $tend=strtotime($end);
                $hours=round(($tend-$tstart)/3600,2);
                if($hours>0){
                    $stmt_dispatch->execute([$order_id,$pid,$date,$start,$end,$hours]);
                    // Also add personnel as an order item
                    $stmt_personnel_rate=$pdo->prepare("SELECT rate FROM personnel WHERE id=? LIMIT 1");
                    $stmt_personnel_rate->execute([$pid]);
                    $rate=(float)$stmt_personnel_rate->fetchColumn();
                    $line_price=$rate*$hours;
                    $stmt_item->execute([$order_id,'personnel',$pid,null,1,f2($line_price)]);
                }
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

<!-- FORM HTML START -->
<?php if($message): ?>
<div class="alert" style="padding:10px;background:#fee;border:1px solid #fbb;margin-bottom:12px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post" class="create-order-grid" id="orderForm" novalidate>
    <div class="flex-1 flex flex-col gap-6">
        <!-- Client Info -->
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
        <div class="bg-white p-4 rounded-xl shadow border border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <span class="font-medium text-gray-700">Material</span>
                <input id="productSearch" class="search-input" placeholder="Search products..." >
            </div>
            <div class="overflow-y-auto max-h-64 border rounded-lg">
                <table class="products-table w-full border-collapse text-sm">
                    <thead class="bg-gray-100 sticky top-0"><tr><th>Name</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        <?php foreach($products as $p): $pid=(int)$p['id']; ?>
                        <tr>
                            <td class="product-name p-2"><?= htmlspecialchars($p['name']) ?></td>
                            <td class="p-2 text-center">$<span class="prod-price"><?= number_format($p['price'],2) ?></span></td>
                            <td class="p-2 text-center">
                                <div class="qty-box">
                                    <button type="button" class="qtbn minus">-</button>
                                    <input type="number" min="0" value="0" name="product[<?= $pid ?>]" class="qty-input" data-price="<?= htmlspecialchars($p['price']) ?>">
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

        <!-- SPLIT INSTALLATION -->
        <div class="bg-white p-4 rounded-xl shadow border border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <span class="font-medium text-gray-700">Split System Installation</span>
                <input id="splitSearch" class="search-input" placeholder="Search split systems..." >
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
        <div class="bg-white p-4 rounded-xl shadow border border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <span class="font-medium text-gray-700">Ducted Installation</span>
            </div>
            <div class="overflow-y-auto max-h-64 border rounded-lg">
                <table class="products-table w-full border-collapse text-sm">
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
        <div class="bg-white p-4 rounded-xl shadow border border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <span class="font-medium text-gray-700">Personnel</span>
                <input id="personnelSearch" class="search-input" placeholder="Search personnel..." >
            </div>
            <div class="overflow-y-auto max-h-64 border rounded-lg">
                <table class="products-table w-full border-collapse text-sm">
                    <thead class="bg-gray-100 sticky top-0"><tr><th>Name</th><th>Rate</th><th>Date</th><th>Time Start</th><th>Time End</th><th>Hours</th></tr></thead>
                    <tbody>
                        <?php foreach($personnel as $p): $pid=(int)$p['id']; ?>
                        <tr class="personnel-row" data-id="<?= $pid ?>">
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td class="text-center"><?= number_format($p['rate'],2) ?></td>
                            <td><input type="date" name="personnel_date[<?= $pid ?>]" class="input personnel-date"></td>
                            <td><input type="time" name="personnel_start[<?= $pid ?>]" class="input personnel-start"></td>
                            <td><input type="time" name="personnel_end[<?= $pid ?>]" class="input personnel-end"></td>
                            <td><input type="text" readonly name="personnel_hours[<?= $pid ?>]" class="input personnel-hours" value="0"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- EQUIPMENT -->
        <div class="bg-white p-4 rounded-xl shadow border border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <span class="font-medium text-gray-700">Equipment</span>
                <input id="equipmentSearch" class="search-input" placeholder="Search equipment...">
            </div>
            <div class="overflow-y-auto max-h-64 border rounded-lg">
                <table class="products-table w-full border-collapse text-sm">
                    <thead><tr><th>Item</th><th>Rate</th><th>Qty</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        <?php foreach($equipment as $e): $eid=(int)$e['id']; ?>
                        <tr>
                            <td><?= htmlspecialchars($e['name']) ?></td>
                            <td>$<span class="equip-rate"><?= number_format($e['rate'],2) ?></span></td>
                            <td>
                                <div class="qty-box">
                                    <button type="button" class="qbtn equip-minus">-</button>
                                    <input type="number" min="0" value="0" name="equipment[<?= $eid ?>]" class="qty-input equip-input" data-price="<?= htmlspecialchars($e['rate']) ?>">
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
        <div class="bg-white p-4 rounded-xl shadow mb-4">
            <span class="font-medium text-gray-700 mb-2">Other Expenses</span>
            <div id="otherExpensesContainer"></div>
            <button type="button" class="qbtn" id="addExpenseBtn">Add</button>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="create-order-right" style="width:360px;">
        <div id="rightPanel" class="bg-white p-6 rounded-2xl shadow border border-gray-200 h-auto max-h-[80vh] flex flex-col">
            <div id="orderSummary" class="flex-1 overflow-y-auto">
                <div class="mb-2"><strong>Subtotal:</strong> $<span id="subtotalDisplay">0.00</span></div>
                <div class="mb-2"><strong>Tax (10%):</strong> $<span id="taxDisplay">0.00</span></div>
                <div class="mb-2"><strong>Grand Total:</strong> $<span id="grandTotalDisplay">0.00</span></div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Save Order</button>
        </div>
    </div>
</form>

<script>
// ------------------------- QUANTITY BUTTONS -------------------------
function updateRowSubtotal(row){
    let qty=parseFloat(row.querySelector('input.qty-input')?.value)||0;
    let price=parseFloat(row.querySelector('input.qty-input')?.dataset.price)||0;
    let subtotal=qty*price;
    row.querySelector('.row-subtotal')?.textContent=subtotal.toFixed(2);
    updateSummary();
}

function updateSummary(){
    let subtotal=0;
    document.querySelectorAll('.row-subtotal').forEach(el=>{subtotal+=parseFloat(el.textContent)||0;});
    document.getElementById('subtotalDisplay').textContent=subtotal.toFixed(2);
    let tax=subtotal*0.10;
    document.getElementById('taxDisplay').textContent=tax.toFixed(2);
    document.getElementById('grandTotalDisplay').textContent=(subtotal+tax).toFixed(2);
}

// QUANTITY BUTTON EVENTS
document.querySelectorAll('.qtbn,.qbtn').forEach(btn=>{
    btn.addEventListener('click', function(){
        const input = btn.closest('td').querySelector('input.qty-input');
        let val=parseInt(input.value)||0;
        if(btn.classList.contains('plus')||btn.classList.contains('split-plus')||btn.classList.contains('ducted-plus')||btn.classList.contains('equip-plus')) val++;
        else if(btn.classList.contains('minus')||btn.classList.contains('split-minus')||btn.classList.contains('ducted-minus')||btn.classList.contains('equip-minus')) val=Math.max(0,val-1);
        input.value=val;
        updateRowSubtotal(btn.closest('tr'));
    });
});

// ------------------------- PERSONNEL HOURS CALC -------------------------
function updatePersonnelHours(row){
    const start=row.querySelector('.personnel-start').value;
    const end=row.querySelector('.personnel-end').value;
    const hoursInput=row.querySelector('.personnel-hours');
    if(start && end){
        const diff=(new Date('1970-01-01T'+end)-new Date('1970-01-01T'+start))/3600000;
        hoursInput.value=(diff>0?diff:0).toFixed(2);
    }else{
        hoursInput.value='0';
    }
}
document.querySelectorAll('.personnel-row').forEach(row=>{
    row.querySelectorAll('.personnel-start,.personnel-end').forEach(input=>{
        input.addEventListener('change',()=>updatePersonnelHours(row));
    });
});

// ------------------------- OTHER EXPENSES -------------------------
let otherCount=0;
document.getElementById('addExpenseBtn').addEventListener('click',()=>{
    const container=document.getElementById('otherExpensesContainer');
    const div=document.createElement('div');
    div.className='flex gap-2 mb-1';
    div.innerHTML=`<input type="text" name="other_expense_name[]" placeholder="Name" class="input">
                     <input type="number" step="0.01" min="0" name="other_expense_amount[]" placeholder="Amount" class="input w-24"> 
                     <button type="button" class="qbtn removeExpense">-</button>`;
    container.appendChild(div);
    div.querySelector('.removeExpense').addEventListener('click',()=>div.remove());
});
</script>

<style>
.qty-box{display:flex;align-items:center;justify-content:center;}
.qty-box button{padding:0 6px;cursor:pointer;}
.qty-box input{width:50px;text-align:center;}
.input{padding:4px 6px;border:1px solid #ccc;border-radius:4px;width:100%;}
.create-order-grid{display:flex;gap:12px;}
.create-order-right{position:sticky;top:10px;}
</style>

<?php
$content = ob_get_clean();
renderLayout($content, 'Create Order');
?>
