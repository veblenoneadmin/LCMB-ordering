<?php
require_once __DIR__.'/config.php';

// Fetch existing data
$products = $pdo->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);
$ducted_installations = $pdo->query("SELECT * FROM ducted_installations")->fetchAll(PDO::FETCH_ASSOC);
$split_installations = $pdo->query("SELECT * FROM split_installations")->fetchAll(PDO::FETCH_ASSOC);
$equipment = $pdo->query("SELECT * FROM equipment")->fetchAll(PDO::FETCH_ASSOC);
$personnel = $pdo->query("SELECT * FROM personnel")->fetchAll(PDO::FETCH_ASSOC);

?>

<form method="POST" action="save_order.php" id="editOrderForm">

<!-- CLIENT INFO -->
<div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200 mb-6">
  <h5 class="text-lg font-medium text-gray-700 mb-4">Client Information</h5>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <?php
    $fields = ['customer_name'=>'Name','customer_email'=>'Email','contact_number'=>'Phone','job_address'=>'Address','appointment_date'=>'Appointment Date'];
    foreach($fields as $name=>$label):
      $type = $name==='customer_email'?'email':($name==='appointment_date'?'date':'text');
      $value = $name==='appointment_date'?date('Y-m-d'):'';
    ?>
    <div class="relative">
      <input type="<?= $type ?>" name="<?= $name ?>" id="<?= $name ?>" placeholder=" " value="<?= $value ?>" 
             class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition" <?= $name==='customer_name'?'required':'' ?>>
      <label for="<?= $name ?>" 
             class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm"><?= $label ?></label>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- FUNCTION TO GENERATE CARD -->
<?php
function renderCard($title,$tableId,$columns,$rows,$readonlyPrice=false,$allowAdd=false){
?>
<div class="bg-white p-4 rounded-xl shadow border border-gray-200 mb-6 <?= $tableId ?>-card">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700"><?= $title ?></span>
    <input type="text" class="search-input" placeholder="Search <?= strtolower($title) ?>...">
  </div>
  <div class="overflow-y-auto max-h-64 border rounded-lg">
    <table class="w-full text-sm border-collapse <?= $tableId ?>-table">
      <thead class="bg-gray-100 sticky top-0">
        <tr>
          <?php foreach($columns as $col) echo "<th class='p-2 text-center'>{$col}</th>"; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr data-id="<?= $r['id'] ?>">
          <?php foreach($columns as $col):
            $key = strtolower(str_replace(' ','_',$col));
            if($key==='price' || $key==='rate' || $key==='unit_price') {
              $val = number_format($r['price'] ?? $r['rate'] ?? 0,2);
              echo "<td class='p-2 text-center'><input type='text' value='$val' class='price-input text-center' ".($readonlyPrice?'readonly':'')."></td>";
            } elseif($key==='qty' || $key==='hours') {
              $dataAttr = $r['price']??$r['rate']??0;
              echo "<td class='p-2 text-center'><input type='number' min='0' value='0' class='qty-input w-16 text-center' data-price='$dataAttr'></td>";
            } elseif($key==='subtotal') {
              echo "<td class='p-2 text-center subtotal'>$0.00</td>";
            } else {
              echo "<td class='p-2 text-left'>".htmlspecialchars($r[$key]??'')."</td>";
            }
          endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  
  <!-- Added items section -->
  <div class="added-items mt-3"></div>
  
  <?php if($allowAdd): ?>
    <button type="button" class="qbtn mt-2 add-item-btn">Add Item</button>
  <?php endif; ?>
</div>
<?php } 
?>

<?php
// PRODUCTS
renderCard('Products','products',['Name','Price','Qty','Subtotal'],$products,true,true);

// DUCTED INSTALLATIONS
renderCard('Ducted Installations','ducted',['Name','Price','Qty','Type','Subtotal'],$ducted_installations,true,true);

// SPLIT INSTALLATIONS
renderCard('Split Installations','split',['Name','Unit Price','Qty','Subtotal'],$split_installations,true,true);

// EQUIPMENT
renderCard('Equipment','equipment',['Item','Rate','Qty','Subtotal'],$equipment,true,true);

