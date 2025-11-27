<?php
// create_order.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/layout.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    $content = '<div style="padding:20px;background:#fee;border:1px solid #fbb;border-radius:8px;">
        <h3>Database connection missing</h3>
        <p>Please make sure <code>$pdo</code> is created in config.php</p>
    </div>';
    renderLayout('Create Order', $content, 'create_order');
    exit;
}

// Load data for tables
$products = $pdo->query("SELECT id,name,price FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$split_installations = $pdo->query("SELECT id,item_name AS name,unit_price AS price FROM split_installation ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$ducted_installations = $pdo->query("SELECT id,equipment_name AS name,model_name_indoor,model_name_outdoor,total_cost AS price FROM ductedinstallations ORDER BY equipment_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$personnel = $pdo->query("SELECT id,name,rate FROM personnel ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$equipment = $pdo->query("SELECT id,item AS name,rate FROM equipment ORDER BY item ASC")->fetchAll(PDO::FETCH_ASSOC);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Insert order
        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_email, contact_number, appointment_date, created_at)
            VALUES (:name, :email, :phone, :appointment, NOW())");
        $stmt->execute([
            ':name'=>$_POST['customer_name']??'',
            ':email'=>$_POST['customer_email']??'',
            ':phone'=>$_POST['contact_number']??'',
            ':appointment'=>$_POST['appointment_date']??date('Y-m-d')
        ]);
        $orderId = $pdo->lastInsertId();

        // Helper to insert order items
        $insertItem = $pdo->prepare("INSERT INTO order_items
            (order_id, item_type, item_id, installation_type, qty, price)
            VALUES (:order_id, :item_type, :item_id, :installation_type, :qty, :price)");

        // Products
        if(!empty($_POST['quantity'])) {
            foreach($_POST['quantity'] as $pid=>$qty) {
                $qty = (int)$qty;
                if($qty>0){
                    $price = $pdo->query("SELECT price FROM products WHERE id=".(int)$pid)->fetchColumn() ?: 0;
                    $insertItem->execute([
                        ':order_id'=>$orderId,
                        ':item_type'=>'product',
                        ':item_id'=>$pid,
                        ':installation_type'=>null,
                        ':qty'=>$qty,
                        ':price'=>$price
                    ]);
                }
            }
        }

        // Split installations
        if(!empty($_POST['split'])){
            foreach($_POST['split'] as $sid=>$data){
                $qty = (int)($data['qty']??0);
                if($qty>0){
                    $price = $pdo->query("SELECT unit_price FROM split_system_installation WHERE id=".(int)$sid)->fetchColumn() ?: 0;
                    $insertItem->execute([
                        ':order_id'=>$orderId,
                        ':item_type'=>'installation',
                        ':item_id'=>$sid,
                        ':installation_type'=>null,
                        ':qty'=>$qty,
                        ':price'=>$price
                    ]);
                }
            }
        }

        // Ducted installations
        if(!empty($_POST['ducted'])){
            foreach($_POST['ducted'] as $did=>$data){
                $qty = (int)($data['qty']??0);
                if($qty>0){
                    $type = $data['installation_type']??null;
                    $price = $pdo->query("SELECT total_cost FROM ductedinstallations WHERE id=".(int)$did)->fetchColumn() ?: 0;
                    $insertItem->execute([
                        ':order_id'=>$orderId,
                        ':item_type'=>'installation',
                        ':item_id'=>$did,
                        ':installation_type'=>$type,
                        ':qty'=>$qty,
                        ':price'=>$price
                    ]);
                }
            }
        }

        // Personnel
        if(!empty($_POST['personnel_hours'])){
            foreach($_POST['personnel_hours'] as $prid=>$hours){
                $hours = (float)$hours;
                if($hours>0){
                    $rate = $pdo->query("SELECT rate FROM personnel WHERE id=".(int)$prid)->fetchColumn() ?: 0;
                    $insertItem->execute([
                        ':order_id'=>$orderId,
                        ':item_type'=>'personnel',
                        ':item_id'=>$prid,
                        ':installation_type'=>null,
                        ':qty'=>$hours,
                        ':price'=>$rate
                    ]);
                }
            }
        }

        // Equipment
        if(!empty($_POST['equipment_qty'])){
            foreach($_POST['equipment_qty'] as $eid=>$qty){
                $qty = (int)$qty;
                if($qty>0){
                    $rate = $pdo->query("SELECT rate FROM equipment WHERE id=".(int)$eid)->fetchColumn() ?: 0;
                    $insertItem->execute([
                        ':order_id'=>$orderId,
                        ':item_type'=>'equipment',
                        ':item_id'=>$eid,
                        ':installation_type'=>null,
                        ':qty'=>$qty,
                        ':price'=>$rate
                    ]);
                }
            }
        }

        // Other expenses
        if(!empty($_POST['other_expenses'])){
            foreach($_POST['other_expenses'] as $exp){
                $name = $exp['name']??'Other';
                $amt = (float)($exp['amount']??0);
                if($amt>0){
                    // Use item_type 'other' and item_id 0
                    $insertItem->execute([
                        ':order_id'=>$orderId,
                        ':item_type'=>'other',
                        ':item_id'=>0,
                        ':installation_type'=>null,
                        ':qty'=>1,
                        ':price'=>$amt
                    ]);
                }
            }
        }

        $pdo->commit();
        header("Location: review_order.php?id=".$orderId);
        exit;

    } catch(Exception $e){
        $pdo->rollBack();
        $message = "Error saving order: ".$e->getMessage();
    }
}

// Render form with tables + JS (same as your original front-end code)
ob_start();
?>
<style>
/* Keep all your table styles, grid layout, card styles, plus/minus buttons etc. */
/* (use the exact CSS from your original create_order.php for full layout) */
</style>

<?php if($message): ?>
<div class="card"><div style="color:#c53030;"><?= htmlspecialchars($message) ?></div></div>
<?php endif; ?>

<form method="post" id="orderForm" class="create-order-grid" novalidate>
  <!-- LEFT COLUMN -->
  <!-- Insert all your original tables for products, split, ducted, personnel, equipment, other expenses -->
  <!-- Keep the plus/minus buttons and quantity inputs -->
  <!-- RIGHT COLUMN: summary sidebar -->
</form>

<script>
// Copy all your original vanilla JS for subtotal calculations, plus/minus buttons, searches, other expenses
</script>

<?php
$content = ob_get_clean();
renderLayout('Create Order', $content, 'create_order');
?>
