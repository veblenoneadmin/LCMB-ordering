<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

$order_id = intval($_GET['order_id'] ?? 0);
if ($order_id <= 0) die("<h2 style='color:red;padding:20px;'>Invalid order id</h2>");

// Fetch order
$order = $pdo->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
$order->execute([$order_id]);
$order = $order->fetch(PDO::FETCH_ASSOC);
if (!$order) die("<h2 style='color:red;padding:20px;'>❌ Order not found</h2>");

// Fetch products, split, ducted, personnel, equipment
$products = $pdo->query("SELECT id,name,price FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$split_installations = $pdo->query("SELECT id,item_name AS name,unit_price AS price FROM split_installation ORDER BY item_name")->fetchAll(PDO::FETCH_ASSOC);
$ducted_installations = $pdo->query("SELECT id,equipment_name AS name,total_cost AS price FROM ductedinstallations ORDER BY equipment_name")->fetchAll(PDO::FETCH_ASSOC);
$equipment = $pdo->query("SELECT id,item AS name,rate AS price FROM equipment ORDER BY item")->fetchAll(PDO::FETCH_ASSOC);
$personnel = $pdo->query("SELECT id,name,rate FROM personnel ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<!-- PRODUCTS TABLE -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200 mb-6">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Products</span>
    <input class="search-input" placeholder="Search products..." >
  </div>
  <div class="overflow-y-auto max-h-64 border rounded-lg">
    <table class="w-full text-sm border-collapse products-table">
      <thead class="bg-gray-100 sticky top-0"><tr>
        <th class="p-2 text-left">Name</th>
        <th class="p-2 text-center">Price</th>
        <th class="p-2 text-center">Qty</th>
        <th class="p-2 text-center">Subtotal</th>
      </tr></thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <tr data-id="<?= $p['id'] ?>">
          <td class="p-2"><?= htmlspecialchars($p['name']) ?></td>
          <td class="p-2 text-center"><input type="text" value="<?= number_format($p['price'],2) ?>" class="price-input text-center" readonly></td>
          <td class="p-2 text-center"><input type="number" min="0" value="0" class="qty-input w-16 text-center" data-price="<?= $p['price'] ?>"></td>
          <td class="p-2 text-center subtotal">$0.00</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="4" class="p-2 text-right"><button type="button" class="qbtn add-product-btn mt-2">Add Selected Products</button></td></tr>
      </tfoot>
    </table>
  </div>
  <div class="added-products mt-2"></div>
</div>

<!-- SPLIT INSTALLATIONS -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200 mb-6">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Split Installations</span>
    <input class="search-input" placeholder="Search split systems..." >
  </div>
  <div class="overflow-y-auto max-h-64 border rounded-lg">
    <table class="w-full text-sm border-collapse split-table">
      <thead class="bg-gray-100 sticky top-0"><tr>
        <th class="p-2 text-left">Name</th>
        <th class="p-2 text-center">Price</th>
        <th class="p-2 text-center">Qty</th>
        <th class="p-2 text-center">Subtotal</th>
      </tr></thead>
      <tbody>
      <?php foreach ($split_installations as $s): ?>
        <tr data-id="<?= $s['id'] ?>">
          <td class="p-2"><?= htmlspecialchars($s['name']) ?></td>
          <td class="p-2 text-center"><input type="text" value="<?= number_format($s['price'],2) ?>" readonly class="price-input text-center"></td>
          <td class="p-2 text-center"><input type="number" min="0" value="0" class="qty-input w-16 text-center" data-price="<?= $s['price'] ?>"></td>
          <td class="p-2 text-center subtotal">$0.00</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="4" class="p-2 text-right"><button type="button" class="qbtn add-split-btn mt-2">Add Selected Split</button></td></tr></tfoot>
    </table>
  </div>
  <div class="added-split mt-2"></div>
</div>

<!-- DUCTED INSTALLATIONS -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200 mb-6">
  <div class="flex items-center justify-between mb-3">
    <span class="font-medium text-gray-700">Ducted Installations</span>
    <input class="search-input" placeholder="Search ducted installations..." >
  </div>
  <div class="overflow-y-auto max-h-64 border rounded-lg">
    <table class="w-full text-sm border-collapse ducted-table">
      <thead class="bg-gray-100 sticky top-0"><tr>
        <th class="p-2 text-left">Name</th>
        <th class="p-2 text-center">Price</th>
        <th class="p-2 text-center">Qty</th>
        <th class="p-2 text-center">Type</th>
        <th class="p-2 text-center">Subtotal</th>
      </tr></thead>
      <tbody>
      <?php foreach ($ducted_installations as $d): ?>
        <tr data-id="<?= $d['id'] ?>">
          <td class="p-2"><?= htmlspecialchars($d['name']) ?></td>
          <td class="p-2 text-center"><input type="text" value="<?= number_format($d['price'],2) ?>" readonly class="price-input text-center"></td>
          <td class="p-2 text-center"><input type="number" min="0" value="0" class="qty-input w-16 text-center" data-price="<?= $d['price'] ?>"></td>
          <td class="p-2 text-center">
            <select class="installation-type w-24">
              <option value="indoor">Indoor</option>
              <option value="outdoor">Outdoor</option>
            </select>
          </td>
          <td class="p-2 text-center subtotal">$0.00</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="5" class="p-2 text-right"><button type="button" class="qbtn add-ducted-btn mt-2">Add Selected Ducted</button></td></tr></tfoot>
    </table>
  </div>
  <div class="added-ducted mt-2"></div>
</div>

<!-- OTHER EXPENSES -->
<div class="bg-white p-4 rounded-xl shadow border border-gray-200 mb-6">
  <span class="font-medium text-gray-700 mb-2 block">Other Expenses</span>
  <div class="added-expenses"></div>
  <button type="button" class="qbtn" id="addExpenseBtn">Add Expense</button>
</div>

<script>
// JS: update subtotal when qty changes
document.querySelectorAll('.qty-input').forEach(input=>{
  input.addEventListener('input',()=>{
    const row = input.closest('tr');
    const price = parseFloat(input.dataset.price||0);
    const qty = parseFloat(input.value||0);
    const subtotal = (price*qty).toFixed(2);
    row.querySelector('.subtotal').textContent = '$'+subtotal;
  });
});

// Add row to added-products
document.querySelectorAll('.add-product-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const tbody = btn.closest('.bg-white').querySelector('.added-products');
    btn.closest('table').querySelectorAll('tbody tr').forEach(row=>{
      const qty = parseInt(row.querySelector('.qty-input').value||0);
      if(qty>0){
        const name = row.querySelector('td:first-child').textContent;
        const price = row.querySelector('.price-input').value;
        tbody.insertAdjacentHTML('beforeend', `<div class="p-2 border-b flex justify-between">
          <span>${name}</span>
          <span>${qty} x $${price}</span>
        </div>`);
      }
    });
  });
});

// Add expense
document.getElementById('addExpenseBtn').addEventListener('click',()=>{
  document.querySelector('.added-expenses').insertAdjacentHTML('beforeend', `<div class="flex gap-2 mt-2">
    <input type="text" name="expense_name[]" placeholder="Description" class="border p-2 rounded flex-1">
    <input type="number" name="expense_price[]" placeholder="Price" class="border p-2 rounded w-24">
  </div>`);
});
</script>

<style>
.qbtn { background:#e6eef8;padding:6px 10px;border-radius:8px;border:1px solid #cfe0f8;cursor:pointer }
</style>

<?php
$content = ob_get_clean();
renderLayout("Edit Order", $content, "orders");
?>