// PERSONNEL (no add button, price editable only via rate)
renderCard('Personnel','personnel',['Name','Rate','Date','Hours','Subtotal'],$personnel,true,false);

// OTHER EXPENSES (price editable, allow add)
renderCard('Other Expenses','other',['Item','Price','Qty','Subtotal'],[],false,true);
?>

<!-- SUMMARY PANEL -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200 summary-panel mb-6">
  <h5 class="text-lg font-medium text-gray-700 mb-3">Summary</h5>
  <div class="flex justify-between mb-1"><span>Products Total:</span><span id="productsTotal">$0.00</span></div>
  <div class="flex justify-between mb-1"><span>Ducted Total:</span><span id="ductedTotal">$0.00</span></div>
  <div class="flex justify-between mb-1"><span>Split Total:</span><span id="splitTotal">$0.00</span></div>
  <div class="flex justify-between mb-1"><span>Equipment Total:</span><span id="equipmentTotal">$0.00</span></div>
  <div class="flex justify-between mb-1"><span>Other Expenses:</span><span id="otherTotal">$0.00</span></div>
  <hr class="my-2">
  <div class="flex justify-between font-bold"><span>Grand Total:</span><span id="grandTotal">$0.00</span></div>
</div>

<button type="submit" class="qbtn w-full mb-6">Save Order</button>
</form>

<script>
// Function to add new item row
document.querySelectorAll('.add-item-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const card = btn.closest('.bg-white');
    const container = card.querySelector('.added-items');
    const isOther = card.querySelector('.added-items')?.closest('.other-card');
    container.insertAdjacentHTML('beforeend', `
      <div class="flex gap-2 mt-2 items-center">
        <input type="text" name="added_item_name[]" placeholder="Item Name" class="border p-2 rounded flex-1">
        <input type="number" name="added_item_price[]" placeholder="Price" class="border p-2 rounded w-24" ${isOther?'':'readonly'}>
        <input type="number" name="added_item_qty[]" placeholder="Qty" class="border p-2 rounded w-16">
        <span class="subtotal">$0.00</span>
      </div>
    `);
    updateTotals();
  });
});

// Update subtotal for all cards
function updateTotals(){
  let totals = {products:0,ducted:0,split:0,equipment:0,other:0};
  
  // Existing table rows
  document.querySelectorAll('.products-card, .ducted-card, .split-card, .equipment-card, .other-card').forEach(card=>{
    const cardName = card.className.split(' ')[5].split('-')[0]; // products, ducted, etc
    let cardTotal = 0;
    
    // Table rows
    card.querySelectorAll('table tbody tr').forEach(row=>{
      const price = parseFloat(row.querySelector('.price-input')?.value||0);
      const qty = parseFloat(row.querySelector('.qty-input')?.value||0);
      const subtotal = price*qty;
      row.querySelector('.subtotal').textContent = '$'+subtotal.toFixed(2);
      cardTotal += subtotal;
    });
    
    // Added items
    card.querySelectorAll('.added-items > div').forEach(row=>{
      const price = parseFloat(row.querySelector('[name^="added_item_price"]').value||0);
      const qty = parseFloat(row.querySelector('[name^="added_item_qty"]').value||0);
      const subtotal = price*qty;
      row.querySelector('.subtotal').textContent = '$'+subtotal.toFixed(2);
      cardTotal += subtotal;
    });
    
    totals[cardName] = cardTotal;
  });
  
  // Update summary
  document.getElementById('productsTotal').textContent = '$'+totals.products.toFixed(2);
  document.getElementById('ductedTotal').textContent = '$'+totals.ducted.toFixed(2);
  document.getElementById('splitTotal').textContent = '$'+totals.split.toFixed(2);
  document.getElementById('equipmentTotal').textContent = '$'+totals.equipment.toFixed(2);
  document.getElementById('otherTotal').textContent = '$'+totals.other.toFixed(2);
  
  const grand = Object.values(totals).reduce((a,b)=>a+b,0);
  document.getElementById('grandTotal').textContent = '$'+grand.toFixed(2);
}

// Trigger update on input change
document.querySelectorAll('.qty-input, .price-input').forEach(input=>{
  input.addEventListener('input', updateTotals);
});

</script>
