<?php
require_once __DIR__ . '/../config.php';

// Fetch order data
$order_id = $_GET['order_id'] ?? 0;
$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$orderStmt->execute([$order_id]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

// Fetch products, ducted, split, equipment, personnel
$products = $pdo->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$split_installations = $pdo->query("SELECT * FROM split_installations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipment = $pdo->query("SELECT * FROM equipment ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$personnel = $pdo->query("SELECT * FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Optional: fetch ducted installations if table exists
try {
    $ducted_installations = $pdo->query("SELECT * FROM ductedinstallation ORDER BY category ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e){
    $ducted_installations = []; // leave empty if table missing
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Order</title>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet">
<style>
thead.sticky th { top: 0; position: sticky; background: #f3f4f6; z-index:10; }
.qty-wrapper { display:flex; justify-content:center; gap:0.25rem; align-items:center; }
.qty-wrapper input { width:3rem; text-align:center; }
.qbtn { padding:0.5rem 1rem; background:#3b82f6; color:white; border-radius:0.5rem; cursor:pointer; margin-top:0.5rem; }
.summary-panel { min-width:20rem; background:white; padding:1rem; border-radius:1rem; box-shadow:0 2px 8px rgba(0,0,0,0.1);}
.row-subtotal, .pers-subtotal, .expense-subtotal { min-width:3rem; display:inline-block; text-align:right; }
</style>
</head>
<body class="bg-gray-100 p-6">

<div class="flex gap-6">

  <!-- LEFT COLUMN -->
  <div class="flex-1 space-y-6">

    <!-- CLIENT INFO -->
    <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
      <h5 class="text-lg font-medium text-gray-700 mb-4">Client Information</h5>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="relative">
          <input type="text" name="customer_name" id="customer_name" placeholder=" " value="<?= htmlspecialchars($order['customer_name'] ?? '') ?>"
                 class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition" required>
          <label for="customer_name" class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">Name</label>
        </div>
        <div class="relative">
          <input type="email" name="customer_email" id="customer_email" placeholder=" " value="<?= htmlspecialchars($order['customer_email'] ?? '') ?>"
                 class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
          <label for="customer_email" class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">Email</label>
        </div>
        <div class="relative">
          <input type="text" name="contact_number" id="contact_number" placeholder=" " value="<?= htmlspecialchars($order['contact_number'] ?? '') ?>"
                 class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
          <label for="contact_number" class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">Phone</label>
        </div>
        <div class="relative">
          <input type="text" name="job_address" id="job_address" placeholder=" " value="<?= htmlspecialchars($order['job_address'] ?? '') ?>"
                 class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
          <label for="job_address" class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">Address</label>
        </div>
        <div class="relative">
          <input type="date" name="appointment_date" id="appointment_date" value="<?= htmlspecialchars($order['appointment_date'] ?? date('Y-m-d')) ?>" 
                 class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
          <label for="appointment_date" class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">Appointment Date</label>
        </div>
      </div>
    </div>

    <!-- CARD TEMPLATE FUNCTION -->
    <?php
    function renderCard($title, $items, $nameAttr, $priceField='price', $qtyField='qty', $typeSelect=false) {
      ?>
      <div class="bg-white p-4 rounded-xl shadow border border-gray-200">
        <div class="flex items-center justify-between mb-3">
          <span class="font-medium text-gray-700"><?= $title ?></span>
          <input type="text" class="search-input" placeholder="Search...">
        </div>
        <div class="overflow-y-auto max-h-64 border rounded-lg mb-2">
          <table class="w-full border-collapse text-sm">
            <thead class="bg-gray-100 sticky top-0">
              <tr>
                <th>Name</th>
                <th class="text-center">Price</th>
                <th class="text-center">Qty</th>
                <?php if($typeSelect) echo '<th class="text-center">Type</th>'; ?>
                <th class="text-center">Subtotal</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($items as $i): $id=(int)$i['id']; ?>
              <tr>
                <td><?= htmlspecialchars($i['name']) ?></td>
                <td class="text-center">$<span class="prod-price"><?= number_format($i[$priceField],2) ?></span></td>
                <td class="text-center">
                  <div class="qty-wrapper">
                    <button type="button" class="qtbn minus">-</button>
                    <input type="number" min="0" value="0" name="<?= $nameAttr ?>[<?= $id ?>]" class="qty-input" data-price="<?= htmlspecialchars($i[$priceField]) ?>">
                    <button type="button" class="qtbn plus">+</button>
                  </div>
                </td>
                <?php if($typeSelect): ?>
                <td class="text-center">
                  <select name="<?= $nameAttr ?>[<?= $id ?>][type]">
                    <option value="indoor">Indoor</option>
                    <option value="outdoor">Outdoor</option>
                  </select>
                </td>
                <?php endif; ?>
                <td class="text-center">$<span class="row-subtotal">0.00</span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button type="button" class="qbtn add-item-btn">Add Item</button>
        <div class="added-items mt-2"></div>
      </div>
    <?php } ?>

    <!-- RENDER CARDS -->
    <?php renderCard('Products',$products,'product'); ?>
    <?php renderCard('Ducted Installations',$ducted_installations,'ducted','price','qty',true); ?>
    <?php renderCard('Split Installations',$split_installations,'split'); ?>
    <?php renderCard('Equipment',$equipment,'equipment','rate'); ?>

    <!-- PERSONNEL -->
    <div class="bg-white p-4 rounded-xl shadow border border-gray-200">
      <div class="flex items-center justify-between mb-3">
        <span class="font-medium text-gray-700">Personnel</span>
        <input type="text" class="search-input" placeholder="Search personnel...">
      </div>
      <div class="overflow-y-auto max-h-64 border rounded-lg mb-2">
        <table class="w-full text-sm border-collapse">
          <thead class="bg-gray-100 sticky top-0">
            <tr><th>Name</th><th>Rate</th><th>Date</th><th>Hours</th><th>Subtotal</th></tr>
          </thead>
          <tbody>
          <?php foreach($personnel as $p): $pid=(int)$p['id']; ?>
            <tr>
              <td><?= htmlspecialchars($p['name']) ?></td>
              <td class="text-center pers-rate"><?= number_format($p['rate'],2) ?></td>
              <td class="text-center"><input type="text" name="personnel_date[<?= $pid ?>]" class="personnel-date w-full text-center" placeholder="YYYY-MM-DD"></td>
              <td class="text-center">
                <div class="qty-wrapper">
                  <button type="button" class="qtbn hour-minus">-</button>
                  <input type="number" min="0" value="0" name="personnel_hours[<?= $pid ?>]" class="qty-input pers-hours" data-rate="<?= $p['rate'] ?>">
                  <button type="button" class="qtbn hour-plus">+</button>
                </div>
              </td>
              <td class="text-center">$<span class="pers-subtotal">0.00</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- OTHER EXPENSES -->
    <div class="bg-white p-4 rounded-xl shadow border border-gray-200">
      <span class="font-medium text-gray-700 mb-2">Other Expenses</span>
      <div id="otherExpensesContainer" class="mb-2"></div>
      <button type="button" class="qbtn" id="addExpenseBtn">Add Expense</button>
    </div>

  </div> <!-- END LEFT COLUMN -->

  <!-- RIGHT COLUMN / SUMMARY PANEL -->
  <div class="summary-panel sticky top-6 h-fit">
    <h5 class="text-lg font-medium text-gray-700 mb-4">Summary</h5>
    <div class="space-y-2">
      <div class="flex justify-between"><span>Products</span><span id="sumProducts">$0.00</span></div>
      <div class="flex justify-between"><span>Ducted</span><span id="sumDucted">$0.00</span></div>
      <div class="flex justify-between"><span>Split</span><span id="sumSplit">$0.00</span></div>
      <div class="flex justify-between"><span>Equipment</span><span id="sumEquipment">$0.00</span></div>
      <div class="flex justify-between"><span>Personnel</span><span id="sumPersonnel">$0.00</span></div>
      <div class="flex justify-between"><span>Other Expenses</span><span id="sumOther">$0.00</span></div>
      <hr>
      <div class="flex justify-between font-bold"><span>Total</span><span id="totalSum">$0.00</span></div>
    </div>
  </div>

</div> <!-- END FLEX CONTAINER -->

<script>
$(document).ready(function(){

  function updateSubtotal(input){
    var price = parseFloat($(input).data('price') || 0);
    var qty = parseFloat($(input).val() || 0);
    $(input).closest('tr').find('.row-subtotal, .pers-subtotal, .expense-subtotal').text((price*qty).toFixed(2));
    updateSummary();
  }

  function updateSummary(){
    let sumProducts=0,sumDucted=0,sumSplit=0,sumEquipment=0,sumPersonnel=0,sumOther=0;
    $('.products-table .row-subtotal').each(function(){ sumProducts+=parseFloat($(this).text()); });
    $('.bg-white:contains("Ducted Installations") .row-subtotal').each(function(){ sumDucted+=parseFloat($(this).text()); });
    $('.bg-white:contains("Split Installations") .row-subtotal').each(function(){ sumSplit+=parseFloat($(this).text()); });
    $('.bg-white:contains("Equipment") .row-subtotal').each(function(){ sumEquipment+=parseFloat($(this).text()); });
    $('.pers-subtotal').each(function(){ sumPersonnel+=parseFloat($(this).text()); });
    $('.expense-subtotal').each(function(){ sumOther+=parseFloat($(this).text()); });
    $('#sumProducts').text('$'+sumProducts.toFixed(2));
    $('#sumDucted').text('$'+sumDucted.toFixed(2));
    $('#sumSplit').text('$'+sumSplit.toFixed(2));
    $('#sumEquipment').text('$'+sumEquipment.toFixed(2));
    $('#sumPersonnel').text('$'+sumPersonnel.toFixed(2));
    $('#sumOther').text('$'+sumOther.toFixed(2));
    let total=sumProducts+sumDucted+sumSplit+sumEquipment+sumPersonnel+sumOther;
    $('#totalSum').text('$'+total.toFixed(2));
  }

  // Quantity controls
  $('.qty-input').on('input change', function(){ updateSubtotal(this); });
  $('.plus').click(function(){ var inp=$(this).siblings('input'); inp.val(parseInt(inp.val())+1).trigger('change'); });
  $('.minus').click(function(){ var inp=$(this).siblings('input'); inp.val(Math.max(0,parseInt(inp.val())-1)).trigger('change'); });
  $('.hour-plus').click(function(){ var inp=$(this).siblings('input'); inp.val(parseInt(inp.val())+1).trigger('change'); });
  $('.hour-minus').click(function(){ var inp=$(this).siblings('input'); inp.val(Math.max(0,parseInt(inp.val())-1)).trigger('change'); });

  // Add Item dynamically
  $('.add-item-btn').click(function(){
    var container=$(this).siblings('.added-items');
    var row=`<div class="flex gap-2 mt-1">
      <input type="text" class="border rounded px-2 py-1 w-1/2" placeholder="Item Name">
      <input type="number" class="border rounded px-2 py-1 w-1/4" placeholder="Price">
      <input type="number" class="border rounded px-2 py-1 w-1/4" placeholder="Qty">
      </div>`;
    container.append(row);
  });

  // Add Expense dynamically
  $('#addExpenseBtn').click(function(){
    var container=$('#otherExpensesContainer');
    var row=`<div class="flex gap-2 mt-1">
      <input type="text" class="border rounded px-2 py-1 w-1/2" placeholder="Expense Name">
      <input type="number" class="border rounded px-2 py-1 w-1/4 expense-price" placeholder="Price">
      <input type="number" class="border rounded px-2 py-1 w-1/4 expense-qty" placeholder="Qty">
      <span class="expense-subtotal">$0.00</span>
    </div>`;
    container.append(row);
  });

  // Update expense subtotal on input
  $(document).on('input','.expense-price, .expense-qty', function(){
    var row=$(this).parent();
    var price=parseFloat(row.find('.expense-price').val()||0);
    var qty=parseFloat(row.find('.expense-qty').val()||0);
    row.find('.expense-subtotal').text('$'+(price*qty).toFixed(2));
    updateSummary();
  });

});
</script>

</body>
</html>
