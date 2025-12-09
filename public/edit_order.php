<?php
require_once __DIR__ . '/../config.php'; // Make sure this path exists

$order_id = $_GET['order_id'] ?? 0;

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order) {
    die("❌ Order not found.");
}

// Fetch data from tables
$products = $pdo->query("SELECT * FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$ducted_installations = $pdo->query("
    SELECT id, equipment_name, total_cost, category
    FROM ductedinstallations
    ORDER BY equipment_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$split_installations = $pdo->query("SELECT * FROM split_installations ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipment = $pdo->query("SELECT * FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC);
$personnel = $pdo->query("SELECT * FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Order #<?= htmlspecialchars($order_id) ?></title>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<style>
/* Minimal styling */
.bg-white { background-color: white; }
.rounded-xl { border-radius: 1rem; }
.shadow { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.border { border: 1px solid #e2e8f0; }
.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.p-4 { padding: 1rem; }
.mb-3 { margin-bottom: 0.75rem; }
.font-medium { font-weight: 500; }
.text-gray-700 { color: #4a5568; }
.text-center { text-align: center; }
.text-left { text-align: left; }
.max-h-64 { max-height: 16rem; overflow-y: auto; }
.products-table input { width: 3rem; text-align: center; }
.summary-panel { width: 20rem; }
.grid-cols-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid { display: grid; }
.gap-6 { gap: 1.5rem; }
.qbtn { padding: 0.5rem 1rem; background-color:#3b82f6; color:white; border:none; border-radius:0.5rem; cursor:pointer;}
.qbtn:hover { background-color:#2563eb;}
</style>
</head>
<body>
<div class="flex gap-6">

<!-- LEFT SIDE: FORM -->
<div class="flex-1 space-y-6">

<!-- CLIENT INFO -->
<div class="bg-white p-6 rounded-xl shadow border border-gray-200">
  <h5 class="text-lg font-medium text-gray-700 mb-4">Client Information</h5>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="relative">
      <input type="text" name="customer_name" id="customer_name" placeholder=" " 
             value="<?= htmlspecialchars($order['customer_name']) ?>"
             class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition" required>
      <label for="customer_name" 
             class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">
        Name
      </label>
    </div>
    <div class="relative">
      <input type="email" name="customer_email" id="customer_email" placeholder=" " 
             value="<?= htmlspecialchars($order['customer_email']) ?>"
             class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
      <label for="customer_email" 
             class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">
        Email
      </label>
    </div>
    <div class="relative">
      <input type="text" name="contact_number" id="contact_number" placeholder=" " 
             value="<?= htmlspecialchars($order['contact_number']) ?>"
             class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
      <label for="contact_number" 
             class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">
        Phone
      </label>
    </div>
    <div class="relative">
      <input type="text" name="job_address" id="job_address" placeholder=" " 
             value="<?= htmlspecialchars($order['job_address']) ?>"
             class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
      <label for="job_address" 
             class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">
        Address
      </label>
    </div>
    <div class="relative">
      <input type="date" name="appointment_date" id="appointment_date" 
             value="<?= htmlspecialchars($order['appointment_date'] ?? date('Y-m-d')) ?>"
             class="peer h-12 w-full border border-gray-300 rounded-xl bg-gray-50 px-4 pt-4 pb-1 text-gray-900 placeholder-transparent focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition">
      <label for="appointment_date" 
             class="absolute left-4 top-1 text-gray-500 text-sm transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:text-base peer-focus:top-1 peer-focus:text-gray-700 peer-focus:text-sm">
        Appointment Date
      </label>
    </div>
  </div>
</div>

<!-- DYNAMIC TABLE FUNCTIONALITY -->
<div x-data="orderData()">

<!-- PRODUCTS -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Products</span>
    <input id="productSearch" placeholder="Search products..." class="border px-2 py-1 rounded">
  </div>
  <div class="overflow-y-auto max-h-64 border rounded-lg mb-3">
    <table class="products-table w-full text-sm border-collapse">
      <thead class="bg-gray-100 sticky top-0">
        <tr><th>Name</th><th class="text-center">Price</th><th class="text-center">Qty</th><th class="text-center">Subtotal</th></tr>
      </thead>
      <tbody>
        <?php foreach($products as $p): $pid=(int)$p['id']; ?>
        <tr>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td class="text-center">$<span><?= number_format($p['price'],2) ?></span></td>
          <td class="text-center">
            <div class="flex items-center justify-center gap-1">
              <button type="button" @click="changeQty($event,-1)">-</button>
              <input type="number" min="0" value="0" class="qty-input" data-price="<?= $p['price'] ?>" @input="updateSubtotal($event)">
              <button type="button" @click="changeQty($event,1)">+</button>
            </div>
          </td>
          <td class="text-center">$<span class="row-subtotal">0.00</span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button type="button" class="qbtn mb-2" @click="addItem('Products')">Add Item</button>
  <div id="addedProducts"></div>
</div>

<!-- Ducted Installations -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200 mt-4">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Ducted Installations</span>
  </div>
  <div class="overflow-y-auto max-h-64 border rounded-lg mb-3">
    <table class="w-full text-sm border-collapse">
      <thead class="bg-gray-100 sticky top-0">
        <tr><th>Name</th><th class="text-center">Price</th><th class="text-center">Qty</th><th class="text-center">Type</th><th class="text-center">Subtotal</th></tr>
      </thead>
      <tbody>
        <?php foreach($ducted_installations as $d): $did=(int)$d['id']; ?>
       <tr>
    <td><?= htmlspecialchars($d['equipment_name']) ?></td>

    <td class="text-center">
        <select name="ducted[<?= $did ?>][type]" class="installation-type">
            <option value="indoor">Indoor</option>
            <option value="outdoor">Outdoor</option>
        </select>
    </td>

    <td class="text-center">
        <div class="qty-wrapper">
            <button type="button" class="qtbn ducted-minus">-</button>
            <input type="number" min="0" value="0" 
                   name="ducted[<?= $did ?>][qty]" 
                   class="qty-input ducted-qty"
                   data-price="<?= htmlspecialchars($d['total_cost']) ?>">
            <button type="button" class="qtbn ducted-plus">+</button>
        </div>
    </td>

    <td class="text-center">$<?= number_format($d['total_cost'],2) ?></td>

    <td class="text-center">$<span class="row-subtotal">0.00</span></td>
</tr>

        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button type="button" class="qbtn mb-2" @click="addItem('Ducted')">Add Item</button>
  <div id="addedDucted"></div>
</div>

<!-- Split Installations -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200 mt-4">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Split Installations</span>
  </div>
  <div class="overflow-y-auto max-h-64 border rounded-lg mb-3">
    <table class="w-full text-sm border-collapse">
      <thead><tr><th>Name</th><th class="text-center">Price</th><th class="text-center">Qty</th><th class="text-center">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach($split_installations as $s): $sid=(int)$s['id']; ?>
        <tr>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td class="text-center">$<?= number_format($s['price'],2) ?></td>
          <td class="text-center">
            <div class="flex justify-center gap-1">
              <button type="button" @click="changeQty($event,-1)">-</button>
              <input type="number" min="0" value="0" class="qty-input" data-price="<?= $s['price'] ?>" @input="updateSubtotal($event)">
              <button type="button" @click="changeQty($event,1)">+</button>
            </div>
          </td>
          <td class="text-center">$<span class="row-subtotal">0.00</span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button type="button" class="qbtn mb-2" @click="addItem('Split')">Add Item</button>
  <div id="addedSplit"></div>
</div>

<!-- Equipment -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200 mt-4">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Equipment</span>
  </div>
  <div class="overflow-y-auto max-h-64 border rounded-lg mb-3">
    <table class="w-full text-sm border-collapse">
      <thead><tr><th>Item</th><th class="text-center">Price</th><th class="text-center">Qty</th><th class="text-center">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach($equipment as $e): $eid=(int)$e['id']; ?>
        <tr>
          <td><?= htmlspecialchars($e['name']) ?></td>
          <td class="text-center">$<?= number_format($e['rate'],2) ?></td>
          <td class="text-center">
            <div class="flex justify-center gap-1">
              <button type="button" @click="changeQty($event,-1)">-</button>
              <input type="number" min="0" value="0" class="qty-input" data-price="<?= $e['rate'] ?>" @input="updateSubtotal($event)">
              <button type="button" @click="changeQty($event,1)">+</button>
            </div>
          </td>
          <td class="text-center">$<span class="row-subtotal">0.00</span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button type="button" class="qbtn mb-2" @click="addItem('Equipment')">Add Item</button>
  <div id="addedEquipment"></div>
</div>

</div> <!-- End x-data -->

<!-- RIGHT SIDE: SUMMARY -->
<div class="summary-panel bg-white p-4 rounded-xl shadow border border-gray-200">
  <h5 class="text-lg font-medium text-gray-700 mb-4">Summary</h5>
  <div>
    <p>Total Products: $<span id="totalProducts">0.00</span></p>
    <p>Total Ducted: $<span id="totalDucted">0.00</span></p>
    <p>Total Split: $<span id="totalSplit">0.00</span></p>
    <p>Total Equipment: $<span id="totalEquipment">0.00</span></p>
    <hr>
    <p><strong>Grand Total: $<span id="grandTotal">0.00</span></strong></p>
  </div>
</div>

<script>
function orderData() {
    return {
        addItem(category) {
            const container = document.getElementById(`added${category}`);
            const div = document.createElement('div');
            div.classList.add('mb-2','flex','gap-2');
            div.innerHTML = `<input type="text" placeholder="Item Name" class="border p-1">
                             <input type="number" placeholder="Price" class="border p-1">`;
            container.appendChild(div);
        },
        changeQty(event, delta) {
            const input = event.target.closest('div').querySelector('input');
            let val = parseInt(input.value) + delta;
            if(val < 0) val = 0;
            input.value = val;
            this.updateSubtotal(input);
        },
        updateSubtotal(input) {
            const row = input.closest('tr');
            const price = parseFloat(input.dataset.price) || 0;
            const qty = parseFloat(input.value) || 0;
            row.querySelector('.row-subtotal span').textContent = (price*qty).toFixed(2);
            this.updateSummary();
        },
        updateSummary() {
            // Simple sum of all .row-subtotal
            const totals = {Products:0,Ducted:0,Split:0,Equipment:0};
            ['Products','Ducted','Split','Equipment'].forEach(cat=>{
                document.querySelectorAll(`#added${cat} input[type=number]`).forEach(i=>{
                    totals[cat]+=parseFloat(i.value)||0;
                });
                document.querySelectorAll(`#added${cat}`).forEach(e=>{
                    e.querySelectorAll('.row-subtotal span').forEach(s=>{
                        totals[cat]+=parseFloat(s.textContent)||0;
                    });
                });
            });
            document.getElementById('totalProducts').textContent = totals.Products.toFixed(2);
            document.getElementById('totalDucted').textContent = totals.Ducted.toFixed(2);
            document.getElementById('totalSplit').textContent = totals.Split.toFixed(2);
            document.getElementById('totalEquipment').textContent = totals.Equipment.toFixed(2);
            const grand = totals.Products+totals.Ducted+totals.Split+totals.Equipment;
            document.getElementById('grandTotal').textContent = grand.toFixed(2);
        }
    }
}
</script>
</body>
</html>
